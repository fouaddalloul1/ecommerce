<?php

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

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

    public function handle(): void
    {
        Log::info("GeneratePdfJob started for order {$this->orderId}");

        $order = Order::with('items.product', 'user')->find($this->orderId);

        if (!$order || !$order->user) {
            Log::error("Order or user not found", ['order_id' => $this->orderId]);
            throw new \Exception("Order {$this->orderId} invalid");
        }

        // تجنب إعادة الإنشاء
        if ($order->invoice_path && Storage::disk('local')->exists($order->invoice_path)) {
            Log::info("PDF already exists", ['order_id' => $this->orderId]);
            return;
        }

        // توليد PDF
        try {
            $html = view('order::emails.invoice', ['order' => $order])->render();
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            $pdfContent = $pdf->output();
        } catch (\Throwable $e) {
            Log::error("PDF generation failed", ['error' => $e->getMessage()]);
            throw new \Exception("Cannot generate PDF: " . $e->getMessage());
        }

        // حفظ الملف
        $relativePath = "invoices/{$order->id}.pdf";
        try {
            Storage::disk('local')->makeDirectory('invoices');
            $saved = Storage::disk('local')->put($relativePath, $pdfContent);

            if (!$saved) {
                throw new \Exception("Storage put returned false");
            }

            Log::info("PDF saved", ['path' => $relativePath]);
        } catch (\Throwable $e) {
            Log::error("Failed to save PDF", ['error' => $e->getMessage()]);
            throw new \Exception("Cannot save PDF: " . $e->getMessage());
        }

        // تحديث الطلب
        $order->update([
            'invoice_path' => $relativePath,
            'invoice_generated_at' => now(),
        ]);

        // إطلاق جوب الإرسال
        SendEmailWithPdfJob::dispatch($order->id, $relativePath)->onQueue('notifications');
    }
}
