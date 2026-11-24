<?php

namespace Database\Seeders;

use App\Services\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Insert any missing settings with their defaults; existing values are kept.
     */
    public function run(): void
    {
        app(Settings::class)->seedDefaults();
    }
}
