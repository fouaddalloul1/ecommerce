<?php
namespace Modules\Order\Repositories;

use Modules\Order\Models\Order;
use Modules\Order\Models\OrderItem;
use Modules\Product\Models\Product;
use Modules\Order\Data\CreateOrderData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderRepository
{
    public function create(CreateOrderData $data): Order
    {
        return DB::transaction(function () use ($data) {
            // compute totals and validate stock
            $subtotal = 0;
            $itemsPayload = [];

            foreach ($data->items as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['product_id']); // lock row
                if ($product->stock < $item['quantity']) {
                    throw new \Exception("Product {$product->id} does not have enough stock.");
                }

                $unitPrice = $product->selling_price;
                $lineTotal = bcmul((string)$unitPrice, (string)$item['quantity'], 2);
                $subtotal = bcadd((string)$subtotal, (string)$lineTotal, 2);

                $itemsPayload[] = [
                    'product' => $product,
                    'quantity' => $item['quantity'],
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'meta' => $item['meta'] ?? null,
                ];
            }

            $shipping = $data->shipping ?? 0;
            $total = bcadd((string)$subtotal, (string)$shipping, 2);

            // create order
            $order = Order::create([
                'user_id' => $data->user_id,
                'order_number' => strtoupper(Str::random(10)),
                'subtotal' => $subtotal,
                'shipping' => $shipping,
                'total' => $total,
                'currency' => $data->currency ?? 'USD',
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'shipping_address' => $data->shipping_address,
                'billing_address' => $data->billing_address,
            ]);

            // create items and decrement stock
            foreach ($itemsPayload as $p) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $p['product']->id,
                    'product_name' => $p['product']->name,
                    'sku' => $p['product']->sku,
                    'unit_price' => $p['unit_price'],
                    'quantity' => $p['quantity'],
                    'line_total' => $p['line_total'],
                    'meta' => $p['meta'],
                ]);

                // decrement stock
                $p['product']->decrement('stock', $p['quantity']);
            }

            return $order->load('items');
        });
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
