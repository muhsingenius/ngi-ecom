<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/** A transparent PNG with a coloured block, at the given size. */
function pngBytes(int $w = 200, int $h = 100): string
{
    $im = imagecreatetruecolor($w, $h);
    imagesavealpha($im, true);
    imagealphablending($im, false);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    imagefilledrectangle($im, 10, 10, max(11, $w - 10), max(11, $h - 10), imagecolorallocate($im, 200, 30, 30));
    ob_start();
    imagepng($im);

    return (string) ob_get_clean();
}

function jpegBytes(int $w = 120, int $h = 80): string
{
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 200));
    ob_start();
    imagejpeg($im);

    return (string) ob_get_clean();
}

/**
 * Create a verified user holding the given role (roles are seeded on demand).
 */
function userWithRole(string $role): User
{
    test()->seed(RoleSeeder::class);

    return tap(User::factory()->create())->assignRole($role);
}
