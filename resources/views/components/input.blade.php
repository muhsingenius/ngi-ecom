@props(['name', 'label', 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium text-stone-700">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name) }}" @endif
           {{ $attributes->class([
               'mt-1 block w-full rounded-lg border px-3 py-2.5 text-base shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500',
               'border-stone-300' => ! $errors->has($name),
               'border-red-500' => $errors->has($name),
           ]) }}>
    @error($name)
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
