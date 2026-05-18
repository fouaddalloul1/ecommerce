<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RunLoadBalancerServers extends Command
{
    protected $signature = 'servers:start';
    protected $description = 'Start parallel Laravel servers on ports 8001, 8002, and 8003 in new terminals';

    public function handle()
    {
        $this->info('🚀 Starting parallel Laravel servers...');

        $projectPath = base_path();
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            $this->startWindows($projectPath);
        } else {
            $this->startLinux($projectPath);
        }

        $this->comment('✅ All server windows opened successfully!');
    }

    private function startWindows($projectPath)
    {
        // Quarter-screen size (approx)
        $size = "mode con: cols=50 lines=15";

        foreach ([8001, 8002, 8003] as $port) {
            $cmd = "start cmd /k \"{$size} && cd /d {$projectPath} && title Laravel Server {$port} && php artisan serve --port={$port}\"";
            pclose(popen($cmd, 'r'));
        }
    }

    private function startLinux($projectPath)
    {
        // Quarter-screen size (approx)
        // WIDTHxHEIGHT
        $geometry = "30x10";

        foreach ([8001, 8002, 8003] as $port) {
            $cmd = "gnome-terminal --geometry={$geometry} -- bash -c \"cd {$projectPath}; php artisan serve --port={$port}; exec bash\"";
            pclose(popen($cmd, 'r'));
        }
    }
}
