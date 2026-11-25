<?php

use App\Models\CartLine;
use App\Models\Design;
use App\Models\DesignArea;
use App\Models\Product;
use App\Services\Settings;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('designs');
    $this->seed(CatalogueSeeder::class);
    $this->withSession(['designer_token' => 'guest-token']);
    $this->tee = Product::with(['variants', 'colours', 'printAreas'])->where('slug', 'classic-t-shirt-standard')->first();
});

/** A saved design owned by the current guest, without going through the browser export. */
function savedDesign(Product $product, ?string $groupKey = null, array $roles = [], string $token = 'guest-token'): Design
{
    $design = Design::create([
        'session_token' => $token, 'product_id' => $product->id, 'product_colour_id' => $product->colours->first()->id,
        'group_key' => $groupKey ?? Str::uuid()->toString(),
    ]);

    $area = $product->printAreas->first();
    DesignArea::create([
        'design_id' => $design->id, 'print_area_id' => $area->id, 'state' => '{"objects":[]}',
        'personalisation' => $roles ?: null, 'export_width' => 100, 'export_height' => 100, 'export_dpi' => 300,
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

it('shows the options page to the owner only', function () {
    $design = savedDesign($this->tee);

    $this->get("/designs/{$design->id}/order")->assertOk()->assertSee('How many do you need?')->assertSee('Add to cart');

    $this->flushSession();
    $this->get("/designs/{$design->id}/order")->assertNotFound();
});

it('adds a mix of sizes and colours to the cart and prices them as one quantity', function () {
    $design = savedDesign($this->tee);

    addToCart($design, [
        ['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 20],
        ['variant_id' => variantId($this->tee, 'Black', 'L'), 'qty' => 20],
        ['variant_id' => variantId($this->tee, 'Black', 'XL'), 'qty' => 10],
    ])->assertRedirect('/cart');

    $line = CartLine::first();
    expect($line->quantity())->toBe(50)->and($line->design_id)->toBe($design->id);

    // 50 together -> GH₵ 38 each = GH₵ 1,900.
    $this->get('/cart')->assertOk()->assertSee('GH₵ 1,900')->assertSee('Black: 20 L, 10 XL')->assertSee('White: 20 M');
});

it('adds size surcharges and updates the same line when the design is added again', function () {
    $design = savedDesign($this->tee);

    addToCart($design, [['variant_id' => variantId($this->tee, 'White', '2XL'), 'qty' => 2]]);
    addToCart($design, [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 1]]);

    expect(CartLine::count())->toBe(1)->and(CartLine::first()->quantity())->toBe(1);
    $this->get('/cart')->assertSee('GH₵ 55');
});

it('rejects empty, foreign, inactive or oversized selections', function () {
    $design = savedDesign($this->tee);
    $polo = Product::with('variants.colour')->where('slug', 'classic-polo-standard')->first();

    addToCart($design, [])->assertSessionHasErrors('items');
    addToCart($design, [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 0]])->assertSessionHasErrors('items');
    addToCart($design, [['variant_id' => $polo->variants->first()->id, 'qty' => 1]])->assertSessionHasErrors('items');
    addToCart($design, [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 6000]])->assertSessionHasErrors('items');

    $this->tee->variants->first(fn ($v) => $v->colour->name === 'White' && $v->size === 'S')->update(['active' => false]);
    addToCart($design, [['variant_id' => variantId($this->tee, 'White', 'S'), 'qty' => 1]])->assertSessionHasErrors('items');

    expect(CartLine::count())->toBe(0);
});

it('cannot order someone else’s design', function () {
    $other = savedDesign($this->tee, token: 'someone-else');

    addToCart($other, [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 1]])->assertNotFound();
});

it('removes a line, and only from the owner’s cart', function () {
    $design = savedDesign($this->tee);
    addToCart($design, [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 1]]);
    $line = CartLine::first();

    $this->flushSession();
    $this->withSession(['designer_token' => 'intruder']);
    $this->delete("/cart/{$line->id}")->assertNotFound();

    $this->withSession(['designer_token' => 'guest-token']);
    $this->delete("/cart/{$line->id}")->assertRedirect('/cart');
    expect(CartLine::count())->toBe(0);
});

it('shows a friendly empty cart', function () {
    $this->get('/cart')->assertOk()->assertSee('Your cart is empty');
});

it('shows the cart count in the header', function () {
    addToCart(savedDesign($this->tee), [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 7]]);

    $this->get('/products')->assertSee('Cart, 7 items');
});

describe('live quote', function () {
    it('prices the selection on the server, including the next tier', function () {
        $design = savedDesign($this->tee);

        $this->postJson('/cart/quote', ['design_id' => $design->id, 'items' => [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 12]]])
            ->assertOk()
            ->assertJsonPath('quantity', 12)
            ->assertJsonPath('unit_price', 4800)
            ->assertJsonPath('line_total', 57600)
            ->assertJsonPath('saving', 8400)
            ->assertJsonPath('next_tier.min_qty', 25);
    });

    it('counts what is already in the cart when the scope spans designs', function () {
        app(Settings::class)->set('pricing.tier_scope', 'order');
        addToCart(savedDesign($this->tee), [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 30]]);

        $other = savedDesign($this->tee);
        $this->postJson('/cart/quote', ['design_id' => $other->id, 'items' => [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 20]]])
            ->assertJsonPath('unit_price', 3800); // 30 + 20 = 50 in the whole order
    });

    it('keeps designs apart under the default scope', function () {
        addToCart(savedDesign($this->tee), [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 30]]);

        $other = savedDesign($this->tee);
        $this->postJson('/cart/quote', ['design_id' => $other->id, 'items' => [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 20]]])
            ->assertJsonPath('unit_price', 4800);
    });

    it('ignores unavailable items and returns nothing for an empty selection', function () {
        $design = savedDesign($this->tee);

        $this->postJson('/cart/quote', ['design_id' => $design->id, 'items' => [['variant_id' => 999999, 'qty' => 3]]])->assertOk()->assertExactJson(['quantity' => 0]);
    });

    it('refuses to quote someone else’s design', function () {
        $other = savedDesign($this->tee, token: 'someone-else');

        $this->postJson('/cart/quote', ['design_id' => $other->id, 'items' => [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 1]]])->assertNotFound();
    });
});

describe('names and numbers', function () {
    it('asks for a roster when the design has placeholders', function () {
        $design = savedDesign($this->tee, roles: ROLES);

        $this->get("/designs/{$design->id}/order")->assertOk()->assertSee('One row per person')->assertSee('Import CSV');
    });

    it('saves one item per person with their name, number and size', function () {
        $design = savedDesign($this->tee, roles: ROLES);

        addToCart($design, [
            ['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 1, 'name' => 'Ama', 'number' => '7'],
            ['variant_id' => variantId($this->tee, 'White', 'L'), 'qty' => 1, 'name' => 'Kofi', 'number' => '7'], // duplicate number is allowed
            ['variant_id' => variantId($this->tee, 'White', '2XL'), 'qty' => 1, 'name' => 'Esi', 'number' => '10'],
        ])->assertRedirect('/cart');

        $line = CartLine::first();
        expect($line->quantity())->toBe(3)
            ->and(collect($line->items)->pluck('name')->all())->toBe(['Ama', 'Kofi', 'Esi'])
            ->and(collect($line->items)->pluck('qty')->unique()->all())->toBe([1]);

        $this->get('/cart')->assertSee('With names and numbers (3 people)');
    });

    it('flags blank and over-long names and missing numbers', function () {
        $design = savedDesign($this->tee, roles: ROLES);
        $m = variantId($this->tee, 'White', 'M');

        addToCart($design, [['variant_id' => $m, 'qty' => 1, 'name' => '', 'number' => '1']])->assertSessionHasErrors(['items' => 'Row 1 needs a name.']);
        addToCart($design, [['variant_id' => $m, 'qty' => 1, 'name' => 'Ama', 'number' => '']])->assertSessionHasErrors(['items' => 'Row 1 needs a number.']);
        addToCart($design, [['variant_id' => $m, 'qty' => 1, 'name' => str_repeat('A', 31), 'number' => '1']])->assertSessionHasErrors('items');
        addToCart($design, [['variant_id' => $m, 'qty' => 1, 'name' => 'Ama', 'number' => '12345']])->assertSessionHasErrors('items');

        expect(CartLine::count())->toBe(0);
    });

    it('shows the roster rows count as the quantity for pricing', function () {
        $design = savedDesign($this->tee, roles: ROLES);
        $rows = collect(range(1, 10))->map(fn ($i) => ['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 1, 'name' => "P{$i}", 'number' => (string) $i])->all();

        addToCart($design, $rows);

        $this->get('/cart')->assertSee('10 × GH₵ 48'); // 10 people -> the 10+ tier
    });
});

it('lets a design reused on another product share its group for cross-product tiers', function () {
    $polo = Product::with(['variants.colour', 'colours', 'printAreas'])->where('slug', 'classic-polo-standard')->first();
    $cap = Product::with(['variants.colour', 'colours', 'printAreas'])->where('slug', 'snapback-cap-standard')->first();

    $post = fn (Product $p, array $extra = []) => $this->postJson('/designs', array_replace([
        'product_id' => $p->id, 'colour_id' => $p->colours->first()->id,
        'areas' => [[
            'print_area_id' => $p->printAreas->first()->id,
            'state' => json_encode(['objects' => [['type' => 'i-text', 'text' => 'LOGO', 'fontSize' => 80, 'scaleX' => 1, 'scaleY' => 1]]]),
            'colours_count' => 1,
            'export' => UploadedFile::fake()->createWithContent('p.png', pngBytes(...$p->printAreas->first()->exportPixels())),
        ]],
    ], $extra));

    $first = $post($polo)->assertCreated()->json('design_id');
    $second = $post($cap, ['group_from' => $first])->assertCreated()->json('design_id');
    $stranger = $post($cap, ['group_from' => savedDesign($polo, token: 'other')->id])->assertCreated()->json('design_id');

    expect(Design::find($second)->group_key)->toBe(Design::find($first)->group_key)
        ->and(Design::find($stranger)->group_key)->not->toBe(Design::find($first)->group_key);

    // 30 polos + 20 caps with one logo, priced per design: each at its own 50+ tier.
    app(Settings::class)->set('pricing.tier_scope', 'design');
    addToCart(Design::find($first), [['variant_id' => $polo->variants->first(fn ($v) => $v->size === 'M')->id, 'qty' => 30]]);

    $this->postJson('/cart/quote', ['design_id' => $second, 'items' => [['variant_id' => $cap->variants->first()->id, 'qty' => 20]]])
        ->assertJsonPath('tier_quantity', 50)
        ->assertJsonPath('unit_price', 3200);
});

it('keeps a guest’s cart when they sign in', function () {
    addToCart(savedDesign($this->tee), [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 3]]);
    $user = userWithRole('customer');

    $this->post('/login', ['email' => $user->email, 'password' => 'password']);

    expect(CartLine::first()->user_id)->toBe($user->id);
    $this->get('/cart')->assertSee('White: 3 M');
});
