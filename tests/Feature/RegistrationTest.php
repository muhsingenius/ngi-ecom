<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(fn () => $this->seed(RoleSeeder::class));

it('registers new users with the customer role', function () {
    Notification::fake();

    $this->post('/register', [
        'name' => 'Ama Mensah',
        'email' => 'ama@example.test',
        'password' => 'a-Long-passw0rd!',
        'password_confirmation' => 'a-Long-passw0rd!',
    ])->assertRedirect('/');

    $user = User::where('email', 'ama@example.test')->firstOrFail();

    expect($user->getRoleNames()->all())->toBe(['customer'])
        ->and($user->hasVerifiedEmail())->toBeFalse();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects a registration with a mismatched password', function () {
    $this->post('/register', [
        'name' => 'Ama Mensah',
        'email' => 'ama@example.test',
        'password' => 'a-Long-passw0rd!',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');

    expect(User::count())->toBe(0);
});

it('logs a user out', function () {
    $this->actingAs(userWithRole('customer'))->post('/logout')->assertRedirect('/');

    $this->assertGuest();
});
