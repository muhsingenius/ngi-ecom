<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\PaystackWebhookController;
use App\Models\Order;
use App\Models\OrderLine;
use App\Services\JobSheet;
use App\Services\OrderService;
use App\Services\Paystack;
use App\Services\ProofService;
use App\Services\RefundService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $orders = Order::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.trim((string) $request->query('q')).'%';
                $query->where(fn ($q) => $q->where('number', 'like', $term)->orWhere('customer_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when(OrderStatus::tryFrom((string) $request->query('status')), fn ($q, $status) => $q->where('status', $status->value))
            ->when($request->boolean('flagged'), fn ($q) => $q->where('flagged', true))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.orders.index', ['orders' => $orders, 'statuses' => OrderStatus::cases()]);
    }

    public function show(Order $order, RefundService $refunds): View
    {
        $order->load(['lines.items', 'lines.areas', 'payments', 'events.user', 'proofs', 'refunds']);

        return view('admin.orders.show', [
            'order' => $order,
            'refundable' => $refunds->refundable($order),
            'latestProof' => $order->latestProof(),
        ]);
    }

    public function dispatch(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $data = $request->validate([
            'courier' => ['nullable', 'string', 'max:120'],
            'rider_phone' => ['nullable', 'string', 'max:30'],
            'tracking_note' => ['nullable', 'string', 'max:500'],
        ]);

        $orders->dispatch($order, $data['courier'] ?? $order->deliveryZone?->courier_name, $data['rider_phone'] ?? null, $data['tracking_note'] ?? null, $request->user()->id);

        return back()->with('status', 'Marked as dispatched and the customer has been emailed.');
    }

    public function pickedUp(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $orders->markPickedUp($order, $request->user()->id);

        return back()->with('status', 'Marked as picked up.');
    }

    public function complete(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $orders->complete($order, $request->user()->id);

        return back()->with('status', 'Order completed.');
    }

    public function cancel(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']]);

        if ($order->cancelled_at || in_array($order->status, [OrderStatus::Completed, OrderStatus::Refunded], true)) {
            return back()->withErrors(['reason' => 'This order can no longer be cancelled.']);
        }

        $orders->cancel($order, $data['reason'], $request->user()->id);

        return back()->with('status', $order->isPaid() ? 'Order cancelled. Remember to refund the payment below.' : 'Order cancelled.');
    }

    public function markPaid(Request $request, Order $order, OrderService $orders): RedirectResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:200']]);

        if ($order->isPaid() || $order->cancelled_at) {
            return back()->withErrors(['note' => 'This order cannot be marked as paid.']);
        }

        $orders->recordManualPayment($order, $data['note'], $request->user()->id);

        return back()->with('status', 'Payment recorded.');
    }

    /** Ask Paystack directly (server to server) whether the latest payment went through. */
    public function verify(Order $order, Paystack $paystack, OrderService $orders): RedirectResponse
    {
        $payment = $order->payments()->where('status', 'pending')->latest('id')->first();

        if (! $payment) {
            return back()->withErrors(['verify' => 'There is no pending payment to check.']);
        }

        try {
            $confirmed = PaystackWebhookController::confirm($paystack->verify($payment->reference), $orders);
        } catch (Throwable $e) {
            return back()->withErrors(['verify' => $e->getMessage()]);
        }

        return back()->with('status', $confirmed ? 'Paystack confirmed the payment.' : 'Paystack has not confirmed a payment for this order yet.');
    }

    public function refund(Request $request, Order $order, RefundService $refunds): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'min:3', 'max:300'],
            'method' => ['required', Rule::in(['paystack', 'manual'])],
        ]);

        try {
            $refunds->refund($order, Money::toMinor($data['amount']), $data['reason'], $data['method'] === 'paystack', $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Refund recorded and the customer has been emailed.');
    }

    public function replaceProof(Request $request, Order $order, ProofService $proofs): RedirectResponse
    {
        $request->validate(['image' => ['required', 'file', 'max:10240']]);

        try {
            $proofs->replace($order, $request->file('image'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['image' => $e->getMessage()]);
        }

        return back()->with('status', 'Your proof has replaced the generated one and the customer has been emailed.');
    }

    public function resendProof(Order $order, ProofService $proofs): RedirectResponse
    {
        $proofs->create($order);

        return back()->with('status', 'A fresh proof was generated and sent.');
    }

    public function proofTiming(Request $request, Order $order, ProofService $proofs): RedirectResponse
    {
        $data = $request->validate(['timing' => ['required', Rule::in(['pay_then_proof', 'proof_then_pay'])]]);

        try {
            $proofs->overrideTiming($order, $data['timing'], $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['timing' => $e->getMessage()]);
        }

        return back()->with('status', 'Proof timing updated for this order.');
    }

    /** Open the customer's design in the designer to fix it for them; saving sends a new proof. */
    public function reviseDesign(Request $request, Order $order, OrderLine $line, ProofService $proofs): RedirectResponse
    {
        abort_unless($line->order_id === $order->id, 404);

        if (! $proofs->canRevise($order)) {
            return back()->withErrors(['revise' => 'The design can only be edited while the order is awaiting proof approval.']);
        }

        try {
            $design = $proofs->startRevision($line, $request);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['revise' => $e->getMessage()]);
        }

        return redirect()->route('designs.edit', $design);
    }

    public function clearFlag(Order $order): RedirectResponse
    {
        $order->forceFill(['flagged' => false])->save();
        $order->record('unflagged', 'Follow-up flag cleared.', auth()->id());

        return back()->with('status', 'Flag cleared.');
    }

    public function sheet(Order $order, JobSheet $sheet): Response
    {
        return response($sheet->pdf($order), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$order->number.'-job-sheet.pdf"',
        ]);
    }

    public function files(Order $order, JobSheet $sheet): BinaryFileResponse
    {
        return response()->download($sheet->zip($order), $order->number.'-print-files.zip')->deleteFileAfterSend();
    }
}
