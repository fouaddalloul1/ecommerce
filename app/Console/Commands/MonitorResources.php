<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class MonitorResources extends Command
{
    protected $signature = 'monitor:full';
    protected $description = 'Monitor system + Laravel resource usage';

    public function handle()
    {
        $this->info('Starting System Resource Monitor...');
        $this->newLine();

        while (true) {

            // =========================
            // REAL SYSTEM CPU (%)
            // =========================
            $cpuOutput = shell_exec(
                'powershell "(Get-Counter \'\\Processor(_Total)\\% Processor Time\').CounterSamples.CookedValue"'
            );

            $cpu = (float) trim($cpuOutput);

            if (!$cpuOutput) {
                $this->error('CPU data not available...');
                sleep(2);
                continue;
            }

            // =========================
            // SYSTEM MEMORY
            // =========================
            $systemOutput = shell_exec(
                'powershell "(Get-CimInstance Win32_OperatingSystem | Select-Object TotalVisibleMemorySize,FreePhysicalMemory) | ConvertTo-Json"'
            );

            $system = json_decode($systemOutput, true);

            if (!$system) {
                $this->error('Memory data not available...');
                sleep(2);
                continue;
            }

            $totalRamMB = round($system['TotalVisibleMemorySize'] / 1024, 2);
            $freeRamMB  = round($system['FreePhysicalMemory'] / 1024, 2);

            $usedRamMB = $totalRamMB - $freeRamMB;
            $ramPercent = round(($usedRamMB / $totalRamMB) * 100, 2);

            // =========================
            // STATE LOGIC (ENGINE CORE)
            // =========================
            if ($cpu < 60 && $ramPercent < 80) {
                $state = 'LOW';
            } elseif ($cpu < 70 && $ramPercent < 90) {
                $state = 'MEDIUM';
            } else {
                $state = 'HIGH';
            }

            // =========================
            // STORE IN REDIS
            // =========================
            Redis::set('metrics:cpu', $cpu);
            Redis::set('metrics:ram', $ramPercent);
            Redis::set('metrics:state', $state);

            // =========================
            // OUTPUT
            // =========================
            $this->line(str_repeat('=', 60));
            $this->info('SYSTEM RESOURCE MONITOR');

            $this->table(
                ['Metric', 'Value'],
                [
                    ['CPU Usage (%)', $cpu . ' %'],
                    ['RAM Usage (%)', $ramPercent . ' %'],
                    ['STATE', $state],
                ]
            );

            sleep(2);
        }
    }
}