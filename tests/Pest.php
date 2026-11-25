<?php

use App\Models\Design;
use App\Models\DesignArea;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
