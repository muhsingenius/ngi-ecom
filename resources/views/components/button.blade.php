<button {{ $attributes->merge(['type' => 'submit'])->class('btn btn-brand btn-block') }}>
    {{ $slot }}
</button>
