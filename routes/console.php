<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// cPanel only offers cron, so the queue is drained by the scheduler (cron runs
// `schedule:run` every minute) instead of a long-running `queue:work` process.
Schedule::command('queue:work --stop-when-empty')->everyMinute()->withoutOverlapping();

Schedule::command('uploads:cleanup')->daily();

// Proof then pay: approved but unpaid orders expire after the configured number of days.
Schedule::command('orders:expire-proofs')->hourly();
