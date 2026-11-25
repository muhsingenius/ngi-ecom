<?php

namespace App\Services;

use App\Models\DeliveryZone;
use App\Support\Money;

/**
 * Delivery fee for a zone: flat, or by order-quantity bands, waived above a
 * free-delivery threshold (the zone's own, else the global setting).
 */
class DeliveryCalculator
{
    public function __construct(private Settings $settings) {}

    /** Fee in minor units for an order of this size and value. */
    public function fee(DeliveryZone $zone, int $quantity, int $subtotalMinor): int
    {
        $threshold = $this->freeThreshold($zone);

        if ($threshold !== null && $subtotalMinor >= $threshold) {
            return 0;
        }

        if ($zone->fee_type !== 'banded') {
            return Money::toMinor($zone->flat_fee);
        }

        $bands = collect($zone->bands ?? [])->sortBy('min_qty')->values();

        $band = $bands->first(fn ($b) => $quantity >= (int) $b['min_qty'] && (($b['max_qty'] ?? null) === null || $b['max_qty'] === '' || $quantity <= (int) $b['max_qty']))
            ?? $bands->last();

        return $band ? Money::toMinor($band['fee']) : 0;
    }

    /** Free-delivery threshold in minor units, or null when there is none. */
    public function freeThreshold(DeliveryZone $zone): ?int
    {
        $value = $zone->free_threshold ?? $this->settings->float('delivery.free_threshold');

        return $value === null ? null : Money::toMinor($value);
    }

    /** What a zone card shows at checkout. */
    public function describe(DeliveryZone $zone, int $quantity, int $subtotalMinor): array
    {
        return [
            'id' => $zone->id,
            'name' => $zone->name,
            'eta' => $zone->eta,
            'courier' => $zone->courier_name,
            'fee' => $this->fee($zone, $quantity, $subtotalMinor),
            'free_threshold' => $this->freeThreshold($zone),
        ];
    }
}
