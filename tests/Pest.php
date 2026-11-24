<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Create a verified user holding the given role (roles are seeded on demand).
 */
function userWithRole(string $role): User
{
    test()->seed(RoleSeeder::class);

    return tap(User::factory()->create())->assignRole($role);
}
