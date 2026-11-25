<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Models\PickupPoint;
use App\Services\Settings;
use Illuminate\View\View;

class PageController extends Controller
{
    /** Shown at the top of the legal pages. Update it whenever their wording changes. */
    public const LEGAL_UPDATED = '25 November 2026';

    public function about(): View
    {
        return view('pages.about');
    }

    public function privacy(Settings $settings): View
    {
        return view('pages.privacy', $this->legalData($settings));
    }

    public function dataProtection(Settings $settings): View
    {
        return view('pages.data-protection', $this->legalData($settings));
    }

    /** @return array<string, string|null> */
    private function legalData(Settings $settings): array
    {
        return [
            'updated' => self::LEGAL_UPDATED,
            'email' => $settings->string('store.email'),
            'phone' => $settings->string('store.phone'),
        ];
    }

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
