<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class StaffUserSeeder extends Seeder
{
    /**
     * Create the first admin and shop users from SEED_* values (via config/shop.php).
     * A user is skipped if its email or password is empty, or if the email already exists.
     */
    public function run(): void
    {
        $this->seedUser('admin', 'Admin');
        $this->seedUser('shop', 'Shop');
    }

    private function seedUser(string $role, string $name): void
    {
        $email = config("shop.seed.{$role}_email");
        $password = config("shop.seed.{$role}_password");

        if (blank($email) || blank($password)) {
            $this->command?->warn("Skipping {$role} user: SEED_".strtoupper($role).'_EMAIL / _PASSWORD not set.');

            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );

        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
        $user->syncRoles([$role]);
    }
}
