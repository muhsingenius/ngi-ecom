# POD Store

Foundation for a custom print-on-demand store. This repo currently contains **setup only**: no catalogue, designer, cart, payments or authentication yet.

## Stack

- PHP 8.2+ (developed on 8.5), Laravel 13, MySQL/MariaDB (utf8mb4 everywhere)
- Blade + Livewire 4 (ships Alpine.js) + Tailwind CSS 4 via Vite
- Pest for tests
- `spatie/laravel-permission` (roles seeded: `admin`, `shop`, `customer`)
- `laravel/breeze` installed as a dev dependency only. The scaffolding has **not** been run, so there is no auth yet.
- `intervention/image` + `intervention/image-laravel` (Imagick preferred, GD fallback, see `config/intervention-image.php`)
- `barryvdh/laravel-dompdf` and `fabric` (npm): installed only, used later
- No Paystack SDK: payments will use Laravel's HTTP client

## Local setup (XAMPP for MySQL only)

Run the app with `php artisan serve`, **not** XAMPP's Apache. XAMPP provides MySQL (and optionally its PHP).

1. Start **MySQL** in the XAMPP Control Panel.
2. Create the databases (default XAMPP: user `root`, empty password).

   **phpMyAdmin** (http://localhost/phpmyadmin): *New* → name `ecom` → collation `utf8mb4_unicode_ci` → *Create*. Repeat for `ecom_test` (used by the test suite).

   **CLI:**
   ```bash
   C:\xampp\mysql\bin\mysql.exe -uroot -e "CREATE DATABASE ecom CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE ecom_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```
3. Install and configure:
   ```bash
   composer install
   npm install
   cp .env.example .env      # already set to mysql / 127.0.0.1 / root / empty password / ecom
   php artisan key:generate
   php artisan migrate --seed   # creates tables and the admin/shop/customer roles
   ```
4. Run:
   ```bash
   composer run dev          # server + queue + vite
   # or: php artisan serve   and   npm run dev
   ```
5. Tests: `php artisan test` (or `vendor/bin/pest`). Uses the `ecom_test` MySQL database.

### PHP extensions

Enable these in `php.ini` (remove the leading `;`): `gd`, `intl`, `zip`, `openssl`, `sodium`, `curl`, `fileinfo`, `mbstring`, `pdo_mysql`. XAMPP ships them but leaves `gd`, `intl`, `zip` and `sodium` commented out. Optionally install the `imagick` extension; when loaded it is used automatically, otherwise GD is used. Force a driver with `IMAGE_DRIVER` in `.env`.

## Production (cPanel)

Point `.env` at the host's MySQL database/user, set `APP_ENV=production`, `APP_DEBUG=false`, run `php artisan migrate --force --seed`, and build assets with `npm run build` before uploading. Serve from `public/`.