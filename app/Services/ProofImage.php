<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderArea;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Composes the proof sheet: one tile per print area, showing the customer's artwork
 * placed on the product mockup exactly where it will be printed.
 */
class ProofImage
{
    private const TILE_W = 560;

    private const TILE_H = 700;

    private const GAP = 24;

    private const MAX_TILES = 12;

    /** @return string JPEG bytes */
    public function compose(Order $order): string
    {
        $areas = OrderArea::whereIn('order_line_id', $order->lines()->pluck('id'))->orderBy('order_line_id')->orderBy('id')->limit(self::MAX_TILES)->get();
        $count = max($areas->count(), 1);
        $cols = min($count, 3);
        $rows = (int) ceil($count / $cols);

        $sheet = imagecreatetruecolor($cols * self::TILE_W + ($cols + 1) * self::GAP, $rows * self::TILE_H + ($rows + 1) * self::GAP);
        imagefill($sheet, 0, 0, imagecolorallocate($sheet, 246, 247, 251));

        foreach ($areas->values() as $i => $area) {
            $x = self::GAP + ($i % $cols) * (self::TILE_W + self::GAP);
            $y = self::GAP + intdiv($i, $cols) * (self::TILE_H + self::GAP);
            $this->tile($sheet, $area, $x, $y);
        }

        ob_start();
        imagejpeg($sheet, null, 88);

        return (string) ob_get_clean();
    }

    private function tile(GdImage $sheet, OrderArea $area, int $x, int $y): void
    {
        $tile = imagecreatetruecolor(self::TILE_W, self::TILE_H);
        imagefill($tile, 0, 0, imagecolorallocate($tile, 255, 255, 255));

        $mockup = $this->load($area->mockup_path);

        if ($mockup) {
            $this->fit($tile, $mockup, 0, 0, self::TILE_W, self::TILE_H);
            $mw = imagesx($mockup);
            $mh = imagesy($mockup);

            // The mockup is drawn contained inside the tile; map the print box onto that drawn rectangle.
            $scale = min(self::TILE_W / $mw, self::TILE_H / $mh);
            $drawW = $mw * $scale;
            $drawH = $mh * $scale;
            $originX = (self::TILE_W - $drawW) / 2;
            $originY = (self::TILE_H - $drawH) / 2;

            $boxX = (int) round($originX + $drawW * $area->box_x / 100);
            $boxY = (int) round($originY + $drawH * $area->box_y / 100);
            $boxW = (int) round($drawW * $area->box_w / 100);
            $boxH = (int) round($drawH * $area->box_h / 100);
        } else {
            [$boxX, $boxY, $boxW, $boxH] = [60, 100, self::TILE_W - 120, self::TILE_H - 200];
        }

        if ($art = $this->load($area->preview_path ?: $area->export_path)) {
            $this->fit($tile, $art, $boxX, $boxY, $boxW, $boxH);
        }

        imagecopy($sheet, $tile, $x, $y, 0, 0, self::TILE_W, self::TILE_H);
    }

    private function load(?string $path): ?GdImage
    {
        if (! $path || ! Storage::disk('designs')->exists($path)) {
            return null;
        }

        $image = @imagecreatefromstring(Storage::disk('designs')->get($path));

        return $image ?: null;
    }

    /** Draw $source into the box, keeping its proportions and centring it, with alpha preserved. */
    private function fit(GdImage $dest, GdImage $source, int $bx, int $by, int $bw, int $bh): void
    {
        $sw = imagesx($source);
        $sh = imagesy($source);
        $scale = min($bw / $sw, $bh / $sh);
        $w = max(1, (int) round($sw * $scale));
        $h = max(1, (int) round($sh * $scale));

        imagealphablending($dest, true);
        imagecopyresampled($dest, $source, $bx + intdiv($bw - $w, 2), $by + intdiv($bh - $h, 2), 0, 0, $w, $h, $sw, $sh);
    }
}
