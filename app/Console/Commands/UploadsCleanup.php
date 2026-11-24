<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('uploads:cleanup')]
#[Description('Placeholder: will remove expired or orphaned uploads (currently logs only)')]
class UploadsCleanup extends Command
{
    public function handle(): int
    {
        Log::info('uploads:cleanup ran (placeholder, nothing deleted).');

        $this->info('uploads:cleanup ran (placeholder, nothing deleted).');

        return self::SUCCESS;
    }
}
