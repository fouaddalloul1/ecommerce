<?php

use App\Jobs\ProcessDailySalesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Daily batch processing runs after the previous day has finished.
 * - onOneServer: only one application server schedules the job.
 * - withoutOverlapping: a second scheduler run cannot overlap the first.
 * - ShouldBeUnique on the job provides another Redis-backed safety layer.
 */
Schedule::job(new ProcessDailySalesJob(), 'reports', 'redis-reports')
    ->name('daily-sales-report')
    ->dailyAt('02:00')
    ->withoutOverlapping(180)
    ->onOneServer()
    ->onFailure(function (): void {
        Log::error('The daily sales report schedule failed to dispatch.');
    });
