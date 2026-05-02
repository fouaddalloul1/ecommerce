<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class StartProjectCommand extends Command
{
    protected $signature = 'project:start';
    protected $description = 'Start Laravel + CPU monitor';

    public function handle(): int
    {
        $this->info('🚀 Starting system...');

        /**
         * Redis already running as Windows service
         */
        $this->info('Redis: Windows service detected (skipping start)');

        /**
         * Start CPU monitor
         */
        $cpu = new Process(['php', 'artisan', 'system:cpu-monitor']);
        $cpu->setTimeout(null);
        $cpu->start();

        $this->info('CPU monitor started');

        /**
         * Start Laravel
         */
        $server = new Process(['php', 'artisan', 'serve']);
        $server->setTimeout(null);

        $this->line('Server running: http://127.0.0.1:8000');
        $this->line('CTRL + C to stop');

        $server->run(function ($type, $buffer) {
            echo $buffer;
        });

        return self::SUCCESS;
    }
}
