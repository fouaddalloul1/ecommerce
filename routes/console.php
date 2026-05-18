<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\ProcessWeeklySalesCommand;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// كل يوم أحد الساعة 2 صباحاً

// Schedule::command('sales:weekly-process')
//     ->weeklyOn(7, '2:00')  // 7 = Sunday
//     ->withoutOverlapping()
//     ->onFailure(function () {
//         Log::error('Weekly sales schedule failed');
//     });

    # for testing
    Schedule::command('sales:weekly-process')->everyMinute();
