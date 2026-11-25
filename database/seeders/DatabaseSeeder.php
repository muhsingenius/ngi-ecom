<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            SettingsSeeder::class,
            StaffUserSeeder::class,
        ]);

        if (config('shop.seed.demo_catalogue')) {
            $this->call(CatalogueSeeder::class);
        }
    }
}
