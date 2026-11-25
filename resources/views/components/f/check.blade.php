@props(['name', 'label', 'checked' => false])

<label class="flex items-center gap-2 text-sm font-medium text-ink">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked)) class="size-4 rounded border-line accent-brand">
    {{ $label }}
</label>
