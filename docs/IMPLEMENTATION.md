# Implementation map

How the design brief (a custom print store for individuals and bulk groups, hosted on a cPanel subdomain, produced by a local print shop) is built, where each part lives, and where the build differs from the brief. The brief itself is the owner's document and is not stored in this repository.

## Roles and areas (5.1)

| Role | Area | Notes |
|---|---|---|
| Customer | storefront, `/account/*` | Guest checkout works; an account gives past orders, saved designs and one-click reorder. Guests keep their designs, uploads and cart when they sign in (a session token is handed to the account at login). |
| Admin | `/admin/*` | `role:admin` + verified email. |
| Print shop | `/shop/*` | `role:shop`. Sees only released jobs and never prices. |

Staff logins come from `SEED_*` values or `php artisan staff:create` (see DEPLOY.md).

## Catalogue (5.2) and pricing (5.3)

* Models: `Product` (type, quality, included print areas, tier-scope override), `ProductPriceTier`, `ProductColour`, `ProductVariant` (colour × size, size surcharge, on/off), `PrintArea` (size in cm, method DTF/embroidery/engraving, colour and text limits, extra fee, name/number flags, position on the mockup), `Mockup` (one per colour per area).
* **The same product in different qualities is simply a second product** ("Classic T-shirt, Standard" / "Premium") with its own price tiers.
* `App\Services\PriceCalculator` is the only place prices are decided. Sizes and colours count together; the tier scope (per product + design, per design across products, whole order) comes from the `pricing.tier_scope` setting, with a per-product override. A design reused on another product joins the first design's group key, which is how "30 polos + 20 caps with one logo" reaches the 50+ tier of each.
* The browser shows live totals for convenience, but the product page, the order options page (`/cart/quote`), the cart and checkout all call the server calculator. Prices are locked into the order when it is placed.
* "Base price includes one print area": the product's `included_print_areas` (default 1) areas are free; extra areas cost each area's `extra_fee` per item, and the most expensive areas are the ones waived.

## Designer (5.4) and personalisation (5.5)

* `resources/js/designer.js` (Fabric 7, one canvas per print area, drawn at 20 px/cm and exported at 300 DPI by scaling). Printable colours and fonts: `config/designer.php` (to be confirmed with the print shop).
* Uploads go through `DesignUploads`: type decided by content, rasters decoded and re-encoded (metadata and payloads dropped), SVG passed through an allow-list sanitiser (`SvgSanitizer`).
* Saving stores the Fabric JSON, a 300 DPI PNG per area (private `designs` disk, resolution written into the file) and a preview. `DesignRules` re-checks colour limits, minimum embroidery text size and engraving length on the server.
* Names and numbers: admin switches them on per print area; the customer places name/number placeholders in the designer, then enters a roster (or imports a CSV) on the options page, which also sets the quantity. Blank names and over-long text are errors, duplicate numbers a warning. The print file never contains the roster; the job sheet and the roster CSV carry each row plus the placeholder's font, size and position.

## Cart, checkout and payment (5.6)

* `CartService`, `CheckoutController`, `DeliveryCalculator` (flat or by quantity band, zone or global free-delivery threshold), pickup points, manual dispatch with courier / rider phone / tracking note emailed to the customer.
* `Paystack` (Laravel HTTP client, no SDK): initialise, verify, refund. The order becomes paid only from `PaystackWebhookController` (HMAC-SHA512 signature, then amount and currency must match), or from an admin pressing "Check with Paystack now" (a server-to-server verify).
* At placement the order takes **immutable copies** of the print files and the customer's original uploads (`OrderSnapshot`), so editing or deleting a design later never changes what is printed.

## Proofs (5.7)

* `ProofService` / `ProofImage`: a proof sheet is composed from the order's mockups and artwork, emailed with a secret link (`/proofs/{token}`, no login). Approve, or request changes with a comment.
* Settings: threshold (0 = every order, blank = off), timing (pay-then-proof default, or proof-then-pay), max revision rounds (flags the order), proof-then-pay expiry (`orders:expire-proofs`, hourly). Admin can override the timing on one order, replace the generated image, or edit the design for the customer.
* Requesting changes lets the customer (or admin) open an editable copy of the design; saving it replaces the order's print files and sends a new proof.
* If a paid order's proof is rejected and the order cancelled, the admin issues a full or partial refund from the order page (Paystack refund, or recorded manually).

## Print shop (5.8) and lifecycle (5.9)

`Pending payment → Paid → (Awaiting proof → Proof approved) → In production → Ready → Dispatched / Picked up → Completed`, plus `Cancelled` and `Refunded` (`App\Enums\OrderStatus`, transitions in `OrderService`). The print shop moves a released job Queued → Printing → Ready; the admin then marks it dispatched or picked up. Customers are emailed at paid, proof ready, in production, ready and dispatched. Job sheet PDF (DomPDF) and a ZIP of print files, originals and rosters: `JobSheet`.

## Admin (5.10)

Products and all their parts, delivery zones and pickup points, settings (every key has a default; `Settings::definitions()` is the single list), orders (search, filters, dispatch, pickup, cancel, manual payment, refunds, proof tools), a dashboard of what needs attention, and reports (orders and revenue by day or week, net of refunds, top products).

## Files, security, operations (5.11)

* Customer uploads and print files live on the private `designs` disk and are served only through signed, expiring URLs (`PrivateFile`). Product mockups are on the public disk.
* CSRF on every form (the webhook is exempt and signature-verified instead), rate limits on uploads, design saves, checkout, payment retries, proof responses and the auth routes, security headers on every page.
* `uploads:cleanup` (daily) removes abandoned guest designs and unused uploads older than 30 days and never touches order files.
* Background work is the database queue drained from `schedule:run` by one cron line (see DEPLOY.md). Daily backups are a cPanel setting, documented but not automated here.

## Differences from the brief, and things to check

* **PHP 8.3+, not 8.2:** Laravel 13 requires it.
* **Autosave "to the session"** is browser local storage plus the saved design on the server; it survives reloads on the same device, not across devices unless the design is saved.
* **Embroidery colour count for images is an estimate** (quantised sample of the picture), enforced in the designer and by the declared count on the server.
* **Proof image** shows artwork on mockups; names and numbers are listed for checking on the proof page rather than drawn into the image.
* **Fonts** load from `fonts.bunny.net` in the customer's browser. Self-hosting them is a later option.
* **Landing page** product photos for mugs, tote bags and stickers are illustrative: they are not sellable products in this version.
* Placeholder mockups are generated flat silhouettes; upload real photos per colour and area in the admin.
* The Paystack integration has been exercised against faked responses only. Run a full test-mode order on the live host before taking real payments.
* Refund webhook event names (`refund.processed`, `refund.failed`) follow Paystack's documentation; confirm them in test mode.
* Not yet verified on a real cPanel host: PHP and MySQL versions, extensions, upload limits and the cron schedule.
