<?php

use App\Models\CartLine;
use App\Models\DeliveryZone;
use App\Models\Design;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickupPoint;
use App\Models\Product;
use App\Models\User;
use App\Services\Settings;
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
    DeliveryZone::create(['name' => 'Accra', 'fee_type' => 'flat', 'flat_fee' => 30]);

    $this->seed(CatalogueSeeder::class);
    $this->withSession(['designer_token' => 'guest-token']);
});

describe('contact page', function () {
    it('shows the details the admin has entered', function () {
        $settings = app(Settings::class);
        $settings->set('store.phone', '024 111 2222');
        $settings->set('store.whatsapp', '233241112222');
        $settings->set('store.email', 'hello@example.test');
        PickupPoint::create(['name' => 'Our shop', 'address' => '1 Main Street', 'hours' => 'Mon-Sat 9-6']);

        $this->get('/contact')->assertOk()->assertSee('024 111 2222')->assertSee('tel:0241112222', false)->assertSee('https://wa.me/233241112222', false)
            ->assertSee('hello@example.test')->assertSee('Our shop')->assertSee('Mon-Sat 9-6')->assertSee('Accra')->assertSee('about 5 days');
    });

    it('says so when no details are set up yet', function () {
        $this->get('/contact')->assertOk()->assertSee('being set up');
    });

    it('is linked from the footer, and the phone number appears there once set', function () {
        $this->get('/')->assertSee(route('contact'));

        app(Settings::class)->set('store.phone', '024 111 2222');
        $this->get('/')->assertSee('024 111 2222');
    });

    it('validates the contact settings in the admin', function () {
        $this->actingAs(userWithRole('admin'));

        $this->put('/admin/settings', ['store_whatsapp' => 'not a number', 'store_email' => 'nope'])->assertSessionHasErrors(['store_whatsapp', 'store_email']);
        $this->put('/admin/settings', ['store_phone' => '024 111 2222', 'store_whatsapp' => '+233241112222', 'store_email' => 'hi@example.test'])->assertSessionHasNoErrors();
        $this->get('/admin/settings')->assertSee('Store phone number')->assertSee('024 111 2222');
    });
});

describe('order again', function () {
    it('puts the same designs, sizes and names back in the cart', function () {
        $tee = Product::with(['variants.colour', 'colours', 'printAreas'])->where('slug', 'classic-t-shirt-standard')->first();
        $design = savedDesign($tee, roles: ROLES);
        $area = $design->areas()->first();
        $area->update(['state' => json_encode(['objects' => [['type' => 'i-text', 'text' => 'GO']]])]);
        addToCart($design, [
            ['variant_id' => variantId($tee, 'White', 'M'), 'qty' => 1, 'name' => 'Ama', 'number' => '7'],
            ['variant_id' => variantId($tee, 'White', 'L'), 'qty' => 1, 'name' => 'Kofi', 'number' => '9'],
        ], 'Soft print please');
        $this->post('/checkout', checkoutData());
        $order = Order::latest('id')->first();

        $this->post($order->url() ? "/orders/{$order->number}/reorder?t={$order->access_token}" : '')->assertRedirect(route('cart.index'));

        $line = CartLine::first();
        $copy = Design::find($line->design_id);
        expect($line->quantity())->toBe(2)->and($line->notes)->toBe('Soft print please')
            ->and(collect($line->items)->pluck('name')->all())->toBe(['Ama', 'Kofi'])
            ->and($copy->id)->not->toBe($design->id)->and($copy->group_key)->toBe($design->group_key)
            ->and($copy->areas()->count())->toBe(1)->and($copy->revises_order_line_id)->toBeNull();
        $this->get('/cart')->assertSee('Added 1 item')->assertSee('With names and numbers (2 people)');
    });

    it('skips products and sizes that are no longer offered and says so', function () {
        cartWith(3);
        $this->post('/checkout', checkoutData());
        $order = Order::latest('id')->first();
        $order->lines()->first()->areas()->update(['state' => json_encode(['objects' => [['type' => 'i-text']]])]);
        Product::where('slug', 'classic-t-shirt-standard')->update(['active' => false]);

        $this->post("/orders/{$order->number}/reorder?t={$order->access_token}")->assertRedirect(route('home'))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Nothing from this order') && str_contains($m, 'Classic T-shirt'));
        expect(CartLine::count())->toBe(0);
    });

    it('needs the secret link or the owner', function () {
        cartWith(3);
        $this->post('/checkout', checkoutData());
        $order = Order::latest('id')->first();

        $this->post("/orders/{$order->number}/reorder")->assertNotFound();
        $this->post("/orders/{$order->number}/reorder?t=wrong")->assertNotFound();
    });

    it('is offered on paid orders only', function () {
        cartWith(3);
        $this->post('/checkout', checkoutData());
        $order = Order::latest('id')->first();

        $this->get($order->url())->assertDontSee('Order the same again');

        webhook(chargeSuccess(Payment::where('order_id', $order->id)->first()));
        $this->get($order->url())->assertSee('Order the same again');
    });
});

describe('staff:create', function () {
    it('creates a print shop login that can sign in', function () {
        $this->artisan('staff:create shop@print.test shop --name="Print shop" --password=secret-pass-123')->expectsOutputToContain('Created shop login')->assertSuccessful();

        $user = User::where('email', 'shop@print.test')->first();
        expect($user->hasRole('shop'))->toBeTrue()->and($user->hasVerifiedEmail())->toBeTrue()->and($user->name)->toBe('Print shop');

        $this->post('/login', ['email' => 'shop@print.test', 'password' => 'secret-pass-123'])->assertRedirect('/shop');
    });

    it('generates a password when none is given', function () {
        $this->artisan('staff:create boss@print.test admin')->expectsOutputToContain('Password:')->assertSuccessful();

        expect(User::where('email', 'boss@print.test')->first()->hasRole('admin'))->toBeTrue();
    });

    it('refuses bad input and will not overwrite an existing user without --reset', function () {
        $this->artisan('staff:create x@print.test customer')->assertFailed();
        $this->artisan('staff:create not-an-email shop')->assertFailed();
        $this->artisan('staff:create x@print.test shop --password=short')->assertFailed();

        $this->artisan('staff:create x@print.test shop --password=first-password-1')->assertSuccessful();
        $this->artisan('staff:create x@print.test admin --password=second-password-2')->assertFailed();
        expect(User::where('email', 'x@print.test')->first()->hasRole('shop'))->toBeTrue();

        $this->artisan('staff:create x@print.test admin --password=second-password-2 --reset')->assertSuccessful();
        $user = User::where('email', 'x@print.test')->first();
        expect($user->hasRole('admin'))->toBeTrue()->and($user->hasRole('shop'))->toBeFalse();
        $this->post('/login', ['email' => 'x@print.test', 'password' => 'second-password-2'])->assertRedirect('/admin');
    });
});

describe('landing page for individuals and groups', function () {
    it('explains both ways to order and shows a real bulk price', function () {
        $this->get('/')->assertOk()->assertSee('Made for one, priced for many')->assertSee('Just for you')->assertSee('For your school, church, team or company')
            ->assertSee('Add a name and number for each person')->assertSee('GH₵ 55')->assertSee('GH₵ 34');
    });

    it('still renders with an empty catalogue', function () {
        Product::query()->delete();

        $this->get('/')->assertOk()->assertSee('Made for one, priced for many');
    });
});
