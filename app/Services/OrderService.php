<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Mail\NewJobMail;
use App\Mail\OrderUpdateMail;
use App\Models\CartLine;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Order lifecycle: placing an order from the cart, recording payment, and releasing
 * it to the print shop once it is paid (and, when proofs apply, approved).
 *
 *   Pending payment -> Paid -> (Awaiting proof -> Proof approved) -> In production -> Ready
 *                                                                  -> Dispatched / Picked up -> Completed
 */
class OrderService
{
    public function __construct(
        private CartService $cart,
        private DeliveryCalculator $delivery,
        private OrderSnapshot $snapshot,
        private Settings $settings,
    ) {}

    /**
     * Whether an order of this size needs a proof: threshold 0 = every order, blank = never.
     */
    public function proofRequired(int $quantity): bool
    {
        $threshold = $this->settings->int('proof.threshold');

        return $threshold !== null && $quantity >= $threshold;
    }

    /**
     * Turn the cart into an order. Prices are recalculated here, on the server.
     *
     * @param  array{name: string, phone: string, email: string, fulfilment: string, zone_id?: int|null, address?: string|null, pickup_point_id?: int|null, notes?: string|null}  $data
     * @param  Collection<int, CartLine>  $lines
     */
    public function place(Request $request, array $data, Collection $lines): Order
    {
        $quote = $this->cart->quote($lines);
        $zone = $data['fulfilment'] === 'delivery' ? DeliveryZone::where('active', true)->findOrFail($data['zone_id']) : null;
        $pickup = $data['fulfilment'] === 'pickup' ? PickupPoint::where('active', true)->findOrFail($data['pickup_point_id']) : null;
        $deliveryFee = $zone ? $this->delivery->fee($zone, $quote['quantity'], $quote['subtotal']) : 0;

        $proofRequired = $this->proofRequired($quote['quantity']);
        $timing = $this->settings->string('proof.timing');

        $order = DB::transaction(function () use ($request, $data, $lines, $quote, $zone, $pickup, $deliveryFee, $proofRequired, $timing) {
            $order = Order::create([
                'user_id' => $request->user()?->id,
                'customer_name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'],
                'status' => $proofRequired && $timing === 'proof_then_pay' ? OrderStatus::AwaitingProof : OrderStatus::PendingPayment,
                'fulfilment' => $data['fulfilment'],
                'delivery_zone_id' => $zone?->id, 'zone_name' => $zone?->name, 'zone_eta' => $zone?->eta,
                'delivery_address' => $zone ? $data['address'] : null,
                'pickup_point_id' => $pickup?->id, 'pickup_name' => $pickup?->name, 'pickup_address' => $pickup?->address,
                'currency' => config('shop.currency'),
                'subtotal' => Money::toDecimal($quote['subtotal']),
                'delivery_fee' => Money::toDecimal($deliveryFee),
                'total' => Money::toDecimal($quote['subtotal'] + $deliveryFee),
                'quantity' => $quote['quantity'],
                'tier_scope' => $this->settings->string('pricing.tier_scope'),
                'proof_required' => $proofRequired,
                'proof_timing' => $proofRequired ? $timing : null,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $cartLine) {
                $priced = $quote['lines'][$cartLine->design_id];
                $product = $cartLine->product;
                $variants = $product->variants->keyBy('id');

                $line = $order->lines()->create([
                    'product_id' => $product->id, 'design_id' => $cartLine->design_id,
                    'product_name' => $product->name, 'quality' => $product->quality, 'product_type' => $product->type->value,
                    'quantity' => $priced['quantity'],
                    'unit_price' => Money::toDecimal($priced['unit_price']), 'single_price' => Money::toDecimal($priced['single_price']),
                    'extra_area_fee' => Money::toDecimal($priced['extra_area_fee']), 'saving' => Money::toDecimal($priced['saving']),
                    'line_total' => Money::toDecimal($priced['line_total']), 'tier_quantity' => $priced['tier_quantity'],
                    'design_group_key' => $cartLine->design->group_key, 'notes' => $cartLine->notes,
                ]);

                foreach ($priced['items'] as $i => $item) {
                    $variant = $variants[$item['variant_id']];
                    $source = $cartLine->items[$i] ?? [];

                    $line->items()->create([
                        'variant_id' => $variant->id, 'colour_name' => $variant->colour->name, 'colour_hex' => $variant->colour->hex,
                        'size' => $variant->size, 'qty' => $item['qty'], 'unit_price' => Money::toDecimal($item['unit_price']),
                        'name' => ($source['name'] ?? '') !== '' ? $source['name'] : null,
                        'number' => ($source['number'] ?? '') !== '' ? $source['number'] : null,
                    ]);
                }

                $this->snapshot->copy($order, $line, $cartLine->design);
            }

            CartLine::whereIn('id', $lines->pluck('id'))->delete();
            $order->record('placed', 'Order placed.');

            return $order;
        });

        // Proof then pay: the proof goes out straight away, before any payment.
        if ($order->status === OrderStatus::AwaitingProof) {
            app(ProofService::class)->create($order);
        }

        return $order;
    }

    /**
     * Record a confirmed payment. Safe to call more than once for the same order.
     */
    public function markPaid(Order $order): void
    {
        if ($order->isPaid()) {
            return;
        }

        // Money arrived for an order that was already cancelled (e.g. it expired): keep it cancelled and ask for a refund.
        if ($order->cancelled_at) {
            $order->forceFill(['paid_at' => now(), 'flagged' => true])->save();
            $order->record('paid_after_cancel', 'Payment received for a cancelled order. It needs a refund.');

            return;
        }

        $awaitingProof = $order->proof_required && $order->status !== OrderStatus::ProofApproved;

        $order->forceFill(['paid_at' => now(), 'status' => $awaitingProof ? OrderStatus::AwaitingProof : OrderStatus::Paid, 'proof_expires_at' => null])->save();
        $order->record('paid', 'Payment received.');

        if ($awaitingProof) {
            $this->notify($order, 'Payment received', 'Thank you, your payment is confirmed.', [
                'We are preparing a proof of your design for you to approve before we print.',
            ]);

            app(ProofService::class)->create($order);

            return;
        }

        $this->release($order, announce: false);
        $this->notify($order, 'Payment received', 'Thank you, your payment is confirmed.', [
            'Your order is now in production. We will email you again when it is ready.',
        ]);
    }

    /** Hand a paid (and, where needed, approved) order to the print shop. */
    public function release(Order $order, bool $announce = true): void
    {
        $order->forceFill([
            'status' => OrderStatus::InProduction,
            'production_stage' => 'queued',
            'due_date' => now()->addDays($this->settings->int('orders.turnaround_days') ?? 0)->toDateString(),
        ])->save();

        $order->record('released', 'Released to the print shop.');

        if ($announce) {
            $this->notify($order, 'In production', 'Your order is now in production.', ['We will email you when it is ready.']);
        }

        if ($shop = $this->settings->string('shop.notification_email')) {
            Mail::to($shop)->queue(new NewJobMail($order));
        }
    }

    /** Cancel an order and tell the customer. Refunding any payment is a separate admin action. */
    public function cancel(Order $order, string $reason, ?int $userId = null): void
    {
        if ($order->cancelled_at) {
            return;
        }

        $order->forceFill(['status' => OrderStatus::Cancelled, 'cancelled_at' => now(), 'cancel_reason' => $reason, 'proof_expires_at' => null])->save();
        $order->proofs()->whereIn('status', ['sent', 'changes_requested'])->update(['status' => 'superseded']);
        $order->record('cancelled', "Order cancelled: {$reason}", $userId);

        $lines = [$reason.'.'];

        if ($order->isPaid()) {
            $lines[] = 'Your payment will be refunded. We will email you when the refund is on its way.';
        }

        $this->notify($order, 'Order cancelled', 'Your order has been cancelled.', $lines);
    }

    /**
     * @param  list<string>  $lines
     */
    public function notify(Order $order, string $subject, string $headline, array $lines, ?string $actionText = null, ?string $actionUrl = null): void
    {
        Mail::to($order->email)->queue(new OrderUpdateMail($order, $subject, $headline, $lines, $actionText, $actionUrl));
    }
}
