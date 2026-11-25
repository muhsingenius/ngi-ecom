<?php

namespace App\Support;

use App\Enums\ProductType;
use GdImage;

/**
 * Draws a flat, recolourable product silhouette (800x1000 PNG) used as a placeholder
 * mockup. Admins can replace any of these with real photos.
 */
class MockupGenerator
{
    public const WIDTH = 800;

    public const HEIGHT = 1000;

    /** @var array<string, string> */
    private static array $cache = [];

    private static int $outline = 0;

    public static function make(ProductType $type, string $hex): string
    {
        return self::$cache[$type->value.$hex] ??= self::draw($type, $hex);
    }

    private static function draw(ProductType $type, string $hex): string
    {
        $img = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagealphablending($img, true);

        [$r, $g, $b] = self::rgb($hex);
        $base = imagecolorallocate($img, $r, $g, $b);
        $shade = imagecolorallocate($img, (int) ($r * 0.82), (int) ($g * 0.82), (int) ($b * 0.82));
        self::$outline = imagecolorallocate($img, (int) ($r * 0.6), (int) ($g * 0.6), (int) ($b * 0.6));
        $light = imagecolorallocate($img, min(255, (int) ($r * 1.12) + 6), min(255, (int) ($g * 1.12) + 6), min(255, (int) ($b * 1.12) + 6));

        match ($type) {
            ProductType::TShirt => self::tee($img, $base, $shade),
            ProductType::Polo => self::polo($img, $base, $shade, $light),
            ProductType::Hoodie => self::hoodie($img, $base, $shade),
            ProductType::Cap => self::cap($img, $base, $shade),
            ProductType::Bracelet => self::bracelet($img, $base, $shade, $light),
        };

        ob_start();
        imagepng($img, null, 6);

        return (string) ob_get_clean();
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /** @param list<int> $points */
    private static function poly(GdImage $img, array $points, int $colour): void
    {
        imagefilledpolygon($img, $points, $colour);
        imagesetthickness($img, 4);
        imagepolygon($img, $points, self::$outline);
        imagesetthickness($img, 1);
    }

    private static function tee(GdImage $img, int $base, int $shade): void
    {
        self::poly($img, [300, 90, 150, 150, 40, 310, 130, 380, 185, 335, 185, 920, 615, 920, 615, 335, 670, 380, 760, 310, 650, 150, 500, 90, 450, 150, 350, 150], $base);
        imagefilledellipse($img, 400, 100, 200, 120, self::$outline);
        imagefilledellipse($img, 400, 96, 188, 108, $shade);
    }

    private static function polo(GdImage $img, int $base, int $shade, int $light): void
    {
        self::tee($img, $base, $shade);
        self::poly($img, [330, 80, 400, 150, 360, 190, 290, 120], $light);
        self::poly($img, [470, 80, 400, 150, 440, 190, 510, 120], $light);
        imagefilledrectangle($img, 394, 150, 406, 330, $shade);

        foreach ([200, 260, 315] as $y) {
            imagefilledellipse($img, 400, $y, 14, 14, $light);
        }
    }

    private static function hoodie(GdImage $img, int $base, int $shade): void
    {
        self::poly($img, [260, 110, 130, 170, 20, 560, 110, 590, 190, 360, 195, 920, 605, 920, 610, 360, 690, 590, 780, 560, 670, 170, 540, 110, 450, 160, 350, 160], $base);
        imagefilledellipse($img, 400, 110, 300, 190, $shade);
        imagefilledellipse($img, 400, 140, 190, 140, $base);
        self::poly($img, [290, 690, 510, 690, 540, 820, 260, 820], $shade);
        imagesetthickness($img, 6);
        imageline($img, 360, 160, 360, 300, $shade);
        imageline($img, 440, 160, 440, 300, $shade);
    }

    private static function cap(GdImage $img, int $base, int $shade): void
    {
        imagefilledellipse($img, 400, 520, 560, 560, $base);
        imagefilledrectangle($img, 120, 520, 680, 640, $base);
        imagefilledellipse($img, 400, 650, 760, 150, $shade);
        imagefilledellipse($img, 400, 640, 700, 100, $base);
        imagefilledellipse($img, 400, 252, 30, 30, $shade);
    }

    private static function bracelet(GdImage $img, int $base, int $shade, int $light): void
    {
        imagefilledrectangle($img, 60, 420, 740, 580, $base);
        imagefilledellipse($img, 60, 500, 90, 160, $base);
        imagefilledellipse($img, 740, 500, 90, 160, $base);
        imagefilledrectangle($img, 60, 420, 740, 432, $light);
        imagefilledrectangle($img, 60, 568, 740, 580, $shade);
    }
}
