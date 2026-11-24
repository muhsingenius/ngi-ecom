<?php

use App\Models\Setting;
use App\Services\Settings;
use Database\Seeders\SettingsSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(fn () => $this->settings = app(Settings::class));

it('returns the defined defaults when nothing is stored', function () {
    expect($this->settings->string('pricing.tier_scope'))->toBe('product_design')
        ->and($this->settings->int('proof.threshold'))->toBe(20)
        ->and($this->settings->string('proof.timing'))->toBe('pay_then_proof')
        ->and($this->settings->int('proof.max_revisions'))->toBe(3)
        ->and($this->settings->int('proof.unpaid_expiry_days'))->toBe(7)
        ->and($this->settings->float('delivery.free_threshold'))->toBeNull()
        ->and($this->settings->string('shop.notification_email'))->toBeNull()
        ->and($this->settings->int('orders.turnaround_days'))->toBe(5);
});

it('seeds every setting with its default and keeps existing values on re-seed', function () {
    $this->seed(SettingsSeeder::class);

    expect(Setting::count())->toBe(count(Settings::definitions()));

    $this->settings->set('proof.max_revisions', 6);
    $this->seed(SettingsSeeder::class);

    expect($this->settings->int('proof.max_revisions'))->toBe(6);
});

it('stores valid values and serves them from the refreshed cache', function () {
    $this->settings->get('proof.max_revisions'); // warm the cache

    $this->settings->set('proof.max_revisions', '4');
    $this->settings->set('pricing.tier_scope', 'order');
    $this->settings->set('shop.notification_email', 'shop@example.test');

    expect($this->settings->int('proof.max_revisions'))->toBe(4)
        ->and($this->settings->string('pricing.tier_scope'))->toBe('order')
        ->and($this->settings->string('shop.notification_email'))->toBe('shop@example.test');
});

it('turns the proof threshold off with null', function () {
    $this->settings->set('proof.threshold', null);

    expect($this->settings->int('proof.threshold'))->toBeNull();
});

it('rejects invalid values', function (string $key, mixed $value) {
    expect(fn () => $this->settings->set($key, $value))->toThrow(ValidationException::class);
})->with([
    'bad tier scope' => ['pricing.tier_scope', 'nonsense'],
    'bad proof timing' => ['proof.timing', 'whenever'],
    'negative threshold' => ['proof.threshold', -1],
    'non-integer revisions' => ['proof.max_revisions', 'many'],
    'zero expiry days' => ['proof.unpaid_expiry_days', 0],
    'negative free threshold' => ['delivery.free_threshold', -5],
    'invalid email' => ['shop.notification_email', 'not-an-email'],
]);

it('does not store a rejected value', function () {
    try {
        $this->settings->set('proof.timing', 'whenever');
    } catch (ValidationException) {
    }

    expect($this->settings->string('proof.timing'))->toBe('pay_then_proof');
});

it('throws for unknown keys', function () {
    expect(fn () => $this->settings->get('nope.nothing'))->toThrow(InvalidArgumentException::class);
});
