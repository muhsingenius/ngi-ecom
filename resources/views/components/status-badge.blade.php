@props(['status'])

@php
    $tones = ['amber' => 'bg-accent-tint text-ink', 'red' => 'bg-red-50 text-red-700', 'green' => 'bg-green-50 text-green-700', 'blue' => 'bg-brand-tint text-brand-strong'];
@endphp

<span {{ $attributes->class(['inline-block whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold', $tones[$status->tone()]]) }}>{{ $status->label() }}</span>
