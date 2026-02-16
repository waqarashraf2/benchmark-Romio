<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\ImportPortalOrdersJob;


Schedule::command('app:metro-import')
    ->everyMinute()
    ->withoutOverlapping();

// Run every minute (for testing or real-time updates)
Schedule::job(new ImportPortalOrdersJob)
    ->everyMinute()  // Changed from everyFifteenMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/portal-scheduler.log'));

