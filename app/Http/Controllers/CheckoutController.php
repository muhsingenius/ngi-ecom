<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\PickupPoint;
use App\Services\CartService;
use App\Services\DeliveryCalculator;
use App\Services\OrderService;
use App\Services\PaymentStarter;
use App\Services\Paystack;
use App\Services\Settings;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Request $request, CartService $cart, DeliveryCalculator $delivery, Settings $settings): View|RedirectResponse
    {
        $lines = $cart->lines($request);

        if ($lines->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $quote = $cart->quote($lines);
        $zones = DeliveryZone::where('active', true)->orderBy('sort')->orderBy('id')->get();
        $pickups = PickupPoint::where('active', true)->orderBy('sort')->orderBy('id')->get();

        return view('checkout.show', [
            'quote' => $quote,
            'zones' => $zones->map(fn ($z) => $delivery->describe($z, $quote['quantity'], $quote['subtotal']))->values(),
            'pickups' => $pickups,
            'turnaround' => $settings->int('orders.turnaround_days'),
            'proofRequired' => app(OrderService::class)->proofRequired($quote['quantity']),
            'proofTiming' => $settings->string('proof.timing'),
            'symbol' => Money::symbol(),
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request, CartService $cart, OrderService $orders, Paystack $paystack): RedirectResponse
    {
        $lines = $cart->lines($request);

        if ($lines->isEmpty()) {
            return redirect()->route('cart.index');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-]{7,18}$/'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'fulfilment' => ['required', Rule::in(['delivery', 'pickup'])],
            'zone_id' => ['required_if:fulfilment,delivery', 'nullable', Rule::exists('delivery_zones', 'id')->where('active', true)],
            'address' => ['required_if:fulfilment,delivery', 'nullable', 'string', 'min:5', 'max:500'],
            'pickup_point_id' => ['required_if:fulfilment,pickup', 'nullable', Rule::exists('pickup_points', 'id')->where('active', true)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'phone.regex' => 'Enter a phone number we can reach you on, for example 024 123 4567.',
            'zone_id.required_if' => 'Choose where we should deliver.',
            'address.required_if' => 'Enter the delivery address.',
            'pickup_point_id.required_if' => 'Choose a pickup point.',
        ]);

        // Everything in the cart must still be orderable.
        foreach ($lines as $line) {
            $variants = $line->product->variants->where('active', true)->keyBy('id');

            if (! $line->product->active || collect($line->items)->contains(fn ($i) => ! $variants->has($i['variant_id']))) {
                return redirect()->route('cart.index')->withErrors(['cart' => "{$line->product->name} is no longer available in the options you chose. Please update your cart."]);
            }
        }

        $order = $orders->place($request, $data, $lines);

        return $this->payment($order, $paystack);
    }

    /** Send the customer to Paystack, or to the order page when payment is not needed yet. */
    private function payment(Order $order, Paystack $paystack): RedirectResponse
    {
        if ($order->proof_required && $order->proof_timing === 'proof_then_pay') {
            return redirect($order->url())->with('status', 'Order placed. We will email you a proof to approve, and then you can pay.');
        }

        try {
            return redirect()->away(app(PaymentStarter::class)->start($order));
        } catch (\Throwable $e) {
            report($e);

            return redirect($order->url())->with('payment_error', $paystack->configured()
                ? 'We could not reach the payment page. Use "Pay now" to try again.'
                : 'Your order is saved, but online payment is not available right now. The store will contact you.');
        }
    }
}
