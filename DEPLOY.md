# Deploying to cPanel (subdomain)

Target: a subdomain such as `shop.example.com` on a cPanel host. Replace `USER` with your cPanel username and `APP_DIR` with the folder the app lives in (e.g. `pod-store`).

## 1. Host checklist

Check these in cPanel (**MultiPHP Manager**, **MultiPHP INI Editor**, **Select PHP Version → Extensions**) before deploying.

| Item | Requirement |
|---|---|
| PHP version | **8.3 or newer** (Laravel 13 requires `^8.3`). Use the same major/minor as local if possible (local: 8.5) |
| Database | MySQL or MariaDB. Local dev uses **MariaDB 10.11 (XAMPP)**; note the host's engine and version and test against it |
| Extensions | `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd` (or `imagick`), `zip`, `bcmath`, `intl`, `sodium`, plus `ctype`, `curl`, `dom`, `json`, `tokenizer`, `xml` |
| `upload_max_filesize` | 25M minimum, **64M recommended**. The designer uploads a 300 DPI print file per print area (a 28 × 35 cm area is about 3300 × 4100 px) and customers upload images up to 20 MB |
| `post_max_size` | 25M minimum, **64M recommended** (a design with several areas is sent in one request) |
| `memory_limit` | 256M minimum, **512M recommended**. Decoding a large print file for its preview needs roughly 60 MB per image |
| `max_execution_time` | 60 seconds or more (building a job ZIP and the proof sheet) |
| Image library | **Imagick** (preferred) or **GD** with PNG/JPEG support. The app picks Imagick when it is loaded, else GD |
| Outbound HTTPS | Needed to reach `api.paystack.co` (payments). Browsers also load the designer fonts from `fonts.bunny.net` |
| Disk | Ability to run cron jobs; SSH or **Git Version Control** access (preferred). Plan for growth: every order keeps its 300 DPI print files |

Ask the host to confirm the points above **before** building: PHP version, MySQL vs MariaDB version, that Composer and SSH are available (or that you can upload `vendor/`), and the limits in the table.

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

MAIL_MAILER=smtp             # the host's SMTP; also MAIL_HOST / PORT / USERNAME / PASSWORD / SCHEME / FROM_ADDRESS
MAIL_FROM_ADDRESS=orders@example.com
PAYSTACK_PUBLIC_KEY=...
PAYSTACK_SECRET_KEY=...      # use the test key (sk_test_...) until you have done a test order

SEED_DEMO_CATALOGUE=false    # no demo products or zones on the live site

SEED_ADMIN_EMAIL=you@example.com
SEED_ADMIN_PASSWORD=a-long-unique-password
SEED_SHOP_EMAIL=shop@example.com
SEED_SHOP_PASSWORD=another-long-unique-password
```

`SEED_DEMO_CATALOGUE` already defaults to off when `APP_ENV=production`; it is shown so the intent is explicit. Without demo data you start with an empty store: add products, delivery zones and pickup points in the admin (see "Setting up the store" below).

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

More staff logins, or a forgotten password, can be handled any time over SSH (the password is printed once):

```bash
php artisan staff:create shop2@example.com shop --name="Print shop"     # a print shop login
php artisan staff:create boss@example.com admin                          # another admin
php artisan staff:create boss@example.com admin --reset                  # reset an existing user
```

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

The scheduler then runs:

| Task | When | What it does |
|---|---|---|
| `queue:work --stop-when-empty` | every minute (no overlap) | sends the queued emails (receipts, proofs, ready/dispatched notices, new-job alerts to the shop) |
| `orders:expire-proofs` | hourly | cancels proof-then-pay orders that were approved but not paid within the expiry window, and emails the customer |
| `uploads:cleanup` | daily | removes abandoned guest designs and unused uploads older than 30 days. It never touches account designs, carts or anything under `storage/app/designs/orders/` |

Use the full PHP path in the cron line if the default `php` is too old. Emails are sent by the queue, so **if the cron line is missing no emails go out** (and the order flow still works). `php artisan uploads:cleanup --pretend` shows what a clean-up would remove without deleting anything.

## 7a. Paystack

1. In the Paystack dashboard (**Settings → API Keys & Webhooks**) copy the **public** and **secret** keys into `.env` (`PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY`), then run `php artisan config:cache`.
2. Set the **Webhook URL** to `https://shop.example.com/webhooks/paystack`. The app trusts a payment only from this webhook (signature checked with your secret key, amount and currency compared with the order). The browser redirect after paying is never trusted.
3. Do a full test order in **test mode** (card and Mobile Money test numbers from the Paystack docs) and confirm the order becomes *In production* (or *Awaiting proof*) by itself. Then switch to the live keys.
4. If a webhook is ever missed, an admin can press **Check with Paystack now** on the order page; it asks Paystack directly (server to server) and applies the result.
5. Refunds are issued from the order page in the admin; Paystack must be allowed to refund the transaction in your account.

## 7b. Setting up the store

After the first login as admin:

1. **Products**: add product types, qualities, colours, sizes, print areas, mockups and bulk prices. Switch a product on only when it has a colour, a size and a print area.
2. **Delivery**: add delivery zones (flat or by quantity band, ETA, courier, free-delivery threshold) and at least one pickup point. Customers outside every zone can only pick up.
3. **Settings**: tier scope, proof threshold and timing, revision rounds, proof expiry, free-delivery threshold, the print shop's notification email, turnaround days, and the store phone/WhatsApp/email shown on the contact page.
4. Check the fonts and printable colours with the print shop (`config/designer.php`).

## 7c. Backups

Use cPanel's **Backup** (or **JetBackup**, if your host offers it) to take a **daily** backup of **both** the home directory (it includes `storage/app/designs`, i.e. every customer's print files) and the MySQL database. Test a restore once. Keep `.env` out of the repository.

## 8. After deploying

* Visit `https://shop.example.com/up`: it should return 200.
* Sign in as the seeded admin and confirm you land on `/admin`; the shop user lands on `/shop`.
* Registration and password reset need working SMTP; send a test reset email.
* Open a product, start the designer, add text and an image, and save: the design should reach the "How many do you need?" page. This proves uploads, the image library and the private `designs` disk.
* Place a test order and watch it through payment, proof (if it is big enough), the print shop's *Ready* button and your *Dispatched* button, checking each email arrives.
* Confirm customer files are private: `https://shop.example.com/storage/` must only expose product mockups. Print files and uploads under `storage/app/designs` have no public URL and are reachable only through signed, expiring `/files/download` links.
* Check `storage/logs/laravel.log` if anything fails, and ensure `storage/` and `bootstrap/cache/` are writable (`chmod -R ug+rwX storage bootstrap/cache`).

## Troubleshooting

| Symptom | Likely cause |
|---|---|
| "Saving failed" in the designer, or HTTP 413/422 on save | `post_max_size`/`upload_max_filesize` too small, or the host's mod_security limit. Raise the limits above |
| Designer save returns 500 | Not enough `memory_limit` to decode a large print file; raise it to 512M |
| Uploads rejected as "could not be read" | Neither Imagick nor GD can decode the image (missing JPEG/PNG support); check the PHP extensions |
| No emails | Cron line missing, or SMTP settings wrong. Run `php artisan queue:work --stop-when-empty` by hand and read `storage/logs/laravel.log` |
| Orders stay *Pending payment* after paying | The Paystack webhook URL is wrong or blocked, or the secret key differs from the one that created the payment. Use *Check with Paystack now* |
| Fonts look wrong in the designer | The browser could not reach `fonts.bunny.net`; text then falls back to a system font |
| Pages show old CSS/JS | `public/build` was not updated on the server (see sections 2 and 4) |

## Updating

```bash
cd /home/USER/APP_DIR
git pull                        # or cPanel "Pull or Deploy"
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
```
