<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Creates (or resets) an admin or print shop login from the command line, which is
 * what you have over SSH or cPanel Terminal.
 */
#[Signature('staff:create {email : Email address to sign in with} {role=shop : admin or shop} {--name= : Display name} {--password= : Use this password instead of generating one} {--reset : Reset the password of an existing user}')]
#[Description('Create an admin or print shop login (prints the password once)')]
class CreateStaffUser extends Command
{
    public function handle(): int
    {
        $role = (string) $this->argument('role');
        $email = (string) $this->argument('email');

        if (! in_array($role, ['admin', 'shop'], true)) {
            $this->error('The role must be admin or shop.');

            return self::FAILURE;
        }

        if (Validator::make(['email' => $email], ['email' => ['required', 'email:rfc']])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: Str::password(16, symbols: false));

        if (strlen($password) < 8) {
            $this->error('The password must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user && ! $this->option('reset')) {
            $this->error("{$email} already exists. Use --reset to set a new password and role.");

            return self::FAILURE;
        }

        Role::findOrCreate($role, 'web');

        $user ??= new User(['email' => $email]);
        $user->fill(['name' => $this->option('name') ?: ($user->name ?: ucfirst($role)), 'password' => $password])->save();
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();
        $user->syncRoles([$role]);

        $this->info("Created {$role} login.");
        $this->line("  Email:    {$email}");
        $this->line("  Password: {$password}");
        $this->warn('Store the password now; it is not shown again.');

        return self::SUCCESS;
    }
}
