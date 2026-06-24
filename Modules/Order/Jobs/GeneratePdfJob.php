<?php

namespace Modules\Order\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Order\Models\Order;
use Throwable;

class GeneratePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 180;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 90];

    public function __construct(public int $orderId)
    {
        $this->onConnection('redis');
        $this->onQueue('invoices');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("invoice-pdf:{$this->orderId}"))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(): void
    {
        Log::info('Invoice PDF job started.', ['order_id' => $this->orderId]);

        $order = Order::with(['items.product', 'user'])->find($this->orderId);

        if (! $order || ! $order->user) {
            throw new \RuntimeException("Order {$this->orderId} or its user does not exist.");
        }

        $relativePath = $order->invoice_path ?: "invoices/{$order->id}.pdf";

        /*
         * Idempotency: retries do not regenerate an existing PDF. However, the
         * email job is still dispatched when the file exists but was not sent.
         */
        if (Storage::disk('local')->exists($relativePath)) {
            if (! $order->invoice_path || ! $order->invoice_generated_at) {
                $order->update([
                    'invoice_path' => $relativePath,
                    'invoice_generated_at' => now(),
                ]);
            }

            $this->dispatchEmailWhenNeeded($order, $relativePath);

            Log::info('Existing invoice PDF reused.', [
                'order_id' => $this->orderId,
                'path' => $relativePath,
            ]);

            return;
        }

        $html = view('order::emails.invoice', ['order' => $order])->render();
        $pdfContent = Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->output();

        Storage::disk('local')->makeDirectory('invoices');

        if (! Storage::disk('local')->put($relativePath, $pdfContent)) {
            throw new \RuntimeException("Could not store invoice PDF for order {$this->orderId}.");
        }

        $order->update([
            'invoice_path' => $relativePath,
            'invoice_generated_at' => now(),
        ]);

        $this->dispatchEmailWhenNeeded($order->fresh(), $relativePath);

        Log::info('Invoice PDF generated.', [
            'order_id' => $this->orderId,
            'path' => $relativePath,
        ]);
    }

    private function dispatchEmailWhenNeeded(Order $order, string $relativePath): void
    {
        if ($order->invoice_sent_at) {
            return;
        }

        SendEmailWithPdfJob::dispatch($order->id, $relativePath)
            ->onConnection('redis')
            ->onQueue('notifications');
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Invoice PDF job exhausted all retries.', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
