@php
    use App\Support\Money;
    $personalised = $order->lines->flatMap->items->contains(fn ($i) => $i->name || $i->number);
@endphp

<x-layouts.app :title="'Proof for '.$order->number">
    <section class="section">
        <div class="container-page max-w-5xl">
            <span class="pill bg-brand-tint shadow-none"><x-icon name="eye" class="size-4" /> Proof {{ $proof->version }} · order {{ $order->number }}</span>
            <h1 class="type-title mt-4">Check your proof</h1>

            @if (session('status'))<p class="mt-4 rounded-control bg-brand-tint px-4 py-3 text-sm font-semibold text-brand-strong" role="status">{{ session('status') }}</p>@endif
            @if ($errors->any())<p class="mt-4 rounded-control bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>@endif

            @if ($proof->status === 'approved')
                <p class="mt-4 rounded-control bg-green-50 px-4 py-3 text-sm text-green-700">You approved this proof on {{ $proof->responded_at->format('j M Y') }}.
                    @if (! $order->isPaid() && ! $order->cancelled_at) Pay to start printing: <a href="{{ $order->url() }}" class="font-semibold underline">go to your order</a>.@endif</p>
            @elseif ($proof->status === 'changes_requested')
                <p class="mt-4 rounded-control bg-accent-tint px-4 py-3 text-sm text-ink">You asked for changes: “{{ $proof->comment }}”</p>
            @endif

            @if ($order->cancelled_at)
                <p class="mt-4 rounded-control bg-red-50 px-4 py-3 text-sm text-red-700">This order was cancelled, so the proof can no longer be approved.</p>
            @endif

            <div class="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div>
                    <div class="overflow-hidden rounded-card bg-mist p-2">
                        <img src="{{ route('proofs.image', $proof->token) }}" alt="Proof of your design on the product" class="block w-full rounded-chip">
                    </div>
                    <p class="mt-3 text-sm text-muted">The artwork is shown where it will be printed. Colours on screen can differ slightly from the finished print.</p>

                    @if ($personalised)
                        <div class="mt-6">
                            <h2 class="type-heading">Names and numbers</h2>
                            <p class="mt-1 text-sm text-copy">Check every spelling and number below. This is exactly what will be printed.</p>
                            @foreach ($order->lines as $line)
                                @if ($line->items->contains(fn ($i) => $i->name || $i->number))
                                    <div class="mt-3 overflow-x-auto rounded-control border border-line">
                                        <table class="w-full min-w-[22rem] text-left text-sm">
                                            <thead class="bg-mist text-muted"><tr><th class="px-3 py-2 font-semibold">#</th><th class="px-3 py-2 font-semibold">Name</th><th class="px-3 py-2 font-semibold">Number</th><th class="px-3 py-2 font-semibold">Size</th><th class="px-3 py-2 font-semibold">Colour</th></tr></thead>
                                            <tbody>@foreach ($line->items as $item)<tr class="border-t border-line"><td class="px-3 py-2 text-muted">{{ $loop->iteration }}</td><td class="px-3 py-2 font-semibold text-ink">{{ $item->name }}</td><td class="px-3 py-2 font-semibold text-ink">{{ $item->number }}</td><td class="px-3 py-2">{{ $item->size }}</td><td class="px-3 py-2">{{ $item->colour_name }}</td></tr>@endforeach</tbody>
                                        </table>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>

                <aside class="space-y-5">
                    <div class="panel p-5 text-sm">
                        @foreach ($order->lines as $line)
                            <p class="font-semibold text-ink">{{ $line->product_name }} <span class="font-normal text-muted">· {{ $line->quality }}</span></p>
                            <p class="mb-3 text-copy">{{ $line->quantity }} {{ \Illuminate\Support\Str::plural('item', $line->quantity) }}: {{ $line->items->groupBy('colour_name')->map(fn ($items, $colour) => $colour.' '.$items->groupBy('size')->map(fn ($g, $s) => $g->sum('qty').' '.$s)->implode(', '))->implode('; ') }}</p>
                        @endforeach
                        <p class="border-t border-line pt-3 font-semibold text-ink">Total {{ Money::format(Money::toMinor($order->total), $order->currency) }}</p>
                    </div>

                    @if ($proof->isOpen() && ! $order->cancelled_at)
                        <form method="POST" action="{{ route('proofs.approve', $proof->token) }}">
                            @csrf
                            <button class="btn btn-brand btn-lg btn-block"><x-icon name="check" class="size-4" /> Approve this proof</button>
                            <p class="mt-2 text-xs text-muted">{{ $order->isPaid() ? 'We will start printing as soon as you approve.' : 'After you approve, you can pay and we will start printing.' }}</p>
                        </form>

                        <form method="POST" action="{{ route('proofs.changes', $proof->token) }}" class="space-y-3 rounded-card bg-mist p-5">
                            @csrf
                            <label for="comment" class="block text-sm font-semibold text-ink">Something not right?</label>
                            <textarea id="comment" name="comment" rows="4" class="field py-2" placeholder="Tell us what to change" required>{{ old('comment') }}</textarea>
                            <button class="btn btn-outline btn-block">Request changes</button>
                            <p class="text-xs text-muted">Round {{ $order->revisions_used + 1 }} of {{ $maxRevisions }} before we follow up personally.</p>
                        </form>
                    @endif

                    @if ($proof->status === 'changes_requested' && $canRevise)
                        <div class="panel p-5">
                            <h2 class="type-heading text-base">Edit the design yourself</h2>
                            <p class="mt-1 text-sm text-copy">Or leave it to us: we can update it and send a new proof.</p>
                            @foreach ($order->lines as $line)
                                <form method="POST" action="{{ route('proofs.revise', [$proof->token, $line]) }}" class="mt-3">@csrf<button class="btn btn-outline btn-block !min-h-10">Edit {{ $line->product_name }} design</button></form>
                            @endforeach
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    </section>
</x-layouts.app>
