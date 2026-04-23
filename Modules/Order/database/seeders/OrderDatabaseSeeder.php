<?php
namespace Modules\Order\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Order\Repositories\OrderRepository;
use Modules\Product\Models\Product;
use Modules\User\Models\User;

class OrderDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::count() === 0) {
            User::factory()->count(5)->create();
        }

        $repo = app(OrderRepository::class);
        $users = User::inRandomOrder()->take(5)->get();

        foreach ($users as $user) {
            $availableProducts = Product::where('stock', '>', 0)->inRandomOrder()->take(3)->get();
            if ($availableProducts->isEmpty()) continue;

            $items = [];
            foreach ($availableProducts as $p) {
                $qty = min(2, max(1, (int)($p->stock > 0 ? 1 : 0)));
                $items[] = ['product_id' => $p->id, 'quantity' => $qty];
            }

            $payload = [
                'user_id' => $user->id,
                'items' => $items,
                'shipping' => 5.00,
                'currency' => 'USD',
                'shipping_address' => ['line1' => 'Seeder St', 'city' => 'Seeder City'],
                'billing_address' => ['line1' => 'Seeder St', 'city' => 'Seeder City'],
            ];

            try {
                $repo->create(\Modules\Order\Data\CreateOrderData::from($payload));
            } catch (\Exception $e) {
                continue;
            }
        }
    }
}


