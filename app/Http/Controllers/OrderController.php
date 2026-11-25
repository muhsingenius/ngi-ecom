<?php

namespace App\Http\Controllers;

use App\Models\CartLine;
use App\Models\Order;
use App\Models\Product;
use App\Services\PaymentStarter;
use App\Services\ProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

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

    /**
     * Put the same designs and quantities back in a new cart. Items whose size or colour is no
     * longer offered, or products no longer sold, are skipped and reported.
     */
    public function reorder(Request $request, Order $order, ProofService $proofs): RedirectResponse
    {
        $this->authorizeView($request, $order);
        $order->load(['lines.items', 'lines.areas']);

        $added = 0;
        $skipped = [];

        foreach ($order->lines as $line) {
            $product = Product::with('variants')->find($line->product_id);
            $variants = $product?->variants->where('active', true)->keyBy('id') ?? collect();
            $items = $line->items->filter(fn ($i) => $variants->has($i->variant_id))
                ->map(fn ($i) => array_filter(['variant_id' => $i->variant_id, 'qty' => $i->qty, 'name' => $i->name, 'number' => $i->number], fn ($v) => $v !== null))
                ->values()->all();

            if (! $product?->active || ! $items) {
                $skipped[] = $line->product_name;

                continue;
            }

            try {
                $design = $proofs->cloneFromOrderLine($line, $request, null, 'Reorder of '.$order->number);
            } catch (InvalidArgumentException) {
                $skipped[] = $line->product_name;

                continue;
            }

            // Orders from before designs were kept editable have nothing to copy.
            if ($design->areas()->doesntExist()) {
                $design->delete();
                $skipped[] = $line->product_name;

                continue;
            }

            CartLine::updateOrCreate(
                ['design_id' => $design->id, 'user_id' => $request->user()?->id, 'session_token' => $request->user() ? null : $request->session()->get('designer_token')],
                ['product_id' => $product->id, 'items' => $items, 'notes' => $line->notes],
            );

            $added++;
        }

        $message = $added ? "Added {$added} ".Str::plural('item', $added).' from '.$order->number.' to your cart.' : 'Nothing from this order could be added to your cart.';

        if ($skipped) {
            $message .= ' Not available any more: '.implode(', ', array_unique($skipped)).'.';
        }

        return redirect()->route($added ? 'cart.index' : 'home')->with('status', $message);
    }

    private function authorizeView(Request $request, Order $order): void
    {
        $token = (string) $request->query('t', '');
        $allowed = ($token !== '' && hash_equals($order->access_token, $token))
            || ($request->user() && $order->user_id === $request->user()->id);

        abort_unless($allowed, 404);
    }
}
