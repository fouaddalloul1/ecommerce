<?php

namespace Modules\Category\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Category\Models\Category;
class CategoryDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $fixed = [
            ['name' => 'Electronics', 'slug' => 'electronics'],
            ['name' => 'Clothing', 'slug' => 'clothing'],
            ['name' => 'Home & Kitchen', 'slug' => 'home-kitchen'],
            ['name' => 'Books', 'slug' => 'books'],
        ];

        foreach ($fixed as $f) {
            Category::updateOrCreate(['slug' => $f['slug']], array_merge($f, ['description' => $f['name'] . ' category', 'is_active' => true]));
        }

        \Modules\Category\Database\Factories\CategoryFactory::new()->count(8)->create();
    }
}
