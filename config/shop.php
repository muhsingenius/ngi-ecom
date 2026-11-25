<?php

return [

    /*
    | Store currency (ISO 4217 code) and how long signed private-file links live.
    */

    'currency' => env('APP_CURRENCY', 'GHS'),

    'private_url_ttl_minutes' => 15,

    // Order numbers look like PRT-00042.
    'order_prefix' => env('ORDER_PREFIX', 'PRT'),

    /*
    | The organisation behind the store (shown on the About page and in the footer).
    */

    'organisation' => [
        'name' => 'Northern Girl Initiative',
        'short' => 'NGI',
        'url' => 'https://northerngirlinitiative.org',
        'donate_url' => 'https://northerngirlinitiative.org/donate/',
        'address' => 'Sabonjida, Pagsara Road, Tamale, Ghana',
    ],

    'developer' => [
        'name' => 'Gadabsi Infotech',
        'phone' => '0248724619',
    ],

    /*
    | Paystack keys. Payments use Laravel's HTTP client (no SDK).
    */

    'paystack' => [
        'public_key' => env('PAYSTACK_PUBLIC_KEY'),
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
    ],

    /*
    | Optional first admin and shop users created by `db:seed`.
    | Read here (not via env() in the seeder) so they work with a cached config.
    */

    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL'),
        'admin_password' => env('SEED_ADMIN_PASSWORD'),
        'shop_email' => env('SEED_SHOP_EMAIL'),
        'shop_password' => env('SEED_SHOP_PASSWORD'),
        // A small demo catalogue; on by default everywhere except production.
        'demo_catalogue' => (bool) env('SEED_DEMO_CATALOGUE', env('APP_ENV') !== 'production'),
    ],

];
