<?php
// php artisan queue:work redis --queue=default --tries=3 --timeout=120 --sleep=3
namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $orderId;
    public int $tries = 3;
    public array|int $backoff = [5, 10];
    public int $timeout = 120;
    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Method injection keeps the job class small and makes services mockable in tests.
     */
    public function handle(
        \Modules\Order\Services\OrderFetcherService $fetcher,
        \Modules\Order\Services\InvoiceGenerator $generator,
        \Modules\Order\Services\InvoiceStorageService $storage,
        \Modules\Order\Services\InvoiceRecordService $recorder,
        \Modules\Order\Services\InvoiceMailerService $mailer
    ): void {
        // Log::info('🔥🔥🔥 INVOICE JOB STARTED at: ' . now());
        // sleep(5);  // ← أضف هذا السطر
        // Log::info('🔥 Invoice job executed at: ' . now());
        $order = $fetcher->fetchWithRelations($this->orderId);
        if (! $order) {
            // nothing to do
            return;
        }
        if ($recorder->isGenerated($order)) {
            return;
        }
        $pdfContent = $generator->generatePdf($order);

        $relativePath = $storage->storeInvoice($order->id, $pdfContent);

        $recorder->markGenerated($order, $relativePath);

        $mailer->sendInvoice($order, $relativePath);
    }
}
