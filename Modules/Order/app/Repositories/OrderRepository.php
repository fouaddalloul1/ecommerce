<?php

namespace Modules\Order\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Data\CreateOrderData;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentStatus;
use Modules\Order\Jobs\GeneratePdfJob;
use Modules\Order\Jobs\SendNotificationJob;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use Modules\Product\Services\PopularProductService;
use Modules\User\Models\User;

class OrderRepository
{
    public function create(CreateOrderData $data): Order
    {
        $order = DB::transaction(function () use ($data): Order {
            /*
             * Normalize duplicate product rows before checking stock. Without
             * this step, the same product could pass two separate stock checks
             * and then be decremented twice inside one request.
             */
            $normalizedItems = collect($data->items)
                ->groupBy(fn (array $item): int => (int) $item['product_id'])
                ->map(fn ($items, $productId): array => [
                    'product_id' => (int) $productId,
                    'quantity' => (int) $items->sum('quantity'),
                ])
                ->values();

            $productIds = $normalizedItems->pluck('product_id')->all();

            // Pessimistic row locks serialize concurrent stock updates.
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $user = User::query()
                ->whereKey(auth()->id())
                ->lockForUpdate()
                ->firstOrFail();

            $total = 0.0;
            $itemsPayload = [];

            foreach ($normalizedItems as $item) {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    throw new \RuntimeException("Product {$item['product_id']} not found.");
                }

                if ($product->stock < $item['quantity']) {
                    throw new \RuntimeException(
                        "Product {$product->id} does not have enough stock."
                    );
                }

                $unitPrice = (float) $product->price;
                $lineTotal = round($unitPrice * $item['quantity'], 2);
                $total += $lineTotal;

                $itemsPayload[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            $total = round($total, 2);

            if ((float) $user->balance < $total) {
                throw new \RuntimeException('Insufficient balance.');
            }

            $user->decrement('balance', $total);

            $order = Order::create([
                'user_id' => $user->id,
                'total' => $total,
                'status' => OrderStatus::COMPLETED->value,
                'payment_status' => PaymentStatus::PAID->value,
            ]);

            foreach ($itemsPayload as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);

                $item['product']->decrement('stock', $item['quantity']);
            }

            /*
             * Jobs are registered inside the transaction but are pushed only
             * after commit. A rollback therefore produces no invoice or email
             * for an order that does not exist.
             */
            GeneratePdfJob::dispatch($order->id)->afterCommit();
            SendNotificationJob::dispatch($order->id)->afterCommit();

            return $order->load('items');
        }, 5);

        try {
            PopularProductService::evictPopularProducts();
        } catch (\Throwable $exception) {
            // Cache invalidation must not turn a committed order into an API error.
            Log::warning('Popular products cache eviction failed after order commit.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $order;
    }

    public function find(int $id): Order
    {
        return Order::with('items')->findOrFail($id);
    }

    public function listForUser(int $userId, int $perPage = 15)
    {
        return Order::with('items')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function updateStatus(Order $order, string $status, ?string $paymentStatus = null): Order
    {
        $order->status = $status;

        if ($paymentStatus) {
            $order->payment_status = $paymentStatus;
        }

        $order->save();

        return $order;
    }

    public function cancel(Order $order): Order
    {
        $cancelledOrder = DB::transaction(function () use ($order): Order {
            $lockedOrder = Order::query()
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($order->id);

            if ($lockedOrder->status === OrderStatus::CANCELLED->value) {
                return $lockedOrder;
            }

            $productIds = $lockedOrder->items->pluck('product_id')->unique()->all();
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($lockedOrder->items as $item) {
                $products->get($item->product_id)?->increment('stock', $item->quantity);
            }

            $user = User::query()
                ->whereKey($lockedOrder->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $user->increment('balance', (float) $lockedOrder->total);

            $lockedOrder->update([
                'status' => OrderStatus::CANCELLED->value,
                'payment_status' => PaymentStatus::REFUNDED->value,
            ]);

            return $lockedOrder;
        }, 5);

        try {
            PopularProductService::evictPopularProducts();
        } catch (\Throwable $exception) {
            Log::warning('Popular products cache eviction failed after order cancellation.', [
                'order_id' => $cancelledOrder->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $cancelledOrder;
    }
}
