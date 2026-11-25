<?php

use App\Enums\OrderStatus;
use App\Mail\NewJobMail;
use App\Mail\OrderUpdateMail;
use App\Models\CartLine;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Services\DeliveryCalculator;
use App\Services\OrderService;
use App\Services\Settings;
use Database\Seeders\CatalogueSeeder;
use Illuminate\Http\Client\Factory as HttpFactory;
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
    $this->tee = Product::with(['variants.colour', 'colours', 'printAreas'])->where('slug', 'classic-t-shirt-standard')->first();

    $this->accra = DeliveryZone::create(['name' => 'Accra', 'fee_type' => 'flat', 'flat_fee' => 30, 'eta' => '1-2 days']);
    $this->shopPickup = PickupPoint::create(['name' => 'Our shop', 'address' => '1 Main Street', 'hours' => '9-6']);
});

describe('checkout page', function () {
    it('sends an empty cart back to the cart', function () {
        $this->get('/checkout')->assertRedirect('/cart');
    });

    it('shows delivery zones with their fees and pickup points', function () {
        cartWith();

        $this->get('/checkout')->assertOk()->assertSee('Accra')->assertSee('Our shop')->assertSee('Phone number')->assertSee('GH₵ 165');
    });

    it('offers only pickup when no zone is set up, and explains when nothing is', function () {
        cartWith();
        DeliveryZone::query()->delete();
        $this->get('/checkout')->assertOk()->assertDontSee('Where are we delivering?')->assertSee('Our shop');

        PickupPoint::query()->delete();
        $this->get('/checkout')->assertOk()->assertSee('Checkout is not available yet');
    });
});

describe('delivery fees', function () {
    it('can be banded by quantity', function () {
        $zone = DeliveryZone::create(['name' => 'Kumasi', 'fee_type' => 'banded', 'flat_fee' => 0, 'bands' => [['min_qty' => 1, 'max_qty' => 20, 'fee' => 30], ['min_qty' => 21, 'max_qty' => null, 'fee' => 60]]]);
        $calc = app(DeliveryCalculator::class);

        expect($calc->fee($zone, 1, 10000))->toBe(3000)->and($calc->fee($zone, 20, 10000))->toBe(3000)
            ->and($calc->fee($zone, 21, 10000))->toBe(6000)->and($calc->fee($zone, 500, 10000))->toBe(6000);
    });

    it('is waived above the free-delivery threshold, per zone or globally', function () {
        $calc = app(DeliveryCalculator::class);

        expect($calc->fee($this->accra, 3, 99999))->toBe(3000);

        app(Settings::class)->set('delivery.free_threshold', 1000);
        expect($calc->fee($this->accra, 3, 99999))->toBe(3000)->and($calc->fee($this->accra, 3, 100000))->toBe(0);

        $this->accra->update(['free_threshold' => 2000]);
        expect($calc->fee($this->accra->fresh(), 3, 100000))->toBe(3000)->and($calc->fee($this->accra->fresh(), 3, 200000))->toBe(0);
    });
});

describe('placing an order', function () {
    it('creates the order, copies the print files, clears the cart and sends the customer to Paystack', function () {
        cartWith(3, 'M');

        $res = $this->post('/checkout', checkoutData());

        $res->assertRedirect('https://checkout.paystack.com/abc');

        $order = Order::with(['lines.items', 'lines.areas'])->first();
        expect($order->number)->toBe('POD-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT))
            ->and($order->status)->toBe(OrderStatus::PendingPayment)
            ->and((float) $order->subtotal)->toBe(165.0)
            ->and((float) $order->delivery_fee)->toBe(30.0)
            ->and((float) $order->total)->toBe(195.0)
            ->and($order->quantity)->toBe(3)
            ->and($order->zone_name)->toBe('Accra')
            ->and($order->delivery_address)->toContain('Palm Street')
            ->and($order->customer_name)->toBe('Ama Mensah')
            ->and($order->proof_required)->toBeFalse()
            ->and($order->lines)->toHaveCount(1)
            ->and($order->lines[0]->items[0]->colour_name)->toBe('White')
            ->and($order->lines[0]->areas)->toHaveCount(1);

        Storage::disk('designs')->assertExists($order->lines[0]->areas[0]->export_path);
        expect($order->lines[0]->areas[0]->export_path)->toStartWith("orders/{$order->id}/");
        expect(CartLine::count())->toBe(0);

        $payment = Payment::first();
        expect($payment->status)->toBe('pending')->and((float) $payment->amount)->toBe(195.0)->and($order->fresh()->paystack_reference)->toBe($payment->reference);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'transaction/initialize') && $r['amount'] === 19500 && $r['currency'] === 'GHS' && $r['email'] === 'ama@example.test');
    });

    it('prices on the server at the moment of checkout', function () {
        cartWith(3, 'M');
        $this->tee->tiers()->where('min_qty', 1)->update(['unit_price' => 70]);

        $this->post('/checkout', checkoutData());

        expect((float) Order::first()->subtotal)->toBe(210.0);
    });

    it('supports pickup with no delivery fee', function () {
        cartWith();

        $this->post('/checkout', checkoutData(['fulfilment' => 'pickup', 'zone_id' => null, 'address' => null, 'pickup_point_id' => $this->shopPickup->id]));

        $order = Order::first();
        expect($order->fulfilment)->toBe('pickup')->and((float) $order->delivery_fee)->toBe(0.0)->and($order->pickup_name)->toBe('Our shop')->and($order->delivery_zone_id)->toBeNull();
    });

    it('applies banded delivery by the order quantity', function () {
        DeliveryZone::create(['name' => 'Kumasi', 'fee_type' => 'banded', 'bands' => [['min_qty' => 1, 'max_qty' => 20, 'fee' => 30], ['min_qty' => 21, 'max_qty' => null, 'fee' => 60]]]);
        cartWith(25, 'M');

        $this->post('/checkout', checkoutData(['zone_id' => DeliveryZone::where('name', 'Kumasi')->first()->id]));

        expect((float) Order::first()->delivery_fee)->toBe(60.0);
    });

    it('validates the form', function (array $override, string $field) {
        cartWith();

        $this->post('/checkout', checkoutData($override))->assertSessionHasErrors($field);
        expect(Order::count())->toBe(0);
    })->with([
        'no name' => [['name' => ''], 'name'],
        'bad phone' => [['phone' => 'abc'], 'phone'],
        'no email' => [['email' => ''], 'email'],
        'bad email' => [['email' => 'nope'], 'email'],
        'no zone' => [['zone_id' => null], 'zone_id'],
        'no address' => [['address' => ''], 'address'],
        'pickup without a point' => [['fulfilment' => 'pickup', 'pickup_point_id' => null], 'pickup_point_id'],
        'unknown fulfilment' => [['fulfilment' => 'drone'], 'fulfilment'],
    ]);

    it('refuses inactive zones and pickup points', function () {
        cartWith();
        $this->accra->update(['active' => false]);

        $this->post('/checkout', checkoutData(['zone_id' => $this->accra->id]))->assertSessionHasErrors('zone_id');
    });

    it('does not let a product that went off sale be ordered', function () {
        cartWith();
        $this->tee->update(['active' => false]);

        $this->post('/checkout', checkoutData())->assertRedirect('/cart')->assertSessionHasErrors('cart');
        expect(Order::count())->toBe(0);
    });

    it('keeps the order and shows a clear message when Paystack is unreachable or unconfigured', function () {
        cartWith();
        Http::swap(new HttpFactory);
        Http::fake(['api.paystack.co/*' => Http::response(['status' => false, 'message' => 'Down'], 500)]);

        $this->post('/checkout', checkoutData())->assertRedirect(Order::first()->url());
        expect(Order::count())->toBe(1);

        config(['shop.paystack.secret_key' => null]);
        cartWith();
        $this->post('/checkout', checkoutData())->assertRedirect();
        $this->get(Order::latest('id')->first()->url())->assertSee('online payment is not available');
    });

    it('prefills the form for a signed-in customer and attaches the order to them', function () {
        $user = userWithRole('customer');
        cartWith();

        $this->actingAs($user)->get('/checkout')->assertSee($user->email);
        $this->post('/checkout', checkoutData());

        expect(Order::first()->user_id)->toBe($user->id);
        $this->get('/account/orders')->assertOk()->assertSee('POD-');
    });

    it('keeps the files in the order when the design is later edited or deleted', function () {
        $user = userWithRole('customer');
        $this->actingAs($user);
        $design = savedDesign($this->tee);
        $design->update(['user_id' => $user->id, 'session_token' => null]);
        addToCart($design, [['variant_id' => variantId($this->tee, 'White', 'M'), 'qty' => 2]]);

        $this->post('/checkout', checkoutData());
        $path = Order::first()->lines()->first()->areas()->first()->export_path;

        $this->delete("/account/designs/{$design->id}")->assertRedirect();

        Storage::disk('designs')->assertMissing("designs/{$design->id}/area-{$design->areas()->first()?->print_area_id}.png");
        Storage::disk('designs')->assertExists($path);
        expect(Order::first()->lines()->first()->design_id)->toBeNull();
    });
});

describe('payment webhook', function () {
    beforeEach(function () {
        cartWith(3, 'M');
        $this->post('/checkout', checkoutData());
        $this->order = Order::first();
        $this->payment = Payment::first();
    });

    it('rejects calls without a valid signature', function () {
        $payload = chargeSuccess($this->payment);

        webhook($payload, 'wrong-secret')->assertUnauthorized();
        $this->postJson('/webhooks/paystack', $payload)->assertUnauthorized();
        webhook($payload, '')->assertUnauthorized();

        expect($this->order->fresh()->isPaid())->toBeFalse();
    });

    it('rejects everything when no secret key is configured', function () {
        config(['shop.paystack.secret_key' => null]);

        webhook(chargeSuccess($this->payment), 'anything')->assertUnauthorized();
    });

    it('marks the order paid, releases it to the shop and emails everyone', function () {
        app(Settings::class)->set('shop.notification_email', 'print@example.test');

        webhook(chargeSuccess($this->payment))->assertOk();

        $order = $this->order->fresh();
        expect($order->isPaid())->toBeTrue()
            ->and($order->status)->toBe(OrderStatus::InProduction)
            ->and($order->production_stage)->toBe('queued')
            ->and($order->due_date->isSameDay(now()->addDays(5)))->toBeTrue()
            ->and($this->payment->fresh()->status)->toBe('success')
            ->and($this->payment->fresh()->channel)->toBe('mobile_money');

        Mail::assertQueued(OrderUpdateMail::class, fn ($m) => $m->hasTo('ama@example.test') && $m->subjectLine === 'Payment received');
        Mail::assertQueued(NewJobMail::class, fn ($m) => $m->hasTo('print@example.test'));
    });

    it('is idempotent: a repeated webhook changes nothing and sends nothing twice', function () {
        webhook(chargeSuccess($this->payment))->assertOk();
        $paidAt = $this->order->fresh()->paid_at;

        webhook(chargeSuccess($this->payment))->assertOk();

        expect($this->order->fresh()->paid_at->equalTo($paidAt))->toBeTrue();
        Mail::assertQueued(OrderUpdateMail::class, 1);
    });

    it('does not trust a payment whose amount or currency differs from the order', function () {
        webhook(chargeSuccess($this->payment, ['amount' => 100]))->assertOk();
        webhook(chargeSuccess($this->payment, ['currency' => 'USD']))->assertOk();

        $order = $this->order->fresh();
        expect($order->isPaid())->toBeFalse()->and($order->flagged)->toBeTrue()->and($order->status)->toBe(OrderStatus::PendingPayment);
        expect($order->events()->where('type', 'payment_mismatch')->exists())->toBeTrue();
    });

    it('ignores failed charges, unknown references and other events', function () {
        webhook(chargeSuccess($this->payment, ['status' => 'failed']))->assertOk();
        webhook(chargeSuccess($this->payment, ['reference' => 'NOPE']))->assertOk();
        webhook(['event' => 'charge.dispute.create', 'data' => []])->assertOk();

        expect($this->order->fresh()->isPaid())->toBeFalse();
    });

    it('is not fooled by the browser redirect alone', function () {
        $this->get($this->order->url().'&reference='.$this->payment->reference.'&trxref='.$this->payment->reference)
            ->assertOk()->assertSee('Confirming your payment');

        expect($this->order->fresh()->isPaid())->toBeFalse();
    });
});

describe('proofs and payment order', function () {
    it('holds a large paid order for proof approval (pay then proof)', function () {
        cartWith(25, 'M');
        $this->post('/checkout', checkoutData());
        $order = Order::first();
        $payment = Payment::first();

        expect($order->proof_required)->toBeTrue()->and($order->proof_timing)->toBe('pay_then_proof')->and($order->status)->toBe(OrderStatus::PendingPayment);

        webhook(chargeSuccess($payment))->assertOk();

        expect($order->fresh()->status)->toBe(OrderStatus::AwaitingProof)->and($order->fresh()->production_stage)->toBeNull();
        Mail::assertNotQueued(NewJobMail::class);
    });

    it('asks for the proof first when the store is set to proof then pay', function () {
        app(Settings::class)->set('proof.timing', 'proof_then_pay');
        cartWith(25, 'M');

        $this->post('/checkout', checkoutData())->assertRedirect();

        $order = Order::first();
        expect($order->status)->toBe(OrderStatus::AwaitingProof)->and($order->proof_timing)->toBe('proof_then_pay')->and(Payment::count())->toBe(0);
        Http::assertNothingSent();
        $this->get($order->url())->assertOk()->assertSee('preparing your proof')->assertDontSee('Pay GH');
    });

    it('treats a threshold of 0 as every order and a blank threshold as never', function () {
        $orders = app(OrderService::class);

        app(Settings::class)->set('proof.threshold', 0);
        expect($orders->proofRequired(1))->toBeTrue();

        app(Settings::class)->set('proof.threshold', null);
        expect($orders->proofRequired(10000))->toBeFalse();

        app(Settings::class)->set('proof.threshold', 20);
        expect($orders->proofRequired(19))->toBeFalse()->and($orders->proofRequired(20))->toBeTrue();
    });
});

describe('order page', function () {
    beforeEach(function () {
        cartWith();
        $this->post('/checkout', checkoutData());
        $this->order = Order::first();
    });

    it('opens with the secret link and shows the order', function () {
        $this->get($this->order->url())->assertOk()->assertSee($this->order->number)->assertSee('Pending payment')->assertSee('GH₵ 195')->assertSee('Pay GH₵ 195 now');
    });

    it('is private without the link', function () {
        $this->get("/orders/{$this->order->number}")->assertNotFound();
        $this->get("/orders/{$this->order->number}?t=wrong")->assertNotFound();
        $this->get("/orders/{$this->order->number}/status?t=wrong")->assertNotFound();
        $this->actingAs(userWithRole('customer'))->get("/orders/{$this->order->number}")->assertNotFound();
    });

    it('reports status as JSON for the live refresh', function () {
        $this->getJson("/orders/{$this->order->number}/status?t={$this->order->access_token}")->assertOk()->assertJson(['status' => 'pending_payment', 'paid' => false]);
    });

    it('restarts payment, but not for a paid or cancelled order', function () {
        $this->post("/orders/{$this->order->number}/pay?t={$this->order->access_token}")->assertRedirect('https://checkout.paystack.com/abc');
        expect(Payment::count())->toBe(2);

        $this->order->update(['cancelled_at' => now()]);
        $this->post("/orders/{$this->order->number}/pay?t={$this->order->access_token}")->assertForbidden();

        $this->order->update(['cancelled_at' => null, 'paid_at' => now()]);
        $this->post("/orders/{$this->order->number}/pay?t={$this->order->access_token}")->assertForbidden();
    });

    it('shows dispatch details to the customer', function () {
        $this->order->update(['courier' => 'Speedy', 'rider_phone' => '0240000000', 'tracking_note' => 'Leaves the shop at noon']);

        $this->get($this->order->url())->assertSee('Speedy')->assertSee('0240000000')->assertSee('Leaves the shop at noon');
    });
});

describe('admin delivery settings', function () {
    beforeEach(fn () => $this->actingAs(userWithRole('admin')));

    it('lists, adds, updates and removes zones', function () {
        $this->get('/admin/delivery')->assertOk()->assertSee('Accra')->assertSee('Our shop');

        $this->post('/admin/delivery/zones', ['name' => 'Kumasi', 'fee_type' => 'banded', 'bands' => [['min_qty' => 21, 'max_qty' => '', 'fee' => 60], ['min_qty' => 1, 'max_qty' => 20, 'fee' => 30]], 'eta' => '2-3 days', 'active' => 1])->assertSessionHasNoErrors();

        $zone = DeliveryZone::where('name', 'Kumasi')->first();
        expect($zone->bands)->toBe([['min_qty' => 1, 'max_qty' => 20, 'fee' => 30], ['min_qty' => 21, 'max_qty' => null, 'fee' => 60]])->and($zone->active)->toBeTrue();

        $this->put("/admin/delivery/zones/{$zone->id}", ['name' => 'Kumasi', 'fee_type' => 'flat', 'flat_fee' => 45, 'active' => 0])->assertSessionHasNoErrors();
        expect($zone->fresh()->fee_type)->toBe('flat')->and($zone->fresh()->bands)->toBeNull()->and($zone->fresh()->active)->toBeFalse();

        $this->delete("/admin/delivery/zones/{$zone->id}")->assertRedirect();
        expect(DeliveryZone::where('name', 'Kumasi')->exists())->toBeFalse();
    });

    it('requires bands for banded fees', function () {
        $this->post('/admin/delivery/zones', ['name' => 'X', 'fee_type' => 'banded'])->assertSessionHasErrors('bands');
    });

    it('manages pickup points', function () {
        $this->post('/admin/delivery/pickups', ['name' => 'Kiosk', 'address' => 'Market Circle', 'hours' => '8-4', 'active' => 1])->assertSessionHasNoErrors();
        $pickup = PickupPoint::where('name', 'Kiosk')->first();

        $this->put("/admin/delivery/pickups/{$pickup->id}", ['name' => 'Kiosk', 'address' => 'Market Circle', 'active' => 0]);
        expect($pickup->fresh()->active)->toBeFalse();

        $this->delete("/admin/delivery/pickups/{$pickup->id}");
        expect(PickupPoint::where('name', 'Kiosk')->exists())->toBeFalse();
    });

    it('is admin only', function () {
        auth()->logout();
        $this->actingAs(userWithRole('customer'))->get('/admin/delivery')->assertForbidden();
    });
});
