<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderArea;
use App\Models\OrderLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Everything the print shop needs for a job: a one-page-per-line PDF sheet (no prices)
 * and a ZIP with the print-ready PNGs, the customer's original uploads and the rosters.
 */
class JobSheet
{
    public function __construct(private ProofImage $placements) {}

    public function pdf(Order $order): string
    {
        $order->loadMissing(['lines.items', 'lines.areas']);

        $images = [];

        foreach ($order->lines as $line) {
            foreach ($line->areas as $area) {
                $images[$area->id] = 'data:image/jpeg;base64,'.base64_encode($this->placements->placement($area));
            }
        }

        return Pdf::loadView('shop.sheet', ['order' => $order, 'images' => $images])->setPaper('a4')->output();
    }

    /**
     * Build the ZIP in a temporary file and return its path. The caller deletes it after sending.
     */
    public function zip(Order $order): string
    {
        $order->loadMissing(['lines.items', 'lines.areas']);

        $path = tempnam(sys_get_temp_dir(), 'job');
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP file.');
        }

        $root = $order->number;
        $disk = Storage::disk('designs');

        $zip->addFromString("$root/job-sheet.pdf", $this->pdf($order));

        foreach ($order->lines as $i => $line) {
            $folder = "$root/line-".($i + 1).'-'.Str::slug($line->product_name.' '.$line->quality);

            foreach ($line->areas as $area) {
                if ($disk->exists($area->export_path)) {
                    $zip->addFromString($folder.'/'.$this->areaFile($area), $disk->get($area->export_path));
                }

                foreach ($area->assets ?? [] as $asset) {
                    if ($disk->exists($asset['path'])) {
                        $zip->addFromString("$folder/originals/".basename($asset['path']), $disk->get($asset['path']));
                    }
                }
            }

            if ($line->items->contains(fn ($item) => $item->name || $item->number)) {
                $zip->addFromString("$folder/roster.csv", $this->rosterCsv($line));
            }
        }

        $zip->close();

        return $path;
    }

    /** File name of an area's print file inside the ZIP, e.g. front-300dpi-3307x4133.png */
    public function areaFile(OrderArea $area): string
    {
        return Str::slug($area->area_name)."-{$area->export_dpi}dpi-{$area->export_width}x{$area->export_height}.png";
    }

    /** @param  OrderLine  $line */
    private function rosterCsv($line): string
    {
        $rows = ['name,number,size,colour'];

        foreach ($line->items as $item) {
            $rows[] = implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', [$item->name, $item->number, $item->size, $item->colour_name]));
        }

        return implode("\n", $rows)."\n";
    }
}
