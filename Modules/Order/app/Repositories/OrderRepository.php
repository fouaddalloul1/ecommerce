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
        /*
         * نخزن نتيجة الـTransaction بدل إرجاعها مباشرة،
         * حتى نستطيع حذف كاش المنتجات بعد نجاح الـCommit.
         */
        $order = DB::transaction(function () use ($data) {
            $total = 0;
            $itemsPayload = [];

            // Get all product ids once.
            $productIds = collect($data->items)
                ->pluck('product_id')
                ->unique()
                ->values()
                ->toArray();

            /*
             * Pessimistic locking:
             * نحجز سجلات المنتجات حتى لا يعدل طلب متزامن
             * المخزون نفسه قبل انتهاء هذه المعاملة.
             */
            $products = Product::query()
                ->whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // Lock the authenticated user before deducting balance.
            $user = User::query()
                ->where('id', auth()->id())
                ->lockForUpdate()
                ->first();

            if (! $user) {
                throw new \Exception('Authenticated user not found.');
            }

            foreach ($data->items as $item) {
                $product = $products->get($item['product_id']);

                if (! $product) {
                    throw new \Exception(
                        "Product {$item['product_id']} not found."
                    );
                }

                if ($product->stock < $item['quantity']) {
                    throw new \Exception(
                        "Product {$product->id} does not have enough stock."
                    );
                }

                /*
                 * Snapshot للسعر وقت تنفيذ الطلب.
                 * التقارير المستقبلية يجب ألا تعتمد على سعر المنتج الحالي،
                 * لأن السعر قد يتغير بعد إتمام عملية الشراء.
                 */
                $unitPrice = (float) $product->price;
                $lineTotal = round(
                    $unitPrice * $item['quantity'],
                    2
                );

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
                throw new \Exception('Insufficient balance.');
            }

            // Deduct balance.
            $user->decrement('balance', $total);

            $order = Order::create([
                'user_id' => $data->user_id,
                'total' => $total,
                'status' => OrderStatus::COMPLETED->value,
                'payment_status' => PaymentStatus::PAID->value,
            ]);

            foreach ($itemsPayload as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],

                    // Required for accurate sales reports.
                    'unit_price' => $item['unit_price'],
                    'line_total' => $item['line_total'],
                ]);

                $item['product']->decrement(
                    'stock',
                    $item['quantity']
                );
            }

            /*
             * ترسل المهام إلى Redis بعد نجاح الـCommit فقط.
             *
             * إذا فشلت المعاملة وعمل النظام Rollback،
             * فلن يتم إنشاء PDF أو إرسال إشعار لطلب فاشل.
             */
            GeneratePdfJob::dispatch($order->id)
                ->onQueue('invoices')
                ->afterCommit();

            SendNotificationJob::dispatch($order->id)
                ->onQueue('notifications')
                ->afterCommit();

            return $order->load('items');
        }, 5);

        /*
         * كان هذا السطر بعد return في النسخة القديمة،
         * ولذلك لم يكن يُنفذ نهائيًا.
         *
         * نضعه بعد نجاح المعاملة، ولا نجعل فشل Redis Cache
         * يحول الطلب الناجح إلى استجابة خطأ.
         */
        try {
            PopularProductService::evictPopularProducts();
        } catch (\Throwable $exception) {
            Log::warning('Popular products cache eviction failed.', [
                'order_id' => $order->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $order;
    }

    public function find(int $id): Order
    {
        return Order::query()
            ->select([
                'id',
                'total',
                'status',
            ])
            ->findOrFail($id);
    }

    public function listForUser(int $perPage = 10)
    {
        return auth()->user()
            ->orders()
            ->select(['id', 'total', 'status'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function updateStatus(
        Order $order,
        string $status,
        ?string $paymentStatus = null
    ): Order {
        $order->status = $status;

        if ($paymentStatus) {
            $order->payment_status = $paymentStatus;
        }

        $order->save();

        return $order;
    }

    public function cancel(Order $order): Order
    {
        /*
         * أبقينا منطق cancel الخاص برفيقك كما هو،
         * ولم ندمج تعديلات أخرى فيه.
         */
        return DB::transaction(function () use ($order) {
            if ($order->status === OrderStatus::CANCELLED->value) {
                return $order;
            }

            // Restore stock in bulk.
            $items = $order->items;

            $productStockMap = [];

            foreach ($items as $item) {
                $productStockMap[$item->product_id] =
                    ($productStockMap[$item->product_id] ?? 0)
                    + $item->quantity;
            }

            $products = Product::whereIn(
                'id',
                array_keys($productStockMap)
            )->get();

            foreach ($products as $product) {
                $product->increment(
                    'stock',
                    $productStockMap[$product->id]
                );
            }

            $order->update([
                'status' => OrderStatus::CANCELLED->value,
                'payment_status' => 'refunded',
            ]);

            PopularProductService::evictPopularProducts();

            return $order->refresh();
        });
    }
}
