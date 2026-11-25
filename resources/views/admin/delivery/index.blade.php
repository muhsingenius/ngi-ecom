@php use App\Support\Money; @endphp

<x-layouts.admin heading="Delivery and pickup">
    <p class="type-lead max-w-2xl">Customers choose a delivery zone at checkout. If their area is not listed they can only pick up, so keep the zones that cover your customers switched on.</p>

    {{-- Zones --}}
    <h2 class="type-heading mt-10">Delivery zones</h2>
    <div class="mt-4 space-y-4">
        @foreach ($zones as $zone)
            <details class="panel p-5">
                <summary class="cursor-pointer font-semibold text-ink">{{ $zone->name }}
                    <span class="font-normal text-muted">· {{ $zone->fee_type === 'flat' ? Money::format(Money::toMinor($zone->flat_fee)) : 'by quantity' }}@if (! $zone->active) · off @endif</span></summary>
                <form method="POST" action="{{ route('admin.delivery.zones.update', $zone) }}" class="mt-5">
                    @csrf @method('PUT')
                    @include('admin.delivery._zone-fields', ['zone' => $zone])
                    <button class="btn btn-brand mt-5">Save zone</button>
                </form>
                <form method="POST" action="{{ route('admin.delivery.zones.destroy', $zone) }}" class="mt-3" onsubmit="return confirm('Remove this zone? Past orders keep their details.')">@csrf @method('DELETE')<button class="text-sm text-red-600">Remove zone</button></form>
            </details>
        @endforeach

        <details class="panel border-dashed p-5" @if ($zones->isEmpty()) open @endif>
            <summary class="cursor-pointer font-semibold text-brand-strong">+ Add delivery zone</summary>
            <form method="POST" action="{{ route('admin.delivery.zones.store') }}" class="mt-5">
                @csrf
                @include('admin.delivery._zone-fields', ['zone' => null])
                <button class="btn btn-brand mt-5">Add zone</button>
            </form>
        </details>
    </div>

    {{-- Pickup points --}}
    <h2 class="type-heading mt-12">Pickup points</h2>
    <div class="mt-4 space-y-4">
        @foreach ($pickups as $pickup)
            <details class="panel p-5">
                <summary class="cursor-pointer font-semibold text-ink">{{ $pickup->name }} @if (! $pickup->active)<span class="font-normal text-muted">· off</span>@endif</summary>
                <form method="POST" action="{{ route('admin.delivery.pickups.update', $pickup) }}" class="mt-5">
                    @csrf @method('PUT')
                    @include('admin.delivery._pickup-fields', ['pickup' => $pickup])
                    <button class="btn btn-brand mt-5">Save pickup point</button>
                </form>
                <form method="POST" action="{{ route('admin.delivery.pickups.destroy', $pickup) }}" class="mt-3" onsubmit="return confirm('Remove this pickup point?')">@csrf @method('DELETE')<button class="text-sm text-red-600">Remove</button></form>
            </details>
        @endforeach

        <details class="panel border-dashed p-5" @if ($pickups->isEmpty()) open @endif>
            <summary class="cursor-pointer font-semibold text-brand-strong">+ Add pickup point</summary>
            <form method="POST" action="{{ route('admin.delivery.pickups.store') }}" class="mt-5">
                @csrf
                @include('admin.delivery._pickup-fields', ['pickup' => null])
                <button class="btn btn-brand mt-5">Add pickup point</button>
            </form>
        </details>
    </div>
</x-layouts.admin>
