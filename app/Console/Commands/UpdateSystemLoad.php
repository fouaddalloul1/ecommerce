<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UpdateSystemLoad extends Command
{
    protected $signature = 'system:monitor';
    protected $description = 'Store CPU & RAM usage in Redis';

    public function handle()
    {
        while (true) {

            $metrics = $this->getSystemMetrics();

            Log::info('metrics : ' . json_encode($metrics));
            
            // store in Redis
            Redis::set('system:cpu_load', $metrics['cpu']);
            Redis::set('system:ram_usage', $metrics['ram']);

            Log::info("CPU: {$metrics['cpu']}% | RAM: {$metrics['ram']}%");

            $this->info("CPU: {$metrics['cpu']}% | RAM: {$metrics['ram']}%");

            sleep(2);
        }
    }

    private function getSystemMetrics(): array
    {
        if (PHP_OS_FAMILY === 'Windows') {

            // CPU usage
            $cpuCmd = 'powershell -command "Get-CimInstance Win32_Processor | Select-Object -ExpandProperty LoadPercentage"';
            $cpu = (float) trim(shell_exec($cpuCmd));

            // RAM usage (%)
            $ramCmd = 'powershell -command "(Get-CimInstance Win32_OperatingSystem | ForEach-Object { ((($_.TotalVisibleMemorySize - $_.FreePhysicalMemory) * 100) / $_.TotalVisibleMemorySize) })"';
            $ram = (float) trim(shell_exec($ramCmd));

            return [
                'cpu' => $cpu,
                'ram' => $ram,
            ];
        }

        // Linux fallback
        return [
            'cpu' => sys_getloadavg()[0] ?? 0,
            'ram' => memory_get_usage(true),
        ];
    }
}
