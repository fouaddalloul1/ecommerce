<?php

namespace Modules\Product\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Product\Repositories\ProductRepository;

class PopularProductService
{
    /**
     * Fixed cache key for popular products.
     */
    private const CACHE_KEY = 'popular_products';

    /**
     * Distributed lock key shared between all application servers.
     */
    private const CACHE_LOCK_KEY = 'popular_products:rebuild_lock';

    /**
     * Cache lifetime: 2 hours.
     */
    private const CACHE_TTL_SECONDS = 7200;

    /**
     * Maximum lifetime of the lock.
     *
     * It must be longer than the maximum expected duration of:
     * database query + data transformation + cache write.
     */
    private const LOCK_TTL_SECONDS = 30;

    /**
     * Maximum time other requests wait for the request
     * that is currently rebuilding the cache.
     */
    private const LOCK_WAIT_SECONDS = 10;

    public function __construct(
        private ProductRepository $repository
    ) {}

    public function popular(): array
    {
        /*
         * First cache check.
         *
         * This is the normal fast path. When the cache exists,
         * the request returns immediately without acquiring a lock.
         */
        $cachedPayload = Cache::get(self::CACHE_KEY);

        if ($cachedPayload !== null) {
            return $cachedPayload;
        }

        /*
         * The cache is missing.
         *
         * All application servers compete for the same Redis lock.
         * Only one request can enter the callback.
         * The other requests wait for a maximum of
         * LOCK_WAIT_SECONDS.
         */
        return Cache::lock(
            self::CACHE_LOCK_KEY,
            self::LOCK_TTL_SECONDS
        )->block(
            self::LOCK_WAIT_SECONDS,
            function (): array {
                /*
                 * Second cache check.
                 *
                 * Another request may have created the cache while
                 * this request was waiting for the distributed lock.
                 */
                $cachedPayload = Cache::get(self::CACHE_KEY);

                if ($cachedPayload !== null) {
                    return $cachedPayload;
                }

                /*
                 * Only the request that owns the distributed lock
                 * reaches the database.
                 */
                $products = $this->repository->popular();

                $payload = $products
                    ->map(fn ($product) => $this->transformProduct($product))
                    ->values()
                    ->all();

                Cache::put(
                    self::CACHE_KEY,
                    $payload,
                    now()->addSeconds(self::CACHE_TTL_SECONDS)
                );

                return $payload;
            }
        );
    }

    /**
     * Invalidate the popular-products cache.
     *
     * The same distributed lock prevents invalidation from running
     * concurrently with cache rebuilding.
     */
    public static function evictPopularProducts(): void
    {
        Cache::lock(
            self::CACHE_LOCK_KEY,
            self::LOCK_TTL_SECONDS
        )->block(
            self::LOCK_WAIT_SECONDS,
            function (): void {
                Cache::forget(self::CACHE_KEY);
            }
        );
    }

    private function transformProduct($product): array
    {
        return [
            'id' => (int) $product->id,

            'category' => $product->relationLoaded('category')
                && $product->category
                    ? [
                        'id' => (int) $product->category->id,
                        'name' => $product->category->name,
                    ]
                    : null,

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


// without lock

// namespace Modules\Product\Services;

// use Illuminate\Support\Facades\Cache;
// use Modules\Product\Repositories\ProductRepository;

// class PopularProductService
// {
//     private const CACHE_KEY = 'popular_products';
//     private const CACHE_TTL_SECONDS = 7200;

//     public function __construct(
//         private ProductRepository $repository
//     ) {}

//     public function popular(): array
//     {
//         return Cache::remember(
//             self::CACHE_KEY,
//             self::CACHE_TTL_SECONDS,
//             function (): array {
//                 $products = $this->repository->popular();

//                 return $products
//                     ->map(fn($product) => $this->transformProduct($product))
//                     ->values()
//                     ->all();
//             }
//         );
//     }

//     public static function evictPopularProducts(): void
//     {
//         Cache::forget(self::CACHE_KEY);
//     }

//     private function transformProduct($product): array
//     {
//         return [
//             'id' => (int) $product->id,

//             'category' => $product->relationLoaded('category') && $product->category
//                 ? [
//                     'id' => (int) $product->category->id,
//                     'name' => $product->category->name,
//                 ]
//                 : null,

//             'name' => $product->name,
//             'price' => (float) $product->price,
//             'image_url' => $product->image_url,
//             'is_active' => (bool) $product->is_active,

//             'popularity' => [
//                 'sold_quantity' => (int) $product->sold_quantity,
//                 'orders_count' => (int) $product->orders_count,
//                 'estimated_revenue' => (float) $product->estimated_revenue,
//             ],
//         ];
//     }
// }
