@php
    use App\Enums\OrderStatus;
    use App\Enums\TierScope;
    use App\Support\Money;

    $money = fn ($v) => Money::format(Money::toMinor($v), $order->currency);
    $s = $order->status;
    $open = ! $order->cancelled_at && ! in_array($s, [OrderStatus::Completed, OrderStatus::Refunded], true);
@endphp

<x-layouts.admin :heading="'Order '.$order->number">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.orders.index') }}" class="link-arrow">← All orders</a>
        <x-status-badge :status="$s" />
        @if ($order->flagged)
            <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">Needs follow-up</span>
            <form method="POST" action="{{ route('admin.orders.clear-flag', $order) }}">@csrf<button class="text-sm text-brand-strong underline">Clear flag</button></form>
        @endif
        <span class="text-sm text-muted">Placed {{ $order->created_at->format('j M Y, g:ia') }}</span>
    </div>

    @if ($errors->any())<p class="mt-4 rounded-control bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>@endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-6">
            {{-- Items --}}
            <div class="panel overflow-hidden">
                @foreach ($order->lines as $line)
                    <div class="border-b border-line p-5">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="font-semibold text-ink">{{ $line->product_name }} <span class="font-normal text-muted">· {{ $line->quality }}</span></p>
                            <p class="font-semibold text-ink">{{ $money($line->line_total) }}</p>
                        </div>
                        <p class="mt-1 text-sm text-copy">{{ $line->quantity }} × {{ $money($line->unit_price + $line->extra_area_fee) }} @if ((float) $line->saving > 0) · saved {{ $money($line->saving) }} @endif · priced on {{ $line->tier_quantity }} counted together ({{ TierScope::tryFrom($order->tier_scope)?->label() ?? $order->tier_scope }})</p>
                        <p class="mt-1 text-sm text-muted">@foreach ($line->items->groupBy('colour_name') as $colour => $items){{ $colour }}: {{ $items->groupBy('size')->map(fn ($g, $sz) => $g->sum('qty').' '.$sz)->implode(', ') }}@if (! $loop->last) · @endif @endforeach</p>
                        @if ($line->items->contains(fn ($i) => $i->name || $i->number))<p class="mt-1 text-sm text-muted">Personalised: {{ $line->items->count() }} names and numbers</p>@endif
                        @if ($line->notes)<p class="mt-1 text-sm text-ink">Customer note: {{ $line->notes }}</p>@endif
                        <div class="mt-3 flex flex-wrap gap-4 text-sm">
                            @if ($s === OrderStatus::AwaitingProof && ! $order->cancelled_at)
                                <form method="POST" action="{{ route('admin.orders.revise', [$order, $line]) }}">@csrf<button class="link-arrow">Edit this design for the customer</button></form>
                            @endif
                        </div>
                    </div>
                @endforeach
                <dl class="space-y-1 bg-mist p-5 text-sm">
                    <div class="flex justify-between"><dt class="text-copy">Items</dt><dd>{{ $money($order->subtotal) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-copy">Delivery ({{ $order->zone_name ?? 'pickup' }})</dt><dd>{{ $money($order->delivery_fee) }}</dd></div>
                    <div class="flex justify-between border-t border-line pt-2 text-base"><dt class="font-semibold text-ink">Total</dt><dd class="font-extrabold text-ink">{{ $money($order->total) }}</dd></div>
                    @foreach ($order->refunds as $r)<div class="flex justify-between text-red-700"><dt>Refund ({{ $r->status }})</dt><dd>− {{ $money($r->amount) }}</dd></div>@endforeach
                </dl>
            </div>

            {{-- Proof --}}
            @if ($order->proof_required)
                <div class="panel p-5">
                    <h2 class="type-heading">Proof</h2>
                    <p class="mt-1 text-sm text-copy">Timing: <span class="font-semibold text-ink">{{ $order->proof_timing === 'proof_then_pay' ? 'proof then pay' : 'pay then proof' }}</span> · changes requested {{ $order->revisions_used }} {{ \Illuminate\Support\Str::plural('time', $order->revisions_used) }}
                        @if ($order->proof_expires_at) · payment due by {{ $order->proof_expires_at->format('j M Y') }} @endif</p>

                    @if ($latestProof)
                        <div class="mt-4 grid gap-4 sm:grid-cols-[14rem_1fr]">
                            <img src="{{ route('proofs.image', $latestProof->token) }}" alt="Latest proof" class="w-full rounded-control border border-line">
                            <div class="text-sm">
                                <p class="font-semibold text-ink">Version {{ $latestProof->version }} · {{ str_replace('_', ' ', $latestProof->status) }}{{ $latestProof->replaced ? ' · uploaded by admin' : '' }}</p>
                                @if ($latestProof->comment)<p class="mt-2 rounded-control bg-accent-tint p-3 text-ink">Customer asked: “{{ $latestProof->comment }}”</p>@endif
                                <p class="mt-2"><a href="{{ $latestProof->url() }}" class="link-arrow" target="_blank" rel="noopener">Open the customer's proof page</a></p>
                            </div>
                        </div>
                    @endif

                    @if ($open && $s !== OrderStatus::InProduction && $s !== OrderStatus::Ready)
                        <div class="mt-4 flex flex-wrap items-end gap-4 border-t border-line pt-4">
                            <form method="POST" action="{{ route('admin.orders.proof.replace', $order) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="file" name="image" accept="image/png,image/jpeg" required class="max-w-[14rem] text-xs" aria-label="Replacement proof image">
                                <button class="btn btn-outline !min-h-9">Replace and send</button>
                            </form>
                            <form method="POST" action="{{ route('admin.orders.proof.resend', $order) }}">@csrf<button class="btn btn-outline !min-h-9">Regenerate and resend</button></form>
                            @unless ($order->isPaid() || $s === OrderStatus::ProofApproved)
                                <form method="POST" action="{{ route('admin.orders.proof.timing', $order) }}" class="flex items-center gap-2">
                                    @csrf
                                    <select name="timing" class="field !min-h-9 w-44 text-sm" aria-label="Proof timing for this order">
                                        <option value="pay_then_proof" @selected($order->proof_timing === 'pay_then_proof')>Pay then proof</option>
                                        <option value="proof_then_pay" @selected($order->proof_timing === 'proof_then_pay')>Proof then pay</option>
                                    </select>
                                    <button class="btn btn-outline !min-h-9">Change timing</button>
                                </form>
                            @endunless
                        </div>
                    @endif
                </div>
            @endif

            {{-- Timeline --}}
            <div class="panel p-5">
                <h2 class="type-heading">History</h2>
                <ol class="mt-3 space-y-2 text-sm">
                    @foreach ($order->events->sortByDesc('id') as $event)
                        <li><span class="text-muted">{{ $event->created_at->format('j M, g:ia') }}</span> · {{ $event->message }} @if ($event->user)<span class="text-muted">({{ $event->user->name }})</span>@endif</li>
                    @endforeach
                </ol>
            </div>
        </div>

        <aside class="space-y-6">
            <div class="panel p-5 text-sm">
                <h2 class="type-heading text-base">Customer</h2>
                <p class="mt-2 font-semibold text-ink">{{ $order->customer_name }}</p>
                <p class="text-copy"><a href="tel:{{ $order->phone }}" class="underline">{{ $order->phone }}</a> · <a href="mailto:{{ $order->email }}" class="underline">{{ $order->email }}</a></p>
                <h3 class="mt-4 font-semibold text-ink">{{ $order->isDelivery() ? 'Delivery' : 'Pickup' }}</h3>
                @if ($order->isDelivery())<p class="text-copy">{{ $order->zone_name }}@if ($order->zone_eta) · {{ $order->zone_eta }}@endif</p><p class="whitespace-pre-line text-ink">{{ $order->delivery_address }}</p>
                @else<p class="text-copy">{{ $order->pickup_name }}</p><p class="whitespace-pre-line text-ink">{{ $order->pickup_address }}</p>@endif
                @if ($order->notes)<p class="mt-3 rounded-control bg-accent-tint p-3 text-ink">{{ $order->notes }}</p>@endif
                @if ($order->due_date)<p class="mt-3 text-copy">Due {{ $order->due_date->format('D j M Y') }}</p>@endif
            </div>

            <div class="panel space-y-4 p-5 text-sm">
                <h2 class="type-heading text-base">Production files</h2>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.orders.sheet', $order) }}" class="btn btn-outline !min-h-9">Job sheet (PDF)</a>
                    <a href="{{ route('admin.orders.files', $order) }}" class="btn btn-outline !min-h-9">Print files (ZIP)</a>
                </div>
                @if ($order->production_stage)<p class="text-copy">Print shop stage: <span class="font-semibold text-ink">{{ ucfirst($order->production_stage) }}</span></p>@endif
            </div>

            {{-- Fulfilment --}}
            @if ($s === OrderStatus::Ready && $order->isDelivery())
                <form method="POST" action="{{ route('admin.orders.dispatch', $order) }}" class="panel space-y-3 p-5">
                    @csrf
                    <h2 class="type-heading text-base">Dispatch</h2>
                    <x-f.input name="courier" label="Courier" :value="$order->deliveryZone?->courier_name" />
                    <x-f.input name="rider_phone" label="Rider phone" type="tel" />
                    <div><label for="tracking_note" class="block text-sm font-semibold text-ink">Tracking note</label><textarea id="tracking_note" name="tracking_note" rows="2" class="field mt-1.5 py-2" placeholder="Waybill number, expected time…"></textarea></div>
                    <button class="btn btn-brand btn-block">Mark dispatched and email the customer</button>
                </form>
            @elseif ($s === OrderStatus::Ready)
                <form method="POST" action="{{ route('admin.orders.picked-up', $order) }}" class="panel p-5">@csrf<h2 class="type-heading text-base">Pickup</h2><button class="btn btn-brand btn-block mt-3">Mark as picked up</button></form>
            @elseif (in_array($s, [OrderStatus::Dispatched, OrderStatus::PickedUp], true))
                <form method="POST" action="{{ route('admin.orders.complete', $order) }}" class="panel p-5">@csrf<button class="btn btn-brand btn-block">Mark order completed</button></form>
            @endif

            {{-- Payment --}}
            <div class="panel space-y-4 p-5 text-sm">
                <h2 class="type-heading text-base">Payment</h2>
                @forelse ($order->payments as $p)
                    <p class="text-copy"><span class="font-semibold text-ink">{{ $money($p->amount) }}</span> · {{ $p->status }}{{ $p->channel ? ' · '.str_replace('_', ' ', $p->channel) : '' }}<span class="block text-xs text-muted">{{ $p->reference }}</span></p>
                @empty
                    <p class="text-copy">No payment started yet.</p>
                @endforelse

                @if (! $order->isPaid() && ! $order->cancelled_at)
                    @if ($order->payments->contains('status', 'pending'))
                        <form method="POST" action="{{ route('admin.orders.verify', $order) }}">@csrf<button class="btn btn-outline btn-block !min-h-9">Check with Paystack now</button></form>
                    @endif
                    <form method="POST" action="{{ route('admin.orders.mark-paid', $order) }}" class="space-y-2">
                        @csrf
                        <label for="note" class="block font-semibold text-ink">Paid another way?</label>
                        <input id="note" name="note" class="field" placeholder="e.g. bank transfer, ref 1234" required>
                        <button class="btn btn-outline btn-block !min-h-9">Record payment</button>
                    </form>
                @endif
            </div>

            {{-- Refund --}}
            @if ($order->isPaid() && ($refundable > 0 || $order->refunds->isNotEmpty()))
                <div class="panel p-5 text-sm">
                    <h2 class="type-heading text-base">Refund</h2>
                    @if ($refundable > 0)
                        <form method="POST" action="{{ route('admin.orders.refund', $order) }}" class="mt-3 space-y-3">
                            @csrf
                            <x-f.input name="amount" label="Amount ({{ Money::symbol($order->currency) }})" type="number" step="0.01" min="0.01" :max="Money::toDecimal($refundable)" :value="Money::toDecimal($refundable)" :help="'Up to '.Money::format($refundable, $order->currency).' can still be refunded.'" />
                            <div><label for="reason" class="block font-semibold text-ink">Reason</label><input id="reason" name="reason" class="field mt-1.5" required></div>
                            <div class="space-y-1">
                                <label class="flex items-center gap-2"><input type="radio" name="method" value="paystack" checked class="accent-brand"> Refund through Paystack</label>
                                <label class="flex items-center gap-2"><input type="radio" name="method" value="manual" class="accent-brand"> I paid it back another way</label>
                            </div>
                            <button class="btn btn-outline btn-block !min-h-9" onclick="return confirm('Issue this refund?')">Issue refund</button>
                        </form>
                    @else
                        <p class="mt-2 text-copy">Fully refunded.</p>
                    @endif
                </div>
            @endif

            @if ($open)
                <form method="POST" action="{{ route('admin.orders.cancel', $order) }}" class="panel space-y-3 p-5 text-sm">
                    @csrf
                    <h2 class="type-heading text-base">Cancel order</h2>
                    <input name="reason" class="field" placeholder="Reason (emailed to the customer)" required aria-label="Cancellation reason">
                    <button class="btn btn-outline btn-block !min-h-9 !text-red-600" onclick="return confirm('Cancel this order?')">Cancel order</button>
                </form>
            @endif
        </aside>
    </div>
</x-layouts.admin>
