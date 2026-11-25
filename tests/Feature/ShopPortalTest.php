<?php

use App\Enums\OrderStatus;
use App\Mail\OrderUpdateMail;
use App\Models\DeliveryZone;
use App\Models\DesignAsset;
use App\Models\Order;
use App\Models\OrderArea;
use App\Models\Payment;
use App\Models\PickupPoint;
use App\Models\Product;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('designs');
    Mail::fake();
    config(['shop.paystack.secret_key' => 'sk_test_secret']);
    Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc', 'reference' => 'x']])]);

    $this->seed(CatalogueSeeder::class);
    $this->withSession(['designer_token' => 'guest-token']);
    DeliveryZone::create(['name' => 'Accra', 'fee_type' => 'flat', 'flat_fee' => 30]);
    $this->pickup = PickupPoint::create(['name' => 'Our shop', 'address' => '1 Main Street', 'hours' => '9-6']);
});

describe('access', function () {
    it('is for the print shop role only', function () {
        $this->get('/shop')->assertRedirect(route('login'));
        $this->actingAs(userWithRole('customer'))->get('/shop')->assertForbidden();
        $this->actingAs(userWithRole('admin'))->get('/shop')->assertForbidden();
        $this->actingAs(userWithRole('shop'))->get('/shop')->assertOk()->assertSee('Job list');
    });

    it('protects every job route', function () {
        $order = releasedOrder();
        $area = OrderArea::first();

        foreach (['', '/sheet', '/files', "/files/{$area->id}", "/placement/{$area->id}"] as $path) {
            $this->get("/shop/jobs/{$order->number}{$path}")->assertRedirect(route('login'));
        }

        $this->actingAs(userWithRole('customer'));
        foreach (['', '/sheet', '/files', "/files/{$area->id}", "/placement/{$area->id}"] as $path) {
            $this->get("/shop/jobs/{$order->number}{$path}")->assertForbidden();
        }

        $this->post("/shop/jobs/{$order->number}/stage", ['stage' => 'ready'])->assertForbidden();
    });
});

describe('job list', function () {
    beforeEach(fn () => $this->actingAs(userWithRole('shop')));

    it('shows only orders released for production', function () {
        $released = releasedOrder();

        cartWith(2);
        $this->post('/checkout', checkoutData());
        $unpaid = Order::latest('id')->first();

        $this->get('/shop')->assertOk()->assertSee($released->number)->assertDontSee($unpaid->number);
        $this->get("/shop/jobs/{$unpaid->number}")->assertNotFound();
    });

    it('holds back orders that are paid but still waiting for a proof', function () {
        $order = releasedOrder(25);

        expect($order->status)->toBe(OrderStatus::AwaitingProof);
        $this->get('/shop')->assertDontSee($order->number);
        $this->get("/shop/jobs/{$order->number}")->assertNotFound();
    });

    it('filters by stage and lists finished jobs separately', function () {
        $queued = releasedOrder();
        $printing = releasedOrder(2);
        $printing->forceFill(['production_stage' => 'printing'])->save();
        $ready = releasedOrder(4);
        $ready->forceFill(['status' => OrderStatus::Ready, 'production_stage' => 'ready'])->save();
        $done = releasedOrder(5);
        $done->forceFill(['status' => OrderStatus::Dispatched])->save();

        $this->get('/shop?filter=queued')->assertSee($queued->number)->assertDontSee($printing->number)->assertDontSee($ready->number);
        $this->get('/shop?filter=printing')->assertSee($printing->number)->assertDontSee($queued->number);
        $this->get('/shop?filter=ready')->assertSee($ready->number)->assertDontSee($queued->number);
        $this->get('/shop?filter=done')->assertSee($done->number)->assertDontSee($queued->number);
        $this->get('/shop')->assertSee($queued->number)->assertSee($printing->number)->assertDontSee($ready->number)->assertDontSee($done->number);
        $this->get('/shop?filter=nonsense')->assertOk()->assertSee($queued->number);
    });

    it('orders jobs by due date and marks overdue ones', function () {
        $later = releasedOrder();
        $later->update(['due_date' => now()->addDays(9)]);
        $soon = releasedOrder(2);
        $soon->update(['due_date' => now()->subDays(2)]);

        $this->get('/shop')->assertSeeInOrder([$soon->number, $later->number])->assertSee('overdue');
    });
});

describe('job detail', function () {
    beforeEach(fn () => $this->actingAs(userWithRole('shop')));

    it('shows everything needed to print and nothing about money', function () {
        $order = releasedOrder(3);
        $order->update(['notes' => 'Please use the soft hand feel']);

        $this->get("/shop/jobs/{$order->number}")
            ->assertOk()
            ->assertSee('Ama Mensah')->assertSee('024 123 4567')
            ->assertSee('Accra')->assertSee('12 Palm Street')
            ->assertSee('3 × M')->assertSee('White')
            ->assertSee('Front')->assertSee('DTF')->assertSee('300 DPI')
            ->assertSee('Please use the soft hand feel')
            ->assertSee('Download all print files')->assertSee('Job sheet (PDF)')
            ->assertDontSee('GH₵')->assertDontSee('Total');
    });

    it('shows the roster and name/number placement for personalised jobs', function () {
        $tee = Product::with(['variants.colour', 'colours', 'printAreas'])->where('slug', 'classic-t-shirt-standard')->first();
        addToCart(savedDesign($tee, roles: ROLES), [
            ['variant_id' => variantId($tee, 'White', 'M'), 'qty' => 1, 'name' => 'Kwame', 'number' => '9'],
            ['variant_id' => variantId($tee, 'White', 'L'), 'qty' => 1, 'name' => 'Esi', 'number' => '10'],
        ]);
        $this->post('/checkout', checkoutData());
        $order = Order::latest('id')->first();
        webhook(chargeSuccess(Payment::where('order_id', $order->id)->first()));

        $this->get("/shop/jobs/{$order->number}")->assertSee('Names and numbers (2)')->assertSee('Kwame')->assertSee('Esi')->assertSee('Anton, 30 mm');
    });

    it('serves the placement image and individual print files', function () {
        $order = releasedOrder();
        $area = $order->lines()->first()->areas()->first();

        $this->get("/shop/jobs/{$order->number}/placement/{$area->id}")->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->get("/shop/jobs/{$order->number}/files/{$area->id}")->assertOk()->assertDownload("{$order->number}-front-300dpi-100x100.png");
    });

    it('will not serve another order’s files through this job', function () {
        $one = releasedOrder();
        $two = releasedOrder(2);
        $foreignArea = $two->lines()->first()->areas()->first();

        $this->get("/shop/jobs/{$one->number}/files/{$foreignArea->id}")->assertNotFound();
        $this->get("/shop/jobs/{$one->number}/placement/{$foreignArea->id}")->assertNotFound();
    });
});

describe('status updates', function () {
    beforeEach(fn () => $this->actingAs(userWithRole('shop')));

    it('moves a job from queued to printing to ready', function () {
        $order = releasedOrder();
        expect($order->production_stage)->toBe('queued');

        $this->post("/shop/jobs/{$order->number}/stage", ['stage' => 'printing'])->assertRedirect();
        expect($order->fresh()->production_stage)->toBe('printing')->and($order->fresh()->status)->toBe(OrderStatus::InProduction);

        $this->post("/shop/jobs/{$order->number}/stage", ['stage' => 'ready'])->assertRedirect();
        expect($order->fresh()->status)->toBe(OrderStatus::Ready)->and($order->fresh()->production_stage)->toBe('ready');
        expect($order->events()->where('type', 'ready')->exists())->toBeTrue();
    });

    it('emails the customer when a delivery job is ready, and once only', function () {
        $order = releasedOrder();
        Mail::fake(); // ignore the earlier emails

        $this->post("/shop/jobs/{$order->number}/stage", ['stage' => 'ready']);
        $this->post("/shop/jobs/{$order->number}/stage", ['stage' => 'ready']);

        Mail::assertQueued(OrderUpdateMail::class, 1);
        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->hasTo('ama@example.test') && $m->subjectLine === 'Your order is ready' && str_contains(implode(' ', $m->lines), 'dispatch it to Accra'));
    });

    it('tells a pickup customer where to collect', function () {
        $order = releasedOrder(3, ['fulfilment' => 'pickup', 'zone_id' => null, 'address' => null, 'pickup_point_id' => $this->pickup->id]);
        Mail::fake();

        $this->post("/shop/jobs/{$order->number}/stage", ['stage' => 'ready']);

        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => str_contains(implode(' ', $m->lines), 'Our shop, 1 Main Street'));
    });

    it('lets a job step back without emailing the customer again', function () {
        $order = releasedOrder();
        $this->post("/shop/jobs/{$order->number}/stage", ['stage' => 'ready']);
        Mail::fake();

        $this->post("/shop/jobs/{$order->number}/stage", ['stage' => 'printing']);

        expect($order->fresh()->status)->toBe(OrderStatus::InProduction)->and($order->fresh()->production_stage)->toBe('printing');
        Mail::assertNothingQueued();
    });

    it('rejects unknown stages', function () {
        $order = releasedOrder();

        $this->post("/shop/jobs/{$order->number}/stage", ['stage' => 'dispatched'])->assertSessionHasErrors('stage');
        expect($order->fresh()->production_stage)->toBe('queued');
    });
});

describe('job sheet and files', function () {
    beforeEach(fn () => $this->actingAs(userWithRole('shop')));

    it('produces a PDF job sheet', function () {
        $order = releasedOrder();

        $res = $this->get("/shop/jobs/{$order->number}/sheet")->assertOk()->assertHeader('content-type', 'application/pdf');

        expect(str_starts_with($res->getContent(), '%PDF'))->toBeTrue()->and(strlen($res->getContent()))->toBeGreaterThan(2000);
        expect($res->headers->get('content-disposition'))->toContain("{$order->number}-job-sheet.pdf");
    });

    it('lays out the sheet with the roster, notes and no prices', function () {
        $order = releasedOrder(3);
        $order->update(['notes' => 'Fold, do not hang']);
        $order->lines()->first()->items()->update(['name' => 'Kwame', 'number' => '9']);

        $html = view('shop.sheet', ['order' => $order->fresh()->load(['lines.items', 'lines.areas']), 'images' => []])->with('images', collect(OrderArea::pluck('id'))->mapWithKeys(fn ($id) => [$id => 'data:image/jpeg;base64,AAAA'])->all())->render();

        expect($html)->toContain('Job sheet '.$order->number)->toContain('Ama Mensah')->toContain('Fold, do not hang')->toContain('Kwame')->toContain('3 x M')
            ->not->toContain('GH₵')->not->toContain('Total');
    });

    it('bundles the print files, originals, roster and job sheet in a ZIP', function () {
        $tee = Product::with(['variants.colour', 'colours', 'printAreas'])->where('slug', 'classic-t-shirt-standard')->first();
        $design = savedDesign($tee, roles: ROLES);
        $asset = DesignAsset::create(['session_token' => 'guest-token', 'path' => 'uploads/s/logo.png', 'original_name' => 'logo.png', 'mime' => 'image/png', 'kind' => 'raster', 'width' => 100, 'height' => 100, 'bytes' => 10]);
        Storage::disk('designs')->put('uploads/s/logo.png', pngBytes(100, 100));
        $design->areas()->first()->update(['state' => json_encode(['objects' => [['type' => 'image', 'assetId' => $asset->id]]])]);
        addToCart($design, [['variant_id' => variantId($tee, 'White', 'M'), 'qty' => 1, 'name' => 'Kwame', 'number' => '9']]);
        $this->post('/checkout', checkoutData());
        $order = Order::latest('id')->first();
        webhook(chargeSuccess(Payment::where('order_id', $order->id)->first()));

        $res = $this->get("/shop/jobs/{$order->number}/files")->assertOk();
        $zip = new ZipArchive;
        expect($zip->open($res->baseResponse->getFile()->getPathname()))->toBeTrue();

        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        expect($names->contains("{$order->number}/job-sheet.pdf"))->toBeTrue()
            ->and($names->contains(fn ($n) => str_ends_with($n, '/front-300dpi-100x100.png')))->toBeTrue()
            ->and($names->contains(fn ($n) => str_contains($n, '/originals/') && str_ends_with($n, 'logo.png')))->toBeTrue();

        $roster = $names->first(fn ($n) => str_ends_with($n, 'roster.csv'));
        expect($zip->getFromName($roster))->toContain('"Kwame","9","M","White"');
        $zip->close();
    });
});
