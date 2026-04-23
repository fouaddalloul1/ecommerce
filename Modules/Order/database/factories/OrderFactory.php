<?php
namespace Modules\Order\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Order\Models\Order;
use Modules\User\Models\User;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        $user = User::inRandomOrder()->first() ?? User::factory()->create();

        $subtotal = $this->faker->randomFloat(2, 20, 500);
        $shipping = $this->faker->randomFloat(2, 0, 20);
        $total = $subtotal + $shipping;

        return [
            'user_id' => $user->id,
            'order_number' => strtoupper(Str::random(10)),
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total' => $total,
            'currency' => 'USD',
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'shipping_address' => ['line1' => $this->faker->streetAddress(), 'city' => $this->faker->city()],
            'billing_address' => ['line1' => $this->faker->streetAddress(), 'city' => $this->faker->city()],
        ];
    }
}
