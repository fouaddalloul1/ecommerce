<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Product\Models\Product;
use Modules\Category\Models\Category;

class ProductDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Category::count() === 0) {
            $this->call(\Modules\Category\Database\Seeders\CategoryDatabaseSeeder::class);
        }

        \Modules\Product\Database\Factories\ProductFactory::new()->count(80)->create();

        $electronics = Category::where('slug', 'electronics')->first();
        if ($electronics) {
            \Modules\Product\Database\Factories\ProductFactory::new()->count(5)->create(['category_id' => $electronics->id, 'stock' => 200]);
        }
    }
}
