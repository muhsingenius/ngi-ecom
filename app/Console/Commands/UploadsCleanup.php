<?php

namespace App\Console\Commands;

use App\Models\Design;
use App\Models\DesignArea;
use App\Models\DesignAsset;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Removes design uploads nobody is using any more: abandoned guest designs and the
 * images only they referenced. Files that belong to an order (everything under
 * orders/) are never touched, and account-saved designs are always kept.
 */
#[Signature('uploads:cleanup {--days=30 : Only remove things older than this many days} {--pretend : Show what would be removed without deleting}')]
#[Description('Delete unused design uploads older than 30 days (never files linked to an order)')]
class UploadsCleanup extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));
        $pretend = (bool) $this->option('pretend');
        $disk = Storage::disk('designs');

        $designs = 0;
        $assets = 0;

        // 1. Abandoned designs: guest-owned, not in any cart or order, and not mid-revision of a live order.
        Design::query()
            ->whereNull('user_id')
            ->where('updated_at', '<', $cutoff)
            ->whereDoesntHave('cartLines')
            ->whereNotIn('id', fn ($q) => $q->select('design_id')->from('order_lines')->whereNotNull('design_id'))
            ->each(function (Design $design) use ($pretend, $disk, &$designs) {
                $designs++;

                if (! $pretend) {
                    $disk->deleteDirectory("designs/{$design->id}");
                    $design->delete();
                }
            });

        // 2. Uploads no remaining design refers to.
        DesignAsset::query()
            ->where('created_at', '<', $cutoff)
            ->each(function (DesignAsset $asset) use ($pretend, $disk, &$assets) {
                $id = $asset->id;

                $inUse = DesignArea::where('state', 'like', "%\"assetId\":{$id},%")
                    ->orWhere('state', 'like', "%\"assetId\":{$id}}%")
                    ->orWhere('state', 'like', "%\"assetId\":\"{$id}\"%")
                    ->exists();

                if ($inUse) {
                    return;
                }

                $assets++;

                if (! $pretend) {
                    // Order copies live under orders/, so deleting the working file cannot affect an order.
                    $disk->delete($asset->path);
                    $asset->delete();
                }
            });

        $verb = $pretend ? 'Would remove' : 'Removed';
        $message = "{$verb} {$designs} abandoned design(s) and {$assets} unused upload(s) older than {$cutoff->toDateString()}.";

        Log::info("uploads:cleanup: {$message}");
        $this->info($message);

        return self::SUCCESS;
    }
}
