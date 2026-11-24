<?php

use Database\Seeders\RoleSeeder;
use Intervention\Image\Drivers\Imagick\Driver;
use Spatie\Permission\Models\Role;

it('reports healthy on /up', function () {
    $this->get('/up')->assertOk();
});

it('serves the storefront home page', function () {
    $this->get('/')->assertOk();
});

it('seeds the admin, shop and customer roles', function () {
    $this->seed(RoleSeeder::class);

    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'customer', 'shop']);
});

it('uses utf8mb4 on the mysql connection', function () {
    expect(config('database.default'))->toBe('mysql')
        ->and(config('database.connections.mysql.charset'))->toBe('utf8mb4');
});

it('selects an image driver that is available', function () {
    expect(config('intervention-image.driver'))->toBeIn([
        Driver::class,
        Intervention\Image\Drivers\Gd\Driver::class,
    ]);
});

it('reads timezone and currency from config', function () {
    expect(config('app.timezone'))->toBe('Africa/Accra')
        ->and(config('shop.currency'))->toBe('GHS');
});
