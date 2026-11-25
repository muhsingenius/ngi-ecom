@php
    $v = fn ($key, $default = null) => $zone?->{$key} ?? $default;
    $bands = old('bands', $zone?->bands ?? [['min_qty' => 1, 'max_qty' => 20, 'fee' => 30], ['min_qty' => 21, 'max_qty' => null, 'fee' => 60]]);
@endphp

<div x-data="{ type: @js(old('fee_type', $v('fee_type', 'flat'))), bands: @js(array_values($bands)) }" class="space-y-5">
    <div class="grid gap-5 sm:grid-cols-2">
        <x-f.input name="name" label="Zone name" :value="$v('name')" placeholder="Greater Accra" required />
        <x-f.input name="eta" label="Estimated delivery time" :value="$v('eta')" placeholder="1-2 working days" />
        <x-f.input name="courier_name" label="Courier (optional)" :value="$v('courier_name')" />
        <x-f.input name="free_threshold" label="Free delivery above (GH₵)" type="number" step="0.01" min="0" :value="$v('free_threshold')" help="Blank uses the store-wide setting." />
    </div>

    <div>
        <p class="text-sm font-semibold text-ink">Fee</p>
        <div class="mt-2 flex gap-6 text-sm">
            <label class="flex items-center gap-2"><input type="radio" name="fee_type" value="flat" x-model="type" class="accent-brand"> One flat fee</label>
            <label class="flex items-center gap-2"><input type="radio" name="fee_type" value="banded" x-model="type" class="accent-brand"> By number of items</label>
        </div>

        <div class="mt-4" x-show="type === 'flat'"><x-f.input name="flat_fee" label="Flat fee (GH₵)" type="number" step="0.01" min="0" :value="$v('flat_fee', 0)" class="max-w-xs" /></div>

        <div class="mt-4 space-y-2" x-show="type === 'banded'" x-cloak>
            <template x-for="(band, i) in bands" :key="i">
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-copy">From</span>
                    <input type="number" min="1" class="field w-20" :name="`bands[${i}][min_qty]`" x-model.number="band.min_qty" aria-label="From items">
                    <span class="text-copy">to</span>
                    <input type="number" min="1" class="field w-20" :name="`bands[${i}][max_qty]`" x-model="band.max_qty" placeholder="no limit" aria-label="Up to items">
                    <span class="text-copy">items: GH₵</span>
                    <input type="number" min="0" step="0.01" class="field w-24" :name="`bands[${i}][fee]`" x-model="band.fee" aria-label="Fee">
                    <button type="button" class="text-red-600" x-on:click="bands.splice(i, 1)" x-show="bands.length > 1">Remove</button>
                </div>
            </template>
            <button type="button" class="link-arrow text-sm" x-on:click="bands.push({ min_qty: (Number(bands.at(-1)?.max_qty) || Number(bands.at(-1)?.min_qty) || 0) + 1, max_qty: null, fee: bands.at(-1)?.fee ?? 0 })">+ Add band</button>
            @error('bands')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <x-f.check name="active" label="Available to customers" :checked="$v('active', true)" />
</div>
