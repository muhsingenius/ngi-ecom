@props(['title' => null, 'heading' => null, 'subheading' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-stone-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-full flex-col items-center justify-center px-4 py-10 font-sans text-stone-900 antialiased">
        <a href="{{ route('home') }}" class="mb-6 text-xl font-semibold tracking-tight">{{ config('app.name') }}</a>

        <div class="w-full max-w-sm rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-8">
            @if ($heading)
                <h1 class="text-xl font-semibold">{{ $heading }}</h1>
            @endif
            @if ($subheading)
                <p class="mt-1 text-sm text-stone-600">{{ $subheading }}</p>
            @endif

            <div class="{{ $heading ? 'mt-6' : '' }}">
                {{ $slot }}
            </div>
        </div>

        @isset($footer)
            <p class="mt-6 text-sm text-stone-600">{{ $footer }}</p>
        @endisset
    </body>
</html>
