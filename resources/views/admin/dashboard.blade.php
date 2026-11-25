@php use App\Support\Money; @endphp

<x-layouts.admin heading="Admin dashboard">
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ($cards as [$label, $count, $query])
            <a href="{{ route('admin.orders.index', $query) }}" @class(['block rounded-card p-5 transition hover:-translate-y-0.5 hover:shadow-float', 'bg-red-50' => $query === ['flagged' => 1] && $count > 0, 'bg-mist' => ! ($query === ['flagged' => 1] && $count > 0)])>
                <p class="text-3xl font-extrabold tracking-tight text-ink">{{ $count }}</p>
                <p class="mt-1 text-sm text-copy">{{ $label }}</p>
            </a>
        @endforeach
    </div>

    <h2 class="type-heading mt-10">Recent orders</h2>
    @if ($recent->isEmpty())
        <p class="mt-3 text-copy">No orders yet. They will appear here as customers check out.</p>
    @else
        <ul class="mt-4 divide-y divide-line rounded-card border border-line">
            @foreach ($recent as $order)
                <li><a href="{{ route('admin.orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 hover:bg-mist">
                    <span class="font-semibold text-ink">{{ $order->number }} <span class="font-normal text-muted">· {{ $order->customer_name }}</span></span>
                    <x-status-badge :status="$order->status" />
                    <span class="font-semibold text-ink">{{ Money::format(Money::toMinor($order->total), $order->currency) }}</span>
                </a></li>
            @endforeach
        </ul>
    @endif

    <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([['Products', 'admin.products.index', 'Catalogue, prices, colours, areas'], ['Delivery', 'admin.delivery.index', 'Zones, fees and pickup points'], ['Reports', 'admin.reports', 'Orders and revenue'], ['Settings', 'admin.settings.edit', 'Tiers, proofs, delivery rules']] as [$label, $route, $blurb])
            <a href="{{ route($route) }}" class="panel block p-5 transition hover:-translate-y-0.5 hover:shadow-float"><h3 class="font-bold text-ink">{{ $label }}</h3><p class="mt-1 text-sm text-copy">{{ $blurb }}</p></a>
        @endforeach
    </div>
</x-layouts.admin>
