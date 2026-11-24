<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\URL;

class PrivateFile
{
    /**
     * Disks that may be served through the signed download route.
     *
     * @var list<string>
     */
    public const DOWNLOADABLE_DISKS = ['designs'];

    /**
     * Build a signed, expiring URL to a file on a private disk.
     */
    public static function url(string $path, ?DateTimeInterface $expiresAt = null, string $disk = 'designs'): string
    {
        return URL::temporarySignedRoute(
            'files.download',
            $expiresAt ?? now()->addMinutes((int) config('shop.private_url_ttl_minutes', 15)),
            ['disk' => $disk, 'path' => $path],
        );
    }
}
