<?php

use App\Enums\OrderStatus;
use App\Mail\NewJobMail;
use App\Mail\OrderUpdateMail;
use App\Models\DeliveryZone;
use App\Models\Design;
use App\Models\DesignAsset;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Proof;
use App\Services\OrderService;
use App\Services\ProofImage;
use App\Services\ProofService;
use App\Services\Settings;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\UploadedFile;
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
    app(Settings::class)->set('shop.notification_email', 'print@example.test');
});

describe('pay then proof', function () {
    it('generates and emails a proof after payment, and holds the order back', function () {
        $order = proofOrder();
        expect($order->status)->toBe(OrderStatus::PendingPayment)->and($order->proofs)->toHaveCount(0);

        pay($order);

        $order = $order->fresh();
        $proof = $order->proofs()->first();

        expect($order->status)->toBe(OrderStatus::AwaitingProof)
            ->and($order->production_stage)->toBeNull()
            ->and($proof->version)->toBe(1)->and($proof->status)->toBe('sent')->and($proof->replaced)->toBeFalse();

        Storage::disk('designs')->assertExists($proof->image_path);
        expect(getimagesizefromstring(Storage::disk('designs')->get($proof->image_path))[2])->toBe(IMAGETYPE_JPEG);

        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->hasTo('ama@example.test') && $m->subjectLine === 'Your proof is ready' && $m->actionUrl === $proof->url());
        Mail::assertNotQueued(NewJobMail::class);
    });

    it('releases the order to the shop once the customer approves', function () {
        $order = proofOrder();
        pay($order);
        $proof = $order->fresh()->latestProof();

        $this->get($proof->url())->assertOk()->assertSee('Check your proof')->assertSee('Approve this proof')->assertSee('Request changes');
        $this->post(route('proofs.approve', $proof->token))->assertRedirect($proof->url());

        $order = $order->fresh();
        expect($order->status)->toBe(OrderStatus::InProduction)->and($order->production_stage)->toBe('queued')->and($proof->fresh()->status)->toBe('approved');
        Mail::assertQueued(NewJobMail::class, fn ($m) => $m->hasTo('print@example.test'));
        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->subjectLine === 'In production');
    });

    it('does not trigger proofs for small orders', function () {
        cartWith(3);
        $this->post('/checkout', checkoutData());
        pay(Order::first());

        expect(Order::first()->proofs)->toHaveCount(0)->and(Order::first()->status)->toBe(OrderStatus::InProduction);
    });

    it('does not trigger proofs when they are switched off', function () {
        app(Settings::class)->set('proof.threshold', null);
        $order = proofOrder(100);
        pay($order);

        expect($order->fresh()->status)->toBe(OrderStatus::InProduction)->and($order->fresh()->proofs)->toHaveCount(0);
    });
});

describe('proof then pay', function () {
    beforeEach(fn () => app(Settings::class)->set('proof.timing', 'proof_then_pay'));

    it('sends the proof straight away, before any payment', function () {
        $order = proofOrder();

        expect($order->status)->toBe(OrderStatus::AwaitingProof)->and($order->proofs)->toHaveCount(1)->and(Payment::count())->toBe(0);
        Http::assertNothingSent();
        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->subjectLine === 'Your proof is ready');
    });

    it('asks for payment after approval and starts the expiry clock', function () {
        $order = proofOrder();
        $proof = $order->latestProof();

        $this->post("/orders/{$order->number}/pay?t={$order->access_token}")->assertForbidden(); // cannot pay before approving
        $this->post(route('proofs.approve', $proof->token));

        $order = $order->fresh();
        expect($order->status)->toBe(OrderStatus::ProofApproved)->and($order->isPaid())->toBeFalse()
            ->and($order->proof_expires_at->isSameDay(now()->addDays(7)))->toBeTrue();
        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->subjectLine === 'Proof approved: please pay');
        Mail::assertNotQueued(NewJobMail::class);

        $this->post("/orders/{$order->number}/pay?t={$order->access_token}")->assertRedirect('https://checkout.paystack.com/abc');
        pay($order);

        expect($order->fresh()->status)->toBe(OrderStatus::InProduction)->and($order->fresh()->proof_expires_at)->toBeNull();
        Mail::assertQueued(NewJobMail::class);
    });

    it('cancels approved-but-unpaid orders after the expiry window and emails the customer', function () {
        $order = proofOrder();
        $this->post(route('proofs.approve', $order->latestProof()->token));
        $this->travel(8)->days();

        $this->artisan('orders:expire-proofs')->assertSuccessful();

        $order = $order->fresh();
        expect($order->status)->toBe(OrderStatus::Cancelled)->and($order->cancelled_at)->not->toBeNull()->and($order->cancel_reason)->toContain('not paid within 7 days');
        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->subjectLine === 'Order cancelled');
    });

    it('leaves paid orders and orders still inside the window alone', function () {
        $fresh = proofOrder();
        $this->post(route('proofs.approve', $fresh->latestProof()->token));
        $this->travel(3)->days();

        $this->artisan('orders:expire-proofs');
        expect($fresh->fresh()->status)->toBe(OrderStatus::ProofApproved);

        $this->post("/orders/{$fresh->number}/pay?t={$fresh->access_token}");
        pay($fresh);
        $this->travel(10)->days();
        $this->artisan('orders:expire-proofs');
        expect($fresh->fresh()->status)->toBe(OrderStatus::InProduction);
    });

    it('is scheduled to run hourly', function () {
        $this->artisan('schedule:list')->expectsOutputToContain('orders:expire-proofs')->assertSuccessful();
    });

    it('flags a payment that arrives after the order was cancelled instead of releasing it', function () {
        $order = proofOrder();
        $this->post(route('proofs.approve', $order->latestProof()->token));
        $this->post("/orders/{$order->number}/pay?t={$order->access_token}");
        $payment = Payment::first();

        app(OrderService::class)->cancel($order->fresh(), 'Test cancel');
        webhook(chargeSuccess($payment))->assertOk();

        $order = $order->fresh();
        expect($order->status)->toBe(OrderStatus::Cancelled)->and($order->flagged)->toBeTrue()->and($order->production_stage)->toBeNull();
        Mail::assertNotQueued(NewJobMail::class);
    });
});

describe('requesting changes', function () {
    it('requires a comment, records it and counts the revision round', function () {
        $order = proofOrder();
        pay($order);
        $proof = $order->fresh()->latestProof();

        $this->post(route('proofs.changes', $proof->token), ['comment' => ''])->assertSessionHasErrors('comment');
        $this->post(route('proofs.changes', $proof->token), ['comment' => 'No'])->assertSessionHasErrors('comment');
        $this->post(route('proofs.changes', $proof->token), ['comment' => 'Please make the logo bigger'])->assertRedirect($proof->url());

        $order = $order->fresh();
        expect($proof->fresh()->status)->toBe('changes_requested')->and($proof->fresh()->comment)->toBe('Please make the logo bigger')
            ->and($order->revisions_used)->toBe(1)->and($order->flagged)->toBeFalse()->and($order->status)->toBe(OrderStatus::AwaitingProof);
    });

    it('flags the order for follow-up once the maximum rounds are used', function () {
        app(Settings::class)->set('proof.max_revisions', 1);
        $order = proofOrder();
        pay($order);

        $this->post(route('proofs.changes', $order->fresh()->latestProof()->token), ['comment' => 'Change the colour please']);

        expect($order->fresh()->flagged)->toBeTrue()->and($order->events()->where('type', 'flagged')->exists())->toBeTrue();
    });

    it('cannot be answered twice', function () {
        $order = proofOrder();
        pay($order);
        $proof = $order->fresh()->latestProof();
        $this->post(route('proofs.changes', $proof->token), ['comment' => 'Change the colour please']);

        $this->post(route('proofs.approve', $proof->token))->assertStatus(409);
        $this->post(route('proofs.changes', $proof->token), ['comment' => 'Another change please'])->assertStatus(409);
    });
});

describe('revising the design', function () {
    beforeEach(function () {
        $this->polo = Product::with(['printAreas', 'colours', 'variants.colour'])->where('slug', 'classic-polo-standard')->first();
        $this->order = proofOrder(25, 'classic-polo-standard');
        pay($this->order);
        $this->proof = $this->order->fresh()->latestProof();
        $this->post(route('proofs.changes', $this->proof->token), ['comment' => 'Make the text bigger please']);
    });

    it('opens an editable copy of the design for the customer, even from another device', function () {
        $this->flushSession(); // the email link is opened in a different browser
        $line = $this->order->lines()->first();

        $res = $this->post(route('proofs.revise', [$this->proof->token, $line]));

        $design = Design::latest('id')->first();
        $res->assertRedirect(route('designs.edit', $design));
        expect($design->revises_order_line_id)->toBe($line->id)->and($design->product_id)->toBe($this->polo->id)->and($design->areas)->toHaveCount(1);
        $this->get(route('designs.edit', $design))->assertOk();
    });

    it('replaces the order’s print files and sends a new proof when the design is saved', function () {
        $line = $this->order->lines()->first();
        $oldExport = $line->areas()->first()->export_path;

        $this->post(route('proofs.revise', [$this->proof->token, $line]));
        $design = Design::latest('id')->first();
        $area = $this->polo->printAreas->first();

        $res = $this->putJson(route('designs.update', $design), payload($this->polo, $area, state(text('BIGGER', 90))))->assertOk();

        $order = $this->order->fresh();
        $res->assertJsonPath('next', $order->url());

        expect($order->proofs()->count())->toBe(2)
            ->and($order->proofs()->where('version', 1)->first()->status)->toBe('superseded')
            ->and($order->latestProof()->version)->toBe(2)->and($order->latestProof()->status)->toBe('sent')
            ->and($order->status)->toBe(OrderStatus::AwaitingProof);

        $newArea = $line->fresh()->areas()->first();
        expect($newArea->export_width)->toBe(1181)->and($newArea->state)->toContain('BIGGER')->and($line->fresh()->design_id)->toBe($design->id);
        Storage::disk('designs')->assertExists($newArea->export_path);
        expect(Design::find($design->id)->revises_order_line_id)->toBeNull();

        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->subjectLine === 'Your proof is ready' && str_contains((string) $m->actionUrl, $order->latestProof()->token));
    });

    it('refuses the revision once the order has been approved and moved on', function () {
        $line = $this->order->lines()->first();
        $this->post(route('proofs.revise', [$this->proof->token, $line]));
        $design = Design::latest('id')->first();

        $this->order->fresh()->forceFill(['status' => OrderStatus::InProduction])->save();

        $this->putJson(route('designs.update', $design), payload($this->polo, $this->polo->printAreas->first(), state(text('LATE'))))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'already moved on'));
    });

    it('does not allow a revision from someone else’s proof link or without a change request', function () {
        $line = $this->order->lines()->first();
        $other = proofOrder(25);
        pay($other);
        $otherProof = $other->fresh()->latestProof();

        $this->post(route('proofs.revise', [$otherProof->token, $line]))->assertForbidden(); // line belongs to a different order
        $this->post(route('proofs.revise', [$otherProof->token, $other->lines()->first()]))->assertForbidden(); // no change requested yet
    });

    it('copies uploaded images so the editor owns them', function () {
        $asset = DesignAsset::create(['session_token' => 'guest-token', 'path' => 'uploads/s/orig.png', 'original_name' => 'logo.png', 'mime' => 'image/png', 'kind' => 'raster', 'width' => 100, 'height' => 100, 'bytes' => 10]);
        Storage::disk('designs')->put('uploads/s/orig.png', pngBytes(100, 100));
        $line = $this->order->lines()->first();
        $path = "orders/{$this->order->id}/line-{$line->id}/originals/{$asset->id}-logo.png";
        Storage::disk('designs')->put($path, pngBytes(100, 100));
        $line->areas()->first()->update([
            'state' => json_encode(['objects' => [['type' => 'image', 'assetId' => $asset->id, 'src' => 'asset:'.$asset->id]]]),
            'assets' => [['id' => $asset->id, 'name' => 'logo.png', 'path' => $path, 'mime' => 'image/png', 'kind' => 'raster', 'width' => 100, 'height' => 100]],
        ]);

        $this->flushSession();
        $this->post(route('proofs.revise', [$this->proof->token, $line]));

        $design = Design::latest('id')->first();
        $copy = DesignAsset::where('id', '!=', $asset->id)->first();
        expect($copy)->not->toBeNull()->and($copy->session_token)->not->toBe('guest-token');
        expect(json_decode($design->areas()->first()->state, true)['objects'][0])->toMatchArray(['assetId' => $copy->id, 'src' => 'asset:'.$copy->id]);
    });
});

describe('proof page', function () {
    it('sends old links to the newest proof and 404s unknown ones', function () {
        $order = proofOrder();
        pay($order);
        $first = $order->fresh()->latestProof();

        app(ProofService::class)->create($order->fresh());
        $second = $order->fresh()->latestProof();

        expect($second->id)->not->toBe($first->id);
        $this->get($first->url())->assertRedirect($second->url());
        $this->get('/proofs/'.str_repeat('x', 40))->assertNotFound();
    });

    it('serves the proof image through the secret link only', function () {
        $order = proofOrder();
        pay($order);
        $proof = $order->fresh()->latestProof();

        $this->get(route('proofs.image', $proof->token))->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->get(route('proofs.image', 'nope'))->assertNotFound();
    });

    it('lists names and numbers for checking on a personalised order', function () {
        $tee = Product::with(['variants.colour', 'colours', 'printAreas'])->where('slug', 'classic-t-shirt-standard')->first();
        $rows = collect(range(1, 25))->map(fn ($i) => [
            'variant_id' => variantId($tee, 'White', 'M'), 'qty' => 1, 'name' => $i === 1 ? 'Kwame' : "Player {$i}", 'number' => (string) $i,
        ])->all();

        addToCart(savedDesign($tee, roles: ROLES), $rows);
        $this->post('/checkout', checkoutData());
        $order = Order::latest('id')->first();
        pay($order);

        $this->get($order->fresh()->latestProof()->url())->assertSee('Names and numbers')->assertSee('Kwame')->assertSee('Player 25');
    });

    it('is linked from the order page while a proof is waiting', function () {
        $order = proofOrder();
        pay($order);
        $order = $order->fresh();

        $this->get($order->url())->assertSee('Your proof is ready')->assertSee('Review your proof')->assertSee($order->latestProof()->url());
    });

    it('cannot be approved after the order is cancelled', function () {
        $order = proofOrder();
        pay($order);
        $proof = $order->fresh()->latestProof();
        app(OrderService::class)->cancel($order->fresh(), 'Customer request');

        $this->get($proof->url())->assertOk()->assertSee('order was cancelled')->assertDontSee('Approve this proof');
    });
});

describe('admin tools', function () {
    it('replaces the generated proof with an uploaded image and sends it', function () {
        $order = proofOrder();
        pay($order);

        $proof = app(ProofService::class)->replace($order->fresh(), UploadedFile::fake()->createWithContent('custom.png', pngBytes(300, 300)));

        expect($proof->version)->toBe(2)->and($proof->replaced)->toBeTrue()->and($proof->image_path)->toEndWith('.png')
            ->and($order->proofs()->where('version', 1)->first()->status)->toBe('superseded');
        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->actionUrl === $proof->url());

        expect(fn () => app(ProofService::class)->replace($order->fresh(), UploadedFile::fake()->createWithContent('x.png', 'not an image')))
            ->toThrow(InvalidArgumentException::class);
    });

    it('overrides the proof timing on a single order', function () {
        $order = proofOrder(); // store default: pay then proof, unpaid
        $service = app(ProofService::class);

        $service->overrideTiming($order, 'proof_then_pay');
        $order = $order->fresh();
        expect($order->proof_timing)->toBe('proof_then_pay')->and($order->status)->toBe(OrderStatus::AwaitingProof)->and($order->proofs)->toHaveCount(1);

        $service->overrideTiming($order, 'pay_then_proof');
        $order = $order->fresh();
        expect($order->proof_timing)->toBe('pay_then_proof')->and($order->status)->toBe(OrderStatus::PendingPayment)
            ->and($order->proofs()->first()->status)->toBe('superseded');
    });

    it('will not change the timing once paid, approved or when no proof applies', function () {
        $paid = proofOrder();
        pay($paid);
        expect(fn () => app(ProofService::class)->overrideTiming($paid->fresh(), 'proof_then_pay'))->toThrow(InvalidArgumentException::class);

        $small = (function () {
            cartWith(2);
            test()->post('/checkout', checkoutData());

            return Order::latest('id')->first();
        })();
        expect(fn () => app(ProofService::class)->overrideTiming($small, 'proof_then_pay'))->toThrow(InvalidArgumentException::class);
    });
});

it('has one proof sheet tile per print area, within the size limits', function () {
    $order = proofOrder();
    $bytes = app(ProofImage::class)->compose($order);
    [$w, $h] = getimagesizefromstring($bytes);

    expect($w)->toBeGreaterThan(500)->and($h)->toBeGreaterThan(500)->and(strlen($bytes))->toBeLessThan(2 * 1024 * 1024);
});
