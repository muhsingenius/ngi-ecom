@php
    use App\Enums\PrintMethod;
    use App\Support\Money;

    $typeOptions = collect($types)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
    $scopeOptions = collect($scopes)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all();
    $methodOptions = collect(PrintMethod::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all();
    $tiers = old('tiers', $product->tiers->map(fn ($t) => ['min_qty' => $t->min_qty, 'unit_price' => $t->unit_price])->all());
@endphp

<x-layouts.admin :heading="$product->name.' · '.$product->quality">
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.products.index') }}" class="link-arrow">← All products</a>
        <a href="{{ route('catalogue.show', $product) }}" class="link-arrow" target="_blank" rel="noopener">View in shop</a>
    </div>

    {{-- Details and price tiers --}}
    <form method="POST" action="{{ route('admin.products.update', $product) }}" enctype="multipart/form-data" class="panel mt-6 space-y-5 p-6">
        @csrf @method('PUT')
        <h2 class="type-heading">Details and bulk prices</h2>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-f.select name="type" label="Product type" :options="$typeOptions" :value="$product->type->value" />
            <x-f.input name="name" label="Name" :value="$product->name" required />
            <x-f.input name="quality" label="Quality" :value="$product->quality" required />
            <x-f.input name="sort" label="Sort order" type="number" min="0" :value="$product->sort" />
            <x-f.input name="included_print_areas" label="Print areas included in the price" type="number" min="0" max="5" :value="$product->included_print_areas" help="Extra areas beyond this cost each area's extra fee." />
            <x-f.select name="tier_scope_override" label="Tier scope for this product" :options="$scopeOptions" :value="$product->tier_scope_override?->value" blank="Use the store setting" help="Override to e.g. keep bracelets out of cross-product discounts." />
        </div>

        <div>
            <label for="description" class="block text-sm font-semibold text-ink">Description</label>
            <textarea id="description" name="description" rows="3" class="field mt-1.5">{{ old('description', $product->description) }}</textarea>
        </div>

        <div class="flex flex-wrap items-center gap-4">
            @if ($product->imageUrl())<img src="{{ $product->imageUrl() }}" alt="" class="size-16 rounded-chip bg-mist object-cover">@endif
            <x-f.input name="image" label="Shop image (optional)" type="file" accept="image/*" />
        </div>

        <div x-data="{ tiers: @js(array_values($tiers)) }">
            <p class="text-sm font-semibold text-ink">Price tiers <span class="font-normal text-muted">(the first must start at quantity 1)</span></p>
            @error('tiers')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            <div class="mt-3 space-y-2">
                <template x-for="(tier, i) in tiers" :key="i">
                    <div class="flex items-center gap-3">
                        <input type="number" min="1" class="field w-28" :name="`tiers[${i}][min_qty]`" x-model.number="tier.min_qty" aria-label="From quantity">
                        <span class="text-sm text-copy">and up: GH₵</span>
                        <input type="number" min="0" step="0.01" class="field w-32" :name="`tiers[${i}][unit_price]`" x-model="tier.unit_price" aria-label="Price each">
                        <button type="button" class="text-sm text-red-600" x-on:click="tiers.splice(i, 1)" x-show="tiers.length > 1">Remove</button>
                    </div>
                </template>
            </div>
            <button type="button" class="link-arrow mt-3 text-sm" x-on:click="tiers.push({ min_qty: (tiers.at(-1)?.min_qty ?? 0) + 10, unit_price: tiers.at(-1)?.unit_price ?? 0 })">+ Add tier</button>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4 border-t border-line pt-5">
            <x-f.check name="active" label="Show in the shop" :checked="$product->active" />
            <button class="btn btn-brand">Save product</button>
        </div>
    </form>

    {{-- Colours --}}
    <div class="panel mt-6 p-6">
        <h2 class="type-heading">Colours</h2>
        <div class="mt-4 space-y-3">
            @foreach ($product->colours as $colour)
                <div class="flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('admin.colours.update', $colour) }}" class="flex flex-wrap items-center gap-3">
                        @csrf @method('PUT')
                        <input type="color" name="hex" value="{{ $colour->hex }}" class="size-10 rounded-control border border-line" aria-label="Colour">
                        <input type="text" name="name" value="{{ $colour->name }}" class="field w-40" aria-label="Colour name">
                        <input type="hidden" name="active" value="0">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" @checked($colour->active) class="size-4 accent-brand"> On</label>
                        <button class="btn btn-outline !min-h-9">Save</button>
                    </form>
                    <form method="POST" action="{{ route('admin.colours.destroy', $colour) }}" onsubmit="return confirm('Remove this colour and its sizes and mockups?')">
                        @csrf @method('DELETE')
                        <button class="text-sm text-red-600">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('admin.colours.store', $product) }}" class="mt-5 flex flex-wrap items-end gap-3 border-t border-line pt-5">
            @csrf
            <input type="color" name="hex" value="#1d4ed8" class="size-10 rounded-control border border-line" aria-label="New colour">
            <x-f.input name="name" label="New colour name" class="w-48" required />
            <button class="btn btn-brand">Add colour</button>
        </form>
    </div>

    {{-- Sizes and availability --}}
    <div class="panel mt-6 p-6">
        <h2 class="type-heading">Sizes and availability</h2>
        <p class="mt-1 text-sm text-copy">A size is created for every colour. The surcharge is added to each item of that size.</p>

        <div class="mt-4 space-y-3">
            @foreach ($sizes as $size => $surcharge)
                <div class="flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('admin.sizes.update', $product) }}" class="flex items-center gap-3">
                        @csrf @method('PUT')
                        <input type="hidden" name="size" value="{{ $size }}">
                        <span class="w-20 font-semibold text-ink">{{ $size }}</span>
                        <span class="text-sm text-copy">+ GH₵</span>
                        <input type="number" step="0.01" min="0" name="size_surcharge" value="{{ $surcharge }}" class="field w-28" aria-label="Surcharge for {{ $size }}">
                        <button class="btn btn-outline !min-h-9">Save</button>
                    </form>
                    <form method="POST" action="{{ route('admin.sizes.destroy', $product) }}" onsubmit="return confirm('Remove size {{ $size }} from every colour?')">
                        @csrf @method('DELETE')
                        <input type="hidden" name="size" value="{{ $size }}">
                        <button class="text-sm text-red-600">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.sizes.store', $product) }}" class="mt-5 flex flex-wrap items-end gap-3 border-t border-line pt-5">
            @csrf
            <x-f.input name="size" label="New size" class="w-32" placeholder="XL" required />
            <x-f.input name="size_surcharge" label="Surcharge (GH₵)" type="number" step="0.01" min="0" value="0" class="w-32" />
            <button class="btn btn-brand">Add size</button>
        </form>

        @if ($product->variants->isNotEmpty())
            <form method="POST" action="{{ route('admin.variants.update', $product) }}" class="mt-6 border-t border-line pt-5">
                @csrf @method('PUT')
                <p class="text-sm font-semibold text-ink">In stock <span class="font-normal text-muted">(untick a combination when you are out of that blank)</span></p>
                <div class="mt-3 overflow-x-auto">
                    <table class="text-sm">
                        <thead><tr><th class="py-1 pr-4 text-left font-semibold text-muted">Colour</th>@foreach ($sizes as $size => $s)<th class="px-3 py-1 font-semibold text-muted">{{ $size }}</th>@endforeach</tr></thead>
                        <tbody>
                            @foreach ($product->colours as $colour)
                                <tr>
                                    <td class="py-1 pr-4 text-ink">{{ $colour->name }}</td>
                                    @foreach ($sizes as $size => $s)
                                        @php($variant = $product->variants->first(fn ($v) => $v->product_colour_id === $colour->id && $v->size === $size))
                                        <td class="px-3 py-1 text-center">
                                            @if ($variant)<input type="checkbox" name="variants[{{ $variant->id }}]" value="1" @checked($variant->active) class="size-4 accent-brand" aria-label="{{ $colour->name }} {{ $size }}">@endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="btn btn-outline mt-4">Save availability</button>
            </form>
        @endif
    </div>

    {{-- Print areas --}}
    <div class="panel mt-6 p-6">
        <h2 class="type-heading">Print areas</h2>
        <p class="mt-1 text-sm text-copy">Size drives print-file resolution (300 DPI at this size). The box positions the area on the mockup, as a percentage of the image.</p>

        @foreach ($product->printAreas as $area)
            <details class="mt-4 rounded-control border border-line p-4" @if ($loop->first && $product->printAreas->count() === 1) open @endif>
                <summary class="cursor-pointer font-semibold text-ink">{{ $area->name }} <span class="font-normal text-muted">· {{ $area->method->label() }} · {{ $area->width_cm }} × {{ $area->height_cm }} cm @if(! $area->active) · off @endif</span></summary>
                <form method="POST" action="{{ route('admin.areas.update', $area) }}" class="mt-4">
                    @csrf @method('PUT')
                    @include('admin.products._area-fields', ['area' => $area, 'methodOptions' => $methodOptions])
                    <div class="mt-4 flex gap-3"><button class="btn btn-brand">Save area</button></div>
                </form>
                <form method="POST" action="{{ route('admin.areas.destroy', $area) }}" class="mt-3" onsubmit="return confirm('Remove this print area?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-red-600">Remove area</button>
                </form>
            </details>
        @endforeach

        <details class="mt-4 rounded-control border border-dashed border-line p-4" @if ($product->printAreas->isEmpty()) open @endif>
            <summary class="cursor-pointer font-semibold text-brand-strong">+ Add print area</summary>
            <form method="POST" action="{{ route('admin.areas.store', $product) }}" class="mt-4">
                @csrf
                @include('admin.products._area-fields', ['area' => null, 'methodOptions' => $methodOptions])
                <button class="btn btn-brand mt-4">Add area</button>
            </form>
        </details>
    </div>

    {{-- Mockups --}}
    @if ($product->colours->isNotEmpty() && $product->printAreas->isNotEmpty())
        <div class="panel mt-6 p-6">
            <h2 class="type-heading">Mockups</h2>
            <p class="mt-1 text-sm text-copy">One image per colour per print area. The designer places the customer's artwork on top of it.</p>
            <div class="mt-4 grid gap-6 lg:grid-cols-2">
                @foreach ($product->colours as $colour)
                    <div class="rounded-control border border-line p-4">
                        <p class="flex items-center gap-2 font-semibold text-ink"><span class="size-4 rounded-full border border-line" style="background: {{ $colour->hex }}"></span>{{ $colour->name }}</p>
                        <div class="mt-3 space-y-3">
                            @foreach ($product->printAreas as $area)
                                @php($mockup = $colour->mockups->firstWhere('print_area_id', $area->id))
                                <div class="flex flex-wrap items-center gap-3">
                                    @if ($mockup)<img src="{{ $mockup->url() }}" alt="" class="size-14 rounded-chip bg-mist object-contain">@else<span class="flex size-14 items-center justify-center rounded-chip bg-mist text-xs text-muted">none</span>@endif
                                    <span class="w-24 text-sm text-ink">{{ $area->name }}</span>
                                    <form method="POST" action="{{ route('admin.mockups.store', $colour) }}" enctype="multipart/form-data" class="flex items-center gap-2">
                                        @csrf
                                        <input type="hidden" name="print_area_id" value="{{ $area->id }}">
                                        <input type="file" name="image" accept="image/*" required class="max-w-[11rem] text-xs" aria-label="Mockup for {{ $colour->name }} {{ $area->name }}">
                                        <button class="btn btn-outline !min-h-8 !px-3 text-xs">Upload</button>
                                    </form>
                                    @if ($mockup)
                                        <form method="POST" action="{{ route('admin.mockups.destroy', $mockup) }}">@csrf @method('DELETE')<button class="text-xs text-red-600">Remove</button></form>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.products.destroy', $product) }}" class="mt-8" onsubmit="return confirm('Delete this product permanently?')">
        @csrf @method('DELETE')
        <button class="text-sm text-red-600">Delete product</button>
    </form>
</x-layouts.admin>
