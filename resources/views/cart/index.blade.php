@php
    use App\Support\Money;
@endphp

<x-layouts.app title="Your cart">
    <section class="section">
        <div class="container-page">
            <h1 class="type-title">Your cart</h1>

            @if (session('status'))
                <p class="mt-4 rounded-control bg-brand-tint px-4 py-3 text-sm font-semibold text-brand-strong" role="status">{{ session('status') }}</p>
            @endif

            @if ($lines->isEmpty())
                <div class="mt-8 rounded-card bg-mist p-8 text-center">
                    <p class="text-copy">Your cart is empty.</p>
                    <a href="{{ route('catalogue.index') }}" class="btn btn-brand btn-lg mt-4">Choose a product to design</a>
                </div>
            @else
                <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <ul class="space-y-4">
                        @foreach ($lines as $line)
                            @php
                                $priced = $quote['lines'][$line->design_id];
                                $variants = $line->product->variants->keyBy('id');
                                $summary = collect($line->items)->groupBy(fn ($i) => $variants[$i['variant_id']]->colour->name)
                                    ->map(fn ($items, $colour) => $colour.': '.collect($items)->groupBy(fn ($i) => $variants[$i['variant_id']]->size)->map(fn ($g, $size) => $g->sum('qty').' '.$size)->implode(', '));
                                $personalised = collect($line->items)->contains(fn ($i) => isset($i['name']) || isset($i['number']));
                            @endphp
                            <li class="panel flex flex-col gap-4 p-4 sm:flex-row">
                                <div class="flex size-28 shrink-0 items-center justify-center self-center rounded-control bg-mist">
                                    @if ($previews[$line->id])<img src="{{ $previews[$line->id] }}" alt="" class="max-h-full max-w-full object-contain">@endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <h2 class="type-heading">{{ $line->product->name }} <span class="font-normal text-muted">· {{ $line->product->quality }}</span></h2>
                                            <p class="mt-1 text-sm text-copy">{{ $summary->implode(' · ') }}</p>
                                            @if ($personalised)<p class="text-sm text-copy">With names and numbers ({{ $priced['quantity'] }} people)</p>@endif
                                        </div>
                                        <p class="text-lg font-extrabold text-ink">{{ Money::format($priced['line_total']) }}</p>
                                    </div>

                                    <p class="mt-2 text-sm text-copy">
                                        {{ $priced['quantity'] }} × {{ Money::format($priced['unit_price'] + $priced['extra_area_fee']) }}
                                        @if ($priced['saving'] > 0)<span class="font-semibold text-brand-strong">· you save {{ Money::format($priced['saving']) }}</span>@endif
                                    </p>
                                    @if ($priced['next_tier'])
                                        <p class="text-sm text-muted">Add {{ $priced['next_tier']['min_qty'] - $priced['tier_quantity'] }} more of this design to pay {{ Money::format($priced['next_tier']['unit_price']) }} each.</p>
                                    @endif

                                    <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                                        <a href="{{ route('designs.order', $line->design) }}" class="link-arrow">Change quantities</a>
                                        <a href="{{ route('designs.edit', $line->design) }}" class="link-arrow">Edit design</a>
                                        <form method="POST" action="{{ route('cart.destroy', $line) }}">@csrf @method('DELETE')<button class="text-red-600 hover:underline">Remove</button></form>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <aside class="h-fit rounded-card bg-mist p-6">
                        <h2 class="type-heading">Summary</h2>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-copy">Items</dt><dd class="font-semibold text-ink">{{ $quote['quantity'] }}</dd></div>
                            @php($saved = collect($quote['lines'])->sum('saving'))
                            @if ($saved > 0)<div class="flex justify-between"><dt class="text-copy">Bulk savings</dt><dd class="font-semibold text-brand-strong">− {{ Money::format($saved) }}</dd></div>@endif
                            <div class="flex justify-between border-t border-line pt-3 text-base"><dt class="font-semibold text-ink">Subtotal</dt><dd class="font-extrabold text-ink">{{ Money::format($quote['subtotal']) }}</dd></div>
                        </dl>
                        <p class="mt-3 text-xs text-muted">Delivery or pickup is chosen at checkout.</p>
                        @if (Route::has('checkout.show'))
                            <a href="{{ route('checkout.show') }}" class="btn btn-brand btn-lg btn-block mt-5">Checkout</a>
                        @endif
                        <a href="{{ route('catalogue.index') }}" class="link-arrow mt-4 justify-center text-sm">Add another product</a>
                    </aside>
                </div>
            @endif
        </div>
    </section>
</x-layouts.app>
