<?php

namespace App\Services;

use App\Models\Design;
use App\Models\DesignAsset;
use App\Models\Mockup;
use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Support\Facades\Storage;

/**
 * Copies a design's print files into the order, so editing or deleting the design
 * afterwards can never change what the shop prints. Originals the customer
 * uploaded are kept alongside the print-ready PNGs.
 */
class OrderSnapshot
{
    public function copy(Order $order, OrderLine $line, Design $design): void
    {
        $design->loadMissing('areas.printArea');
        $disk = Storage::disk('designs');
        $base = "orders/{$order->id}/line-{$line->id}";

        foreach ($design->areas as $area) {
            $print = $area->printArea;
            $slug = $print->slug;

            $export = "$base/{$slug}.png";
            $disk->copy($area->export_path, $export);

            $preview = null;

            if ($area->preview_path && $disk->exists($area->preview_path)) {
                $preview = "$base/{$slug}-preview.png";
                $disk->copy($area->preview_path, $preview);
            }

            $mockupPath = null;
            $mockup = Mockup::where('product_colour_id', $design->product_colour_id)->where('print_area_id', $print->id)->first();

            if ($mockup && Storage::disk('public')->exists($mockup->path)) {
                $mockupPath = "$base/{$slug}-mockup.".pathinfo($mockup->path, PATHINFO_EXTENSION);
                $disk->put($mockupPath, Storage::disk('public')->get($mockup->path));
            }

            $line->areas()->create([
                'area_name' => $print->name, 'method' => $print->method->value,
                'width_cm' => $print->width_cm, 'height_cm' => $print->height_cm,
                'export_path' => $export, 'preview_path' => $preview, 'mockup_path' => $mockupPath,
                'box_x' => $print->box_x, 'box_y' => $print->box_y, 'box_w' => $print->box_w, 'box_h' => $print->box_h,
                'export_width' => $area->export_width, 'export_height' => $area->export_height, 'export_dpi' => $area->export_dpi,
                'personalisation' => $area->personalisation,
                'state' => $area->state,
                'assets' => $this->assets($order, $line, $area->state),
            ]);
        }
    }

    /**
     * @return list<array{id: int, name: string, path: string, mime: string, kind: string, width: int|null, height: int|null}>
     */
    private function assets(Order $order, OrderLine $line, ?string $state): array
    {
        $ids = collect(json_decode((string) $state, true)['objects'] ?? [])->pluck('assetId')->filter()->unique()->values();
        $copied = [];

        foreach (DesignAsset::whereIn('id', $ids)->get() as $asset) {
            if (! Storage::disk('designs')->exists($asset->path)) {
                continue;
            }

            $target = "orders/{$order->id}/line-{$line->id}/originals/{$asset->id}-".preg_replace('/[^\w.\-]/', '_', (string) $asset->original_name);
            Storage::disk('designs')->copy($asset->path, $target);
            $copied[] = ['id' => $asset->id, 'name' => (string) $asset->original_name, 'path' => $target, 'mime' => $asset->mime, 'kind' => $asset->kind, 'width' => $asset->width, 'height' => $asset->height];
        }

        return $copied;
    }
}
