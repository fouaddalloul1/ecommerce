<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UpdateSystemLoad extends Command
{
    protected $signature = 'system:cpu-monitor';
    protected $description = 'Store CPU load in Redis for dynamic limiting';

    public function handle()
    {
        while (true) {

            // Windows-compatible CPU usage approximation
            $load = $this->getCpuLoad();

            Log::info("CPU Load: {$load}% ");

            Redis::set('system:cpu_load', $load);

            $this->info("CPU Load Stored: {$load}%");

            sleep(2); // update every 2 seconds
        }
    }

    private function getCpuLoad(): float
    {
        if (PHP_OS_FAMILY === 'Windows') {

            // PowerShell CPU usage (real and supported)
            $cmd = 'powershell -command "Get-CimInstance Win32_Processor | Select-Object -ExpandProperty LoadPercentage"';

            $output = shell_exec($cmd);

            return (float) trim($output ?? 0);
        }

        // Linux fallback
        $load = sys_getloadavg();
        return $load[0] ?? 0;
    }
}
