<?php

namespace App\Support;

/**
 * Small helpers for PNG bytes, used to validate and label print-ready exports.
 */
class Png
{
    private const SIGNATURE = "\x89PNG\r\n\x1a\n";

    /**
     * Width and height read from the IHDR chunk, or null when the bytes are not a PNG.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function dimensions(string $bytes): ?array
    {
        if (strlen($bytes) < 33 || ! str_starts_with($bytes, self::SIGNATURE) || substr($bytes, 12, 4) !== 'IHDR') {
            return null;
        }

        $size = unpack('Nw/Nh', substr($bytes, 16, 8));

        return $size['w'] > 0 && $size['h'] > 0 ? [$size['w'], $size['h']] : null;
    }

    /**
     * Write the print resolution into a pHYs chunk so the shop's software opens the
     * file at its real physical size.
     */
    public static function withDpi(string $bytes, int $dpi): string
    {
        if (self::dimensions($bytes) === null) {
            return $bytes;
        }

        $perMetre = (int) round($dpi / 0.0254);
        $data = pack('NNC', $perMetre, $perMetre, 1);
        $chunk = pack('N', 9).'pHYs'.$data.pack('N', crc32('pHYs'.$data));

        // Drop any existing pHYs chunk, then insert ours straight after IHDR (8 + 25 bytes in).
        $bytes = self::withoutChunk($bytes, 'pHYs');

        return substr($bytes, 0, 33).$chunk.substr($bytes, 33);
    }

    public static function dpi(string $bytes): ?int
    {
        $pos = 8;
        $length = strlen($bytes);

        while ($pos + 12 <= $length) {
            $size = unpack('N', substr($bytes, $pos, 4))[1];
            $type = substr($bytes, $pos + 4, 4);

            if ($type === 'pHYs') {
                $data = unpack('Nx/Ny/Cunit', substr($bytes, $pos + 8, 9));

                return $data['unit'] === 1 ? (int) round($data['x'] * 0.0254) : null;
            }

            $pos += 12 + $size;
        }

        return null;
    }

    private static function withoutChunk(string $bytes, string $name): string
    {
        $out = substr($bytes, 0, 8);
        $pos = 8;
        $length = strlen($bytes);

        while ($pos + 12 <= $length) {
            $size = unpack('N', substr($bytes, $pos, 4))[1];
            $type = substr($bytes, $pos + 4, 4);

            if ($type !== $name) {
                $out .= substr($bytes, $pos, 12 + $size);
            }

            $pos += 12 + $size;
        }

        return $out;
    }
}
