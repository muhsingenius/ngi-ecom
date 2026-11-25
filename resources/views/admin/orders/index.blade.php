@php use App\Support\Money; @endphp

<x-layouts.admin heading="Orders">
    <form method="GET" class="flex flex-wrap items-end gap-3">
        <div class="min-w-[14rem] flex-1">
            <label for="q" class="block text-sm font-semibold text-ink">Search</label>
            <input id="q" name="q" value="{{ request('q') }}" placeholder="Order number, name, email or phone" class="field mt-1.5">
        </div>
        <div>
            <label for="status" class="block text-sm font-semibold text-ink">Status</label>
            <select id="status" name="status" class="field mt-1.5 w-56">
                <option value="">All statuses</option>
                @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach
            </select>
        </div>
        <label class="flex items-center gap-2 pb-3 text-sm font-medium text-ink"><input type="checkbox" name="flagged" value="1" @checked(request()->boolean('flagged')) class="size-4 accent-brand"> Needs follow-up</label>
        <button class="btn btn-brand">Search</button>
        @if (request()->hasAny(['q', 'status', 'flagged']))<a href="{{ route('admin.orders.index') }}" class="link-arrow pb-3">Clear</a>@endif
    </form>

    <div class="mt-6 overflow-x-auto rounded-card border border-line">
        <table class="w-full min-w-[48rem] text-left text-sm">
            <thead class="bg-mist text-muted"><tr>
                <th class="px-4 py-3 font-semibold">Order</th><th class="px-4 py-3 font-semibold">Customer</th><th class="px-4 py-3 font-semibold">Items</th>
                <th class="px-4 py-3 font-semibold">Total</th><th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 font-semibold">Placed</th>
            </tr></thead>
            <tbody>
                @forelse ($orders as $order)
                    <tr class="border-t border-line hover:bg-mist/60">
                        <td class="px-4 py-3 font-semibold"><a href="{{ route('admin.orders.show', $order) }}" class="text-brand-strong hover:underline">{{ $order->number }}</a>@if ($order->flagged) <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">follow up</span>@endif</td>
                        <td class="px-4 py-3">{{ $order->customer_name }}<span class="block text-xs text-muted">{{ $order->email }}</span></td>
                        <td class="px-4 py-3">{{ $order->quantity }}</td>
                        <td class="px-4 py-3 font-semibold text-ink">{{ Money::format(Money::toMinor($order->total), $order->currency) }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$order->status" /></td>
                        <td class="px-4 py-3 text-muted">{{ $order->created_at->format('j M, g:ia') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-copy">No orders match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $orders->links() }}</div>
</x-layouts.admin>
