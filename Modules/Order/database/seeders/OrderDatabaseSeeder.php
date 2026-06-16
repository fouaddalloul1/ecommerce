<?php

namespace Modules\Order\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Order\Enums\OrderStatus;
use Modules\Order\Enums\PaymentStatus;

class OrderDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = DB::table('users')->pluck('id');
        $products = DB::table('products')->get();

        foreach ($users as $userId) {
            for ($i = 0; $i < 25; $i++) {

                $orderId = DB::table('orders')->insertGetId([
                    'user_id' => $userId,
                    'total' => 0,
                    'status' => OrderStatus::COMPLETED,
                    'payment_status' => PaymentStatus::PAID,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $total = 0;

                $selectedProducts = $products->random(10);

                foreach ($selectedProducts as $product) {

                    $qty = rand(1, 3);
                    $lineTotal = $product->price * $qty;
                    $total += $lineTotal;

                    DB::table('order_items')->insert([
                        'order_id' => $orderId,
                        'product_id' => $product->id,
                        'quantity' => $qty,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('orders')
                    ->where('id', $orderId)
                    ->update(['total' => $total]);
            }
        }
    }
}
