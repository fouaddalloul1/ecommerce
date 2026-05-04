<?php

namespace Modules\Order\Repositories;

use Exception;
use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use Modules\Order\Data\CreateOrderData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentStatus;
use Modules\Product\Repositories\ProductRepository;

class OrderRepository
{
    public function __construct(
        protected ProductRepository $productRepository
    ) {}


    public function create(CreateOrderData $data)
    {
        $items = $data->items;

        /**
         * 1. Extract product IDs (pure PHP array, no collections)
         */
        $productIds = array_unique(array_column($items, 'product_id'));

        /**
         * 2. Load products using RAW query (FAST)
         */
        $products = DB::table('products')
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $total = 0;
        $orderItems = [];
        $decrements = [];

        /**
         * 3. Pure PHP loop (no model access)
         */
        foreach ($items as $item) {

            $product = $products[$item['product_id']] ?? null;

            $qty = (int) $item['quantity'];

            Log::info('product  : ', ['product' => $product]);

            $total += $product->price * $qty;

            $orderItems[] = [
                'product_id' => $product->id,
                'quantity'   => $qty,
            ];

            $decrements[$product->id] = ($decrements[$product->id] ?? 0) + $qty;
        }

        /**
         * 4. Insert order (RAW insert)
         */
        $orderId = DB::table('orders')->insertGetId([
            'user_id'        => $data->user_id,
            'total'          => $total,
            'status'         => OrderStatus::PENDING->value,
            'payment_status' => PaymentStatus::UNPAID->value,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        /**
         * 5. Batch insert order items (RAW)
         */
        $insertItems = [];

        foreach ($orderItems as $item) {
            $insertItems[] = [
                'order_id'   => $orderId,
                'product_id' => $item['product_id'],
                'quantity'   => $item['quantity'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('order_items')->insert($insertItems);

        /**
         * 6. Single bulk stock update (FASTEST PART)
         */
        $cases = [];
        $ids = [];

        foreach ($decrements as $id => $qty) {
            $cases[] = "WHEN {$id} THEN stock - {$qty}";
            $ids[] = $id;
        }

        $caseSql = implode(' ', $cases);
        $idList = implode(',', $ids);

        DB::statement("
        UPDATE products
        SET stock = CASE id {$caseSql} END
        WHERE id IN ({$idList})
    ");

        return null;
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
