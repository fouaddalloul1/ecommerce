<?php

namespace Modules\Order\Services;

use Modules\Order\Models\Order;

class OrderFetcherService
{
    /**
     * Fetch order with required relations. Return null if invalid.
     */
    public function fetchWithRelations(int $orderId): ?Order
    {
        $order = Order::with('items.product', 'user')->find($orderId);

        if (! $order || ! $order->user || ! $order->user->email) {
            return null;
        }

        return $order;
    }
}
