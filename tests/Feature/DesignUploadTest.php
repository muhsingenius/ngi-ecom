<?php

use App\Models\DesignAsset;
use App\Services\SvgSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(fn () => Storage::fake('designs'));

function pngBytes(int $w = 200, int $h = 100): string
{
    $im = imagecreatetruecolor($w, $h);
    imagesavealpha($im, true);
    imagealphablending($im, false);
    imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    imagefilledrectangle($im, 10, 10, $w - 10, $h - 10, imagecolorallocate($im, 200, 30, 30));
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

function upload(string $name, string $content): TestResponse
{
    return test()->postJson('/design-assets', ['file' => UploadedFile::fake()->createWithContent($name, $content)]);
}

it('accepts a PNG, re-encodes it and returns a signed url', function () {
    $res = upload('logo.png', pngBytes(200, 100))->assertCreated();

    $asset = DesignAsset::first();
    expect($asset->kind)->toBe('raster')
        ->and($asset->mime)->toBe('image/png')
        ->and([$asset->width, $asset->height])->toBe([200, 100])
        ->and($res->json('url'))->toContain('signature=');

    Storage::disk('designs')->assertExists($asset->path);
    expect($asset->path)->toStartWith('uploads/s');
});

it('accepts a JPEG', function () {
    upload('photo.jpg', jpegBytes())->assertCreated()->assertJsonPath('kind', 'raster');

    expect(DesignAsset::first()->mime)->toBe('image/jpeg');
});

it('decides the type by content, not the file name', function () {
    upload('logo.png', "<?php echo 'pwned'; ?>")->assertStatus(422);
    upload('totally-an-image.png', 'MZ this is an executable')->assertStatus(422);
    upload('doc.png', '%PDF-1.4 fake')->assertStatus(422);

    expect(DesignAsset::count())->toBe(0);
});

it('stores a PNG that was named .txt as a PNG when the content is a real image', function () {
    upload('weird.txt', pngBytes())->assertCreated();

    expect(DesignAsset::first()->path)->toEndWith('.png');
});

it('rejects files over the size limit', function () {
    config(['designer.max_upload_kb' => 1]);

    upload('big.png', pngBytes(400, 400))->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'too large'));
});

it('rejects a corrupt image', function () {
    upload('broken.png', substr(pngBytes(), 0, 40))->assertStatus(422);
});

it('requires a file', function () {
    $this->postJson('/design-assets', [])->assertStatus(422);
});

describe('svg', function () {
    $evil = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="100" height="80" onload="alert(1)">
  <script>alert('xss')</script>
  <foreignObject><div>hi</div></foreignObject>
  <image href="https://evil.example/track.png"/>
  <style>@import url(https://evil.example/x.css);</style>
  <a xlink:href="javascript:alert(1)"><rect x="0" y="0" width="50" height="50" fill="#ff0000" onclick="alert(2)"/></a>
  <circle cx="60" cy="40" r="20" fill="url(https://evil.example/f)" stroke="#00f"/>
  <use xlink:href="#ok"/><use xlink:href="https://evil.example/a.svg#x"/>
  <path id="ok" d="M0 0 L10 10" fill="#00ff00"/>
</svg>
SVG;

    it('strips scripts, handlers, external references and embedded content', function () use ($evil) {
        $out = (new SvgSanitizer)->sanitize($evil);

        expect($out['svg'])
            ->not->toContain('script')->not->toContain('onload')->not->toContain('onclick')
            ->not->toContain('foreignObject')->not->toContain('<image')->not->toContain('<style')
            ->not->toContain('javascript:')->not->toContain('evil.example')
            ->toContain('<rect')->toContain('<path')->toContain('#ok');
    });

    it('is stored sanitised and sized', function () use ($evil) {
        upload('art.svg', $evil)->assertCreated()->assertJsonPath('kind', 'svg')->assertJsonPath('width', 100);

        $stored = Storage::disk('designs')->get(DesignAsset::first()->path);
        expect($stored)->not->toContain('script')->not->toContain('onload');
    });

    it('rejects DOCTYPE and entity tricks', function () {
        $xxe = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><text>&x;</text></svg>';

        upload('xxe.svg', $xxe)->assertStatus(422);
    });

    it('rejects things that are not SVG and SVGs without a size', function () {
        expect(fn () => (new SvgSanitizer)->sanitize('<html><body>hi</body></html>'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => (new SvgSanitizer)->sanitize('<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>'))->toThrow(InvalidArgumentException::class);
    });

    it('derives the size from the viewBox when width and height are missing', function () {
        $out = (new SvgSanitizer)->sanitize('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150"><rect width="10" height="10"/></svg>');

        expect([$out['width'], $out['height']])->toBe([300.0, 150.0]);
    });
});

it('serves uploads only through signed urls and never from the public disk', function () {
    $url = upload('logo.png', pngBytes())->json('url');
    $asset = DesignAsset::first();

    $this->get($url)->assertOk();
    $this->get(route('files.download', ['disk' => 'designs', 'path' => $asset->path]))->assertForbidden();
    expect(Storage::disk('public')->exists($asset->path))->toBeFalse();
});

it('refreshes signed urls only for assets the visitor owns', function () {
    $id = upload('logo.png', pngBytes())->json('id');

    $this->getJson('/design-assets/urls?ids='.$id)->assertOk()->assertJsonStructure([$id]);

    $this->flushSession();
    $this->getJson('/design-assets/urls?ids='.$id)->assertOk()->assertExactJson([]);
});

it('rate limits uploads', function () {
    foreach (range(1, 30) as $i) {
        upload('a.png', pngBytes(20, 20))->assertCreated();
    }

    upload('a.png', pngBytes(20, 20))->assertStatus(429);
});
