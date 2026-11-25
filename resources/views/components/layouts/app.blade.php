@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('head')
        @livewireStyles
    </head>
    <body class="flex min-h-full flex-col">
        @php
            $user = auth()->user();
            $links = [
                ['Home', route('home'), request()->routeIs('home')],
                ['Products', route('catalogue.index'), request()->routeIs('catalogue.*')],
                ['How it works', route('home').'#how-it-works', false],
            ];

            if ($user?->hasRole('admin')) {
                $links[] = ['Admin', route('admin.dashboard'), request()->routeIs('admin.*')];
            }

            if ($user?->hasRole('shop')) {
                $links[] = ['Jobs', route('shop.jobs'), request()->routeIs('shop.*')];
            }
        @endphp

        {{-- Utility strip --}}
        <div class="bg-night text-xs text-[#aeb7c5]">
            <div class="container-page flex h-9 items-center justify-between gap-4">
                <div class="hidden items-center gap-6 sm:flex">
                    <span class="inline-flex items-center gap-2"><x-icon name="shield" class="size-4 text-accent" /> Printed to order</span>
                    <span class="inline-flex items-center gap-2"><x-icon name="truck" class="size-4 text-accent" /> Delivered to you</span>
                </div>

                <div class="ml-auto flex items-center gap-5">
                    @auth
                        <span class="hidden items-center gap-2 sm:inline-flex"><x-icon name="user" class="size-4" /> {{ $user->name }}</span>
                        <a href="{{ route('account.orders') }}" class="hover:text-white">My orders</a>
                        <a href="{{ route('account.designs') }}" class="hover:text-white">My designs</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="hover:text-white">Log out</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="hover:text-white">Log in</a>
                        <a href="{{ route('register') }}" class="hover:text-white">Create account</a>
                    @endauth
                </div>
            </div>
        </div>

        {{-- Main header --}}
        <header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-line bg-white">
            <div class="container-page flex h-[4.5rem] items-center justify-between gap-6">
                <a href="{{ route('home') }}" class="flex items-center gap-3" aria-label="{{ config('app.name') }} home">
                    <span class="inline-flex size-10 items-center justify-center rounded-chip bg-brand text-white">
                        <svg class="size-6" viewBox="-28 -28 56 56" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" aria-hidden="true">
                            <circle r="8" fill="currentColor" stroke="none" />
                            <path d="M0-16v-7M0 16v7M-16 0h-7M16 0h7M-11-11l-4-4M11 11l4 4M-11 11l-4 4M11-11l4-4" />
                        </svg>
                    </span>
                    <span class="text-lg font-extrabold tracking-tight text-ink">{{ config('app.name') }}</span>
                </a>

                <nav class="hidden items-center gap-1 lg:flex" aria-label="Main">
                    @foreach ($links as [$label, $url, $active])
                        <a href="{{ $url }}" @class(['rounded-control px-4 py-2 text-sm font-semibold', 'text-brand' => $active, 'text-ink hover:text-brand' => ! $active])>{{ $label }}</a>
                    @endforeach
                </nav>

                <div class="flex items-center gap-3">
                    @php($cartCount = app(\App\Services\CartService::class)->count(request()))
                    <a href="{{ route('cart.index') }}" class="relative inline-flex size-11 items-center justify-center rounded-chip text-ink hover:bg-mist" aria-label="Cart, {{ $cartCount }} {{ \Illuminate\Support\Str::plural('item', $cartCount) }}">
                        <x-icon name="bag" class="size-6" />
                        @if ($cartCount)
                            <span class="absolute -right-0.5 -top-0.5 flex min-w-5 items-center justify-center rounded-full bg-brand px-1 text-[0.7rem] font-bold leading-5 text-white">{{ $cartCount }}</span>
                        @endif
                    </a>

                    <a href="{{ route('catalogue.index') }}" class="btn btn-brand hidden sm:inline-flex">
                        <x-icon name="pencil" class="size-4" /> Design your own
                    </a>

                    <button type="button" class="inline-flex size-11 items-center justify-center rounded-chip bg-brand-tint text-brand lg:hidden"
                            x-on:click="open = ! open" x-bind:aria-expanded="open" aria-controls="mobile-nav" aria-label="Toggle menu">
                        <x-icon name="menu" class="size-6" x-show="! open" />
                        <x-icon name="close" class="size-6" x-show="open" x-cloak />
                    </button>
                </div>
            </div>

            <nav id="mobile-nav" x-show="open" x-cloak x-transition.opacity class="border-t border-line bg-white shadow-float lg:hidden" aria-label="Mobile">
                <ul class="container-page py-2">
                    @foreach ($links as [$label, $url, $active])
                        <li class="border-b border-line last:border-0">
                            <a href="{{ $url }}" x-on:click="open = false" @class(['block py-3.5 text-base font-bold', 'text-brand' => $active, 'text-ink' => ! $active])>{{ $label }}</a>
                        </li>
                    @endforeach
                    <li class="py-3">
                        <a href="{{ route('catalogue.index') }}" x-on:click="open = false" class="btn btn-brand btn-block"><x-icon name="pencil" class="size-4" /> Design your own</a>
                    </li>
                </ul>
            </nav>
        </header>

        @if ($user && ! $user->hasVerifiedEmail())
            <div class="bg-accent-tint py-3 text-sm text-ink">
                <div class="container-page">
                    Please verify your email address.
                    <a href="{{ route('verification.notice') }}" class="font-semibold underline">Resend the link</a>
                </div>
            </div>
        @endif

        <main class="flex-1">
            {{ $slot }}
        </main>

        {{-- Footer --}}
        <footer class="border-t border-white/10 bg-night text-sm text-[#aeb7c5]">
            <div class="container-page grid gap-10 py-14 md:grid-cols-[1.5fr_1fr_1fr]">
                <div>
                    <p class="text-lg font-extrabold tracking-tight text-white">{{ config('app.name') }}</p>
                    <p class="mt-3 max-w-xs leading-relaxed">Custom printed products, made to order and delivered to you.</p>
                </div>

                <div>
                    <p class="font-bold text-white">Shop</p>
                    <ul class="mt-4 space-y-3">
                        <li><a href="{{ route('catalogue.index') }}" class="hover:text-white">Products</a></li>
                        <li><a href="{{ route('home') }}#how-it-works" class="hover:text-white">How it works</a></li>
                    </ul>
                </div>

                <div>
                    <p class="font-bold text-white">Account</p>
                    <ul class="mt-4 space-y-3">
                        @auth
                            <li>{{ $user->name }}</li>
                        @else
                            <li><a href="{{ route('login') }}" class="hover:text-white">Log in</a></li>
                            <li><a href="{{ route('register') }}" class="hover:text-white">Create account</a></li>
                        @endauth
                    </ul>
                </div>
            </div>

            <div class="border-t border-white/10 py-5">
                <p class="container-page">&copy; {{ now()->year }} {{ config('app.name') }}</p>
            </div>
        </footer>

        @livewireScripts
    </body>
</html>
