<?php

use App\Enums\OrderStatus;
use App\Mail\OrderUpdateMail;
use App\Models\DeliveryZone;
use App\Models\Design;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\Refund;
use App\Services\OrderService;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Factory as HttpFactory;
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
    DeliveryZone::create(['name' => 'Accra', 'fee_type' => 'flat', 'flat_fee' => 30, 'courier_name' => 'Speedy']);
    $this->pickup = PickupPoint::create(['name' => 'Our shop', 'address' => '1 Main Street']);
    $this->shopCheckout = ['fulfilment' => 'pickup', 'zone_id' => null, 'address' => null, 'pickup_point_id' => $this->pickup->id];
});

function asAdmin(): void
{
    test()->actingAs(userWithRole('admin'));
}

function readyOrder(array $checkout = []): Order
{
    $order = releasedOrder(3, $checkout);
    $order->forceFill(['status' => OrderStatus::Ready, 'production_stage' => 'ready'])->save();
    Mail::fake();

    return $order->fresh();
}

/** Swap in a fresh Http fake so earlier stubs do not shadow the new ones. */
function fakePaystack(array $stubs): void
{
    Http::swap(new HttpFactory);
    Http::fake($stubs);
}

describe('access', function () {
    it('is admin only', function () {
        $order = releasedOrder();

        foreach (['/admin/orders', "/admin/orders/{$order->number}", '/admin/reports'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->actingAs(userWithRole('shop'))->get('/admin/orders')->assertForbidden();
        $this->actingAs(userWithRole('customer'))->get("/admin/orders/{$order->number}")->assertForbidden();
        $this->post("/admin/orders/{$order->number}/cancel", ['reason' => 'x'])->assertForbidden();
    });
});

describe('order list', function () {
    beforeEach(fn () => asAdmin());

    it('lists, searches and filters orders', function () {
        $one = releasedOrder();
        cartWith(2);
        $this->post('/checkout', checkoutData(['name' => 'Kofi Boateng', 'email' => 'kofi@example.test', 'phone' => '020 999 8888']));
        $two = Order::latest('id')->first();

        $this->get('/admin/orders')->assertOk()->assertSee($one->number)->assertSee($two->number);
        $this->get('/admin/orders?q='.$two->number)->assertSee($two->number)->assertDontSee($one->number);
        $this->get('/admin/orders?q=Boateng')->assertSee($two->number)->assertDontSee($one->number);
        $this->get('/admin/orders?q=kofi@example')->assertSee($two->number)->assertDontSee($one->number);
        $this->get('/admin/orders?q=999')->assertSee($two->number)->assertDontSee($one->number);
        $this->get('/admin/orders?status=in_production')->assertSee($one->number)->assertDontSee($two->number);
        $this->get('/admin/orders?status=pending_payment')->assertSee($two->number)->assertDontSee($one->number);

        $two->update(['flagged' => true]);
        $this->get('/admin/orders?flagged=1')->assertSee($two->number)->assertDontSee($one->number)->assertSee('follow up');
    });

    it('paginates', function () {
        releasedOrder();
        Order::query()->each(fn ($o) => collect(range(1, 26))->each(fn () => Order::create($o->only(['customer_name', 'phone', 'email', 'fulfilment', 'subtotal', 'delivery_fee', 'total', 'quantity', 'tier_scope']))));

        $this->get('/admin/orders')->assertOk()->assertSee('Next');
    });

    it('shows the order with prices, history and payment', function () {
        $order = releasedOrder();

        $this->get("/admin/orders/{$order->number}")->assertOk()
            ->assertSee($order->number)->assertSee('Classic T-shirt')->assertSee('GH₵ 195')->assertSee('Payment received')
            ->assertSee('mobile money')->assertSee('Job sheet (PDF)');
    });
});

describe('dashboard', function () {
    it('counts what needs attention', function () {
        asAdmin();
        $large = releasedOrder(25); // paid, awaiting proof
        $small = releasedOrder(2);  // in production
        $large->update(['flagged' => true]);

        $this->get('/admin')->assertOk()->assertSee('Awaiting proof approval')->assertSee('Needs follow-up')->assertSee($small->number)->assertSee($large->number);
    });
});

describe('fulfilment', function () {
    beforeEach(fn () => asAdmin());

    it('dispatches a ready delivery order and emails the courier details', function () {
        $order = readyOrder();

        $this->post("/admin/orders/{$order->number}/dispatch", ['courier' => 'Speedy', 'rider_phone' => '055 111 2222', 'tracking_note' => 'Waybill 7788, arrives by 4pm'])->assertRedirect();

        $order = $order->fresh();
        expect($order->status)->toBe(OrderStatus::Dispatched)->and($order->courier)->toBe('Speedy')->and($order->rider_phone)->toBe('055 111 2222')
            ->and($order->tracking_note)->toContain('Waybill 7788')->and($order->dispatched_at)->not->toBeNull();

        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->hasTo('ama@example.test') && $m->subjectLine === 'Your order has been dispatched'
            && str_contains(implode(' ', $m->lines), 'Courier: Speedy') && str_contains(implode(' ', $m->lines), 'Rider phone: 055 111 2222') && str_contains(implode(' ', $m->lines), 'Waybill 7788'));
        $this->get($order->url())->assertSee('Speedy')->assertSee('055 111 2222');
    });

    it('uses the zone’s courier by default', function () {
        $order = readyOrder();

        $this->post("/admin/orders/{$order->number}/dispatch", [])->assertRedirect();

        expect($order->fresh()->courier)->toBe('Speedy');
    });

    it('only dispatches ready delivery orders', function () {
        $inProduction = releasedOrder();
        $pickup = readyOrder($this->shopCheckout);

        $this->post("/admin/orders/{$inProduction->number}/dispatch", [])->assertStatus(422);
        $this->post("/admin/orders/{$pickup->number}/dispatch", [])->assertStatus(422);
        expect($inProduction->fresh()->status)->toBe(OrderStatus::InProduction);
    });

    it('marks pickup orders picked up and then completed', function () {
        $order = readyOrder($this->shopCheckout);

        $this->post("/admin/orders/{$order->number}/picked-up")->assertRedirect();
        expect($order->fresh()->status)->toBe(OrderStatus::PickedUp);

        $this->post("/admin/orders/{$order->number}/complete")->assertRedirect();
        expect($order->fresh()->status)->toBe(OrderStatus::Completed)->and($order->fresh()->completed_at)->not->toBeNull();

        $this->post("/admin/orders/{$order->number}/complete")->assertStatus(422);
    });

    it('completes a dispatched delivery', function () {
        $order = readyOrder();
        $this->post("/admin/orders/{$order->number}/dispatch", []);
        $this->post("/admin/orders/{$order->number}/complete")->assertRedirect();

        expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    });

    it('does not let a delivery order be marked picked up', function () {
        $order = readyOrder();

        $this->post("/admin/orders/{$order->number}/picked-up")->assertStatus(422);
    });
});

describe('cancelling', function () {
    beforeEach(fn () => asAdmin());

    it('cancels with a reason and tells the customer', function () {
        cartWith(2);
        $this->post('/checkout', checkoutData());
        $order = Order::latest('id')->first();

        $this->post("/admin/orders/{$order->number}/cancel", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post("/admin/orders/{$order->number}/cancel", ['reason' => 'Customer changed their mind'])->assertRedirect();

        $order = $order->fresh();
        expect($order->status)->toBe(OrderStatus::Cancelled)->and($order->cancel_reason)->toBe('Customer changed their mind');
        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->subjectLine === 'Order cancelled' && str_contains(implode(' ', $m->lines), 'changed their mind'));

        $this->post("/admin/orders/{$order->number}/cancel", ['reason' => 'Again'])->assertSessionHasErrors('reason');
    });

    it('takes a cancelled order out of the print shop’s list', function () {
        $order = releasedOrder();

        $this->post("/admin/orders/{$order->number}/cancel", ['reason' => 'Duplicate order']);

        $this->actingAs(userWithRole('shop'))->get('/shop')->assertDontSee($order->number);
    });
});

describe('payments', function () {
    beforeEach(fn () => asAdmin());

    it('records a manual payment and releases the order', function () {
        cartWith(2);
        $this->post('/checkout', checkoutData());
        $order = Order::latest('id')->first();

        $this->post("/admin/orders/{$order->number}/mark-paid", ['note' => ''])->assertSessionHasErrors('note');
        $this->post("/admin/orders/{$order->number}/mark-paid", ['note' => 'Bank transfer ref 4421'])->assertRedirect();

        $order = $order->fresh();
        expect($order->isPaid())->toBeTrue()->and($order->status)->toBe(OrderStatus::InProduction)
            ->and($order->payments()->where('channel', 'manual')->exists())->toBeTrue();

        $this->post("/admin/orders/{$order->number}/mark-paid", ['note' => 'Again'])->assertSessionHasErrors('note');
    });

    it('confirms a payment with Paystack on request', function () {
        cartWith(2);
        $this->post('/checkout', checkoutData());
        $order = Order::latest('id')->first();
        $payment = Payment::first();

        fakePaystack(["api.paystack.co/transaction/verify/{$payment->reference}" => Http::response(['status' => true, 'data' => ['reference' => $payment->reference, 'status' => 'abandoned', 'amount' => 14000, 'currency' => 'GHS']])]);
        $this->post("/admin/orders/{$order->number}/verify")->assertRedirect()->assertSessionHas('status', 'Paystack has not confirmed a payment for this order yet.');
        expect($order->fresh()->isPaid())->toBeFalse();

        fakePaystack(["api.paystack.co/transaction/verify/{$payment->reference}" => Http::response(['status' => true, 'data' => ['reference' => $payment->reference, 'status' => 'success', 'amount' => 14000, 'currency' => 'GHS', 'channel' => 'card']])]);
        $this->post("/admin/orders/{$order->number}/verify")->assertSessionHas('status', 'Paystack confirmed the payment.');
        expect($order->fresh()->isPaid())->toBeTrue()->and($payment->fresh()->channel)->toBe('card');
    });

    it('reports when there is nothing to check', function () {
        $order = releasedOrder();

        $this->post("/admin/orders/{$order->number}/verify")->assertSessionHasErrors('verify');
    });
});

describe('refunds', function () {
    beforeEach(fn () => asAdmin());

    it('refunds in full through Paystack and marks the order refunded', function () {
        $order = releasedOrder();
        $payment = Payment::first();
        fakePaystack(['api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['id' => 555, 'status' => 'pending']])]);

        $this->post("/admin/orders/{$order->number}/refund", ['amount' => '195', 'reason' => 'Proof rejected and order cancelled', 'method' => 'paystack'])->assertSessionHasNoErrors();

        Http::assertSent(fn ($r) => str_contains($r->url(), '/refund') && $r['transaction'] === $payment->reference && $r['amount'] === 19500);

        $refund = Refund::first();
        expect($refund->status)->toBe('pending')->and($refund->method)->toBe('paystack')->and((float) $refund->amount)->toBe(195.0)->and($refund->gateway_id)->toBe('555');
        expect($order->fresh()->status)->toBe(OrderStatus::Refunded)->and($order->fresh()->cancelled_at)->not->toBeNull();
        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->subjectLine === 'Refund issued' && str_contains(implode(' ', $m->lines), 'GH₵ 195'));
    });

    it('supports partial refunds and stops at what was paid', function () {
        $order = releasedOrder();
        fakePaystack(['api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['id' => 1, 'status' => 'processed']])]);

        $this->post("/admin/orders/{$order->number}/refund", ['amount' => '50', 'reason' => 'Late delivery', 'method' => 'paystack'])->assertSessionHasNoErrors();
        expect($order->fresh()->status)->toBe(OrderStatus::InProduction)->and(Refund::first()->status)->toBe('processed');

        $this->post("/admin/orders/{$order->number}/refund", ['amount' => '146', 'reason' => 'Too much', 'method' => 'paystack'])->assertSessionHasErrors('amount');
        $this->post("/admin/orders/{$order->number}/refund", ['amount' => '145', 'reason' => 'The rest', 'method' => 'paystack'])->assertSessionHasNoErrors();

        expect($order->fresh()->status)->toBe(OrderStatus::Refunded)->and(Refund::count())->toBe(2);
        $this->post("/admin/orders/{$order->number}/refund", ['amount' => '1', 'reason' => 'Nothing left', 'method' => 'paystack'])->assertSessionHasErrors('amount');
    });

    it('records a refund paid back another way without calling Paystack', function () {
        $order = releasedOrder();
        fakePaystack([]);

        $this->post("/admin/orders/{$order->number}/refund", ['amount' => '195', 'reason' => 'Cash returned at the shop', 'method' => 'manual'])->assertSessionHasNoErrors();

        Http::assertNothingSent();
        expect(Refund::first()->method)->toBe('manual')->and(Refund::first()->status)->toBe('recorded')->and($order->fresh()->status)->toBe(OrderStatus::Refunded);
    });

    it('refuses to refund an unpaid order, or a manual payment through Paystack', function () {
        cartWith(2);
        $this->post('/checkout', checkoutData());
        $unpaid = Order::latest('id')->first();
        $this->post("/admin/orders/{$unpaid->number}/refund", ['amount' => '10', 'reason' => 'Nope', 'method' => 'manual'])->assertSessionHasErrors('amount');
        expect(Refund::count())->toBe(0);

        $this->post("/admin/orders/{$unpaid->number}/mark-paid", ['note' => 'Cash']);
        $this->post("/admin/orders/{$unpaid->number}/refund", ['amount' => '10', 'reason' => 'Nope', 'method' => 'paystack'])->assertSessionHasErrors('amount');
        expect(Refund::count())->toBe(0);
    });

    it('shows Paystack’s error and records nothing when the refund fails', function () {
        $order = releasedOrder();
        fakePaystack(['api.paystack.co/refund' => Http::response(['status' => false, 'message' => 'Insufficient balance'], 400)]);

        $this->post("/admin/orders/{$order->number}/refund", ['amount' => '20', 'reason' => 'Goodwill', 'method' => 'paystack'])
            ->assertSessionHasErrors('amount');

        expect(Refund::count())->toBe(0)->and($order->fresh()->status)->toBe(OrderStatus::InProduction);
        expect(session('errors')->first('amount'))->toContain('Insufficient balance');
    });

    it('updates the refund when Paystack reports it finished or failed', function () {
        $order = releasedOrder();
        fakePaystack(['api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['id' => 77, 'status' => 'pending']])]);
        $this->post("/admin/orders/{$order->number}/refund", ['amount' => '20', 'reason' => 'Goodwill', 'method' => 'paystack']);

        webhook(['event' => 'refund.processed', 'data' => ['id' => 77, 'status' => 'processed']])->assertOk();
        expect(Refund::first()->status)->toBe('processed');

        webhook(['event' => 'refund.failed', 'data' => ['id' => 77]])->assertOk();
        expect(Refund::first()->status)->toBe('failed')->and($order->fresh()->flagged)->toBeTrue();
    });
});

describe('proof tools', function () {
    beforeEach(function () {
        asAdmin();
        $this->order = releasedOrder(25);
        $this->proof = $this->order->latestProof();
    });

    it('replaces, regenerates and changes the timing from the order page', function () {
        $this->post("/admin/orders/{$this->order->number}/proof/replace", ['image' => UploadedFile::fake()->createWithContent('p.png', pngBytes(200, 200))])->assertSessionHasNoErrors();
        expect($this->order->fresh()->latestProof()->replaced)->toBeTrue()->and($this->order->proofs()->count())->toBe(2);

        $this->post("/admin/orders/{$this->order->number}/proof/replace", ['image' => UploadedFile::fake()->createWithContent('p.png', 'junk')])->assertSessionHasErrors('image');

        $this->post("/admin/orders/{$this->order->number}/proof/resend")->assertRedirect();
        expect($this->order->proofs()->count())->toBe(3);

        $this->post("/admin/orders/{$this->order->number}/proof/timing", ['timing' => 'proof_then_pay'])->assertSessionHasErrors('timing'); // already paid
    });

    it('overrides the timing of an unpaid order', function () {
        cartWith(25);
        $this->post('/checkout', checkoutData());
        $unpaid = Order::latest('id')->first();

        $this->post("/admin/orders/{$unpaid->number}/proof/timing", ['timing' => 'proof_then_pay'])->assertSessionHasNoErrors();

        expect($unpaid->fresh()->proof_timing)->toBe('proof_then_pay')->and($unpaid->fresh()->status)->toBe(OrderStatus::AwaitingProof);
    });

    it('lets the admin edit the customer’s design and sends a fresh proof when saved', function () {
        $polo = Product::with(['printAreas', 'colours', 'variants.colour'])->where('slug', 'classic-polo-standard')->first();
        $order = proofOrder(25, 'classic-polo-standard');
        webhook(chargeSuccess(Payment::where('order_id', $order->id)->first()));
        $line = $order->lines()->first();

        $this->post("/admin/orders/{$order->number}/lines/{$line->id}/revise")->assertRedirect();
        $design = Design::latest('id')->first();

        expect($design->revises_order_line_id)->toBe($line->id)->and($design->user_id)->not->toBeNull();
        $this->get(route('designs.edit', $design))->assertOk();

        $this->putJson(route('designs.update', $design), payload($polo, $polo->printAreas->first(), state(text('FIXED', 90))))->assertOk()->assertJsonPath('next', $order->url());
        expect($order->fresh()->latestProof()->version)->toBe(2);
    });

    it('refuses design edits once the order has moved on', function () {
        $order = releasedOrder(2);
        $line = $order->lines()->first();

        $this->post("/admin/orders/{$order->number}/lines/{$line->id}/revise")->assertSessionHasErrors('revise');
    });
});

describe('production files', function () {
    it('lets the admin download the job sheet and print files', function () {
        asAdmin();
        $order = releasedOrder();

        $this->get("/admin/orders/{$order->number}/sheet")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/admin/orders/{$order->number}/files")->assertOk();
    });
});

describe('reports', function () {
    beforeEach(fn () => asAdmin());

    it('totals orders, items and revenue for the period', function () {
        releasedOrder(3);          // GH₵ 195 (165 + 30 delivery)
        releasedOrder(10);         // GH₵ 510 (480 + 30)
        cartWith(2);
        $this->post('/checkout', checkoutData()); // placed, unpaid

        $this->get('/admin/reports')->assertOk()
            ->assertSee('GH₵ 705')   // 195 + 510
            ->assertSee('Orders placed')->assertSee('Top products')->assertSee('Classic T-shirt')
            ->assertViewHas('totals', fn ($t) => $t['orders'] === 3 && $t['items'] === 15 && $t['paid'] === 2 && $t['revenue'] === 70500);
    });

    it('subtracts refunds from revenue', function () {
        $order = releasedOrder(3);
        fakePaystack(['api.paystack.co/refund' => Http::response(['status' => true, 'data' => ['id' => 9, 'status' => 'processed']])]);
        $this->post("/admin/orders/{$order->number}/refund", ['amount' => '45', 'reason' => 'Goodwill', 'method' => 'paystack']);

        $this->get('/admin/reports')->assertViewHas('totals', fn ($t) => $t['revenue'] === 15000);
    });

    it('groups by day or week and respects the date range', function () {
        releasedOrder(3);

        $daily = $this->get('/admin/reports?by=day&from='.now()->subDays(6)->toDateString().'&to='.now()->toDateString());
        $daily->assertViewHas('rows', fn ($rows) => count($rows) === 7 && collect($rows)->sum('orders') === 1);

        $weekly = $this->get('/admin/reports?by=week&from='.now()->subDays(20)->toDateString().'&to='.now()->toDateString());
        $weekly->assertViewHas('rows', fn ($rows) => count($rows) >= 3 && count($rows) <= 4 && collect($rows)->sum('revenue') === 19500);

        $past = $this->get('/admin/reports?from='.now()->subDays(60)->toDateString().'&to='.now()->subDays(40)->toDateString());
        $past->assertViewHas('totals', fn ($t) => $t['orders'] === 0 && $t['revenue'] === 0)->assertSee('No paid orders in this period');
    });

    it('swaps a reversed range instead of failing', function () {
        $this->get('/admin/reports?from='.now()->toDateString().'&to='.now()->subDays(5)->toDateString())->assertOk();
    });

    it('excludes cancelled orders from the count of orders placed', function () {
        $order = releasedOrder(3);
        app(OrderService::class)->cancel($order, 'Test');

        $this->get('/admin/reports')->assertViewHas('totals', fn ($t) => $t['orders'] === 0);
    });
});
