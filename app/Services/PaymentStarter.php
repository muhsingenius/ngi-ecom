<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Begins (or restarts) payment for an order. Each attempt gets its own reference and
 * a pending Payment row, so a late webhook for an earlier attempt is still matched.
 */
class PaymentStarter
{
    public function __construct(private Paystack $paystack) {}

    /** @return string Paystack's hosted payment URL */
    public function start(Order $order): string
    {
        $reference = $order->number.'-'.Str::upper(Str::random(8));

        $result = $this->paystack->initialize($order, $reference, $order->url());

        Payment::create([
            'order_id' => $order->id, 'reference' => $reference, 'amount' => $order->total,
            'currency' => $order->currency, 'status' => 'pending',
        ]);

        $order->forceFill(['paystack_reference' => $reference])->save();
        $order->record('payment_started', 'Payment page opened.');

        return $result['authorization_url'];
    }
}
