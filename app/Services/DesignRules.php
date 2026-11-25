<?php

namespace App\Services;

use App\Enums\PrintMethod;
use App\Models\PrintArea;

/**
 * Re-checks the print rules on the server. The designer enforces them for a smooth
 * experience, but the saved JSON is what the shop prints, so it is verified here too.
 */
class DesignRules
{
    /**
     * @param  array<string, mixed>  $state  decoded Fabric JSON for one print area
     * @return list<string> human-readable problems (empty when the design is printable)
     */
    public function problems(PrintArea $area, array $state, int $declaredColours): array
    {
        $problems = [];
        $objects = $state['objects'] ?? [];
        $pxPerCm = (float) config('designer.px_per_cm');

        if ($area->method === PrintMethod::Embroidery && $area->max_colours && $declaredColours > $area->max_colours) {
            $problems[] = "{$area->name}: embroidery allows at most {$area->max_colours} colours (this design uses {$declaredColours}).";
        }

        $characters = 0;

        foreach ($objects as $object) {
            $type = strtolower((string) ($object['type'] ?? ''));

            if (! in_array($type, ['itext', 'i-text', 'textbox', 'text'], true) || ($object['role'] ?? null)) {
                continue;
            }

            $characters += mb_strlen((string) ($object['text'] ?? ''));

            if ($area->method === PrintMethod::Embroidery && $area->min_line_mm) {
                $heightMm = ((float) ($object['fontSize'] ?? 0)) * ((float) ($object['scaleY'] ?? 1)) / $pxPerCm * 10;

                if ($heightMm > 0 && $heightMm < $area->min_line_mm) {
                    $problems[] = "{$area->name}: text is too small to embroider (minimum {$area->min_line_mm} mm).";
                    break;
                }
            }
        }

        if ($area->method === PrintMethod::Engraving && $area->max_chars && $characters > $area->max_chars) {
            $problems[] = "{$area->name}: engraving allows at most {$area->max_chars} characters (this has {$characters}).";
        }

        return $problems;
    }

    /**
     * Asset ids referenced by a Fabric state, so ownership can be checked.
     *
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    public function assetIds(array $state): array
    {
        $ids = [];

        foreach ($state['objects'] ?? [] as $object) {
            if (isset($object['assetId'])) {
                $ids[] = (int) $object['assetId'];
            }
        }

        return array_values(array_unique($ids));
    }
}
