<?php

namespace App\Support;

use GdImage;
use RuntimeException;

/**
 * Turns a plain product photo (one garment on an even background) into a transparent
 * mockup in any colour. The garment is cut out from the background, and the photo's own
 * light and shade (folds, seams, collar) are kept and tinted with the chosen colour,
 * so the designer shows a real garment with the customer's artwork on it.
 */
class PhotoMockup
{
    /** @var array<string, array<string, mixed>> */
    private static array $sources = [];

    /** @var array<string, string> */
    private static array $cache = [];

    /**
     * @param  string  $path  photo path relative to public/ (e.g. media/tshirt.webp)
     * @param  list<array{0: float, 1: float, 2: float, 3: float}>  $patches  [x0, y0, x1, y1] fractions of the picture to smooth over (a small label)
     * @return string PNG bytes
     */
    public static function make(string $path, string $hex, int $width = 600, array $patches = []): string
    {
        $key = $path.'|'.$hex.'|'.$width.'|'.json_encode($patches);

        return self::$cache[$key] ??= self::render(self::source($path, $width, $patches), $hex);
    }

    /**
     * Where the garment sits in the picture, as fractions of its width and height.
     *
     * @return array{x0: float, y0: float, x1: float, y1: float, w: int, h: int}
     */
    public static function geometry(string $path, int $width = 600, array $patches = []): array
    {
        $s = self::source($path, $width, $patches);

        return ['x0' => $s['bbox'][0] / $s['w'], 'y0' => $s['bbox'][1] / $s['h'], 'x1' => $s['bbox'][2] / $s['w'], 'y1' => $s['bbox'][3] / $s['h'], 'w' => $s['w'], 'h' => $s['h']];
    }

    /** @return array<string, mixed> */
    private static function source(string $path, int $width, array $patches): array
    {
        $key = $path.'|'.$width.'|'.json_encode($patches);

        return self::$sources[$key] ??= self::analyse($path, $width, $patches);
    }

    /** @return array<string, mixed> */
    private static function analyse(string $path, int $width, array $patches): array
    {
        $full = public_path($path);
        $bytes = is_file($full) ? (string) file_get_contents($full) : '';
        $original = $bytes !== '' ? @imagecreatefromstring($bytes) : false;

        if (! $original instanceof GdImage) {
            throw new RuntimeException("Cannot read product photo [{$path}].");
        }

        $ow = imagesx($original);
        $oh = imagesy($original);
        $w = $width;
        $h = (int) round($oh * $w / $ow);

        $img = imagecreatetruecolor($w, $h);
        imagecopyresampled($img, $original, 0, 0, 0, 0, $w, $h, $ow, $oh);

        // Background colour: the average of the four corners.
        $bg = [0, 0, 0];
        $n = 0;

        foreach ([[0, 0], [$w - 8, 0], [0, $h - 8], [$w - 8, $h - 8]] as [$cx, $cy]) {
            for ($y = $cy; $y < $cy + 8; $y++) {
                for ($x = $cx; $x < $cx + 8; $x++) {
                    $c = imagecolorat($img, $x, $y);
                    $bg[0] += ($c >> 16) & 255;
                    $bg[1] += ($c >> 8) & 255;
                    $bg[2] += $c & 255;
                    $n++;
                }
            }
        }

        $bg = array_map(fn ($v) => $v / $n, $bg);

        $lum = new \SplFixedArray($w * $h);
        $isBgLike = new \SplFixedArray($w * $h);
        $threshold = 130.0;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($img, $x, $y);
                $r = ($c >> 16) & 255;
                $g = ($c >> 8) & 255;
                $b = $c & 255;
                $i = $y * $w + $x;
                $lum[$i] = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $isBgLike[$i] = sqrt(($r - $bg[0]) ** 2 + ($g - $bg[1]) ** 2 + ($b - $bg[2]) ** 2) <= $threshold;
            }
        }

        // Background = background-like pixels connected to the picture's edge (so light areas inside the garment stay).
        $outside = new \SplFixedArray($w * $h);
        $stack = [];

        for ($x = 0; $x < $w; $x++) {
            foreach ([$x, ($h - 1) * $w + $x] as $i) {
                if ($isBgLike[$i] && ! $outside[$i]) {
                    $outside[$i] = true;
                    $stack[] = $i;
                }
            }
        }

        for ($y = 0; $y < $h; $y++) {
            foreach ([$y * $w, $y * $w + $w - 1] as $i) {
                if ($isBgLike[$i] && ! $outside[$i]) {
                    $outside[$i] = true;
                    $stack[] = $i;
                }
            }
        }

        while ($stack) {
            $i = array_pop($stack);
            $x = $i % $w;

            foreach ([$x > 0 ? $i - 1 : -1, $x < $w - 1 ? $i + 1 : -1, $i - $w, $i + $w] as $j) {
                if ($j >= 0 && $j < $w * $h && $isBgLike[$j] && ! $outside[$j]) {
                    $outside[$j] = true;
                    $stack[] = $j;
                }
            }
        }

        // Hide small labels (a neck tag, a collar mark): blend the fabric on either side across them.
        foreach ($patches as [$px0, $py0, $px1, $py1]) {
            $xa = (int) ($px0 * $w);
            $xb = (int) ($px1 * $w);

            for ($y = (int) ($py0 * $h); $y < (int) ($py1 * $h); $y++) {
                $left = $lum[$y * $w + max(0, $xa - 3)];
                $right = $lum[$y * $w + min($w - 1, $xb + 3)];

                for ($x = $xa; $x < $xb; $x++) {
                    $lum[$y * $w + $x] = $left + ($right - $left) * (($x - $xa) / max(1, $xb - $xa));
                }
            }
        }

        // Garment brightness range, ignoring the extremes, to spread the shading evenly.
        $hist = array_fill(0, 256, 0);
        $garment = 0;
        [$x0, $y0, $x1, $y1] = [$w, $h, 0, 0];

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $i = $y * $w + $x;

                if ($outside[$i]) {
                    continue;
                }

                $hist[(int) min(255, $lum[$i])]++;
                $garment++;
                $x0 = min($x0, $x);
                $y0 = min($y0, $y);
                $x1 = max($x1, $x);
                $y1 = max($y1, $y);
            }
        }

        $percentile = function (float $p) use ($hist, $garment): int {
            $target = $garment * $p;
            $sum = 0;

            foreach ($hist as $value => $count) {
                $sum += $count;

                if ($sum >= $target) {
                    return $value;
                }
            }

            return 255;
        };

        return [
            'w' => $w, 'h' => $h, 'lum' => $lum, 'outside' => $outside,
            'lo' => $percentile(0.04), 'hi' => max($percentile(0.96), $percentile(0.04) + 8),
            'bbox' => [$x0, $y0, $x1, $y1],
        ];
    }

    /** @param  array<string, mixed>  $s */
    private static function render(array $s, string $hex): string
    {
        [$w, $h, $lum, $outside, $lo, $hi] = [$s['w'], $s['h'], $s['lum'], $s['outside'], $s['lo'], $s['hi']];

        $hex = ltrim($hex, '#');
        $target = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        // Light colours get gentler shadows so white still reads as white.
        $brightness = (0.299 * $target[0] + 0.587 * $target[1] + 0.114 * $target[2]) / 255;
        $shadowFactor = 0.60 + 0.32 * $brightness ** 2;
        $shadow = array_map(fn ($v) => $v * $shadowFactor, $target);
        $light = array_map(fn ($v) => min(255, $v * 1.10 + 14), $target);

        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, 127 << 24);

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $i = $y * $w + $x;

                if ($outside[$i]) {
                    continue;
                }

                $t = max(0.0, min(1.0, ($lum[$i] - $lo) / ($hi - $lo)));

                // A pixel touching the background is softened so the outline is not jagged.
                $edge = ($x > 0 && $outside[$i - 1]) || ($x < $w - 1 && $outside[$i + 1]) || ($y > 0 && $outside[$i - $w]) || ($y < $h - 1 && $outside[$i + $w]);
                $alpha = $edge ? 60 : 0;

                $r = (int) ($shadow[0] + ($light[0] - $shadow[0]) * $t);
                $g = (int) ($shadow[1] + ($light[1] - $shadow[1]) * $t);
                $b = (int) ($shadow[2] + ($light[2] - $shadow[2]) * $t);

                imagesetpixel($img, $x, $y, ($alpha << 24) | ($r << 16) | ($g << 8) | $b);
            }
        }

        ob_start();
        imagepng($img, null, 6);

        return (string) ob_get_clean();
    }
}
