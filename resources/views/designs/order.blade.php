@php
    $fontFamilies = collect($fonts)->map(fn ($f) => strtolower(str_replace(' ', '-', $f)).':400,700')->implode('|');
    $personalised = $config['needsName'] || $config['needsNumber'];
    $hasRoles = collect($config['preview']['roles'] ?? [])->isNotEmpty();
@endphp

<x-layouts.app title="Choose sizes and quantities">
    @push('head')
        @if ($hasRoles)
            <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
            <link rel="stylesheet" href="https://fonts.bunny.net/css?family={{ $fontFamilies }}&display=swap">
        @endif
    @endpush

    <section class="section">
        <div class="container-page" x-data="orderForm(@js($config))">
            <span class="pill bg-brand-tint shadow-none"><x-icon name="check" class="size-4" /> Design saved</span>
            <h1 class="type-title mt-4">How many do you need?</h1>
            <p class="type-lead mt-2">{{ $product->name }} · {{ $product->quality }}. Sizes and colours count together towards your bulk price.</p>

            <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)] lg:gap-14">
                {{-- Preview --}}
                <div>
                    @if ($config['preview'])
                        <div class="relative mx-auto w-full max-w-[26rem] overflow-hidden rounded-card bg-mist">
                            <img x-bind:src="cfg.preview.mockups[previewColour] ?? Object.values(cfg.preview.mockups)[0]" alt="" class="block w-full">
                            <div class="absolute" style="container-type: inline-size; left: {{ $config['preview']['box']['x'] }}%; top: {{ $config['preview']['box']['y'] }}%; width: {{ $config['preview']['box']['w'] }}%; aspect-ratio: {{ $config['preview']['widthCm'] }} / {{ $config['preview']['heightCm'] }};">
                                <img src="{{ $config['preview']['image'] }}" alt="Your design" class="absolute inset-0 size-full object-contain">
                                @foreach ($config['preview']['roles'] ?? [] as $role => $r)
                                    <span class="absolute whitespace-nowrap leading-none"
                                          style="left: {{ ($r['x_cm'] + $r['width_cm'] / 2) / $config['preview']['widthCm'] * 100 }}%; top: {{ ($r['y_cm'] + $r['height_cm'] / 2) / $config['preview']['heightCm'] * 100 }}%; transform: translate(-50%, -50%) rotate({{ $r['angle'] }}deg); font-family: '{{ $r['font'] }}'; font-size: {{ $r['font_height_mm'] / 10 / $config['preview']['widthCm'] * 100 }}cqw; color: {{ $r['fill'] }};"
                                          x-text="mode === 'roster' && previewRow ? ('{{ $role }}' === 'name' ? previewRow.name : previewRow.number) || @js($r['sample']) : @js($r['sample'])"></span>
                                @endforeach
                            </div>
                        </div>

                        @if ($hasRoles)
                            <div class="mx-auto mt-3 flex max-w-[26rem] items-center justify-between gap-3" x-show="mode === 'roster' && rows.length > 1" x-cloak>
                                <button type="button" class="btn btn-outline !min-h-10" x-on:click="step(-1)" aria-label="Previous person">←</button>
                                <p class="text-sm text-copy">Preview <span class="font-semibold text-ink" x-text="previewIndex + 1"></span> of <span x-text="rows.length"></span></p>
                                <button type="button" class="btn btn-outline !min-h-10" x-on:click="step(1)" aria-label="Next person">→</button>
                            </div>
                        @endif
                    @endif

                    <div class="mx-auto mt-4 flex max-w-[26rem] flex-wrap gap-4 text-sm">
                        <a href="{{ route('designs.edit', $design) }}" class="link-arrow">Edit design</a>
                        <details class="relative">
                            <summary class="link-arrow cursor-pointer">Use this design on another product</summary>
                            <ul class="absolute left-0 z-10 mt-2 w-64 rounded-control border border-line bg-white p-2 shadow-float">
                                @forelse ($others as $other)
                                    <li><a class="block rounded-control px-3 py-2 hover:bg-mist" href="{{ route('designs.create', ['product' => $other, 'from' => $design->id]) }}">{{ $other->name }} <span class="text-muted">· {{ $other->quality }}</span></a></li>
                                @empty
                                    <li class="px-3 py-2 text-muted">No other products yet.</li>
                                @endforelse
                            </ul>
                        </details>
                    </div>
                </div>

                {{-- Form --}}
                <form method="POST" action="{{ route('cart.store') }}" x-ref="form" x-on:submit.prevent="submit()" class="space-y-6">
                    @csrf
                    <input type="hidden" name="design_id" value="{{ $design->id }}">
                    <input type="hidden" name="items" x-ref="items">

                    @if ($hasRoles && ! $personalised)
                        <div class="flex gap-2" role="tablist">
                            <button type="button" role="tab" class="btn" :class="mode === 'sizes' ? 'btn-brand' : 'btn-outline'" x-on:click="mode = 'sizes'">By size</button>
                            <button type="button" role="tab" class="btn" :class="mode === 'roster' ? 'btn-brand' : 'btn-outline'" x-on:click="mode = 'roster'; if (! rows.length) addRows(1)">Names and numbers</button>
                        </div>
                    @endif

                    @if ($errors->any())
                        <p class="rounded-control bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $errors->first() }}</p>
                    @endif

                    {{-- Quantities by size and colour --}}
                    <div x-show="mode === 'sizes'" class="space-y-4">
                        <template x-for="id in colourIds" :key="id">
                            <div class="panel p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="flex items-center gap-2 font-semibold text-ink"><span class="size-5 rounded-full border border-line" :style="`background:${colour(id).hex}`"></span><span x-text="colour(id).name"></span></p>
                                    <button type="button" class="text-sm text-red-600" x-show="colourIds.length > 1" x-on:click="removeColour(id)">Remove</button>
                                </div>
                                <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                                    <template x-for="size in cfg.sizes" :key="size">
                                        <label class="block text-center text-xs text-copy">
                                            <span class="font-semibold text-ink" x-text="size"></span>
                                            <span class="block h-4 text-[0.7rem] text-muted" x-text="(variantFor(id, size)?.surcharge ?? 0) > 0 ? '+' + money(variantFor(id, size).surcharge) : ''"></span>
                                            <input type="number" min="0" :max="cfg.maxQty" inputmode="numeric" class="field mt-1 px-1 text-center" :disabled="! variantFor(id, size)"
                                                   x-model.number="qty[id + ':' + size]" placeholder="0" :aria-label="colour(id).name + ' size ' + size">
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div x-show="addableColours.length" class="flex items-center gap-3">
                            <label for="add-colour" class="text-sm font-semibold text-ink">Add another colour</label>
                            <select id="add-colour" class="field w-48" x-on:change="addColour($event.target.value); $event.target.value = ''">
                                <option value="">Choose…</option>
                                <template x-for="c in addableColours" :key="c.id"><option :value="c.id" x-text="c.name"></option></template>
                            </select>
                        </div>
                    </div>

                    {{-- Roster --}}
                    <div x-show="mode === 'roster'" x-cloak class="space-y-4">
                        <p class="text-sm text-copy">One row per person. Add rows one by one or import a CSV with the columns <span class="font-semibold text-ink">name, number, size</span> (and optionally colour).</p>

                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[30rem] text-sm">
                                <thead class="text-left text-muted"><tr>
                                    <th class="w-8 py-2 font-semibold">#</th>
                                    @if ($config['needsName'])<th class="py-2 pr-2 font-semibold">Name</th>@endif
                                    @if ($config['needsNumber'])<th class="w-24 py-2 pr-2 font-semibold">Number</th>@endif
                                    <th class="w-24 py-2 pr-2 font-semibold">Size</th>
                                    @if (count($config['colours']) > 1)<th class="py-2 pr-2 font-semibold">Colour</th>@endif
                                    <th class="w-10"></th>
                                </tr></thead>
                                <tbody>
                                    <template x-for="(row, i) in rows" :key="i">
                                        <tr class="border-t border-line" x-on:focusin="previewIndex = i">
                                            <td class="py-2 text-muted" x-text="i + 1"></td>
                                            @if ($config['needsName'])<td class="py-2 pr-2"><input class="field" x-model="row.name" maxlength="30" :aria-label="'Name, row ' + (i + 1)"></td>@endif
                                            @if ($config['needsNumber'])<td class="py-2 pr-2"><input class="field" x-model="row.number" maxlength="4" inputmode="numeric" :aria-label="'Number, row ' + (i + 1)"></td>@endif
                                            <td class="py-2 pr-2"><select class="field" x-model="row.size" :aria-label="'Size, row ' + (i + 1)"><template x-for="s in cfg.sizes" :key="s"><option :value="s" x-text="s"></option></template></select></td>
                                            @if (count($config['colours']) > 1)<td class="py-2 pr-2"><select class="field" x-model.number="row.colour" :aria-label="'Colour, row ' + (i + 1)"><template x-for="c in cfg.colours" :key="c.id"><option :value="c.id" x-text="c.name"></option></template></select></td>@endif
                                            <td class="py-2"><button type="button" class="text-red-600" x-on:click="removeRow(i)" :aria-label="'Remove row ' + (i + 1)">✕</button></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" class="btn btn-outline !min-h-10" x-on:click="addRows(1)">Add row</button>
                            <button type="button" class="btn btn-outline !min-h-10" x-on:click="addRows(5)">Add 5 rows</button>
                            <label class="btn btn-outline !min-h-10 cursor-pointer">Import CSV<input type="file" accept=".csv,text/csv,text/plain" class="hidden" x-on:change="importCsv($event)"></label>
                            <button type="button" class="text-sm text-red-600" x-show="rows.length" x-on:click="rows = []">Clear all</button>
                        </div>

                        <ul class="space-y-2" x-show="rowProblems.length" role="alert">
                            <template x-for="(p, i) in rowProblems" :key="i"><li class="rounded-control px-3 py-2 text-sm" :class="p.level === 'error' ? 'bg-red-50 text-red-700' : 'bg-accent-tint text-ink'" x-text="p.text"></li></template>
                        </ul>
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-semibold text-ink">Notes for the print shop (optional)</label>
                        <textarea id="notes" name="notes" rows="2" maxlength="1000" class="field mt-1.5 py-2" x-model="notes"></textarea>
                    </div>

                    {{-- Price --}}
                    <div class="rounded-card border border-line p-5" aria-live="polite">
                        <template x-if="! quote || ! quote.quantity">
                            <p class="text-sm text-copy">Choose quantities to see your price.</p>
                        </template>
                        <template x-if="quote && quote.quantity">
                            <div>
                                <div class="flex flex-wrap items-end justify-between gap-3">
                                    <div>
                                        <p class="text-sm text-copy"><span x-text="quote.quantity"></span> <span x-text="quote.quantity === 1 ? 'item' : 'items'"></span> at</p>
                                        <p class="text-3xl font-extrabold tracking-tight text-ink"><span x-text="money(quote.unit_price)"></span> <span class="text-base font-medium text-copy">each</span></p>
                                    </div>
                                    <p class="text-2xl font-extrabold text-brand-strong" x-text="money(quote.line_total)"></p>
                                </div>
                                <p class="mt-3 text-sm font-semibold text-brand-strong" x-show="quote.saving > 0">You save <span x-text="money(quote.saving)"></span> compared with the single-item price.</p>
                                <p class="mt-1 text-sm text-copy" x-show="quote.extra_area_fee > 0">Includes <span x-text="money(quote.extra_area_fee)"></span> each for extra print areas.</p>
                                <p class="mt-1 text-sm text-copy" x-show="quote.next_tier">Add <span x-text="quote.next_tier ? quote.next_tier.min_qty - quote.tier_quantity : 0"></span> more of this design to pay <span class="font-semibold" x-text="quote.next_tier ? money(quote.next_tier.unit_price) : ''"></span> each.</p>
                            </div>
                        </template>
                    </div>

                    <p x-show="message" x-cloak x-text="message" class="rounded-control bg-red-50 px-3 py-2 text-sm text-red-700" role="alert"></p>

                    <button type="submit" class="btn btn-brand btn-lg btn-block" x-bind:disabled="! total">Add to cart</button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.app>
