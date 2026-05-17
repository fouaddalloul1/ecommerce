<?php

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Services\OrderFetcherService;
use Modules\Order\Services\InvoiceGenerator;
use Modules\Order\Services\InvoiceStorageService;
use Modules\Order\Services\InvoiceRecordService;

class GeneratePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;
    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    public function handle(
        OrderFetcherService $fetcher,
        InvoiceGenerator $generator,
        InvoiceStorageService $storage,
        InvoiceRecordService $recorder
    ): void {
        $order = $fetcher->fetchWithRelations($this->orderId);
        if (!$order || $recorder->isGenerated($order)) {
            return;
        }

        $pdfContent = $generator->generatePdf($order);
        $relativePath = $storage->storeInvoice($order->id, $pdfContent);
        $recorder->markGenerated($order, $relativePath);

        // بعد إنشاء PDF، نشغل Job إرسال الإيميل مع المرفق
        SendEmailWithPdfJob::dispatch($order->id, $relativePath)->onQueue('notifications');
    }
}
