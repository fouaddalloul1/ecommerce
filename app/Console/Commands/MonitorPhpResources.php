<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class MonitorPhpResources extends Command
{
    protected $signature = 'monitor:php';

    protected $description = 'Monitor Laravel/PHP resource usage';

    public function handle()
    {
        $this->info('Starting Laravel Resource Monitor...');
        $this->newLine();

        while (true) {

            // =========================
            // PHP Processes
            // =========================
            $phpOutput = shell_exec(
                'powershell "Get-Process php | Select-Object CPU,WorkingSet | ConvertTo-Json"'
            );

            $phpProcesses = json_decode($phpOutput, true);

            if (!$phpProcesses) {
                $this->error('No PHP processes found...');
                sleep(2);
                continue;
            }

            // Convert single process to array
            if (isset($phpProcesses['CPU'])) {
                $phpProcesses = [$phpProcesses];
            }

            $phpMemoryBytes = 0;
            $phpCpuTime = 0;

            foreach ($phpProcesses as $process) {
                $phpMemoryBytes += $process['WorkingSet'];
                $phpCpuTime += $process['CPU'];
            }

            // =========================
            // System Memory
            // =========================
            $systemOutput = shell_exec(
                'powershell "(Get-CimInstance Win32_OperatingSystem | Select-Object TotalVisibleMemorySize,FreePhysicalMemory) | ConvertTo-Json"'
            );

            $system = json_decode($systemOutput, true);

            $totalRamMB = round($system['TotalVisibleMemorySize'] / 1024, 2);
            $freeRamMB = round($system['FreePhysicalMemory'] / 1024, 2);

            $usedRamMB = $totalRamMB - $freeRamMB;

            // =========================
            // PHP Memory
            // =========================
            $phpMemoryMB = round($phpMemoryBytes / 1024 / 1024, 2);

            // =========================
            // Other Apps Memory
            // =========================
            $otherAppsRamMB = round($usedRamMB - $phpMemoryMB, 2);

            // =========================
            // Percentages
            // =========================
            $phpRamPercent = round(($phpMemoryMB / $totalRamMB) * 100, 2);

            $otherAppsPercent = round(($otherAppsRamMB / $totalRamMB) * 100, 2);


            // =========================
            // STORE ONLY IN REDIS
            // =========================
            Redis::set('metrics:cpu', $phpCpuTime);
            Redis::set('metrics:ram', $phpRamPercent);

            // =========================
            // Beautiful Output
            // =========================
            $this->line(str_repeat('=', 60));

            $this->info('Laravel / PHP Resource Usage');

            $this->table(
                ['Metric', 'Value'],
                [
                    ['PHP CPU Time', $phpCpuTime . ' sec'],
                    ['PHP RAM Usage', $phpMemoryMB . ' MB'],
                    ['PHP RAM %', $phpRamPercent . ' %'],
                ]
            );

            $this->info('System Resource Usage');

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total RAM', $totalRamMB . ' MB'],
                    ['Used By Other Apps', $otherAppsRamMB . ' MB'],
                    ['Other Apps RAM %', $otherAppsPercent . ' %'],
                    ['Free RAM', $freeRamMB . ' MB'],
                ]
            );

            sleep(2);
        }
    }
}
