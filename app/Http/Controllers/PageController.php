<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Models\PickupPoint;
use App\Services\Settings;
use Illuminate\View\View;

class PageController extends Controller
{
    public function contact(Settings $settings): View
    {
        return view('pages.contact', [
            'phone' => $settings->string('store.phone'),
            'whatsapp' => $settings->string('store.whatsapp'),
            'email' => $settings->string('store.email'),
            'pickups' => PickupPoint::where('active', true)->orderBy('sort')->get(),
            'zones' => DeliveryZone::where('active', true)->orderBy('sort')->get(),
            'turnaround' => $settings->int('orders.turnaround_days'),
        ]);
    }
}
