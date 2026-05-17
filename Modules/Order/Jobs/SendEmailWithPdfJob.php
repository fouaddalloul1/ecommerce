<?php

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Order\Services\InvoiceMailerService;
use Modules\Order\Services\OrderFetcherService;

class SendEmailWithPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;
    public string $pdfPath;
    public int $tries = 5;
    public int $timeout = 60;

    public function __construct(int $orderId, string $pdfPath)
    {
        $this->orderId = $orderId;
        $this->pdfPath = $pdfPath;
    }

    public function handle(InvoiceMailerService $mailer, OrderFetcherService $fetcher): void
    {
        $order = $fetcher->fetchWithRelations($this->orderId);
        if (!$order) {
            return;
        }

        $mailer->sendInvoice($order, $this->pdfPath);
    }
}
