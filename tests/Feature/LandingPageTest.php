<?php

use App\Services\Settings;

it('renders every landing section with its anchor', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Your design, printed on things worth keeping')
        ->assertSee('id="design"', false)
        ->assertSee('id="products"', false)
        ->assertSee('id="how-it-works"', false)
        ->assertSee('How ordering works')
        ->assertSee('Products you can print on');
});

it('lists the product range', function () {
    $this->get('/')->assertSeeInOrder(['T-shirts', 'Mugs', 'Hoodies', 'Caps', 'Tote bags', 'Stickers']);
});

it('shows the configured turnaround from settings', function () {
    app(Settings::class)->set('orders.turnaround_days', 9);

    $this->get('/')->assertSee('within 9 days');
});

it('offers sign-up and login links to guests', function () {
    $this->get('/')
        ->assertSee(route('register'))
        ->assertSee(route('login'))
        ->assertSee('Create account');
});

it('hides guest calls to action once signed in', function () {
    $this->actingAs(userWithRole('customer'))
        ->get('/')
        ->assertOk()
        ->assertDontSee('Ready to make something of your own?')
        ->assertSee('Log out');
});

it('shows staff links only to the matching role', function () {
    $this->actingAs(userWithRole('admin'))->get('/')->assertSee(route('admin.dashboard'));
    $this->actingAs(userWithRole('shop'))->get('/')->assertSee(route('shop.jobs'));
    $this->actingAs(userWithRole('customer'))->get('/')->assertDontSee(route('admin.dashboard'));
});

it('only links to anchors that exist on the page', function () {
    $html = $this->get('/')->getContent();

    preg_match_all('/href="[^"#]*#([\w-]+)"/', $html, $matches);

    foreach (array_unique($matches[1]) as $anchor) {
        expect($html)->toContain('id="'.$anchor.'"');
    }
});
