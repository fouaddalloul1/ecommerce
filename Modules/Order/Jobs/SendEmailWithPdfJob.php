<?php

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Order\Mail\InvoiceMail;
use Modules\Order\DTOs\InvoiceEmailMessage;

class SendEmailWithPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;
    public string $pdfPath;
    public int $tries = 5;

    public function __construct(int $orderId, string $pdfPath)
    {
        $this->orderId = $orderId;
        $this->pdfPath = $pdfPath;
    }

    public function handle(): void
    {
        $order = Order::with('user')->find($this->orderId);

        if (!$order || !$order->user) {
            Log::error("Order or user missing", ['order_id' => $this->orderId]);
            return;
        }

        // المسار المطلق
        $absolutePath = storage_path($this->pdfPath);

        // إذا لم يكن موجوداً، جرب المسار المباشر
        if (!file_exists($absolutePath)) {
            $absolutePath = storage_path("app/private/{$this->pdfPath}");
        }

        if (!file_exists($absolutePath)) {
            Log::error("PDF file missing", ['order_id' => $this->orderId, 'path' => $absolutePath]);
            throw new \Exception("PDF file not found: {$this->pdfPath}");
        }

        $msg = new InvoiceEmailMessage($order, $absolutePath);

        try {
            Mail::to($order->user->email)->send(new InvoiceMail($msg));
            Log::info("Invoice email sent", ['order_id' => $this->orderId]);
        } catch (\Throwable $e) {
            Log::error("Failed to send email", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
