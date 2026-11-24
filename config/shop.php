<?php

return [

    /*
    | Store currency (ISO 4217 code) and how long signed private-file links live.
    */

    'currency' => env('APP_CURRENCY', 'GHS'),

    'private_url_ttl_minutes' => 15,

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
    ],

];
