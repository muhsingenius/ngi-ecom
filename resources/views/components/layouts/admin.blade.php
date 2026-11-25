@props(['title' => null, 'heading' => null])

<x-layouts.app :title="($title ?? $heading).' · Admin'">
    @php
        $tabs = [
            ['Dashboard', 'admin.dashboard', 'admin.dashboard'],
            ['Orders', 'admin.orders.index', 'admin.orders.*'],
            ['Products', 'admin.products.index', 'admin.products.*'],
            ['Delivery', 'admin.delivery.index', 'admin.delivery.*'],
            ['Reports', 'admin.reports', 'admin.reports'],
            ['Settings', 'admin.settings.edit', 'admin.settings.*'],
        ];
    @endphp

    <div class="border-b border-line bg-white">
        <nav class="container-page flex gap-1 overflow-x-auto py-2" aria-label="Admin">
            @foreach ($tabs as [$label, $route, $pattern])
                @if (Route::has($route))
                    <a href="{{ route($route) }}" @class(['whitespace-nowrap rounded-control px-4 py-2 text-sm font-semibold', 'bg-brand-tint text-brand-strong' => request()->routeIs($pattern), 'text-copy hover:text-ink' => ! request()->routeIs($pattern)])>{{ $label }}</a>
                @endif
            @endforeach
        </nav>
    </div>

    <section class="section">
        <div class="container-page">
            @if ($heading)
                <h1 class="type-title">{{ $heading }}</h1>
            @endif

            @if (session('status'))
                <p class="mt-4 rounded-control bg-brand-tint px-4 py-3 text-sm font-semibold text-brand-strong" role="status">{{ session('status') }}</p>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-control bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    <p class="font-semibold">Please fix the following:</p>
                    <ul class="mt-1 list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="{{ $heading ? 'mt-8' : '' }}">{{ $slot }}</div>
        </div>
    </section>
</x-layouts.app>
