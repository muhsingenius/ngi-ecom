@php
    $v = fn ($key, $default = null) => $area?->{$key} ?? $default;
@endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <x-f.input name="name" label="Name" :value="$v('name')" placeholder="Front" required />
    <x-f.input name="width_cm" label="Width (cm)" type="number" step="0.1" min="0.5" :value="$v('width_cm')" required />
    <x-f.input name="height_cm" label="Height (cm)" type="number" step="0.1" min="0.5" :value="$v('height_cm')" required />
    <x-f.select name="method" label="Method" :options="$methodOptions" :value="$area?->method->value" />
    <x-f.input name="extra_fee" label="Extra-area fee (GH₵)" type="number" step="0.01" min="0" :value="$v('extra_fee', 0)" help="Charged per item when used beyond the included areas." />
    <x-f.input name="max_colours" label="Max thread colours" type="number" min="1" :value="$v('max_colours')" help="Embroidery only." />
    <x-f.input name="min_line_mm" label="Min line/text height (mm)" type="number" step="0.5" min="0.5" :value="$v('min_line_mm')" help="Embroidery only." />
    <x-f.input name="max_chars" label="Max characters" type="number" min="1" :value="$v('max_chars')" help="Engraving only." />
    <x-f.input name="box_x" label="Box left (%)" type="number" step="0.1" min="0" max="100" :value="$v('box_x', 30)" />
    <x-f.input name="box_y" label="Box top (%)" type="number" step="0.1" min="0" max="100" :value="$v('box_y', 25)" />
    <x-f.input name="box_w" label="Box width (%)" type="number" step="0.1" min="1" max="100" :value="$v('box_w', 40)" />
    <x-f.input name="box_h" label="Box height (%)" type="number" step="0.1" min="1" max="100" :value="$v('box_h', 40)" />
</div>
<div class="mt-4 flex flex-wrap gap-6">
    <x-f.check name="allows_name" label="Allow names" :checked="$v('allows_name', false)" />
    <x-f.check name="allows_number" label="Allow numbers" :checked="$v('allows_number', false)" />
    <x-f.check name="active" label="Available" :checked="$v('active', true)" />
</div>
