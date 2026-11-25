@php use App\Support\Money; @endphp

<x-layouts.admin heading="Reports">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <x-f.input name="from" label="From" type="date" :value="$from->toDateString()" />
        <x-f.input name="to" label="To" type="date" :value="$to->toDateString()" />
        <div>
            <label for="by" class="block text-sm font-semibold text-ink">Group by</label>
            <select id="by" name="by" class="field mt-1.5 w-32"><option value="day" @selected($by === 'day')>Day</option><option value="week" @selected($by === 'week')>Week</option></select>
        </div>
        <button class="btn btn-brand">Update</button>
    </form>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([['Revenue', Money::format($totals['revenue']), 'Paid orders, less refunds'], ['Orders placed', $totals['orders'], 'Excluding cancelled'], ['Items', $totals['items'], 'In those orders'], ['Orders paid', $totals['paid'], 'In this period']] as [$label, $value, $hint])
            <div class="rounded-card bg-mist p-5"><p class="text-sm text-copy">{{ $label }}</p><p class="mt-1 text-3xl font-extrabold tracking-tight text-ink">{{ $value }}</p><p class="text-xs text-muted">{{ $hint }}</p></div>
        @endforeach
    </div>

    <h2 class="type-heading mt-10">Orders and revenue by {{ $by }}</h2>
    <div class="mt-4 overflow-x-auto rounded-card border border-line">
        <table class="w-full min-w-[34rem] text-left text-sm">
            <thead class="bg-mist text-muted"><tr><th class="px-4 py-3 font-semibold">{{ ucfirst($by) }}</th><th class="px-4 py-3 text-right font-semibold">Orders</th><th class="px-4 py-3 text-right font-semibold">Items</th><th class="px-4 py-3 font-semibold">Revenue</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-t border-line">
                        <td class="px-4 py-2.5 text-ink">{{ $row['label'] }}</td>
                        <td class="px-4 py-2.5 text-right">{{ $row['orders'] }}</td>
                        <td class="px-4 py-2.5 text-right">{{ $row['items'] }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-3">
                                <span class="w-24 font-semibold text-ink">{{ $row['revenue'] ? Money::format($row['revenue']) : '–' }}</span>
                                <span class="h-2 rounded-full bg-brand" style="width: {{ round($row['revenue'] / $maxRevenue * 100) }}%"></span>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="type-heading mt-10">Top products</h2>
    @if ($top->isEmpty())
        <p class="mt-3 text-copy">No paid orders in this period yet.</p>
    @else
        <div class="mt-4 overflow-x-auto rounded-card border border-line">
            <table class="w-full min-w-[30rem] text-left text-sm">
                <thead class="bg-mist text-muted"><tr><th class="px-4 py-3 font-semibold">Product</th><th class="px-4 py-3 text-right font-semibold">Units</th><th class="px-4 py-3 text-right font-semibold">Orders</th><th class="px-4 py-3 text-right font-semibold">Sales</th></tr></thead>
                <tbody>
                    @foreach ($top as $row)
                        <tr class="border-t border-line"><td class="px-4 py-2.5 text-ink">{{ $row->product_name }} <span class="text-muted">· {{ $row->quality }}</span></td><td class="px-4 py-2.5 text-right">{{ $row->units }}</td><td class="px-4 py-2.5 text-right">{{ $row->orders }}</td><td class="px-4 py-2.5 text-right font-semibold text-ink">{{ Money::format(Money::toMinor($row->revenue)) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.admin>
