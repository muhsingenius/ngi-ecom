<x-layouts.admin heading="Admin dashboard">
    <p class="type-lead max-w-2xl">Manage what you sell, how it is priced, and how orders move through the shop.</p>

    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['Products', 'admin.products.index', 'Catalogue, colours, sizes, print areas, mockups and bulk prices.'],
            ['Settings', 'admin.settings.edit', 'Tier scope, proof rules, free delivery and turnaround.'],
        ] as [$label, $route, $blurb])
            @if (Route::has($route))
                <a href="{{ route($route) }}" class="panel block p-6 transition hover:-translate-y-0.5 hover:shadow-float">
                    <h2 class="type-heading">{{ $label }}</h2>
                    <p class="mt-2 text-sm text-copy">{{ $blurb }}</p>
                </a>
            @endif
        @endforeach
    </div>
</x-layouts.admin>
