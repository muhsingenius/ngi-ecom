<?php

use App\Services\Settings;

it('declares a type, default and rules for every setting', function () {
    foreach (Settings::definitions() as $key => $definition) {
        expect($definition)->toHaveKeys(['type', 'default', 'rules'], $key)
            ->and($definition['type'])->toBeIn(['string', 'int', 'float']);
    }
});

it('defines exactly the expected setting keys', function () {
    expect(array_keys(Settings::definitions()))->toBe([
        'pricing.tier_scope',
        'proof.threshold',
        'proof.timing',
        'proof.max_revisions',
        'proof.unpaid_expiry_days',
        'delivery.free_threshold',
        'shop.notification_email',
        'orders.turnaround_days',
    ]);
});
