<?php

use App\Enums\TierScope;
use App\Models\Product;
use App\Services\PriceCalculator;
use App\Services\Settings;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(CatalogueSeeder::class);
    $this->calc = app(PriceCalculator::class);
});

/**
 * Build a pricing line for a seeded product. $sizes maps size => quantity
 * and quantities are spread across the first colour unless $colourIndex says otherwise.
 *
 * @param  array<string, int>  $sizes
 * @param  list<string>  $areaSlugs
 */
function priceLine(string $slug, string $design, array $sizes, array $areaSlugs = ['front'], int $colourIndex = 0, ?string $id = null): array
{
    $product = Product::with(['tiers', 'variants', 'printAreas', 'colours'])->where('slug', $slug)->firstOrFail();
    $colour = $product->colours[$colourIndex];

    return [
        'id' => $id ?? $slug.'-'.$design,
        'product' => $product,
        'design_key' => $design,
        'area_ids' => $product->printAreas->whereIn('slug', $areaSlugs)->pluck('id')->all(),
        'items' => collect($sizes)->map(fn ($qty, $size) => [
            'variant_id' => $product->variants->first(fn ($v) => $v->product_colour_id === $colour->id && $v->size === $size)->id,
            'qty' => $qty,
        ])->values()->all(),
    ];
}

const TEE = 'classic-t-shirt-standard';
const POLO = 'classic-polo-standard';
const CAP = 'snapback-cap-standard';
const BRACELET = 'engraved-bracelet-standard';

it('prices a single item at the first tier plus its print area fee when not included', function () {
    // 1 area is included in the base price, so a single front print costs just the tier price.
    $quote = $this->calc->quote([priceLine(TEE, 'a', ['M' => 1])]);

    expect($quote['lines'][TEE.'-a']['unit_price'])->toBe(5500)
        ->and($quote['subtotal'])->toBe(5500);
});

it('drops the unit price at each tier boundary', function (int $qty, int $expected) {
    $quote = $this->calc->quote([priceLine(TEE, 'a', ['M' => $qty])]);

    expect($quote['lines'][TEE.'-a']['unit_price'])->toBe($expected);
})->with([
    'one' => [1, 5500], 'nine' => [9, 5500], 'ten' => [10, 4800], 'twenty four' => [24, 4800],
    'twenty five' => [25, 4200], 'fifty' => [50, 3800], 'hundred' => [100, 3400], 'two hundred' => [200, 3400],
]);

it('counts sizes and colours together towards one tier', function () {
    // 20 M + 20 L + 10 XL of one design = 50 -> the 50+ tier.
    $quote = $this->calc->quote([priceLine(TEE, 'logo', ['M' => 20, 'L' => 20, 'XL' => 10])]);

    expect($quote['lines'][TEE.'-logo']['quantity'])->toBe(50)
        ->and($quote['lines'][TEE.'-logo']['unit_price'])->toBe(3800)
        ->and($quote['subtotal'])->toBe(50 * 3800);
});

it('adds a per-variant size surcharge', function () {
    $quote = $this->calc->quote([priceLine(TEE, 'a', ['M' => 1, '2XL' => 1])]);
    $items = $quote['lines'][TEE.'-a']['items'];

    expect($items[0]['unit_price'])->toBe(5500)
        ->and($items[1]['unit_price'])->toBe(6000)
        ->and($quote['subtotal'])->toBe(11500);
});

it('charges extra print areas beyond the included one, waiving the most expensive', function () {
    // front 12 + back 12 + sleeve 8, one included (a 12 is waived): 12 + 8 = 20 extra each.
    $quote = $this->calc->quote([priceLine(TEE, 'a', ['M' => 1], ['front', 'back', 'left-sleeve'])]);

    expect($quote['lines'][TEE.'-a']['extra_area_fee'])->toBe(2000)
        ->and($quote['subtotal'])->toBe(5500 + 2000);

    $oneArea = $this->calc->quote([priceLine(TEE, 'a', ['M' => 1], ['back'])]);
    expect($oneArea['lines'][TEE.'-a']['extra_area_fee'])->toBe(0);
});

it('reports the saving against the single-item price and the next tier', function () {
    $line = $this->calc->quote([priceLine(TEE, 'a', ['M' => 12])])['lines'][TEE.'-a'];

    expect($line['saving'])->toBe((5500 - 4800) * 12)
        ->and($line['next_tier'])->toBe(['min_qty' => 25, 'unit_price' => 4200]);
});

describe('tier scope', function () {
    it('per product and design: different designs of one product do not combine', function () {
        app(Settings::class)->set('pricing.tier_scope', 'product_design');

        $quote = $this->calc->quote([
            priceLine(TEE, 'a', ['M' => 30], id: 'x'),
            priceLine(TEE, 'b', ['M' => 30], id: 'y'),
        ]);

        expect($quote['lines']['x']['unit_price'])->toBe(4200)
            ->and($quote['lines']['y']['unit_price'])->toBe(4200);
    });

    it('per product and design: the same design on separate lines combines', function () {
        app(Settings::class)->set('pricing.tier_scope', 'product_design');

        $quote = $this->calc->quote([
            priceLine(TEE, 'a', ['M' => 30], colourIndex: 0, id: 'white'),
            priceLine(TEE, 'a', ['M' => 30], colourIndex: 1, id: 'black'),
        ]);

        expect($quote['lines']['white']['unit_price'])->toBe(3800)
            ->and($quote['lines']['black']['unit_price'])->toBe(3800);
    });

    it('per design: 30 polos and 20 caps with one logo each price at their own 50+ tier', function () {
        app(Settings::class)->set('pricing.tier_scope', 'design');

        $quote = $this->calc->quote([
            priceLine(POLO, 'logo', ['M' => 30], ['left-chest'], id: 'polo'),
            priceLine(CAP, 'logo', ['One size' => 20], ['front'], id: 'cap'),
        ]);

        expect($quote['lines']['polo']['unit_price'])->toBe(6200)
            ->and($quote['lines']['cap']['unit_price'])->toBe(3200)
            ->and($quote['lines']['polo']['tier_quantity'])->toBe(50);
    });

    it('per design: different designs stay apart', function () {
        app(Settings::class)->set('pricing.tier_scope', 'design');

        $quote = $this->calc->quote([
            priceLine(POLO, 'one', ['M' => 30], ['left-chest'], id: 'polo'),
            priceLine(CAP, 'two', ['One size' => 20], ['front'], id: 'cap'),
        ]);

        expect($quote['lines']['polo']['unit_price'])->toBe(6800)
            ->and($quote['lines']['cap']['unit_price'])->toBe(4000);
    });

    it('whole order: every unit counts, whatever the design', function () {
        app(Settings::class)->set('pricing.tier_scope', 'order');

        $quote = $this->calc->quote([
            priceLine(TEE, 'a', ['M' => 25], id: 'x'),
            priceLine(TEE, 'b', ['M' => 25], id: 'y'),
        ]);

        expect($quote['lines']['x']['unit_price'])->toBe(3800)
            ->and($quote['lines']['y']['unit_price'])->toBe(3800);
    });

    it('a per-product override excludes that product from cross-product tiers', function () {
        app(Settings::class)->set('pricing.tier_scope', 'order');
        // The seeded bracelet already overrides to product_design.
        expect(Product::where('slug', BRACELET)->first()->tier_scope_override)->toBe(TierScope::ProductDesign);

        $quote = $this->calc->quote([
            priceLine(TEE, 'a', ['M' => 60], id: 'tee'),
            priceLine(BRACELET, 'a', ['One size' => 5], ['band'], id: 'bracelet'),
        ]);

        // 65 units in the order, but the bracelet only counts its own 5.
        expect($quote['lines']['tee']['unit_price'])->toBe(3800)
            ->and($quote['lines']['bracelet']['unit_price'])->toBe(2500)
            ->and($quote['lines']['bracelet']['tier_quantity'])->toBe(5);
    });
});

it('rejects an inactive or foreign variant', function () {
    $line = priceLine(TEE, 'a', ['M' => 1]);
    $line['product']->variants->firstWhere('id', $line['items'][0]['variant_id'])->update(['active' => false]);
    $line['product']->load('variants');

    expect(fn () => $this->calc->quote([$line]))->toThrow(InvalidArgumentException::class);
});

it('prices different qualities of the same product separately', function () {
    $standard = $this->calc->quote([priceLine(TEE, 'a', ['M' => 1])]);
    $premium = $this->calc->quote([priceLine('classic-t-shirt-premium', 'a', ['M' => 1])]);

    expect($standard['subtotal'])->toBe(5500)->and($premium['subtotal'])->toBe(7500);
});
