<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * Records a full or partial refund and, for card and Mobile Money payments, triggers it
 * through Paystack. The order moves to Refunded once everything paid has been returned.
 */
class RefundService
{
    public function __construct(private Paystack $paystack, private OrderService $orders) {}

    /** Total refunded so far, in minor units (failed refunds do not count). */
    public function refunded(Order $order): int
    {
        return Money::toMinor($order->refunds()->whereIn('status', ['pending', 'processed', 'recorded'])->sum('amount'));
    }

    /** What can still be refunded, in minor units. */
    public function refundable(Order $order): int
    {
        return $order->isPaid() ? max(0, Money::toMinor($order->total) - $this->refunded($order)) : 0;
    }

    /**
     * @param  int  $amountMinor  amount to refund, in pesewas
     * @param  bool  $viaPaystack  false records a refund the store paid back by other means
     *
     * @throws InvalidArgumentException
     */
    public function refund(Order $order, int $amountMinor, string $reason, bool $viaPaystack, ?int $userId = null): Refund
    {
        if (! $order->isPaid()) {
            throw new InvalidArgumentException('Only a paid order can be refunded.');
        }

        if ($amountMinor < 1 || $amountMinor > $this->refundable($order)) {
            throw new InvalidArgumentException('Enter an amount between 0.01 and '.Money::toDecimal($this->refundable($order)).'.');
        }

        $payment = Payment::where('order_id', $order->id)->where('status', 'success')->latest('id')->first();
        $status = 'recorded';
        $gatewayId = null;
        $payload = null;

        if ($viaPaystack) {
            if (! $payment || str_starts_with($payment->reference, 'MANUAL-')) {
                throw new InvalidArgumentException('This order was not paid through Paystack, so record the refund as paid back manually.');
            }

            try {
                $payload = $this->paystack->refund($payment->reference, $amountMinor);
            } catch (Throwable $e) {
                throw new InvalidArgumentException($e->getMessage());
            }

            $status = ($payload['status'] ?? 'pending') === 'processed' ? 'processed' : 'pending';
            $gatewayId = isset($payload['id']) ? (string) $payload['id'] : null;
        }

        $refund = DB::transaction(function () use ($order, $amountMinor, $reason, $viaPaystack, $userId, $payment, $status, $gatewayId, $payload) {
            $refund = $order->refunds()->create([
                'payment_id' => $payment?->id, 'user_id' => $userId, 'amount' => Money::toDecimal($amountMinor), 'reason' => $reason,
                'method' => $viaPaystack ? 'paystack' : 'manual', 'status' => $status, 'gateway_id' => $gatewayId, 'payload' => $payload,
            ]);

            $money = Money::format($amountMinor, $order->currency);
            $order->record('refund', "Refund of {$money} ".($viaPaystack ? 'sent to Paystack' : 'recorded').": {$reason}", $userId);

            if ($this->refundable($order->fresh()) === 0) {
                $order->forceFill(['status' => OrderStatus::Refunded, 'cancelled_at' => $order->cancelled_at ?? now(), 'cancel_reason' => $order->cancel_reason ?? 'Refunded in full'])->save();
                $order->record('refunded', 'Order refunded in full.', $userId);
            }

            return $refund;
        });

        $this->orders->notify($order, 'Refund issued', 'We have issued a refund.', [
            'Amount: '.Money::format($amountMinor, $order->currency).'.',
            $viaPaystack ? 'It goes back to the card or Mobile Money wallet you paid with. This can take a few days to show.' : 'We have paid this back to you.',
            "Reason: {$reason}",
        ]);

        return $refund;
    }

    /** Paystack tells us a refund finished (or failed). */
    public function applyWebhook(string $event, array $data): void
    {
        $refund = Refund::where('method', 'paystack')
            ->when(isset($data['id']), fn ($q) => $q->where('gateway_id', (string) $data['id']))
            ->when(! isset($data['id']) && isset($data['transaction_reference']), fn ($q) => $q->whereHas('payment', fn ($p) => $p->where('reference', $data['transaction_reference'])))
            ->latest('id')->first();

        if (! $refund) {
            return;
        }

        $refund->forceFill(['status' => $event === 'refund.processed' ? 'processed' : 'failed', 'payload' => $data])->save();
        $refund->order->record($event === 'refund.processed' ? 'refund_processed' : 'refund_failed', $event === 'refund.processed' ? 'Paystack finished the refund.' : 'Paystack could not complete the refund. It needs attention.');

        if ($event === 'refund.failed') {
            $refund->order->forceFill(['flagged' => true])->save();
        }
    }
}
