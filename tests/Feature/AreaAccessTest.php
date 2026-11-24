<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

it('redirects guests from /admin and /shop to login', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/admin', '/shop']);

it('returns 403 for a customer on /admin and /shop', function (string $path) {
    $this->actingAs(userWithRole('customer'))->get($path)->assertForbidden();
})->with(['/admin', '/shop']);

it('returns 403 for a shop user on /admin and an admin on /shop', function () {
    $this->actingAs(userWithRole('shop'))->get('/admin')->assertForbidden();
    $this->actingAs(userWithRole('admin'))->get('/shop')->assertForbidden();
});

it('lets an admin reach /admin', function () {
    $this->actingAs(userWithRole('admin'))->get('/admin')->assertOk()->assertSee('Admin dashboard');
});

it('lets a shop user reach /shop', function () {
    $this->actingAs(userWithRole('shop'))->get('/shop')->assertOk()->assertSee('Job list');
});

it('redirects each role to its own area after login', function (string $role, string $expected) {
    $user = userWithRole($role);

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect($expected);
})->with([
    'admin' => ['admin', '/admin'],
    'shop' => ['shop', '/shop'],
    'customer' => ['customer', '/'],
]);

it('sends signed-in staff away from the login page to their area', function () {
    $this->actingAs(userWithRole('admin'))->get('/login')->assertRedirect('/admin');
});

it('shows the verification notice to unverified users and blocks staff areas until verified', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->unverified()->create()->assignRole('admin');

    $this->actingAs($admin)->get('/admin')->assertRedirect(route('verification.notice'));
    $this->actingAs($admin)->get(route('verification.notice'))->assertOk();
});

it('renders the auth pages', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['login', 'register', 'password.request']);
