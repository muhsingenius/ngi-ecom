@php use App\Support\Money; @endphp

<x-layouts.app title="My orders">
    <section class="section">
        <div class="container-page">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="type-title">My orders</h1>
                <a href="{{ route('account.designs') }}" class="link-arrow">My saved designs</a>
            </div>

            @if ($orders->isEmpty())
                <div class="mt-8 rounded-card bg-mist p-8 text-center"><p class="text-copy">No orders yet.</p><a href="{{ route('catalogue.index') }}" class="btn btn-brand btn-lg mt-4">Start designing</a></div>
            @else
                <ul class="mt-8 space-y-3">
                    @foreach ($orders as $order)
                        <li>
                            <a href="{{ $order->url() }}" class="panel flex flex-wrap items-center justify-between gap-3 p-5 transition hover:shadow-float">
                                <div><p class="font-semibold text-ink">{{ $order->number }}</p><p class="text-sm text-muted">{{ $order->created_at->format('j M Y') }} · {{ $order->quantity }} {{ \Illuminate\Support\Str::plural('item', $order->quantity) }}</p></div>
                                <p class="text-sm font-semibold text-brand-strong">{{ $order->status->label() }}</p>
                                <p class="font-extrabold text-ink">{{ Money::format(Money::toMinor($order->total), $order->currency) }}</p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</x-layouts.app>
