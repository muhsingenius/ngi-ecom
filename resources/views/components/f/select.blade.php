@props(['name', 'label', 'options' => [], 'value' => null, 'blank' => null, 'help' => null])

<div>
    <label for="{{ $name }}" class="block text-sm font-semibold text-ink">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['field mt-1.5']) }}>
        @if ($blank !== null)<option value="">{{ $blank }}</option>@endif
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @if ($help)<p class="mt-1 text-xs text-muted">{{ $help }}</p>@endif
    @error($name)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
