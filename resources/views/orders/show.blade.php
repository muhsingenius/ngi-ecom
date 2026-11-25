@php
    use App\Enums\OrderStatus;
    use App\Support\Money;

    $tone = ['amber' => 'bg-accent-tint text-ink', 'red' => 'bg-red-50 text-red-700', 'green' => 'bg-green-50 text-green-700', 'blue' => 'bg-brand-tint text-brand-strong'][$order->status->tone()];
    $payable = ! $order->isPaid() && ! $order->cancelled_at
        && (! $order->proof_required || $order->proof_timing !== 'proof_then_pay' || $order->status === OrderStatus::ProofApproved);
    $waitingForPayment = $payable && (session('payment_error') === null) && $order->payments->contains('status', 'pending') && request()->query('reference');
@endphp

<x-layouts.app :title="'Order '.$order->number">
    <section class="section">
        <div class="container-page max-w-4xl" x-data="{
                status: @js($order->status->value), label: @js($order->status->label()), paid: @js($order->isPaid()), polls: 0,
                async poll() {
                    if (this.paid || this.polls > 40) { return; }
                    this.polls++;
                    try {
                        const r = await fetch(@js(route('orders.status', ['order' => $order->number, 't' => $token])), { headers: { Accept: 'application/json' } });
                        const d = await r.json();
                        this.status = d.status; this.label = d.label;
                        if (d.paid) { this.paid = true; location.reload(); return; }
                    } catch (e) {}
                    setTimeout(() => this.poll(), 3000);
                },
             }" @if ($waitingForPayment) x-init="poll()" @endif>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="type-title">Order {{ $order->number }}</h1>
                <span class="rounded-full px-4 py-1.5 text-sm font-semibold {{ $tone }}">{{ $order->status->label() }}</span>
            </div>
            <p class="mt-1 text-sm text-muted">Placed {{ $order->created_at->format('j M Y, g:ia') }} · keep this page's link to follow your order.</p>

            @if (session('status'))<p class="mt-4 rounded-control bg-brand-tint px-4 py-3 text-sm font-semibold text-brand-strong" role="status">{{ session('status') }}</p>@endif
            @if (session('payment_error'))<p class="mt-4 rounded-control bg-accent-tint px-4 py-3 text-sm text-ink" role="alert">{{ session('payment_error') }}</p>@endif

            @if ($waitingForPayment)
                <p class="mt-4 rounded-control bg-brand-tint px-4 py-3 text-sm text-brand-strong" role="status">Confirming your payment with Paystack. This page updates by itself.</p>
            @endif

            @if ($payable)
                <form method="POST" action="{{ route('orders.pay', ['order' => $order->number, 't' => $token]) }}" class="mt-6">
                    @csrf
                    <button class="btn btn-brand btn-lg">Pay {{ Money::format(Money::toMinor($order->total), $order->currency) }} now</button>
                </form>
            @elseif ($order->proof_required && $order->proof_timing === 'proof_then_pay' && $order->status === OrderStatus::AwaitingProof)
                <p class="mt-6 rounded-control bg-accent-tint px-4 py-3 text-sm text-ink">We are preparing your proof. You will get an email when it is ready to approve, and then you can pay.</p>
            @endif

            <div class="mt-8 grid gap-6 md:grid-cols-2">
                <div class="panel p-5">
                    <h2 class="type-heading">{{ $order->isDelivery() ? 'Delivery' : 'Pickup' }}</h2>
                    @if ($order->isDelivery())
                        <p class="mt-2 text-sm text-copy">{{ $order->zone_name }}@if ($order->zone_eta) · {{ $order->zone_eta }}@endif</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-ink">{{ $order->delivery_address }}</p>
                    @else
                        <p class="mt-2 text-sm font-semibold text-ink">{{ $order->pickup_name }}</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-copy">{{ $order->pickup_address }}</p>
                    @endif
                    @if ($order->courier || $order->rider_phone || $order->tracking_note)
                        <div class="mt-4 rounded-control bg-mist p-3 text-sm">
                            <p class="font-semibold text-ink">Dispatch</p>
                            @if ($order->courier)<p class="text-copy">Courier: {{ $order->courier }}</p>@endif
                            @if ($order->rider_phone)<p class="text-copy">Rider: {{ $order->rider_phone }}</p>@endif
                            @if ($order->tracking_note)<p class="text-copy">{{ $order->tracking_note }}</p>@endif
                        </div>
                    @endif
                </div>

                <div class="panel p-5">
                    <h2 class="type-heading">Contact</h2>
                    <p class="mt-2 text-sm text-ink">{{ $order->customer_name }}</p>
                    <p class="text-sm text-copy">{{ $order->phone }} · {{ $order->email }}</p>
                    @if ($order->due_date)<p class="mt-3 text-sm text-copy">Expected ready by <span class="font-semibold text-ink">{{ $order->due_date->format('D j M') }}</span></p>@endif
                </div>
            </div>

            <div class="panel mt-6 overflow-hidden">
                @foreach ($order->lines as $line)
                    <div class="border-b border-line p-5 last:border-0">
                        <div class="flex flex-wrap justify-between gap-2">
                            <p class="font-semibold text-ink">{{ $line->product_name }} <span class="font-normal text-muted">· {{ $line->quality }}</span></p>
                            <p class="font-semibold text-ink">{{ Money::format(Money::toMinor($line->line_total), $order->currency) }}</p>
                        </div>
                        <p class="mt-1 text-sm text-copy">{{ $line->quantity }} × {{ Money::format(Money::toMinor($line->unit_price + $line->extra_area_fee), $order->currency) }}
                            @if ((float) $line->saving > 0)<span class="font-semibold text-brand-strong">· saved {{ Money::format(Money::toMinor($line->saving), $order->currency) }}</span>@endif</p>
                        <p class="mt-2 text-sm text-muted">
                            @foreach ($line->items->groupBy('colour_name') as $colour => $items)
                                {{ $colour }}: {{ $items->groupBy('size')->map(fn ($g, $s) => $g->sum('qty').' '.$s)->implode(', ') }}@if (! $loop->last) · @endif
                            @endforeach
                        </p>
                    </div>
                @endforeach
                <dl class="space-y-1 bg-mist p-5 text-sm">
                    <div class="flex justify-between"><dt class="text-copy">Items</dt><dd>{{ Money::format(Money::toMinor($order->subtotal), $order->currency) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-copy">Delivery</dt><dd>{{ (float) $order->delivery_fee > 0 ? Money::format(Money::toMinor($order->delivery_fee), $order->currency) : 'Free' }}</dd></div>
                    <div class="flex justify-between border-t border-line pt-2 text-base"><dt class="font-semibold text-ink">Total</dt><dd class="font-extrabold text-ink">{{ Money::format(Money::toMinor($order->total), $order->currency) }}</dd></div>
                </dl>
            </div>

            <div class="mt-8">
                <h2 class="type-heading">Progress</h2>
                <ol class="mt-3 space-y-3 border-l-2 border-line pl-5">
                    @foreach ($order->events->whereIn('type', ['placed', 'paid', 'proof_sent', 'proof_approved', 'released', 'ready', 'dispatched', 'picked_up', 'completed', 'cancelled', 'refunded']) as $event)
                        <li class="relative text-sm"><span class="absolute -left-[1.6rem] top-1.5 size-2.5 rounded-full bg-brand"></span><span class="font-semibold text-ink">{{ $event->message }}</span> <span class="text-muted">· {{ $event->created_at->format('j M, g:ia') }}</span></li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>
</x-layouts.app>
