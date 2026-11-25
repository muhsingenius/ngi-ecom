<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use App\Models\PickupPoint;
use Illuminate\Database\Seeder;

/**
 * Demo delivery zones and a pickup point, all editable in the admin. Skipped when any exist.
 */
class DeliverySeeder extends Seeder
{
    public function run(): void
    {
        if (DeliveryZone::query()->exists() || PickupPoint::query()->exists()) {
            return;
        }

        DeliveryZone::create(['name' => 'Accra Central', 'fee_type' => 'flat', 'flat_fee' => 20, 'eta' => 'Same or next day', 'sort' => 0]);
        DeliveryZone::create([
            'name' => 'Greater Accra', 'fee_type' => 'banded', 'eta' => '1-2 working days', 'sort' => 1,
            'bands' => [['min_qty' => 1, 'max_qty' => 20, 'fee' => 30], ['min_qty' => 21, 'max_qty' => null, 'fee' => 60]],
        ]);
        DeliveryZone::create(['name' => 'Kumasi', 'fee_type' => 'flat', 'flat_fee' => 50, 'eta' => '2-3 working days', 'sort' => 2]);
        DeliveryZone::create(['name' => 'Rest of Ghana', 'fee_type' => 'flat', 'flat_fee' => 80, 'eta' => '3-5 working days', 'sort' => 3]);

        PickupPoint::create(['name' => 'Print shop', 'address' => 'Change this address in Admin > Delivery', 'hours' => 'Mon-Sat, 9am-6pm']);
    }
}
