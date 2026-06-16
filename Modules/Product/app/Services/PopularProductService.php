<?php

namespace Modules\Product\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Product\Repositories\ProductRepository;

class PopularProductService
{
    private const CACHE_KEY_PREFIX = 'popular_products';
    private const CACHE_VERSION_KEY = 'popular_products_version';
    private const CACHE_TTL_SECONDS = 600;

    public function __construct(
        private ProductRepository $repository
    ) {}

    public function popular(): array
    {

        $cacheKey = $this->cacheKey();

        $cachedPayload = Cache::get($cacheKey);

        if ($cachedPayload !== null) {
           
            return $cachedPayload;
        }


        $products = $this->repository->popular();

        $payload = $products
            ->map(fn ($product) => $this->transformProduct($product))
            ->values()
            ->all();

        Cache::put(
            $cacheKey,
            $payload,
            now()->addSeconds(self::CACHE_TTL_SECONDS)
        );

        

        return $payload;
    }

    public static function evictPopularProducts(): void
    {
        Cache::increment(self::CACHE_VERSION_KEY);

    }

    private function cacheKey(): string
    {
        $version = Cache::get(self::CACHE_VERSION_KEY, 1);

        return self::CACHE_KEY_PREFIX ;
    }

    private function transformProduct($product): array
    {
        return [
            'id' => (int) $product->id,

            'category' => $product->relationLoaded('category') && $product->category ? [
                'id' => (int) $product->category->id,
                'name' => $product->category->name,
            ] : null,

            'name' => $product->name,
            'price' => (float) $product->price,
            'image_url' => $product->image_url,
            'is_active' => (bool) $product->is_active,

            'popularity' => [
                'sold_quantity' => (int) $product->sold_quantity,
                'orders_count' => (int) $product->orders_count,
                'estimated_revenue' => (float) $product->estimated_revenue,
            ],
        ];
    }

}