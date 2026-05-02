<?php

namespace Modules\Order\Repositories;

use Illuminate\Support\Facades\Log;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use Modules\Order\Data\CreateOrderData;
use Illuminate\Support\Facades\DB;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentStatus;
use Modules\Order\Jobs\SendInvoiceJob;
use Modules\Order\Jobs\SendNotificationJob;


class OrderRepository
{
    public function create(CreateOrderData $data): Order
    {
        return DB::transaction(function () use ($data) {
            $total = 0;
            $itemsPayload = [];
            // Log::info('Order started at: ' . now());
            // Get all product ids once (outside loop)
            $productIds = collect($data->items)
                ->pluck('product_id')
                ->unique()
                ->values()
                ->toArray();
            // Single query + lock rows
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            // sleep(10);
            foreach ($data->items as $item) {
                $product = $products->get($item['product_id']);
                if (! $product) {
                    throw new \Exception(
                        "Product {$item['product_id']} not found."
                    );
                }
                // This belongs here (repository/domain logic)
                if ($product->stock < $item['quantity']) {
                    throw new \Exception(
                        "Product {$product->id} does not have enough stock."
                    );
                }

                $lineTotal = $product->selling_price * $item['quantity'];
                $total += $lineTotal;

                $itemsPayload[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                ];
            }

            $order = Order::create([
                'user_id' => $data->user_id,
                'total' => $total,
                'status' => OrderStatus::PENDING->value,
                'payment_status' => PaymentStatus::UNPAID->value,
            ]);

            SendInvoiceJob::dispatch($order->id)->onQueue('invoices');
            SendNotificationJob::dispatch($order->id)->onQueue('notifications');

            foreach ($itemsPayload as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                ]);

                $item['product']->decrement(
                    'stock',
                    $item['quantity']
                );
            }

            return $order->load('items');
        }, 5);
    }


    public function find(int $id): Order
    {
        return Order::with('items')->findOrFail($id);
    }

    public function listForUser(int $userId, int $perPage = 15)
    {
        return Order::with('items')->where('user_id', $userId)->orderBy('created_at', 'desc')->paginate($perPage);
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
        return DB::transaction(function () use ($order) {
            if ($order->status === 'cancelled') {
                return $order;
            }

            // restore stock
            foreach ($order->items as $item) {
                $product = Product::find($item->product_id);
                if ($product) {
                    $product->increment('stock', $item->quantity);
                }
            }

            $order->status = 'cancelled';
            $order->payment_status = 'refunded';
            $order->save();

            return $order;
        });
    }
}
