<?php

use App\Models\Product;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(CatalogueSeeder::class);
});

it('lists active products with prices up front', function () {
    $this->get('/products')
        ->assertOk()
        ->assertSee('Classic T-shirt')
        ->assertSee('GH₵ 55')
        ->assertSee('down to GH₵ 34 in bulk');
});

it('filters the catalogue by product type', function () {
    $this->get('/products?type=cap')->assertOk()->assertSee('Snapback cap')->assertDontSee('Classic polo');
});

it('hides inactive products and 404s their page', function () {
    $product = Product::where('slug', 'classic-polo-standard')->first();
    $product->update(['active' => false]);

    $this->get('/products')->assertDontSee('Classic polo');
    $this->get('/products/'.$product->slug)->assertNotFound();
});

it('shows the full tier table, savings and what the price includes on the product page', function () {
    $this->get('/products/classic-t-shirt-standard')
        ->assertOk()
        ->assertSee('Bulk prices')
        ->assertSee('1–9')
        ->assertSee('100+')
        ->assertSee('GH₵ 34')
        ->assertSee('off each')
        ->assertSee('One print area of your choice')
        ->assertSee('Sizes and colours count together')
        ->assertSee('2XL +GH₵ 5');
});

it('shows the qualities of one product as separate priced items', function () {
    $this->get('/products?type=tshirt')->assertSee('Standard')->assertSee('Premium')->assertSee('GH₵ 75');
});

it('seeds mockups for every colour and print area', function () {
    $product = Product::with('colours.mockups', 'printAreas')->where('slug', 'classic-t-shirt-standard')->first();

    expect($product->colours)->toHaveCount(6)
        ->and($product->colours->first()->mockups)->toHaveCount($product->printAreas->count());

    Storage::disk('public')->assertExists($product->colours->first()->mockups->first()->path);
});

it('does not reseed when products already exist', function () {
    $count = Product::count();
    $this->seed(CatalogueSeeder::class);

    expect(Product::count())->toBe($count);
});
