# PRINTABLE

A custom print store for a cPanel subdomain. Customers design t-shirts, polos, hoodies, caps and bracelets in the browser and order one or hundreds. Bulk prices are public and drop with quantity, groups can add a name and number per item, large orders get a proof to approve, and a local print shop receives a complete job sheet with print-ready files.

Docs: [DEPLOY.md](DEPLOY.md) (cPanel deployment, Paystack, cron, backups) · [docs/IMPLEMENTATION.md](docs/IMPLEMENTATION.md) (how the design brief maps to the code, and where the build differs). The original design brief is the owner's document and is not stored in this repository.

## What it does

| Who | What |
|---|---|
| Customer (guest or account) | Browse products with public tier prices, design in the browser, choose sizes/colours or upload a roster of names and numbers, see the price live, check out by delivery zone or pickup, pay with card or Mobile Money (Paystack), approve a proof, follow the order, reorder, keep saved designs |
| Admin | Manage products, colours, sizes, print areas, mockups, tier prices, delivery zones, pickup points, settings; handle orders (proofs, dispatch, cancel, refunds); see reports |
| Print shop | Separate login; sees released jobs only, downloads the PDF job sheet and a ZIP of print files, sets Queued → Printing → Ready |

## Stack

- PHP 8.3+ (Laravel 13 requirement; developed on 8.5), Laravel 13, MySQL/MariaDB (utf8mb4 everywhere)
- Blade + Livewire 4 (ships Alpine.js) + Tailwind CSS 4 via Vite; Fabric.js 7 for the designer
- `laravel/fortify` (login, register, password reset, email verification), `spatie/laravel-permission` (roles `admin`, `shop`, `customer`)
- `intervention/image` (Imagick preferred, GD fallback), `barryvdh/laravel-dompdf` (job sheets)
- Payments through Laravel's HTTP client; **no Paystack SDK**
- Pest for tests, Laravel Pint for style, Laravel Boost for agent guidelines

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
   php artisan migrate --seed  # tables, roles, settings, staff, and (locally) a demo catalogue and delivery zones
   php artisan storage:link
   ```
4. Run: `composer run dev` (server + queue + vite), or `php artisan serve` with `npm run dev`.
5. Tests: `php artisan test` (or `vendor/bin/pest`; one file: `vendor/bin/pest tests/Feature/PricingTest.php`). They use the `ecom_test` MySQL database and fake all outside services (Paystack, mail, storage).
6. Code style: `vendor/bin/pint` (fix) or `vendor/bin/pint --test` (check).

Local mail goes to `storage/logs/laravel.log` while `MAIL_MAILER=log`. Without Paystack keys, checkout still saves the order and tells the customer payment is unavailable; an admin can record a manual payment on the order page to move it on.

### PHP extensions

Enable these in `php.ini` (remove the leading `;`): `gd`, `intl`, `zip`, `openssl`, `sodium`, `curl`, `fileinfo`, `mbstring`, `pdo_mysql`, `bcmath`. Optionally install `imagick`; when loaded it is used automatically, otherwise GD. Force a driver with `IMAGE_DRIVER` in `.env`. For the designer, raise `upload_max_filesize` and `post_max_size` to 64M and `memory_limit` to 512M locally too.

## Where things live

| Area | Look at |
|---|---|
| Pricing | `app/Services/PriceCalculator.php`, `app/Enums/TierScope.php` |
| Settings (single list of keys, defaults, rules) | `app/Services/Settings.php` |
| Designer | `resources/js/designer.js`, `resources/views/designs/`, `app/Http/Controllers/DesignController.php`, `config/designer.php` |
| Cart and checkout | `app/Services/CartService.php`, `OrderService.php`, `DeliveryCalculator.php`, `Paystack.php` |
| Proofs | `app/Services/ProofService.php`, `ProofImage.php`, `app/Http/Controllers/ProofController.php` |
| Print shop | `app/Http/Controllers/Shop/JobController.php`, `app/Services/JobSheet.php` |
| Design system | `resources/css/app.css` (tokens and component classes) |

## Staff logins

First admin and print shop users come from the `SEED_*` values at `db:seed`. Add or reset more any time:

```bash
php artisan staff:create shop@example.com shop --name="Print shop"
php artisan staff:create boss@example.com admin --reset
```

## Production

See [DEPLOY.md](DEPLOY.md).
