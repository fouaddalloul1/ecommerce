<?php

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Order\DTOs\InvoiceEmailMessage;
use Modules\Order\Mail\InvoiceMail;
use Modules\Order\Models\Order;
use Throwable;

class SendEmailWithPdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [15, 60, 180, 300];

    public function __construct(
        public int $orderId,
        public string $pdfPath
    ) {
        $this->onConnection('redis');
        $this->onQueue('notifications');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("invoice-email:{$this->orderId}"))
                ->releaseAfter(15)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(): void
    {
        $order = Order::with('user')->find($this->orderId);

        if (! $order || ! $order->user) {
            throw new \RuntimeException("Order {$this->orderId} or its user does not exist.");
        }

        // Idempotency guard for normal retries and duplicate dispatches.
        if ($order->invoice_sent_at) {
            Log::info('Invoice email already sent; job skipped.', ['order_id' => $this->orderId]);

            return;
        }

        if (! Storage::disk('local')->exists($this->pdfPath)) {
            throw new \RuntimeException("Invoice PDF does not exist: {$this->pdfPath}");
        }

        $absolutePath = Storage::disk('local')->path($this->pdfPath);
        $message = new InvoiceEmailMessage($order, $absolutePath);

        Mail::to($order->user->email)->send(new InvoiceMail($message));

        $order->update(['invoice_sent_at' => now()]);

        Log::info('Invoice email sent.', ['order_id' => $this->orderId]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Invoice email job exhausted all retries.', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
