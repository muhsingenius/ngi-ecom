<?php

use App\Models\DeliveryZone;
use App\Models\Design;
use App\Models\DesignArea;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** A transparent PNG with a coloured block, at the given size. */
function pngBytes(int $w = 200, int $h = 100): string
{
    $im = imagecreatetruecolor($w, $h);
    imagesavealpha($im, true);
    imagealphablending($im, false);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    imagefilledrectangle($im, 10, 10, max(11, $w - 10), max(11, $h - 10), imagecolorallocate($im, 200, 30, 30));
    ob_start();
    imagepng($im);

    return (string) ob_get_clean();
}

function jpegBytes(int $w = 120, int $h = 80): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 200));
    ob_start();
    imagejpeg($im);

    return (string) ob_get_clean();
}

/**
 * Create a verified user holding the given role (roles are seeded on demand).
 */
function userWithRole(string $role): User
{
    test()->seed(RoleSeeder::class);

    return tap(User::factory()->create())->assignRole($role);
}

/** A saved design owned by the current guest, without going through the browser export. */
function savedDesign(Product $product, ?string $groupKey = null, array $roles = [], string $token = 'guest-token'): Design
{
    $design = Design::create([
        'session_token' => $token, 'product_id' => $product->id, 'product_colour_id' => $product->colours->first()->id,
        'group_key' => $groupKey ?? Str::uuid()->toString(),
    ]);

    $area = $product->printAreas->first();
    $base = "designs/{$design->id}/area-{$area->id}";
    Storage::disk('designs')->put("$base.png", pngBytes(100, 100));
    Storage::disk('designs')->put("$base-preview.png", pngBytes(50, 50));

    DesignArea::create([
        'design_id' => $design->id, 'print_area_id' => $area->id, 'state' => '{"objects":[]}',
        'personalisation' => $roles ?: null, 'export_path' => "$base.png", 'preview_path' => "$base-preview.png",
        'export_width' => 100, 'export_height' => 100, 'export_dpi' => 300,
    ]);

    return $design->fresh();
}

function variantId(Product $product, string $colour, string $size): int
{
    return $product->variants->first(fn ($v) => $v->colour->name === $colour && $v->size === $size)->id;
}

function addToCart(Design $design, array $items, ?string $notes = null)
{
    return test()->post('/cart', ['design_id' => $design->id, 'items' => json_encode($items), 'notes' => $notes]);
}

const ROLES = [
    'name' => ['x_cm' => 5, 'y_cm' => 2, 'width_cm' => 10, 'height_cm' => 3, 'font' => 'Anton', 'font_height_mm' => 30, 'fill' => '#ffffff', 'angle' => 0, 'sample' => 'NAME'],
    'number' => ['x_cm' => 5, 'y_cm' => 8, 'width_cm' => 8, 'height_cm' => 10, 'font' => 'Anton', 'font_height_mm' => 90, 'fill' => '#ffffff', 'angle' => 0, 'sample' => '10'],
];

function cartWith(int $qty = 3, string $size = 'M', string $slug = 'classic-t-shirt-standard', array $roles = []): Design
{
    $product = Product::with(['variants.colour', 'colours', 'printAreas'])->where('slug', $slug)->first();
    $design = savedDesign($product, roles: $roles);
    addToCart($design, [['variant_id' => variantId($product, 'White', $size), 'qty' => $qty]]);

    return $design;
}

function checkoutData(array $override = []): array
{
    return $override + [
        'name' => 'Ama Mensah', 'phone' => '024 123 4567', 'email' => 'ama@example.test',
        'fulfilment' => 'delivery', 'zone_id' => DeliveryZone::first()->id, 'address' => '12 Palm Street, Osu',
    ];
}

function signed(array $payload, string $secret = 'sk_test_secret'): array
{
    $body = json_encode($payload);

    return [$body, ['HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, $secret), 'CONTENT_TYPE' => 'application/json']];
}

function webhook(array $payload, ?string $secret = 'sk_test_secret')
{
    [$body, $server] = signed($payload, $secret ?? '');

    return test()->call('POST', '/webhooks/paystack', [], [], [], $server, $body);
}

function chargeSuccess(Payment $payment, array $override = []): array
{
    return ['event' => 'charge.success', 'data' => $override + ['reference' => $payment->reference, 'status' => 'success', 'amount' => (int) round($payment->amount * 100), 'currency' => 'GHS', 'channel' => 'mobile_money']];
}

function text(string $text = 'GO', int $size = 60, string $fill = '#ffffff', array $extra = []): array
{
    return $extra + ['type' => 'i-text', 'text' => $text, 'fontSize' => $size, 'scaleX' => 1, 'scaleY' => 1, 'fill' => $fill, 'fontFamily' => 'Anton'];
}

function state(array ...$objects): string
{
    return json_encode(['version' => '7', 'objects' => $objects]);
}

/** @param array<string, mixed> $override */
function payload(Product $product, $area, string $state, array $override = [], ?string $png = null): array
{
    [$w, $h] = $area->exportPixels();

    return array_replace_recursive([
        'product_id' => $product->id,
        'colour_id' => $product->colours->first()->id,
        'name' => 'Test design',
        'areas' => [[
            'print_area_id' => $area->id,
            'state' => $state,
            'colours_count' => 1,
            'export' => UploadedFile::fake()->createWithContent('print.png', $png ?? pngBytes($w, $h)),
        ]],
    ], $override);
}

/** A paid order released to the print shop. */
function releasedOrder(int $qty = 3, array $checkout = [], array $roles = []): Order
{
    cartWith($qty, 'M', roles: $roles);
    test()->post('/checkout', checkoutData($checkout));
    $order = Order::latest('id')->first();
    webhook(chargeSuccess(Payment::where('order_id', $order->id)->first()))->assertOk();

    return $order->fresh();
}

/** Place a large (proof-requiring) order and return it. */
function proofOrder(int $qty = 25, string $slug = 'classic-t-shirt-standard', array $roles = []): Order
{
    cartWith($qty, 'M', $slug, $roles);
    test()->post('/checkout', checkoutData());

    return Order::latest('id')->first();
}

function pay(Order $order): void
{
    webhook(chargeSuccess(Payment::where('order_id', $order->id)->latest('id')->first()))->assertOk();
}
