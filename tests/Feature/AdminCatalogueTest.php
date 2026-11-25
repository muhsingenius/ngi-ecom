<?php

use App\Models\Mockup;
use App\Models\Product;
use App\Services\Settings;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->admin = userWithRole('admin');
    $this->actingAs($this->admin);
});

it('is admin only', function () {
    auth()->logout();
    $this->get('/admin/products')->assertRedirect(route('login'));

    $this->actingAs(userWithRole('customer'))->get('/admin/products')->assertForbidden();
});

it('creates a product that stays hidden until it is ready', function () {
    $this->post('/admin/products', ['type' => 'polo', 'name' => 'Test polo', 'quality' => 'Standard', 'price' => 70])
        ->assertRedirect();

    $product = Product::where('name', 'Test polo')->first();

    expect($product->active)->toBeFalse()
        ->and($product->tiers)->toHaveCount(1)
        ->and($product->singlePrice())->toBe(70.0);

    $this->put(route('admin.products.update', $product), [
        'type' => 'polo', 'name' => 'Test polo', 'quality' => 'Standard', 'included_print_areas' => 1, 'active' => 1,
        'tiers' => [['min_qty' => 1, 'unit_price' => 70]],
    ])->assertSessionHasErrors('active');
});

it('saves price tiers and requires the first to start at 1', function () {
    $this->seed(CatalogueSeeder::class);
    $product = Product::where('slug', 'classic-t-shirt-standard')->first();

    $payload = fn (array $tiers) => [
        'type' => 'tshirt', 'name' => $product->name, 'quality' => 'Standard', 'included_print_areas' => 1, 'active' => 1,
        'tiers' => $tiers,
    ];

    $this->put(route('admin.products.update', $product), $payload([['min_qty' => 5, 'unit_price' => 50]]))
        ->assertSessionHasErrors('tiers');

    $this->put(route('admin.products.update', $product), $payload([
        ['min_qty' => 20, 'unit_price' => 40], ['min_qty' => 1, 'unit_price' => 60], ['min_qty' => 20, 'unit_price' => 99],
    ]))->assertSessionHasNoErrors();

    $tiers = $product->fresh()->tiers;
    expect($tiers->pluck('min_qty')->all())->toBe([1, 20])
        ->and((float) $tiers->first()->unit_price)->toBe(60.0);
});

it('adds a colour with a variant for every existing size', function () {
    $this->seed(CatalogueSeeder::class);
    $product = Product::where('slug', 'classic-t-shirt-standard')->first();
    $before = $product->variants()->count();

    $this->post(route('admin.colours.store', $product), ['name' => 'Gold', 'hex' => '#D4AF37'])->assertSessionHasNoErrors();

    expect($product->variants()->count())->toBe($before + 6);
});

it('adds a size to every colour and updates its surcharge everywhere', function () {
    $this->seed(CatalogueSeeder::class);
    $product = Product::where('slug', 'classic-t-shirt-standard')->first();

    $this->post(route('admin.sizes.store', $product), ['size' => '4XL', 'size_surcharge' => 10])->assertSessionHasNoErrors();
    expect($product->variants()->where('size', '4XL')->count())->toBe(6);

    $this->put(route('admin.sizes.update', $product), ['size' => '4XL', 'size_surcharge' => 12]);
    expect($product->variants()->where('size', '4XL')->pluck('size_surcharge')->unique()->map(fn ($v) => (float) $v)->all())->toBe([12.0]);

    $this->post(route('admin.sizes.store', $product), ['size' => '4XL'])->assertSessionHasErrors('size');
});

it('switches variants on and off', function () {
    $this->seed(CatalogueSeeder::class);
    $product = Product::where('slug', 'snapback-cap-standard')->first();
    $keep = $product->variants()->first();

    $this->put(route('admin.variants.update', $product), ['variants' => [$keep->id => 1]]);

    expect($product->variants()->where('active', true)->pluck('id')->all())->toBe([$keep->id]);
});

it('manages print areas', function () {
    $this->seed(CatalogueSeeder::class);
    $product = Product::where('slug', 'classic-t-shirt-standard')->first();

    $this->post(route('admin.areas.store', $product), [
        'name' => 'Right sleeve', 'width_cm' => 8, 'height_cm' => 8, 'method' => 'dtf', 'extra_fee' => 8,
        'box_x' => 80, 'box_y' => 22, 'box_w' => 14, 'box_h' => 14, 'active' => 1,
    ])->assertSessionHasNoErrors();

    $area = $product->printAreas()->where('slug', 'right-sleeve')->first();
    expect($area)->not->toBeNull()
        ->and($area->mockups)->toHaveCount(6)
        ->and($area->exportPixels())->toBe([945, 945]);

    $this->post(route('admin.areas.store', $product), ['name' => 'Bad', 'width_cm' => 8])->assertSessionHasErrors(['height_cm', 'method']);

    $this->delete(route('admin.areas.destroy', $area))->assertRedirect();
    expect($product->printAreas()->where('slug', 'right-sleeve')->exists())->toBeFalse();
});

it('uploads and replaces a mockup, removing the old file', function () {
    $this->seed(CatalogueSeeder::class);
    $product = Product::where('slug', 'classic-t-shirt-premium')->first();
    $colour = $product->colours()->first();
    $area = $product->printAreas()->first();

    $this->post(route('admin.mockups.store', $colour), [
        'print_area_id' => $area->id, 'image' => UploadedFile::fake()->image('front.png', 800, 1000),
    ])->assertSessionHasNoErrors();

    $mockup = Mockup::where('product_colour_id', $colour->id)->where('print_area_id', $area->id)->first();
    Storage::disk('public')->assertExists($mockup->path);
    $first = $mockup->path;

    $this->post(route('admin.mockups.store', $colour), [
        'print_area_id' => $area->id, 'image' => UploadedFile::fake()->image('front2.png', 800, 1000),
    ]);

    expect($mockup->fresh()->path)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
});

it('rejects a mockup that is not an image', function () {
    $this->seed(CatalogueSeeder::class);
    $product = Product::where('slug', 'classic-t-shirt-premium')->first();

    $this->post(route('admin.mockups.store', $product->colours()->first()), [
        'print_area_id' => $product->printAreas()->first()->id,
        'image' => UploadedFile::fake()->create('evil.php', 10, 'text/x-php'),
    ])->assertSessionHasErrors('image');
});

describe('settings page', function () {
    it('shows every setting and saves valid values', function () {
        $this->get('/admin/settings')->assertOk()->assertSee('Proof threshold')->assertSee('Quantity price tier scope');

        $this->put('/admin/settings', [
            'pricing_tier_scope' => 'order', 'proof_threshold' => '', 'proof_timing' => 'proof_then_pay',
            'proof_max_revisions' => '2', 'proof_unpaid_expiry_days' => '10', 'delivery_free_threshold' => '500',
            'shop_notification_email' => 'shop@example.test', 'orders_turnaround_days' => '4',
        ])->assertSessionHasNoErrors();

        $settings = app(Settings::class);
        expect($settings->string('pricing.tier_scope'))->toBe('order')
            ->and($settings->int('proof.threshold'))->toBeNull()
            ->and($settings->string('proof.timing'))->toBe('proof_then_pay')
            ->and($settings->float('delivery.free_threshold'))->toBe(500.0)
            ->and($settings->int('orders.turnaround_days'))->toBe(4);
    });

    it('rejects invalid values with a readable message', function () {
        $this->put('/admin/settings', ['proof_timing' => 'whenever', 'proof_threshold' => '-3'])
            ->assertSessionHasErrors(['proof_timing', 'proof_threshold']);

        expect(session('errors')->first('proof_threshold'))->toContain('Proof threshold (units)');
    });
});

it('renders the product pages in the admin', function () {
    $this->seed(CatalogueSeeder::class);
    $product = Product::where('slug', 'classic-t-shirt-standard')->first();

    $this->get('/admin/products')->assertOk()->assertSee('Classic T-shirt');
    $this->get('/admin/products/create')->assertOk();
    $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('Print areas')->assertSee('Mockups')->assertSee('Price tiers');
});
