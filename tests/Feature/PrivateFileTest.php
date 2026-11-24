<?php

use App\Support\PrivateFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('designs');
    Storage::disk('designs')->put('orders/1/art.png', 'png-bytes');
});

it('serves a private file through a valid signed url', function () {
    $this->get(PrivateFile::url('orders/1/art.png'))
        ->assertOk()
        ->assertDownload('art.png');
});

it('never exposes the designs disk publicly', function () {
    expect(config('filesystems.disks.designs.root'))->toBe(storage_path('app/designs'))
        ->and(config('filesystems.disks.designs.visibility'))->toBe('private')
        ->and(config('filesystems.links'))->not->toHaveKey(storage_path('app/designs'));
});

it('rejects an unsigned url', function () {
    $this->get(route('files.download', ['disk' => 'designs', 'path' => 'orders/1/art.png']))
        ->assertForbidden();
});

it('rejects a tampered url', function () {
    Storage::disk('designs')->put('orders/2/secret.png', 'secret');

    $url = PrivateFile::url('orders/1/art.png');

    $this->get(str_replace('orders%2F1%2Fart.png', 'orders%2F2%2Fsecret.png', $url))->assertForbidden();
});

it('rejects an expired url', function () {
    $url = PrivateFile::url('orders/1/art.png', now()->addMinutes(5));

    $this->travel(6)->minutes();

    $this->get($url)->assertForbidden();
});

it('refuses disks that are not downloadable even with a valid signature', function () {
    $this->get(PrivateFile::url('orders/1/art.png', disk: 'local'))->assertNotFound();
});

it('returns 404 for a missing file or path traversal', function () {
    $this->get(PrivateFile::url('missing.png'))->assertNotFound();
    $this->get(PrivateFile::url('../.env'))->assertNotFound();
});
