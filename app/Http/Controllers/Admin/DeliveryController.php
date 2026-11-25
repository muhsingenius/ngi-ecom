<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use App\Models\PickupPoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DeliveryController extends Controller
{
    public function index(): View
    {
        return view('admin.delivery.index', [
            'zones' => DeliveryZone::orderBy('sort')->orderBy('id')->get(),
            'pickups' => PickupPoint::orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function storeZone(Request $request): RedirectResponse
    {
        DeliveryZone::create($this->zoneData($request) + ['sort' => DeliveryZone::count()]);

        return back()->with('status', 'Delivery zone added.');
    }

    public function updateZone(Request $request, DeliveryZone $zone): RedirectResponse
    {
        $zone->update($this->zoneData($request));

        return back()->with('status', "{$zone->name} saved.");
    }

    public function destroyZone(DeliveryZone $zone): RedirectResponse
    {
        $zone->delete();

        return back()->with('status', 'Delivery zone removed.');
    }

    public function storePickup(Request $request): RedirectResponse
    {
        PickupPoint::create($this->pickupData($request) + ['sort' => PickupPoint::count()]);

        return back()->with('status', 'Pickup point added.');
    }

    public function updatePickup(Request $request, PickupPoint $pickup): RedirectResponse
    {
        $pickup->update($this->pickupData($request));

        return back()->with('status', "{$pickup->name} saved.");
    }

    public function destroyPickup(PickupPoint $pickup): RedirectResponse
    {
        $pickup->delete();

        return back()->with('status', 'Pickup point removed.');
    }

    /** @return array<string, mixed> */
    private function zoneData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'fee_type' => ['required', Rule::in(['flat', 'banded'])],
            'flat_fee' => ['nullable', 'numeric', 'min:0'],
            'bands' => ['nullable', 'array', 'max:20'],
            'bands.*.min_qty' => ['required_with:bands', 'integer', 'min:1'],
            'bands.*.max_qty' => ['nullable', 'integer', 'min:1'],
            'bands.*.fee' => ['required_with:bands', 'numeric', 'min:0'],
            'eta' => ['nullable', 'string', 'max:120'],
            'courier_name' => ['nullable', 'string', 'max:120'],
            'free_threshold' => ['nullable', 'numeric', 'min:0'],
            'active' => ['boolean'],
        ]);

        if ($data['fee_type'] === 'banded' && empty($data['bands'])) {
            abort(back()->withInput()->withErrors(['bands' => 'Add at least one quantity band, or switch to a flat fee.']));
        }

        $data['flat_fee'] = $data['flat_fee'] ?? 0;
        $data['bands'] = $data['fee_type'] === 'banded'
            ? collect($data['bands'])->map(fn ($b) => ['min_qty' => (int) $b['min_qty'], 'max_qty' => ($b['max_qty'] ?? null) !== null ? (int) $b['max_qty'] : null, 'fee' => (float) $b['fee']])->sortBy('min_qty')->values()->all()
            : null;
        $data['active'] = (bool) ($data['active'] ?? false);

        return $data;
    }

    /** @return array<string, mixed> */
    private function pickupData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'max:500'],
            'hours' => ['nullable', 'string', 'max:200'],
            'active' => ['boolean'],
        ]);

        $data['active'] = (bool) ($data['active'] ?? false);

        return $data;
    }
}
