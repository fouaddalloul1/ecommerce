<?php

namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Order\Notifications\OrderPlaced;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 90;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(public int $orderId)
    {
        $this->onConnection('redis');
        $this->onQueue('notifications');
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("order-notification:{$this->orderId}"))
                ->releaseAfter(10)
                ->expireAfter($this->timeout + 60),
        ];
    }

    public function handle(): void
    {
        $order = Order::with('user')->find($this->orderId);

        if (! $order || ! $order->user) {
            throw new \RuntimeException("Order {$this->orderId} or its user does not exist.");
        }

        if ($order->notification_sent_at) {
            Log::info('Order notification already sent; job skipped.', [
                'order_id' => $this->orderId,
            ]);

            return;
        }

        $order->user->notify(new OrderPlaced($order));
        $order->update(['notification_sent_at' => now()]);

        Log::info('Order notification sent.', ['order_id' => $this->orderId]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Order notification job exhausted all retries.', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
