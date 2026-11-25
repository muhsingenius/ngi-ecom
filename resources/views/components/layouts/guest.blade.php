@props(['title' => null, 'heading' => null, 'subheading' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-full flex-col items-center justify-center bg-mist px-4 py-10">
        <a href="{{ route('home') }}" class="mb-6 flex items-center gap-3" aria-label="{{ config('app.name') }} home">
            <span class="inline-flex size-10 items-center justify-center rounded-chip bg-brand text-white">
                <svg class="size-6" viewBox="-28 -28 56 56" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" aria-hidden="true">
                    <circle r="8" fill="currentColor" stroke="none" />
                    <path d="M0-16v-7M0 16v7M-16 0h-7M16 0h7M-11-11l-4-4M11 11l4 4M-11 11l-4 4M11-11l4-4" />
                </svg>
            </span>
            <span class="text-lg font-extrabold tracking-tight text-ink">{{ config('app.name') }}</span>
        </a>

        <div class="w-full max-w-md rounded-card border border-line bg-white p-6 shadow-float sm:p-8">
            @if ($heading)
                <h1 class="type-heading text-2xl">{{ $heading }}</h1>
            @endif
            @if ($subheading)
                <p class="mt-2 text-sm text-copy">{{ $subheading }}</p>
            @endif

            <div class="{{ $heading ? 'mt-6' : '' }}">
                {{ $slot }}
            </div>
        </div>

        @isset($footer)
            <p class="mt-6 text-sm text-copy">{{ $footer }}</p>
        @endisset
    </body>
</html>
