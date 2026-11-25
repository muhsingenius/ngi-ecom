@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-semibold text-ink">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'file' && $type !== 'password') value="{{ old($name, $value) }}" @endif
           {{ $attributes->class(['field mt-1.5', 'field-error' => $errors->has($name)]) }}>
    @if ($help)<p class="mt-1 text-xs text-muted">{{ $help }}</p>@endif
    @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
