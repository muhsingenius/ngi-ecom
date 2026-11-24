<?php

namespace App\Http\Controllers;

use App\Support\PrivateFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateFileController extends Controller
{
    /**
     * Stream a private file. The route is behind the `signed` middleware, so the
     * disk and path are tamper-proof and the link expires.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        $disk = (string) $request->query('disk');
        $path = (string) $request->query('path');

        abort_unless(in_array($disk, PrivateFile::DOWNLOADABLE_DISKS, true), 404);
        abort_if($path === '' || str_contains($path, '..') || str_contains($path, "\0"), 404);

        $storage = Storage::disk($disk);

        abort_unless($storage->exists($path), 404);

        return $storage->download($path);
    }
}
