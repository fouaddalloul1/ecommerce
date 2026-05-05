<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
class DevStartCommand extends Command
{

    protected $signature = 'dev:start';
    protected $description = 'Clear all caches and start development server';

    public function handle()
    {
        $this->info('🧹 Clearing all caches...');

        // تنفيذ أوامر الـ clear
        Artisan::call('optimize:clear');
        $this->info('✓ optimize:clear');

        Artisan::call('config:clear');
        $this->info('✓ config:clear');

        Artisan::call('route:clear');
        $this->info('✓ route:clear');

        Artisan::call('view:clear');
        $this->info('✓ view:clear');

        $this->newLine();
        $this->info('🚀 Starting development server...');
        $this->newLine();

        // تشغيل السيرفر
        Artisan::call('serve');

        return Command::SUCCESS;
    }
}
