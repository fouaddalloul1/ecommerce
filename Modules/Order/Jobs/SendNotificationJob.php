<?php
namespace Modules\Order\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Order\Notifications\OrderPlaced;
use Illuminate\Support\Facades\Notification;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;
    public $tries = 3;
    public $backoff = 10;

    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }


    public function handle()
    {
        // sleep(2);  // ← أضف هذا السطر
        // Log::info('🔥 Invoice job executed at: ' . now());
        $order = Order::with('user')->find($this->orderId);
        if (! $order || ! $order->user) {
            return;
        }

        if ($order->notification_sent_at) {
            return;
        }

        $order->user->notify(new OrderPlaced($order));
        $order->update(['notification_sent_at' => now()]);
    }
}
