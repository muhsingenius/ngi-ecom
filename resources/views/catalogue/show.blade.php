@php
    use App\Enums\TierScope;
    use App\Support\Money;

    $symbol = Money::symbol();
    $firstColour = $product->colours->first();
    $firstArea = $product->printAreas->first();
    $mockupFor = fn ($colour) => $colour?->mockups->firstWhere('print_area_id', $firstArea?->id)?->url() ?? $product->imageUrl();
    $hasPhoto = str_starts_with((string) $product->image, 'media/');
    $colourData =$product->colours->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'hex' => $c->hex, 'image' => $mockupFor($c)])->values();
@endphp

<x-layouts.app :title="$product->name">
    <section class="section">
        <div class="container-page grid gap-10 lg:grid-cols-2 lg:gap-16"
             x-data="{
                tiers: @js($tiersJson),
                colours: @js($colourData),
                colour: 0,
                photo: @js($hasPhoto),
                photoUrl: @js($product->imageUrl()),
                qty: 1,
                symbol: @js($symbol),
                get tier() { let t = this.tiers[0]; for (const x of this.tiers) { if (x.min <= Math.max(this.qty, 1)) t = x; } return t; },
                get single() { return this.tiers[0].price; },
                get unit() { return this.tier.price; },
                get total() { return this.unit * Math.max(this.qty, 1); },
                get saving() { return (this.single - this.unit) * Math.max(this.qty, 1); },
                get next() { return this.tiers.find(x => x.min > this.qty); },
                money(minor) { const v = minor / 100; return this.symbol + ' ' + (minor % 100 === 0 ? v.toLocaleString('en-GH') : v.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })); },
             }">

            {{-- Mockup and colour picker --}}
            <div>
                <div class="overflow-hidden rounded-card bg-mist">
                    <img x-bind:src="photo ? photoUrl : (colours[colour]?.image ?? photoUrl)" alt="{{ $product->name }}" class="aspect-[4/5] w-full object-contain p-6">
                </div>

                @if ($hasPhoto)
                    <button type="button" class="mt-3 text-sm font-semibold text-brand-strong underline" x-show="! photo" x-cloak x-on:click="photo = true">Show product photo</button>
                @endif

                @if ($product->colours->count() > 1)
                    <div class="mt-5">
                        <p class="text-sm font-semibold text-ink">Colour: <span class="font-normal text-copy" x-text="colours[colour]?.name"></span></p>
                        <div class="mt-3 flex flex-wrap gap-3">
                            @foreach ($product->colours as $i => $c)
                                <button type="button" x-on:click="colour = {{ $i }}; photo = false" title="{{ $c->name }}" aria-label="{{ $c->name }}"
                                        class="size-9 rounded-full border border-line ring-offset-2"
                                        x-bind:class="colour === {{ $i }} ? 'ring-2 ring-brand' : ''" style="background: {{ $c->hex }}"></button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <span class="pill bg-brand-tint shadow-none">{{ $product->type->label() }}</span>
                    <span class="rounded-full bg-mist px-3 py-1 text-xs font-semibold text-copy">{{ $product->quality }} quality</span>
                </div>

                <h1 class="type-title mt-4">{{ $product->name }}</h1>
                @if ($product->description)
                    <p class="type-lead mt-3">{{ $product->description }}</p>
                @endif

                {{-- Live price --}}
                <div class="mt-8 rounded-card border border-line p-6">
                    <div class="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p class="text-sm text-copy">Price per item</p>
                            <p class="text-4xl font-extrabold tracking-tight text-ink" x-text="money(unit)"></p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-copy">Total for <span x-text="Math.max(qty, 1)"></span></p>
                            <p class="text-2xl font-extrabold text-brand-strong" x-text="money(total)"></p>
                        </div>
                    </div>

                    <div class="mt-5 flex items-center gap-3">
                        <label for="qty" class="text-sm font-semibold text-ink">Quantity</label>
                        <div class="flex items-center">
                            <button type="button" class="btn btn-outline size-11 !min-h-11 !px-0" x-on:click="qty = Math.max(1, qty - 1)" aria-label="Fewer">−</button>
                            <input id="qty" type="number" min="1" max="10000" x-model.number="qty" class="field mx-2 w-24 text-center">
                            <button type="button" class="btn btn-outline size-11 !min-h-11 !px-0" x-on:click="qty = qty + 1" aria-label="More">+</button>
                        </div>
                    </div>

                    <p class="mt-4 text-sm font-semibold text-brand-strong" x-show="saving > 0" x-cloak>
                        You save <span x-text="money(saving)"></span> compared with the single-item price.
                    </p>
                    <p class="mt-1 text-sm text-copy" x-show="next" x-cloak>
                        Add <span x-text="next.min - Math.max(qty, 1)"></span> more to pay <span class="font-semibold" x-text="money(next.price)"></span> each.
                    </p>
                </div>

                <a x-bind:href="`{{ route('designs.create', $product) }}?colour=${colours[colour]?.id ?? ''}`" class="btn btn-brand btn-lg btn-block mt-5">
                    <x-icon name="pencil" class="size-4" /> Start designing
                </a>

                {{-- Tier table --}}
                <div class="mt-6">
                    <h2 class="type-heading">Bulk prices</h2>
                    <table class="mt-3 w-full text-left text-sm">
                        <thead class="text-muted"><tr><th class="py-2 font-semibold">Quantity</th><th class="py-2 font-semibold">Each</th><th class="py-2 text-right font-semibold">Save</th></tr></thead>
                        <tbody>
                            @foreach ($product->tiers as $i => $tier)
                                @php($next = $product->tiers->get($i + 1))
                                <tr class="border-t border-line" x-bind:class="tier.min === {{ $tier->min_qty }} ? 'bg-brand-tint/60 font-semibold' : ''">
                                    <td class="py-3 pl-2">{{ $tier->min_qty }}{{ $next ? '–'.($next->min_qty - 1) : '+' }}</td>
                                    <td class="py-3">{{ Money::format(Money::toMinor($tier->unit_price)) }}</td>
                                    <td class="py-3 pr-2 text-right text-brand-strong">
                                        @if ($i > 0)
                                            {{ Money::format(Money::toMinor($product->singlePrice() - $tier->unit_price)) }} off each
                                        @else
                                            n/a
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-3 text-sm text-copy">
                        Sizes and colours count together.
                        @if ($scope === TierScope::Order)
                            Every item in your order counts towards the quantity.
                        @elseif ($scope === TierScope::Design)
                            All items carrying the same design count together, even across products.
                        @else
                            Items of this product with the same design count together.
                        @endif
                    </p>
                </div>

                {{-- What's included --}}
                <div class="mt-6 rounded-card bg-mist p-6 text-sm text-copy">
                    <h2 class="type-heading text-base">What the price includes</h2>
                    <ul class="mt-3 space-y-2">
                        <li>{{ $product->included_print_areas === 1 ? 'One print area of your choice' : $product->included_print_areas.' print areas of your choice' }}.</li>
                        @foreach ($product->printAreas as $area)
                            <li>
                                <span class="font-semibold text-ink">{{ $area->name }}</span>:
                                {{ rtrim(rtrim(number_format($area->width_cm, 1), '0'), '.') }} × {{ rtrim(rtrim(number_format($area->height_cm, 1), '0'), '.') }} cm, {{ strtolower($area->method->label()) }}
                                @if ((float) $area->extra_fee > 0) · {{ Money::format(Money::toMinor($area->extra_fee)) }} each extra @endif
                                @if ($area->allowsPersonalisation()) · names and numbers available @endif
                            </li>
                        @endforeach
                        @if ($surcharges->isNotEmpty())
                            <li>Larger sizes cost a little more: @foreach ($surcharges as $size => $minor){{ $size }} +{{ Money::format($minor) }}{{ ! $loop->last ? ', ' : '' }}@endforeach.</li>
                        @endif
                    </ul>
                </div>

                @if ($product->sizes())
                    <p class="mt-6 text-sm text-copy">Sizes: {{ implode(', ', $product->sizes()) }}</p>
                @endif
            </div>
        </div>
    </section>
</x-layouts.app>
