@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-stone-50">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="flex min-h-full flex-col font-sans text-stone-900 antialiased">
        @php
            $user = auth()->user();
            $links = [['Home', route('home'), request()->routeIs('home')]];

            if ($user?->hasRole('admin')) {
                $links[] = ['Admin', route('admin.dashboard'), request()->routeIs('admin.*')];
            }

            if ($user?->hasRole('shop')) {
                $links[] = ['Jobs', route('shop.jobs'), request()->routeIs('shop.*')];
            }
        @endphp

        <header x-data="{ open: false }" class="border-b border-stone-200 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
                <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</a>

                <button type="button" class="-mr-2 rounded-md p-2 text-stone-600 hover:bg-stone-100 sm:hidden"
                        x-on:click="open = ! open" x-bind:aria-expanded="open" aria-label="Toggle navigation">
                    <svg class="size-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </button>

                <nav class="hidden items-center gap-1 sm:flex">
                    @foreach ($links as [$label, $url, $active])
                        <a href="{{ $url }}" @class(['rounded-md px-3 py-2 text-sm font-medium', 'bg-stone-900 text-white' => $active, 'text-stone-600 hover:bg-stone-100' => ! $active])>{{ $label }}</a>
                    @endforeach

                    @auth
                        <span class="px-3 text-sm text-stone-500">{{ $user->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="rounded-md px-3 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100">Log out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100">Log in</a>
                        <a href="{{ route('register') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">Sign up</a>
                    @endauth
                </nav>
            </div>

            <nav x-show="open" x-cloak x-transition class="border-t border-stone-200 px-4 py-2 sm:hidden">
                @foreach ($links as [$label, $url, $active])
                    <a href="{{ $url }}" @class(['block rounded-md px-3 py-3 text-base font-medium', 'bg-stone-900 text-white' => $active, 'text-stone-700' => ! $active])>{{ $label }}</a>
                @endforeach

                @auth
                    <p class="px-3 pt-3 text-sm text-stone-500">{{ $user->name }}</p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="block w-full rounded-md px-3 py-3 text-left text-base font-medium text-stone-700">Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="block rounded-md px-3 py-3 text-base font-medium text-stone-700">Log in</a>
                    <a href="{{ route('register') }}" class="block rounded-md px-3 py-3 text-base font-medium text-indigo-600">Sign up</a>
                @endauth
            </nav>
        </header>

        <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-8 sm:py-12">
            @if ($user && ! $user->hasVerifiedEmail())
                <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Please verify your email address.
                    <a href="{{ route('verification.notice') }}" class="font-medium underline">Resend the link</a>
                </div>
            @endif

            {{ $slot }}
        </main>

        <footer class="border-t border-stone-200 py-6 text-center text-sm text-stone-500">
            &copy; {{ now()->year }} {{ config('app.name') }}
        </footer>

        @livewireScripts
    </body>
</html>
