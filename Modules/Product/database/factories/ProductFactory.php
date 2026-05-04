<?php

namespace Modules\Product\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Product\Models\Product;
use Modules\Category\Models\Category;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        $name = $this->faker->words(3, true);
        $category = Category::inRandomOrder()->first() ?? Category::factory()->create();

        return [
            'category_id' => $category->id,
            'sku' => strtoupper(Str::random(8)),
            'name' => ucfirst($name),
            'description' => $this->faker->paragraph(),
            'price' => $this->faker->randomFloat(2, 5, 1500),
            'stock' => $this->faker->numberBetween(0, 200),
            'color' => $this->faker->randomElement(['black', 'white', 'red', 'blue', 'green', null]),
            'size' => $this->faker->randomElement(['S', 'M', 'L', 'XL', null]),
            'image_url' => $this->faker->imageUrl(640, 480, 'technics', true),
            'is_active' => $this->faker->boolean(90),
            'version' => 1,
        ];
    }
}
