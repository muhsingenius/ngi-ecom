<?php

use App\Models\Mockup;
use App\Models\PrintArea;
use App\Models\Product;
use App\Support\DemoPhotos;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(CatalogueSeeder::class);
});

it('uses the supplied photos for the polo, premium tee and bracelet', function () {
    expect(Product::where('slug', 'classic-polo-standard')->value('image'))->toBe('media/polo.webp')
        ->and(Product::where('slug', 'classic-t-shirt-premium')->value('image'))->toBe('media/premium_tshirt.webp')
        ->and(Product::where('slug', 'engraved-bracelet-standard')->value('image'))->toBe('media/bracelet.webp');

    foreach (['polo.webp', 'premium_tshirt.webp', 'bracelet.webp'] as $file) {
        expect(public_path("media/{$file}"))->toBeFile();
    }

    $this->get('/products/classic-polo-standard')->assertOk()->assertSee('media\/polo.webp', false);
    $this->get('/products/classic-t-shirt-premium')->assertOk()->assertSee('media\/premium_tshirt.webp', false);
});

it('offers the colours shown in the photos', function () {
    $active = fn (string $slug) => Product::where('slug', $slug)->first()->colours()->where('active', true)->orderBy('sort')->pluck('name')->all();

    expect($active('classic-t-shirt-premium'))->toBe(['White', 'Black', 'Grey'])
        ->and($active('engraved-bracelet-standard'))->toBe(['Black'])
        ->and($active('classic-polo-standard'))->toContain('Navy')->not->toContain('Red');
});

it('builds a photo mockup of the tee and polo in every colour', function () {
    foreach (['classic-t-shirt-standard', 'classic-polo-standard'] as $slug) {
        $product = Product::where('slug', $slug)->first();
        $front = $product->printAreas()->orderBy('sort')->first();

        foreach ($product->colours as $colour) {
            $mockup = $colour->mockups()->where('print_area_id', $front->id)->first();

            expect($mockup->path)->toEndWith('-photo.png');
            Storage::disk('public')->assertExists($mockup->path);
        }
    }
});

it('lets the tee be printed on the left and the right sleeve', function () {
    $tee = Product::where('slug', 'classic-t-shirt-standard')->first();
    $slugs = $tee->printAreas()->orderBy('sort')->pluck('slug')->all();

    expect($slugs)->toContain('left-sleeve', 'right-sleeve');

    $left = $tee->printAreas()->where('slug', 'left-sleeve')->first();
    $right = $tee->printAreas()->where('slug', 'right-sleeve')->first();

    expect($right->width_cm)->toEqual($left->width_cm)
        ->and($right->extra_fee)->toBe($left->extra_fee)
        ->and($right->box_x)->toBeLessThan($left->box_x);

    foreach ($tee->colours as $colour) {
        expect($colour->mockups()->where('print_area_id', $right->id)->exists())->toBeTrue();
    }
});

it('applying the photos again changes nothing', function () {
    $before = [Product::count(), PrintArea::count(), Mockup::count()];

    DemoPhotos::apply();

    expect([Product::count(), PrintArea::count(), Mockup::count()])->toBe($before);
});

it('gives the designer chest, middle and bottom placements on the front and back only', function () {
    $this->get('/products/classic-t-shirt-standard/design')->assertOk()
        ->assertSee('Right sleeve')
        ->assertSee('Place on the')
        ->assertSee('"label":"Chest"', false)->assertSee('"label":"Bottom"', false)
        ->assertSee('"label":"Upper back"', false)->assertSee('"label":"Lower back"', false);

    $html = $this->get('/products/classic-t-shirt-standard/design')->getContent();
    preg_match('#<script type="application/json" id="designer-config">(.*?)</script>#s', $html, $m);
    $areas = collect(json_decode($m[1], true)['areas'])->keyBy('slug');

    expect($areas['front']['zones'])->toHaveCount(3)
        ->and($areas['back']['zones'])->toHaveCount(3)
        ->and($areas['left-sleeve']['zones'])->toBeNull()
        ->and($areas['right-sleeve']['zones'])->toBeNull();
});
