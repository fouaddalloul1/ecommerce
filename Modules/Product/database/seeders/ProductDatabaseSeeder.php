<?php

namespace Modules\Product\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categoryIds = DB::table('categories')->pluck('id');

        foreach ($categoryIds as $categoryId) {
            for ($i = 1; $i <= 10; $i++) {
                DB::table('products')->insert([
                    'category_id' => $categoryId,
                    'name' => "Product {$categoryId}-{$i}",
                    'description' => "Description for product {$i}",
                    'price' => rand(50, 500),
                    'stock' => rand(10000, 100000),
                    'size' => ['S', 'M', 'L', null][array_rand(['S', 'M', 'L', null])],
                    'image_url' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
