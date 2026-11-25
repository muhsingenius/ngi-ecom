<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title') · {{ config('app.name') }}</title>
        {{-- Error pages must render even when the database or the asset build is unavailable. --}}
        @if (file_exists(public_path('build/manifest.json')))
            @vite('resources/css/app.css')
        @endif
    </head>
    <body class="flex min-h-full flex-col items-center justify-center bg-mist px-6 py-12 text-center font-sans">
        <p class="text-lg font-extrabold tracking-tight text-ink">{{ config('app.name') }}</p>
        <p class="mt-10 text-6xl font-extrabold tracking-tight text-brand">@yield('code')</p>
        <h1 class="mt-4 text-2xl font-extrabold tracking-tight text-ink">@yield('title')</h1>
        <p class="mt-3 max-w-md text-copy">@yield('message')</p>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ url('/') }}" class="btn btn-brand">Go to the home page</a>
            @hasSection('extra')@yield('extra')@endif
        </div>
    </body>
</html>
