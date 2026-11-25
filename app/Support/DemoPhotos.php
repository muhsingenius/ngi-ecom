<?php

namespace App\Support;

use App\Models\Mockup;
use App\Models\PrintArea;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

/**
 * Gives the demo catalogue its real product photos: the product-page picture, a recoloured
 * photo mockup behind the designer for the tee and polo, the right sleeve, and the colour
 * choices that match the photos. Safe to run again: it only updates, never deletes.
 */
class DemoPhotos
{
    /** Small labels (a neck tag) to smooth over, as fractions of the picture: [x0, y0, x1, y1]. */
    private const GARMENTS = [
        'classic-t-shirt-standard' => ['source' => 'media/tshirt.webp', 'patches' => [[0.455, 0.195, 0.535, 0.238]]],
        'classic-t-shirt-premium' => ['source' => 'media/tshirt.webp', 'patches' => [[0.455, 0.195, 0.535, 0.238]]],
        'classic-polo-standard' => ['source' => 'media/polo.webp', 'patches' => [[0.465, 0.112, 0.55, 0.157]]],
    ];

    /**
     * Where each print area sits on the photo mockup: [x, y, w, h] as percentages of the picture.
     * The left sleeve is the wearer's left, which is on the right of a front-facing photo.
     */
    private const BOXES = [
        'tee' => [
            'front' => [30, 25, 40, 37.5],
            'left-sleeve' => [80, 29, 10, 7.5],
            'right-sleeve' => [10, 29, 10, 7.5],
        ],
        'polo' => [
            'left-chest' => [58, 30.5, 14, 11],
        ],
    ];

    /** @var array<string, array{image: string, colours?: list<string>}> */
    private const PRODUCTS = [
        'classic-t-shirt-standard' => ['image' => 'media/tshirt.webp'],
        'classic-t-shirt-premium' => ['image' => 'media/premium_tshirt.webp', 'colours' => ['Black', 'White', 'Grey']],
        'classic-polo-standard' => ['image' => 'media/polo.webp', 'colours' => ['Navy', 'Black', 'White', 'Royal blue']],
        'engraved-bracelet-standard' => ['image' => 'media/bracelet.webp', 'colours' => ['Black']],
    ];

    public static function apply(): void
    {
        foreach (self::PRODUCTS as $slug => $config) {
            $product = Product::where('slug', $slug)->first();

            if (! $product) {
                continue;
            }

            $product->update(['image' => $config['image']]);

            if (isset($config['colours'])) {
                $product->colours()->update(['active' => false]);
                $product->colours()->whereIn('name', $config['colours'])->update(['active' => true]);
            }

            if (isset(self::GARMENTS[$slug])) {
                self::photoMockups($product, self::GARMENTS[$slug]);
            }
        }
    }

    /** @param  array{source: string, patches: list<array<int, float>>}  $garment */
    private static function photoMockups(Product $product, array $garment): void
    {
        $kind = $product->type->value === 'polo' ? 'polo' : 'tee';
        $boxes = self::BOXES[$kind];

        if ($kind === 'tee') {
            self::ensureRightSleeve($product);
        }

        $areas = $product->printAreas()->get()->filter(fn (PrintArea $a) => isset($boxes[$a->slug]));

        foreach ($areas as $area) {
            [$x, $y, $w, $h] = $boxes[$area->slug];
            $area->update(['box_x' => $x, 'box_y' => $y, 'box_w' => $w, 'box_h' => $h]);
        }

        foreach ($product->colours()->get() as $colour) {
            $path = "mockups/{$product->id}/".str($colour->name)->slug().'-photo.png';
            Storage::disk('public')->put($path, PhotoMockup::make($garment['source'], $colour->hex, 600, $garment['patches']));

            foreach ($areas as $area) {
                Mockup::updateOrCreate(
                    ['product_colour_id' => $colour->id, 'print_area_id' => $area->id],
                    ['path' => $path],
                );
            }
        }
    }

    private static function ensureRightSleeve(Product $product): void
    {
        $left = $product->printAreas()->where('slug', 'left-sleeve')->first();

        if (! $left) {
            return;
        }

        $product->printAreas()->firstOrCreate(['slug' => 'right-sleeve'], [
            'name' => 'Right sleeve', 'width_cm' => $left->width_cm, 'height_cm' => $left->height_cm,
            'method' => $left->method, 'extra_fee' => $left->extra_fee, 'sort' => $left->sort + 1,
            'max_colours' => $left->max_colours, 'min_line_mm' => $left->min_line_mm, 'max_chars' => $left->max_chars,
            'allows_name' => false, 'allows_number' => false,
            'box_x' => 10, 'box_y' => 29, 'box_w' => 10, 'box_h' => 7.5,
        ]);
    }
}
