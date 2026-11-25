<?php

use App\Models\CartLine;
use App\Models\DeliveryZone;
use App\Models\Design;
use App\Models\DesignArea;
use App\Models\DesignAsset;
use App\Models\Order;
use App\Models\Product;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('designs');
    Mail::fake();
    config(['shop.paystack.secret_key' => 'sk_test_secret']);
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc', 'reference' => 'x']])]);
    DeliveryZone::create(['name' => 'Accra', 'fee_type' => 'flat', 'flat_fee' => 30]);

    $this->seed(CatalogueSeeder::class);
    $this->withSession(['designer_token' => 'guest-token']);
    $this->tee = Product::with(['variants.colour', 'colours', 'printAreas'])->where('slug', 'classic-t-shirt-standard')->first();
});

function age(Design|DesignAsset $model, int $days): void
{
    $model->newQuery()->whereKey($model->getKey())->toBase()->update(['created_at' => now()->subDays($days), 'updated_at' => now()->subDays($days)]);
}

function assetFile(string $token = 'guest-token'): DesignAsset
{
    $path = 'uploads/s/'.Str::uuid().'.png';
    Storage::disk('designs')->put($path, pngBytes(20, 20));

    return DesignAsset::create(['session_token' => $token, 'path' => $path, 'original_name' => 'a.png', 'mime' => 'image/png', 'kind' => 'raster', 'width' => 20, 'height' => 20, 'bytes' => 10]);
}

describe('uploads:cleanup', function () {
    it('removes abandoned guest designs and their files after 30 days', function () {
        $old = savedDesign($this->tee);
        $recent = savedDesign($this->tee);
        age($old, 31);
        age($recent, 5);
        $oldFile = $old->areas()->first()->export_path;

        $this->artisan('uploads:cleanup')->assertSuccessful();

        expect(Design::find($old->id))->toBeNull()->and(Design::find($recent->id))->not->toBeNull();
        Storage::disk('designs')->assertMissing($oldFile);
        Storage::disk('designs')->assertExists($recent->areas()->first()->export_path);
    });

    it('keeps designs that are saved to an account, in a cart, or linked to an order', function () {
        $account = savedDesign($this->tee);
        $account->update(['user_id' => userWithRole('customer')->id, 'session_token' => null]);

        $inCart = savedDesign($this->tee);
        addToCart($inCart, [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 1]]);

        $ordered = releasedOrder(2);
        $orderedDesign = $ordered->lines()->first()->design;

        foreach ([$account, $inCart, $orderedDesign] as $design) {
            age($design, 90);
        }

        $this->artisan('uploads:cleanup')->assertSuccessful();

        expect(Design::find($account->id))->not->toBeNull()->and(Design::find($inCart->id))->not->toBeNull()->and(Design::find($orderedDesign->id))->not->toBeNull();
    });

    it('never touches files that belong to an order', function () {
        $order = releasedOrder(2);
        $line = $order->lines()->first();
        $area = $line->areas()->first();
        age($line->design, 90);

        $this->artisan('uploads:cleanup')->assertSuccessful();

        Storage::disk('designs')->assertExists($area->export_path);
        Storage::disk('designs')->assertExists($area->preview_path);
        expect(Order::find($order->id))->not->toBeNull();
    });

    it('removes old unused uploads but keeps the ones a design still uses', function () {
        $unused = assetFile();
        $usedPlain = assetFile();
        $usedLast = assetFile();
        $usedString = assetFile();
        $recent = assetFile();

        $design = savedDesign($this->tee);
        $design->update(['user_id' => userWithRole('customer')->id, 'session_token' => null]);
        $design->areas()->first()->update(['state' => json_encode(['objects' => [
            ['type' => 'image', 'assetId' => $usedPlain->id, 'src' => 'asset:'.$usedPlain->id],
            ['type' => 'image', 'assetId' => (string) $usedString->id],
            ['assetId' => $usedLast->id],
        ]])]);

        foreach ([$unused, $usedPlain, $usedLast, $usedString] as $asset) {
            age($asset, 40);
        }

        $this->artisan('uploads:cleanup')->assertSuccessful();

        expect(DesignAsset::find($unused->id))->toBeNull()
            ->and(DesignAsset::find($usedPlain->id))->not->toBeNull()
            ->and(DesignAsset::find($usedLast->id))->not->toBeNull()
            ->and(DesignAsset::find($usedString->id))->not->toBeNull()
            ->and(DesignAsset::find($recent->id))->not->toBeNull();
        Storage::disk('designs')->assertMissing($unused->path);
        Storage::disk('designs')->assertExists($usedPlain->path);
    });

    it('does not mistake asset 12 for asset 120', function () {
        $asset = assetFile();
        age($asset, 40);
        $design = savedDesign($this->tee);
        $design->update(['user_id' => userWithRole('customer')->id, 'session_token' => null]);
        $design->areas()->first()->update(['state' => json_encode(['objects' => [['assetId' => $asset->id * 10 + 7]]])]);

        $this->artisan('uploads:cleanup')->assertSuccessful();

        expect(DesignAsset::find($asset->id))->toBeNull();
    });

    it('frees an upload that only an abandoned design used, in the same run', function () {
        $asset = assetFile();
        $design = savedDesign($this->tee);
        $design->areas()->first()->update(['state' => json_encode(['objects' => [['assetId' => $asset->id]]])]);
        age($design, 40);
        age($asset, 40);

        $this->artisan('uploads:cleanup')->assertSuccessful();

        expect(Design::find($design->id))->toBeNull()->and(DesignAsset::find($asset->id))->toBeNull();
    });

    it('only reports with --pretend and honours --days', function () {
        $design = savedDesign($this->tee);
        $asset = assetFile();
        age($design, 31);
        age($asset, 31);

        $this->artisan('uploads:cleanup --pretend')->expectsOutputToContain('Would remove 1 abandoned design(s) and 1 unused upload(s)')->assertSuccessful();
        expect(Design::count())->toBe(1)->and(DesignAsset::count())->toBe(1);

        $this->artisan('uploads:cleanup --days=60')->assertSuccessful();
        expect(Design::count())->toBe(1)->and(DesignAsset::count())->toBe(1);

        $this->artisan('uploads:cleanup --days=7')->assertSuccessful();
        expect(Design::count())->toBe(0)->and(DesignAsset::count())->toBe(0);
    });

    it('runs daily from the scheduler', function () {
        $this->artisan('schedule:list')->expectsOutputToContain('uploads:cleanup')->assertSuccessful();
    });

    it('does not delete designs belonging to a live cart line', function () {
        $design = savedDesign($this->tee);
        CartLine::create(['session_token' => 'x', 'design_id' => $design->id, 'product_id' => $this->tee->id, 'items' => []]);
        age($design, 100);

        $this->artisan('uploads:cleanup');

        expect(Design::find($design->id))->not->toBeNull();
        expect(DesignArea::where('design_id', $design->id)->count())->toBe(1);
    });
});

describe('security headers', function () {
    it('are sent on every page', function () {
        $this->get('/')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')->assertHeader('Permissions-Policy');
    });

    it('add HSTS only over HTTPS', function () {
        $this->get('http://localhost/')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');
    });
});

describe('error pages', function () {
    it('are branded and helpful', function () {
        $this->get('/no-such-page')->assertNotFound()->assertSee("We can't find that page")->assertSee('Go to the home page');

        $this->actingAs(userWithRole('customer'))->get('/admin')->assertForbidden()->assertSee("You don't have access to this page");
    });
});

it('offers a way past the navigation for keyboard users', function () {
    $this->get('/')->assertSee('Skip to content')->assertSee('id="main"', false);
});
