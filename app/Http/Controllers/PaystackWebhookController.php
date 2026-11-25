<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\Paystack;
use App\Services\RefundService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Paystack calls this when a payment succeeds. An order is marked paid only from here
 * (or from an explicit server-to-server verify), never from the browser redirect, and
 * only after the signature is checked and the amount and currency match the order.
 */
class PaystackWebhookController extends Controller
{
    public function __invoke(Request $request, Paystack $paystack, OrderService $orders): Response
    {
        $payload = $request->getContent();

        abort_unless($paystack->validSignature($payload, $request->header('x-paystack-signature')), 401);

        $event = json_decode($payload, true) ?: [];

        $type = $event['event'] ?? null;

        if ($type === 'charge.success') {
            self::confirm((array) ($event['data'] ?? []), $orders);
        }

        if (in_array($type, ['refund.processed', 'refund.failed'], true)) {
            app(RefundService::class)->applyWebhook($type, (array) ($event['data'] ?? []));
        }

        return response('ok');
    }

    /**
     * Apply a successful charge to its order, after checking that it really pays for it.
     *
     * @param  array<string, mixed>  $data  Paystack transaction data (webhook or verify response)
     */
    public static function confirm(array $data, OrderService $orders): bool
    {
        $payment = Payment::where('reference', (string) ($data['reference'] ?? ''))->first();

        if (! $payment || ($data['status'] ?? null) !== 'success') {
            return false;
        }

        $order = Order::find($payment->order_id);

        $matches = (int) ($data['amount'] ?? -1) === Money::toMinor($payment->amount)
            && strtoupper((string) ($data['currency'] ?? '')) === strtoupper($payment->currency)
            && Money::toMinor($payment->amount) === Money::toMinor($order->total);

        if (! $matches) {
            $order->forceFill(['flagged' => true])->save();
            $order->record('payment_mismatch', 'A payment was received that does not match the order total. It needs a manual check.');

            return false;
        }

        $payment->forceFill([
            'status' => 'success', 'channel' => $data['channel'] ?? null, 'payload' => $data, 'paid_at' => now(),
        ])->save();

        $orders->markPaid($order);

        return true;
    }
}
