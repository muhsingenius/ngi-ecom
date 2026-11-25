<?php

use App\Models\Design;
use App\Models\DesignAsset;
use App\Models\Product;
use App\Models\User;
use App\Support\Png;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('designs');
    $this->seed(CatalogueSeeder::class);
    $this->cap = Product::with(['printAreas', 'colours'])->where('slug', 'snapback-cap-standard')->first();
    $this->capArea = $this->cap->printAreas->first(); // embroidery, 10 x 6 cm, max 6 colours, min 5 mm
});

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

it('saves a design with a print-ready 300 DPI file and a preview, privately', function () {
    $this->postJson('/designs', payload($this->cap, $this->capArea, state(text())))->assertCreated()->assertJsonStructure(['design_id', 'next']);

    $design = Design::with('areas')->first();
    $area = $design->areas->first();
    [$w, $h] = $this->capArea->exportPixels();

    expect($design->product_id)->toBe($this->cap->id)
        ->and($design->group_key)->not->toBeEmpty()
        ->and($area->export_dpi)->toBe(300)
        ->and([$area->export_width, $area->export_height])->toBe([$w, $h]);

    $bytes = Storage::disk('designs')->get($area->export_path);
    expect(Png::dpi($bytes))->toBe(300)->and(Png::dimensions($bytes))->toBe([$w, $h]);

    Storage::disk('designs')->assertExists($area->preview_path);
    Storage::disk('public')->assertMissing($area->export_path);
    expect(Png::dimensions(Storage::disk('designs')->get($area->preview_path))[0])->toBeLessThanOrEqual(900);
});

it('skips areas that have nothing on them and refuses a design with nothing at all', function () {
    $this->postJson('/designs', payload($this->cap, $this->capArea, state()))
        ->assertStatus(422)->assertJsonPath('message', 'Add something to your design first.');

    expect(Design::count())->toBe(0);
});

it('rejects a print file that is not a PNG, has the wrong proportions or is too low resolution', function () {
    $this->postJson('/designs', payload($this->cap, $this->capArea, state(text()), png: 'not a png at all'))
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'not a PNG'));

    $this->postJson('/designs', payload($this->cap, $this->capArea, state(text()), png: pngBytes(1181, 1181)))
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'proportions'));

    $this->postJson('/designs', payload($this->cap, $this->capArea, state(text()), png: pngBytes(591, 354)))
        ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'too low resolution'));

    expect(Design::count())->toBe(0);
});

it('rejects an area, product or colour that does not match', function () {
    $other = Product::where('slug', 'classic-polo-standard')->first()->printAreas->first();

    $this->postJson('/designs', payload($this->cap, $other, state(text())))->assertStatus(422);

    $bad = payload($this->cap, $this->capArea, state(text()));
    $bad['colour_id'] = Product::where('slug', 'classic-polo-standard')->first()->colours->first()->id;
    $this->postJson('/designs', $bad)->assertStatus(404);

    $inactive = payload($this->cap, $this->capArea, state(text()));
    $this->cap->update(['active' => false]);
    $this->postJson('/designs', $inactive)->assertStatus(404);
});

describe('server-side print rules', function () {
    it('enforces the embroidery colour limit', function () {
        $body = payload($this->cap, $this->capArea, state(text()), ['areas' => [['colours_count' => 8]]]);

        $this->postJson('/designs', $body)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'at most 6 colours'));
    });

    it('enforces the embroidery minimum text height', function () {
        // 20 px per cm: a 5 mm line is 10 px, so 6 px (3 mm) is too small.
        $this->postJson('/designs', payload($this->cap, $this->capArea, state(text('tiny', 6))))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'too small to embroider'));

        $this->postJson('/designs', payload($this->cap, $this->capArea, state(text('ok', 12))))->assertCreated();
    });

    it('lets name and number placeholders through without counting them as text', function () {
        $this->postJson('/designs', payload($this->cap, $this->capArea, state(text('NAME', 2, extra: ['role' => 'name']), text('OK', 40))))->assertCreated();
    });

    it('enforces the engraving character limit', function () {
        $bracelet = Product::with(['printAreas', 'colours'])->where('slug', 'engraved-bracelet-standard')->first();
        $area = $bracelet->printAreas->first();

        $this->postJson('/designs', payload($bracelet, $area, state(text(str_repeat('A', 31), 20))))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'at most 30 characters'));

        $this->postJson('/designs', payload($bracelet, $area, state(text(str_repeat('A', 30), 20))))->assertCreated();
    });
});

describe('assets in a design', function () {
    it('accepts the visitor’s own uploads and refuses someone else’s', function () {
        $assetId = $this->postJson('/design-assets', ['file' => UploadedFile::fake()->createWithContent('a.png', pngBytes())])->json('id');
        $image = ['type' => 'image', 'assetId' => $assetId, 'src' => 'asset:'.$assetId, 'scaleX' => 1, 'scaleY' => 1];

        $this->postJson('/designs', payload($this->cap, $this->capArea, state($image)))->assertCreated();

        $foreign = DesignAsset::create(['session_token' => 'someone-else', 'path' => 'uploads/x/y.png', 'mime' => 'image/png', 'kind' => 'raster', 'bytes' => 1]);
        $stolen = ['type' => 'image', 'assetId' => $foreign->id, 'src' => 'asset:'.$foreign->id];

        $this->postJson('/designs', payload($this->cap, $this->capArea, state($stolen)))
            ->assertStatus(422)->assertJsonPath('message', 'The design uses an image that is not yours.');
    });
});

describe('ownership', function () {
    beforeEach(function () {
        $this->id = $this->postJson('/designs', payload($this->cap, $this->capArea, state(text())))->json('design_id');
    });

    it('lets the owner view, edit and update', function () {
        $this->get("/designs/{$this->id}")->assertOk()->assertSee('Design saved');
        $this->get("/designs/{$this->id}/edit")->assertOk()->assertSee('designer-config', false);

        $this->putJson("/designs/{$this->id}", payload($this->cap, $this->capArea, state(text('NEW')), ['name' => 'Renamed']))->assertOk();

        expect(Design::find($this->id)->name)->toBe('Renamed')->and(Design::count())->toBe(1);
    });

    it('hides the design from other visitors', function () {
        $this->flushSession();

        $this->get("/designs/{$this->id}")->assertNotFound();
        $this->get("/designs/{$this->id}/edit")->assertNotFound();
        $this->putJson("/designs/{$this->id}", payload($this->cap, $this->capArea, state(text())))->assertNotFound();
    });

    it('moves a guest’s designs and uploads to their account at login', function () {
        $this->postJson('/design-assets', ['file' => UploadedFile::fake()->createWithContent('a.png', pngBytes())]);
        $user = userWithRole('customer');

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        expect(Design::find($this->id)->user_id)->toBe($user->id)
            ->and(Design::find($this->id)->session_token)->toBeNull()
            ->and(DesignAsset::first()->user_id)->toBe($user->id);

        $this->get("/designs/{$this->id}")->assertOk();
    });

    it('keeps a signed-in customer’s designs private from other customers', function () {
        $owner = userWithRole('customer');
        $design = Design::create(['user_id' => $owner->id, 'product_id' => $this->cap->id, 'group_key' => 'g']);

        $this->actingAs(User::factory()->create())->get("/designs/{$design->id}")->assertNotFound();
        $this->actingAs($owner)->get("/designs/{$design->id}")->assertOk();
    });
});

describe('designer page', function () {
    it('renders with everything the script needs', function () {
        $this->get("/products/{$this->cap->slug}/design")
            ->assertOk()
            ->assertSee('designer-config', false)
            ->assertSee('Front panel')
            ->assertSee('Anton')
            ->assertSee('Thread colours');
    });

    it('404s for products that are inactive or not ready', function () {
        $this->cap->update(['active' => false]);
        $this->get("/products/{$this->cap->slug}/design")->assertNotFound();
    });

    it('is offered from the product page with the chosen colour', function () {
        $this->get("/products/{$this->cap->slug}")->assertOk()->assertSee('Start designing')->assertSee('/design?colour=', false);
    });
});

it('writes and reads PNG dimensions and DPI', function () {
    $png = pngBytes(321, 123);

    expect(Png::dimensions($png))->toBe([321, 123])
        ->and(Png::dimensions(Png::withDpi($png, 300)))->toBe([321, 123])
        ->and(Png::dpi(Png::withDpi($png, 300)))->toBe(300)
        ->and(Png::dpi(Png::withDpi(Png::withDpi($png, 72), 300)))->toBe(300)
        ->and(Png::dimensions('nope'))->toBeNull();

    $reencoded = imagecreatefromstring(Png::withDpi($png, 300));
    expect($reencoded)->not->toBeFalse();
});
