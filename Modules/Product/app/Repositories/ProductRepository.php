<?php

namespace Modules\Product\Repositories;

use Modules\Product\Data\IndexProductData;
use Modules\Product\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Category\Models\Category;
use Illuminate\Support\Facades\Cache;

class ProductRepository
{
    public function index(IndexProductData $data): LengthAwarePaginator
    {
        $query = Product::with('category');

        if (!is_null($data->is_active)) {
            $query->where('is_active', $data->is_active);
        }

        if ($data->category_id) {
            $query->where('category_id', $data->category_id);
        }

        if ($data->q) {
            $query->where('name', 'like', '%' . $data->q . '%');
        }

        return $query->orderBy('name')->paginate($data->per_page, ['*'], 'page', $data->page);
    }

    public function indexByCategorySlug(IndexProductData $data, string $slug): LengthAwarePaginator
    {
        /*
         *⚠️ When not to cache
        Data that changes very frequently (like stock counts in checkout)
        Sensitive data per user (like “my orders”)
         */
        //cache caching #tfr
        $cacheKey = "products_category_slug_{$slug}_page_{$data->page}_per_{$data->per_page}_q_{$data->q}_active_{$data->is_active}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($data, $slug) {
            $category = Category::where('slug', $slug)->firstOrFail();
            $data->category_id = $category->id;
            return $this->index($data);
        });
    }
    public function show(int $id): Product
    {
        return Product::with('category')->findOrFail($id);
    }

    public function create(array $payload): Product
    {
        return Product::create($payload);
    }

    public function update(Product $product, array $payload): Product
    {
        $product->update($payload);
        return $product;
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
