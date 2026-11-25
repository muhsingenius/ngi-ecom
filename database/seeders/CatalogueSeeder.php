<?php

namespace Database\Seeders;

use App\Enums\PrintMethod;
use App\Enums\ProductType;
use App\Models\Product;
use App\Support\MockupGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A small demo catalogue so the store works on day one. Prices, areas and colours
 * are all editable in the admin. Skipped when any product already exists.
 */
class CatalogueSeeder extends Seeder
{
    private const COLOURS = [
        'White' => '#FFFFFF', 'Black' => '#111827', 'Navy' => '#1F2A44',
        'Red' => '#C62828', 'Royal blue' => '#1D4ED8', 'Grey' => '#9CA3AF',
    ];

    public function run(): void
    {
        if (Product::query()->exists()) {
            return;
        }

        $clothing = ['S' => 0, 'M' => 0, 'L' => 0, 'XL' => 0, '2XL' => 5, '3XL' => 8];

        $this->product(ProductType::TShirt, 'Classic T-shirt', 'Standard', 'media/tshirt.webp',
            'Everyday cotton tee, a solid choice for events and teams.',
            [1 => 55, 10 => 48, 25 => 42, 50 => 38, 100 => 34], $clothing, self::COLOURS, [
                ['Front', 'front', 28, 35, PrintMethod::Dtf, 12, ['box' => [31, 22, 38, 40]]],
                ['Back', 'back', 30, 40, PrintMethod::Dtf, 12, ['box' => [30, 20, 40, 46], 'name' => true, 'number' => true]],
                ['Left sleeve', 'left-sleeve', 8, 8, PrintMethod::Dtf, 8, ['box' => [6, 22, 14, 14]]],
            ]);

        $this->product(ProductType::TShirt, 'Classic T-shirt', 'Premium', null,
            'Heavier, softer combed cotton with a better fit and finish.',
            [1 => 75, 10 => 66, 25 => 58, 50 => 52, 100 => 46], $clothing, self::COLOURS, [
                ['Front', 'front', 28, 35, PrintMethod::Dtf, 12, ['box' => [31, 22, 38, 40]]],
                ['Back', 'back', 30, 40, PrintMethod::Dtf, 12, ['box' => [30, 20, 40, 46], 'name' => true, 'number' => true]],
            ]);

        $this->product(ProductType::Polo, 'Classic polo', 'Standard', null,
            'Smart-casual polo for staff, schools and clubs.',
            [1 => 85, 10 => 76, 25 => 68, 50 => 62, 100 => 56], $clothing, self::COLOURS, [
                ['Left chest', 'left-chest', 10, 10, PrintMethod::Embroidery, 0, ['box' => [58, 24, 16, 14], 'colours' => 6, 'min_line' => 5]],
                ['Back', 'back', 28, 35, PrintMethod::Dtf, 15, ['box' => [30, 20, 40, 46], 'name' => true, 'number' => true]],
            ]);

        $this->product(ProductType::Hoodie, 'Pullover hoodie', 'Standard', 'media/hoodie.webp',
            'Fleece-lined pullover with a kangaroo pocket.',
            [1 => 160, 10 => 145, 25 => 130, 50 => 118], ['S' => 0, 'M' => 0, 'L' => 0, 'XL' => 0, '2XL' => 8], [
                'Black' => '#111827', 'Navy' => '#1F2A44', 'Grey' => '#9CA3AF', 'Red' => '#C62828',
            ], [
                ['Front', 'front', 28, 30, PrintMethod::Dtf, 15, ['box' => [31, 25, 38, 32]]],
                ['Back', 'back', 30, 40, PrintMethod::Dtf, 15, ['box' => [30, 20, 40, 46], 'name' => true, 'number' => true]],
            ]);

        $this->product(ProductType::Cap, 'Snapback cap', 'Standard', 'media/cap.webp',
            'Structured cap with an embroidered front panel.',
            [1 => 45, 10 => 40, 25 => 36, 50 => 32], ['One size' => 0], [
                'Black' => '#111827', 'Navy' => '#1F2A44', 'White' => '#FFFFFF', 'Red' => '#C62828',
            ], [
                ['Front panel', 'front', 10, 6, PrintMethod::Embroidery, 0, ['box' => [32, 34, 36, 22], 'colours' => 6, 'min_line' => 5]],
            ]);

        $this->product(ProductType::Bracelet, 'Engraved bracelet', 'Standard', null,
            'A simple band engraved with your own text.',
            [1 => 25, 10 => 21, 25 => 18, 50 => 15], ['One size' => 0], ['Black' => '#111827', 'Silver' => '#C0C4CC'], [
                ['Band', 'band', 18, 1.2, PrintMethod::Engraving, 0, ['box' => [12, 43, 76, 14], 'chars' => 30]],
            ], tierScope: 'product_design');
    }

    /**
     * @param  array<int, int|float>  $tiers
     * @param  array<string, int|float>  $sizes
     * @param  array<string, string>  $colours
     * @param  list<array<int, mixed>>  $areas
     */
    private function product(ProductType $type, string $name, string $quality, ?string $image, string $description,
        array $tiers, array $sizes, array $colours, array $areas, ?string $tierScope = null): void
    {
        $product = Product::create([
            'type' => $type, 'name' => $name, 'slug' => Str::slug("$name $quality"), 'quality' => $quality,
            'description' => $description, 'image' => $image, 'included_print_areas' => 1,
            'tier_scope_override' => $tierScope, 'sort' => Product::count(),
        ]);

        foreach ($tiers as $min => $price) {
            $product->tiers()->create(['min_qty' => $min, 'unit_price' => $price]);
        }

        $printAreas = [];

        foreach ($areas as $i => [$areaName, $areaSlug, $w, $h, $method, $fee, $opts]) {
            [$bx, $by, $bw, $bh] = $opts['box'];
            $printAreas[] = $product->printAreas()->create([
                'name' => $areaName, 'slug' => $areaSlug, 'width_cm' => $w, 'height_cm' => $h,
                'method' => $method, 'extra_fee' => $fee, 'sort' => $i,
                'max_colours' => $opts['colours'] ?? null, 'min_line_mm' => $opts['min_line'] ?? null,
                'max_chars' => $opts['chars'] ?? null,
                'allows_name' => $opts['name'] ?? false, 'allows_number' => $opts['number'] ?? false,
                'box_x' => $bx, 'box_y' => $by, 'box_w' => $bw, 'box_h' => $bh,
            ]);
        }

        $sort = 0;

        foreach ($colours as $colourName => $hex) {
            $colour = $product->colours()->create(['name' => $colourName, 'hex' => $hex, 'sort' => $sort++]);

            $path = "mockups/{$product->id}/".Str::slug($colourName).'.png';
            Storage::disk('public')->put($path, MockupGenerator::make($type, $hex));

            foreach ($printAreas as $area) {
                $colour->mockups()->create(['print_area_id' => $area->id, 'path' => $path]);
            }

            $variantSort = 0;

            foreach ($sizes as $size => $surcharge) {
                $product->variants()->create([
                    'product_colour_id' => $colour->id, 'size' => $size, 'size_surcharge' => $surcharge, 'sort' => $variantSort++,
                ]);
            }
        }

        if (! $image) {
            $product->update(['image' => "mockups/{$product->id}/".Str::slug(array_key_first($colours)).'.png']);
        }
    }
}
