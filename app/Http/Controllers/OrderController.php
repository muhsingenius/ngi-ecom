<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\PaymentStarter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The customer's view of an order. Guests reach it through the secret link in their
 * emails (?t=...); signed-in customers can also open their own orders.
 */
class OrderController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        $this->authorizeView($request, $order);

        $order->load(['lines.items', 'lines.areas', 'events']);

        return view('orders.show', ['order' => $order, 'token' => $order->access_token]);
    }

    /** Polled by the order page after payment so it updates when the webhook lands. */
    public function status(Request $request, Order $order): JsonResponse
    {
        $this->authorizeView($request, $order);

        return response()->json(['status' => $order->status->value, 'label' => $order->status->label(), 'paid' => $order->isPaid()]);
    }

    /** Restart payment (e.g. the customer closed the payment page). */
    public function pay(Request $request, Order $order, PaymentStarter $starter): RedirectResponse
    {
        $this->authorizeView($request, $order);

        $payable = ! $order->isPaid() && ! $order->cancelled_at
            && (! $order->proof_required || $order->proof_timing !== 'proof_then_pay' || $order->status->value === 'proof_approved');

        abort_unless($payable, 403);

        try {
            return redirect()->away($starter->start($order));
        } catch (\Throwable $e) {
            report($e);

            return redirect($order->url())->with('payment_error', 'We could not reach the payment page. Please try again in a moment.');
        }
    }

    private function authorizeView(Request $request, Order $order): void
    {
        $token = (string) $request->query('t', '');
        $allowed = ($token !== '' && hash_equals($order->access_token, $token))
            || ($request->user() && $order->user_id === $request->user()->id);

        abort_unless($allowed, 404);
    }
}
