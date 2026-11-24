# POD Store

Custom print-on-demand store. Currently built: foundation, authentication, role-based areas, a settings system, private file handling and background-work scaffolding. **No store features yet** (catalogue, designer, cart, payments).

Docs: [DEPLOY.md](DEPLOY.md) (cPanel deployment). The design document is not in this repository yet; link it here once it exists.

## Stack

- PHP 8.3+ (Laravel 13 requirement; developed on 8.5), Laravel 13, MySQL/MariaDB (utf8mb4 everywhere)
- Blade + Livewire 4 (ships Alpine.js) + Tailwind CSS 4 via Vite
- Pest for tests, Laravel Pint for code style
- `laravel/fortify` for auth (login, register, logout, password reset, email verification) with Blade views
- `spatie/laravel-permission` (roles seeded: `admin`, `shop`, `customer`)
- `laravel/boost` (dev) for agent guidelines
- `intervention/image` + `intervention/image-laravel` (Imagick preferred, GD fallback, see `config/intervention-image.php`)
- `barryvdh/laravel-dompdf` and `fabric` (npm): installed only, used later
- No Paystack SDK: payments will use Laravel's HTTP client

## Areas and roles

| Path | Who | Notes |
|---|---|---|
| `/` | everyone | storefront placeholder |
| `/admin` | `admin` | guests go to login, other roles get 403 |
| `/shop` | `shop` | job list placeholder, same rules |

After login: admins go to `/admin`, shop users to `/shop`, customers to `/`. New registrations get the `customer` role. The first admin and shop users are created by `db:seed` from `SEED_*` values in `.env` (skipped if empty).

## Local setup (XAMPP for MySQL only)

Run the app with `php artisan serve`, **not** XAMPP's Apache. XAMPP provides MySQL.

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
   npm install && npm run build
   cp .env.example .env        # defaults to mysql / 127.0.0.1 / root / empty password / ecom
   # set SEED_ADMIN_EMAIL, SEED_ADMIN_PASSWORD, SEED_SHOP_EMAIL, SEED_SHOP_PASSWORD in .env
   php artisan key:generate
   php artisan migrate --seed  # tables, roles, default settings, admin + shop users
   php artisan storage:link
   ```
4. Run: `composer run dev` (server + queue + vite), or `php artisan serve` with `npm run dev`.
5. Tests: `php artisan test` (or `vendor/bin/pest`; single file: `vendor/bin/pest tests/Feature/SettingsTest.php`). Uses the `ecom_test` MySQL database.
6. Code style: `vendor/bin/pint` (fix) or `vendor/bin/pint --test` (check).

Verification emails are written to `storage/logs/laravel.log` while `MAIL_MAILER=log`.

### PHP extensions

Enable these in `php.ini` (remove the leading `;`): `gd`, `intl`, `zip`, `openssl`, `sodium`, `curl`, `fileinfo`, `mbstring`, `pdo_mysql`, `bcmath`. Optionally install `imagick`; when loaded it is used automatically, otherwise GD. Force a driver with `IMAGE_DRIVER` in `.env`.

## Settings

Store settings live in the `settings` table, read through the cached `App\Services\Settings` service. Every setting is declared once in `Settings::definitions()` (type, default, validation rules); `set()` validates before saving and `db:seed` inserts missing defaults without overwriting existing values.

## Files

- `designs` disk (`storage/app/designs`): private customer artwork, never linked or served directly. Use `App\Support\PrivateFile::url($path)` for a signed, expiring link served by `files.download`.
- `public` disk: mockups; run `php artisan storage:link`.

## Background work

cPanel only offers cron, so one cron line runs everything (see [DEPLOY.md](DEPLOY.md)): `schedule:run` every minute, which drains the database queue (`queue:work --stop-when-empty`, no overlap) and runs `uploads:cleanup` daily. Locally, `composer run dev` runs a queue worker for you.

## Production

See [DEPLOY.md](DEPLOY.md).
