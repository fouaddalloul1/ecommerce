<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDailySalesJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class ProcessDailySalesCommand extends Command
{
    protected $signature = 'sales:daily-process
        {--date= : Report date in YYYY-MM-DD format; defaults to yesterday}
        {--force : Rebuild an already completed report}
        {--sync : Run immediately in the current process for local demonstration}';

    protected $description = 'Queue the daily sales batch-processing report';

    public function handle(): int
    {
        try {
            $date = $this->resolveDate($this->option('date'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $job = new ProcessDailySalesJob($date, $force);

        if ($this->option('sync')) {
            $this->info("Processing daily sales report synchronously for {$date}...");
            ProcessDailySalesJob::dispatchSync($date, $force);
            $this->info('Daily sales report completed.');

            return self::SUCCESS;
        }

        dispatch($job);
        $this->info("Daily sales report queued for {$date} on redis-reports/reports.");

        return self::SUCCESS;
    }

    private function resolveDate(?string $date): string
    {
        if ($date === null || trim($date) === '') {
            return now()->subDay()->toDateString();
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $date);

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('The --date value must use the YYYY-MM-DD format.');
        }

        if ($parsed->isFuture()) {
            throw new \InvalidArgumentException('A future date cannot be processed.');
        }

        return $parsed->toDateString();
    }
}
