<x-layouts.app title="Jobs">
    <section class="section">
        <div class="container-page">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="type-title">Job list</h1>
                    <p class="type-lead mt-2">{{ $counts['active'] }} to do · {{ $counts['ready'] }} ready for the customer</p>
                </div>
            </div>

            @if (session('status'))<p class="mt-4 rounded-control bg-brand-tint px-4 py-3 text-sm font-semibold text-brand-strong" role="status">{{ session('status') }}</p>@endif

            <div class="mt-6 flex flex-wrap gap-2" role="tablist" aria-label="Filter jobs">
                @foreach ($filters as $key => $label)
                    <a href="{{ route('shop.jobs', ['filter' => $key]) }}" @class(['btn', 'btn-brand' => $filter === $key, 'btn-outline' => $filter !== $key])>{{ $label }}</a>
                @endforeach
            </div>

            @if ($orders->isEmpty())
                <div class="mt-8 rounded-card bg-mist p-8 text-center text-copy">No jobs here right now.</div>
            @else
                <ul class="mt-8 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    @foreach ($orders as $order)
                        @php($overdue = $order->due_date && $order->due_date->isPast() && ! $order->due_date->isToday() && $order->status->value === 'in_production')
                        <li>
                            <a href="{{ route('shop.jobs.show', $order) }}" class="panel block p-5 transition hover:-translate-y-0.5 hover:shadow-float">
                                <div class="flex items-start justify-between gap-3">
                                    <p class="text-lg font-extrabold text-ink">{{ $order->number }}</p>
                                    <span @class(['rounded-full px-3 py-1 text-xs font-semibold', 'bg-brand-tint text-brand-strong' => $order->production_stage === 'queued', 'bg-accent-tint text-ink' => $order->production_stage === 'printing', 'bg-green-50 text-green-700' => $order->status->value !== 'in_production'])>
                                        {{ $order->status->value === 'in_production' ? ucfirst((string) $order->production_stage) : $order->status->label() }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-copy">{{ $order->customer_name }} · {{ $order->quantity }} {{ \Illuminate\Support\Str::plural('item', $order->quantity) }}</p>
                                <p class="text-sm text-muted">{{ $order->lines->pluck('product_name')->unique()->implode(', ') }}</p>
                                <p class="mt-3 flex items-center justify-between text-sm">
                                    <span @class(['font-semibold', 'text-red-600' => $overdue, 'text-ink' => ! $overdue])>{{ $order->due_date ? 'Due '.$order->due_date->format('D j M') : 'No due date' }}{{ $overdue ? ' · overdue' : '' }}</span>
                                    <span class="text-muted">{{ $order->isDelivery() ? 'Delivery' : 'Pickup' }}</span>
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>
</x-layouts.app>
