<?php

use App\Models\Order;

it('is branded PRINTABLE', function () {
    expect(config('app.name'))->toBe('PRINTABLE');

    $this->get('/')->assertSee('PRINTABLE')->assertDontSee('POD Store');
    $this->get('/products')->assertSee('<title>Products · PRINTABLE</title>', false);
});

it('numbers orders with the configured prefix', function () {
    config(['shop.order_prefix' => 'PRT']);

    $order = Order::create(['customer_name' => 'A', 'phone' => '1', 'email' => 'a@b.test', 'fulfilment' => 'pickup', 'subtotal' => 1, 'total' => 1, 'quantity' => 1, 'tier_scope' => 'order']);

    expect($order->fresh()->number)->toBe('PRT-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT));
});

describe('about page', function () {
    it('introduces the store and its parent social enterprise', function () {
        $this->get('/about')->assertOk()
            ->assertSee('Printing with a purpose')
            ->assertSee('social enterprise')
            ->assertSee('Northern Girl Initiative')
            ->assertSee('registered in Canada and Ghana')
            ->assertSee('founded in 2016')
            ->assertSee('northerngirlinitiative.org', false)
            ->assertSee('Donate to NGI');
    });

    it('is linked from the header and the footer', function () {
        $this->get('/')->assertSee(route('about'));
    });
});

describe('legal pages', function () {
    it('publish a privacy policy that fits what the store really does', function () {
        $this->get('/privacy')->assertOk()
            ->assertSee('Privacy policy')->assertSee('Data Protection Act, 2012 (Act 843)')
            ->assertSee('Paystack')->assertSee('never see or store your card number')
            ->assertSee('deleted automatically after 30 days')
            ->assertSee('do not sell it')->assertSee('under 18');
    });

    it('publish a data protection page with rights and complaints', function () {
        $this->get('/data-protection')->assertOk()
            ->assertSee('Data protection')->assertSee('Private file storage')->assertSee('Your rights')
            ->assertSee('Data Protection Commission of Ghana')->assertSee('How to make a request');
    });

    it('point to the contact page until the store adds its own details', function () {
        $this->get('/privacy')->assertSee(route('contact'));

        app(App\Services\Settings::class)->set('store.email', 'privacy@example.test');
        app(App\Services\Settings::class)->set('store.phone', '024 111 2222');

        $this->get('/privacy')->assertSee('mailto:privacy@example.test', false)->assertSee('tel:0241112222', false);
        $this->get('/data-protection')->assertSee('privacy@example.test');
    });

    it('show the date they were last updated', function () {
        $this->get('/privacy')->assertSee('Last updated '.App\Http\Controllers\PageController::LEGAL_UPDATED);
    });

    it('are linked from the register and checkout forms', function () {
        $this->get('/register')->assertSee(route('privacy'))->assertSee(route('data-protection'));
    });
});

describe('footer', function () {
    it('lists the legal pages, credits the developer and the parent organisation', function () {
        $this->get('/')
            ->assertSee(route('privacy'))->assertSee(route('data-protection'))->assertSee(route('about'))
            ->assertSee('Developed by')->assertSee('Gadabsi Infotech')->assertSee('0248724619')->assertSee('tel:0248724619', false)
            ->assertSee('A social enterprise of')->assertSee('Northern Girl Initiative');
    });

    it('appears on the legal pages too', function () {
        foreach (['/about', '/privacy', '/data-protection', '/contact', '/products'] as $path) {
            $this->get($path)->assertSee('Gadabsi Infotech');
        }
    });
});
