# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project status

Custom print store for individuals and bulk groups (Laravel 13, PHP 8.3+, developed on 8.5): catalogue with quantity-tier pricing, browser designer (Fabric.js), names and numbers, cart, checkout with delivery zones, Paystack payments, proof approval, print shop portal, admin with refunds and reports. The whole brief is built; docs/IMPLEMENTATION.md maps each part of the brief to code and lists known differences. Deployment is in DEPLOY.md.

## Commands

```bash
composer run dev                      # php artisan serve + queue + vite (use this, NOT XAMPP's Apache)
php artisan serve                     # server only; pair with `npm run dev`
npm run build                         # production Vite build
php artisan migrate --seed            # tables, roles, default settings, staff users from SEED_* in .env
php artisan test                      # or vendor/bin/pest
vendor/bin/pest tests/Feature/SetupTest.php            # single file
vendor/bin/pest --filter "seeds the admin"             # single test by name
vendor/bin/pint                       # fix code style (pint --test to check); must pass
```

## Environment gotchas (Windows + XAMPP)

- XAMPP provides **MySQL only** (it is MariaDB 10.11). The app runs via `php artisan serve`.
- XAMPP's PHP (`C:\xampp\php`, with `gd`, `intl`, `zip`, `sodium` enabled) and Composer are on the machine PATH, but an already-open shell may have a stale PATH. If `php` is not found, reload PATH from the registry rather than creating temp `php.ini` files or downloading `composer.phar`.
- Databases `ecom` (dev) and `ecom_test` (tests) must exist as `utf8mb4_unicode_ci`. `.env` uses `root` with an empty password. Creation steps are in README.md.
- Tests run against **MySQL `ecom_test`** (set in `phpunit.xml`), not SQLite, and `RefreshDatabase` is enabled globally for `tests/Feature` in `tests/Pest.php`, so never point the test DB at `ecom`.

## Conventions and constraints

- Use Eloquent / the query builder; avoid raw SQL unless unavoidable. utf8mb4 everywhere.
- Frontend: Blade + Livewire 4 + Alpine.js + Tailwind 4 via Vite. Livewire bundles Alpine, so don't add Alpine separately.
- Payments (Paystack) must use Laravel's HTTP client. **Do not add a Paystack SDK.**
- Production is cPanel-hosted MySQL, so keep dependencies and deployment compatible with shared hosting (built assets, no long-running Node processes).

## Design system

Defined once in `resources/css/app.css` (Tailwind 4 `@theme` tokens plus component classes); compose these instead of inventing one-off values. It was modelled on the structure of customandprint.com (not its assets, text or branding).

- **Type:** Plus Jakarta Sans only (loaded with the `@fonts` directive, which both layouts must include). Scale classes: `type-display` (56/30px), `type-title` (40/29), `type-heading` (18), `type-lead`; headings are weight 800 with negative tracking. Avoid a trailing period in tight-tracked headings: this font leaves a visible gap before punctuation.
- **Colour tokens:** `ink`, `copy`, `muted`, `line`, `mist`, `night`, `brand` (+`-strong`, `-tint`), `accent` (+`-strong`, `-tint`). Brand is for actions/links; accent is reserved for dark `section-night` bands.
- **Shape and depth:** radii `card` 14px, `panel` 10px, `chip` 8px, `control` 7px; shadows `float`, `brand`, `accent`.
- **Layout:** `container-page` (1220px), `section` (88px padding, 56px mobile) with `section-mist` / `section-night` modifiers; grids use `gap-6`.
- **Components:** `btn` + `btn-brand|accent|outline`, `btn-lg`, `btn-block`, `link-arrow`, `pill`, `card`, `panel`, `panel-night`, `float-card`, `chip-icon`, `well-icon`, `field`. Blade components: `x-layouts.app`, `x-layouts.guest`, `x-icon`, `x-product-art` (original SVG illustrations), `x-input`, `x-button`.
- **Motion:** one load-in (`settle` on the hero cards); respect `prefers-reduced-motion`.
- The landing page (`resources/views/storefront/`) is one partial per section. The mug, tote and sticker photos are illustrative, not sellable products; only the tee, hoodie and cap cards link to the catalogue.
- Tailwind only generates classes it sees at build time: run `npm run build` after adding utility classes in Blade, or new classes will look unstyled. `public/build` is committed.

## Architecture notes

- **Roles and areas:** `spatie/laravel-permission`, guard `web`. `RoleSeeder::ROLES` is the source of truth for `admin`, `shop`, `customer`. Routes in `routes/web.php`: `/admin` and `/shop` use `['auth', 'role:x', 'verified']` (guest -> login, wrong role -> 403; `auth` must stay before `role`). `User::homePath()` decides where each role lands; it is used by `LoginResponse` and the guest-redirect in `bootstrap/app.php`.
- **Auth:** Fortify with Blade views in `resources/views/auth` (registered in `FortifyServiceProvider`). Only registration, password reset and email verification are enabled; 2FA/passkeys/profile features were deliberately removed. `CreateNewUser` assigns the `customer` role. Layouts are anonymous components `x-layouts.app` / `x-layouts.guest`.
- **Settings:** `App\Services\Settings` reads/writes the `settings` table through the cache. `Settings::definitions()` is the single source of keys, types, defaults and validation rules; add new settings there. Values are JSON-encoded, and a stored null (e.g. `proof.threshold` off) overrides a non-null default.
- **Private files:** disk `designs` (`storage/app/designs`) is never public. Generate links with `App\Support\PrivateFile::url()`; they are served by the `files.download` route behind `signed`, restricted to `PrivateFile::DOWNLOADABLE_DISKS`.
- **Background work:** cPanel has cron only. `routes/console.php` schedules `queue:work --stop-when-empty` every minute (no overlap), `orders:expire-proofs` hourly and `uploads:cleanup` daily; one cron entry runs `schedule:run`. Seed users, Paystack keys and the demo-data switch are read via `config/shop.php`, not `env()` directly, so config caching works.
- **Image driver:** `config/intervention-image.php` picks Imagick if the extension is loaded, else GD; override with `IMAGE_DRIVER`. XAMPP currently has no Imagick, so GD is in use.

## Store subsystems (read before changing them)

- **Pricing:** `PriceCalculator` is the only place prices are decided; money is integer pesewas (`App\Support\Money`). The product page, `/cart/quote`, cart, checkout and orders all call it. Never trust a price from the browser. Tier scope = `pricing.tier_scope` setting, with a per-product override; designs reused on another product share `designs.group_key`.
- **Catalogue:** products (a quality is a separate product), `ProductColour` × `ProductVariant` (size, surcharge), `PrintArea` (cm size, method, limits, extra fee, box on the mockup), `Mockup` per colour per area. `CatalogueSeeder`/`DeliverySeeder` give a demo store (on by default except production). Placeholder mockups come from `MockupGenerator`; the tee and polo front mockups are cut from the product photos and recoloured by `PhotoMockup`, applied (idempotently, also by a migration) through `DemoPhotos`. Front/back areas offer chest/middle/bottom placement zones (`DesignController::placementZones`); sleeves are separate print areas (left and right).
- **Designer:** `resources/js/designer.js` (separate Vite entry; Fabric 7 types are lowercase like `'i-text'`, default object origin is **centre**, so clip rectangles must set `originX/originY: 'left'/'top'`). Canvases live outside Alpine state. 20 px/cm logical, exported at 300 DPI. The server re-validates everything in `DesignController::persist` and `DesignRules`. Uploads: `DesignUploads` (+ `SvgSanitizer`); a saved design's files are on the private `designs` disk.
- **Orders:** `OrderService` owns the lifecycle (`place` → `markPaid` → `release` → shop `setStage` → admin `dispatch`/`markPickedUp`/`complete`, `cancel`). An order is paid **only** via `PaystackWebhookController::confirm` (signature, amount, currency) or an admin verify. `OrderSnapshot` copies print files into `orders/{id}/…` so later design edits never change an order; never delete those.
- **Proofs:** `ProofService` (generate, replace, approve, request changes, revise via a cloned design with `designs.revises_order_line_id`, timing override); links are secret tokens, no login.
- **Print shop:** `Shop\JobController` shows only released orders (`Order::released()`), never prices; `JobSheet` builds the PDF and ZIP.
- **Ownership:** guests own designs, uploads and carts through `session('designer_token')` (set by the `designer` middleware, handed to the account at login in `AppServiceProvider`). Scope with `->ownedBy($request)`.
- **Tests:** shared helpers (`savedDesign`, `addToCart`, `cartWith`, `releasedOrder`, `proofOrder`, `webhook`, …) are in `tests/Pest.php`. Fake `Storage` (`public`, `designs`), `Mail` and `Http` in each test file; `Http::fake` stubs registered first win, so swap the factory to override.
- Breeze was removed (unmaintained for current Laravel); don't reinstall it.
- Laravel Boost is installed (dev); its guidelines are appended below and skills are in `.claude/skills`. `AGENTS.md` is still the original bootstrap stub.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== livewire/core rules ===

# Livewire

- Livewire allows you to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
