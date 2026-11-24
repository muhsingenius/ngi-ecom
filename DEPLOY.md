# Deploying to cPanel (subdomain)

Target: a subdomain such as `shop.example.com` on a cPanel host. Replace `USER` with your cPanel username and `APP_DIR` with the folder the app lives in (e.g. `pod-store`).

## 1. Host checklist

Check these in cPanel (**MultiPHP Manager**, **MultiPHP INI Editor**, **Select PHP Version → Extensions**) before deploying.

| Item | Requirement |
|---|---|
| PHP version | **8.3 or newer** (Laravel 13 requires `^8.3`). Use the same major/minor as local if possible (local: 8.5) |
| Database | MySQL or MariaDB. Local dev uses **MariaDB 10.11 (XAMPP)**; note the host's engine and version and test against it |
| Extensions | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` (or `imagick`), `zip`, `bcmath`, `intl`, `sodium`, plus `ctype`, `curl`, `dom`, `json`, `tokenizer`, `xml` |
| `upload_max_filesize` | 25M or more |
| `post_max_size` | 25M or more |
| `memory_limit` | 256M or more |
| Disk | Ability to run cron jobs; SSH or **Git Version Control** access (preferred) |

The CLI `php` in cron/SSH can differ from the web PHP version. If `php -v` over SSH is older than 8.3, use the versioned binary your host provides (for example `/opt/cpanel/ea-php83/root/usr/bin/php`) in every command below, including the cron line.

## 2. Build assets locally

The server does not need Node. Build on your machine and ship `public/build`:

```bash
npm ci
npm run build
```

`public/build` is **committed to git** in this repo so Git Version Control deployments include it. Rebuild and commit it whenever CSS/JS changes. (If you deploy from an archive instead, include `public/build` in it.)

## 3. Get the code onto the server

**Option A: Git Version Control (cPanel).** *Git™ Version Control → Create*: clone this repository into `/home/USER/APP_DIR` (not inside `public_html`). Use *Pull or Deploy* for updates.

**Option B: SSH.**

```bash
cd ~ && git clone <repo-url> APP_DIR
```

**Option C: archive.** Zip the project (without `.git`, `node_modules`, `.env`; with `public/build`), upload with File Manager, and extract to `/home/USER/APP_DIR`.

## 4. Point the subdomain at the app

### Option 1 (preferred): document root = `APP_DIR/public`

1. *Domains* → create/edit the subdomain and set its **Document Root** to `APP_DIR/public` (i.e. `/home/USER/APP_DIR/public`).
2. Done. `vendor/`, `.env` and everything else stay outside the web root.

### Option 2: document root cannot be changed

Some hosts force the subdomain's root to a folder such as `/home/USER/shop.example.com`. Keep the app outside it and copy only the contents of `public/` in:

1. Keep the app at `/home/USER/APP_DIR` (outside the web root).
2. Copy the **contents** of `APP_DIR/public/` (including `build/`, `.htaccess`, `index.php`, `favicon.ico`, `robots.txt`) into the subdomain folder `/home/USER/shop.example.com/`.
3. Edit `/home/USER/shop.example.com/index.php` so the three paths point at the app (the app is one level up from the home folder, so `../APP_DIR`):

   ```php
   if (file_exists($maintenance = __DIR__.'/../APP_DIR/storage/framework/maintenance.php')) {
       require $maintenance;
   }

   require __DIR__.'/../APP_DIR/vendor/autoload.php';

   $app = require_once __DIR__.'/../APP_DIR/bootstrap/app.php';
   ```

4. Every time `public/build` changes, copy it into the subdomain folder again.
5. In `.env` set `APP_URL` to the subdomain URL.

## 5. Install and configure (SSH or cPanel Terminal)

```bash
cd /home/USER/APP_DIR
composer install --no-dev --optimize-autoloader
cp .env.example .env
```

Edit `.env` (File Manager or `nano`):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://shop.example.com
APP_TIMEZONE=Africa/Accra
APP_CURRENCY=GHS

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=USER_ecom        # cPanel prefixes names with your username
DB_USERNAME=USER_ecom
DB_PASSWORD=...

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp             # plus MAIL_HOST / PORT / USERNAME / PASSWORD / FROM_*
PAYSTACK_PUBLIC_KEY=...
PAYSTACK_SECRET_KEY=...

SEED_ADMIN_EMAIL=you@example.com
SEED_ADMIN_PASSWORD=a-long-unique-password
SEED_SHOP_EMAIL=shop@example.com
SEED_SHOP_PASSWORD=another-long-unique-password
```

Create the MySQL database and user first in cPanel (*MySQL® Databases*), and add the user to the database with all privileges. Then:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force      # roles, default settings, admin + shop users from SEED_*
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Remove `SEED_*_PASSWORD` from `.env` after the first seed if you like; the seeder skips users that already exist and never overwrites passwords.

Run `config:cache` **after** `.env` is final. Re-run the three cache commands after every deploy (`php artisan optimize:clear` first if something looks stale).

### `storage:link` fallback

`storage:link` creates `public/storage → storage/app/public` (used for mockups). If the host blocks symlinks:

* **Option 1 layout:** ask the host to enable `symlink()`, or create the link from SSH with `ln -s ../storage/app/public public/storage`.
* **Still blocked:** make the public disk a real folder inside the web root. In `config/filesystems.php` set the `public` disk's `root` to `public_path('storage')` (and keep `url` as `APP_URL/storage`), create that folder, then copy/move any existing files. Do **not** do this for the private `designs` disk.

`storage/app/designs` (customer artwork) must never be inside the web root. It is only served through signed, expiring URLs.

## 6. TLS

In cPanel open **SSL/TLS Status**, select the subdomain and run **AutoSSL** (*Run AutoSSL*). Then keep `APP_URL` on `https://`.

## 7. Cron

cPanel only offers cron, so all background work (queue and scheduled tasks) runs from one entry. In **Cron Jobs** add, every minute:

```
* * * * * cd /home/USER/APP_DIR && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler then runs `queue:work --stop-when-empty` every minute (with `withoutOverlapping()`) and `uploads:cleanup` daily. Use the full PHP path in the cron line if the default `php` is too old.

## 8. After deploying

* Visit `https://shop.example.com/up`: it should return 200.
* Sign in as the seeded admin and confirm you land on `/admin`; the shop user lands on `/shop`.
* Registration and password reset need working SMTP; send a test reset email.
* Check `storage/logs/laravel.log` if anything fails, and ensure `storage/` and `bootstrap/cache/` are writable (`chmod -R ug+rwX storage bootstrap/cache`).

## Updating

```bash
cd /home/USER/APP_DIR
git pull                        # or cPanel "Pull or Deploy"
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```
