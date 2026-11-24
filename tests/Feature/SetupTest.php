<?php

use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

it('serves the home page', function () {
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
    $driver = config('intervention-image.driver');

    expect($driver)->toBeIn([
        \Intervention\Image\Drivers\Imagick\Driver::class,
        \Intervention\Image\Drivers\Gd\Driver::class,
    ]);
});