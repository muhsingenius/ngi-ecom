<?php

namespace App\Services;

use App\Models\DesignAsset;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use InvalidArgumentException;
use Throwable;

/**
 * Turns a customer's upload into a safe stored asset. The file type is decided by
 * its content (never its name or the browser's claim), raster images are decoded
 * and re-encoded (dropping metadata and anything hidden in the file), and SVGs are
 * passed through an allow-list sanitiser.
 */
class DesignUploads
{
    private const MAX_SIDE = 12000;

    public function __construct(private SvgSanitizer $svg) {}

    public function store(UploadedFile $file, Request $request): DesignAsset
    {
        $limit = (int) config('designer.max_upload_kb') * 1024;

        if ($file->getSize() > $limit) {
            throw new InvalidArgumentException('That file is too large. The limit is '.round($limit / 1048576).' MB.');
        }

        $contents = (string) file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        // finfo reports plain XML-ish SVGs as text/xml, text/plain or image/svg+xml.
        $looksLikeSvg = in_array($mime, ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'], true)
            && preg_match('/<svg[\s>]/i', substr($contents, 0, 4096));

        if ($looksLikeSvg) {
            return $this->storeSvg($contents, $file, $request);
        }

        if (in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return $this->storeRaster($contents, $mime, $file, $request);
        }

        throw new InvalidArgumentException('Upload a PNG, JPG or SVG image.');
    }

    private function storeSvg(string $contents, UploadedFile $file, Request $request): DesignAsset
    {
        $clean = $this->svg->sanitize($contents);
        $path = $this->path($request, 'svg');

        Storage::disk('designs')->put($path, $clean['svg']);

        return $this->record($request, $path, $file, 'image/svg+xml', 'svg', (int) round($clean['width']), (int) round($clean['height']), strlen($clean['svg']));
    }

    private function storeRaster(string $contents, string $mime, UploadedFile $file, Request $request): DesignAsset
    {
        try {
            $image = Image::decodeBinary($contents)->orient();
        } catch (Throwable) {
            throw new InvalidArgumentException('That image could not be read. Try saving it again as a PNG or JPG.');
        }

        if ($image->width() > self::MAX_SIDE || $image->height() > self::MAX_SIDE) {
            throw new InvalidArgumentException('That image is too large in pixels. The maximum is '.self::MAX_SIDE.' px on a side.');
        }

        $png = $mime === 'image/png';
        $encoded = $png ? $image->encodeUsingMediaType('image/png') : $image->encodeUsingMediaType('image/jpeg', quality: 92);
        $bytes = (string) $encoded;
        $path = $this->path($request, $png ? 'png' : 'jpg');

        Storage::disk('designs')->put($path, $bytes);

        return $this->record($request, $path, $file, $png ? 'image/png' : 'image/jpeg', 'raster', $image->width(), $image->height(), strlen($bytes));
    }

    private function path(Request $request, string $extension): string
    {
        $owner = $request->user()?->id ? 'u'.$request->user()->id : 's'.substr((string) $request->session()->get('designer_token'), 0, 12);

        return "uploads/{$owner}/".Str::uuid().'.'.$extension;
    }

    private function record(Request $request, string $path, UploadedFile $file, string $mime, string $kind, int $width, int $height, int $bytes): DesignAsset
    {
        return DesignAsset::create([
            'user_id' => $request->user()?->id,
            'session_token' => $request->user() ? null : $request->session()->get('designer_token'),
            'path' => $path,
            'original_name' => Str::limit(preg_replace('/[^\w .\-]/u', '', $file->getClientOriginalName()) ?: 'image', 120, ''),
            'mime' => $mime,
            'kind' => $kind,
            'width' => $width,
            'height' => $height,
            'bytes' => $bytes,
        ]);
    }
}
