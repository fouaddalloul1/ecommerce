<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

class LogJobMetrics
{
    public function handle($job, Closure $next)
    {
        $start = microtime(true);
        $memStart = memory_get_usage(true);

        $next($job);

        $memEnd = memory_get_usage(true);
        $duration = microtime(true) - $start;

        Log::info('JobMetrics', [
            'job' => get_class($job),
            'duration_s' => round($duration, 3),
            'mem_start_mb' => round($memStart / 1024 / 1024, 2),
            'mem_end_mb' => round($memEnd / 1024 / 1024, 2),
        ]);
    }
}
