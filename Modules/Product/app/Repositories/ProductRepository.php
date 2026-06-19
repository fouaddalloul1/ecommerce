<?php

namespace Modules\Product\Repositories;

use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Product\Data\IndexProductData;
use Modules\Product\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Category\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Product\Data\DecreaseProductStockData;
use Modules\Product\Data\ShowProductData;
use Modules\Product\Data\StoreProductData;
use Modules\Product\Data\UpdateProductData;
use Modules\Product\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Modules\Order\Models\OrderItem;
use Modules\Product\Services\PopularProductService;

class ProductRepository
{
    public function index(IndexProductData $data): LengthAwarePaginator
    {
        $query = Product::with('category')->where('is_active', true);


        if ($data->category_id) {
            $query->where('category_id', $data->category_id);
        }

        if (!is_null($data->q)) {
            $query->where('name', 'like', '%' . $data->q . '%');
        }

        return $query->orderBy('name')->paginate();
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

    public function show(ShowProductData $data): Product
    {
        return $data->product;
    }

    public function create(StoreProductData $data)
    {
        return Product::create([
            'name'        => $data->name,
            'description' => $data->description,
            'price'       => $data->price,
            'stock'       => $data->stock,
            'size'        => $data->size,
            'image_url'   => $data->image_url,
            'is_active'   => $data->is_active,
            'category_id' => $data->category_id,
        ]);

        return null;
    }

    public function update(UpdateProductData $data)
    {
        $fields = [
            'name'        => $data->name,
            'description' => $data->description,
            'price'       => $data->price,
            'size'        => $data->size,
            'image_url'   => $data->image_url,
            'is_active'   => $data->is_active,
            'category_id' => $data->category_id,
            'stock' => $data->stock,
        ];

        // Log::info('Updating product with fields: ', $fields);

        $fields = array_filter($fields, fn($value) => !is_null($value));

        $data->product->update($fields);


        return null;
    }

    public function decreaseStock(
        DecreaseProductStockData $data
    ) {

        \Illuminate\Support\Facades\Log::info("Request handled by server port: " . $_SERVER['SERVER_PORT']);

        return DB::transaction(function () use ($data) {

            $product = Product::lockForUpdate()
                ->find($data->product->id);

            if ($product->stock < $data->quantity) {
                throw new \Exception('Insufficient stock');
            }

            // simulate delay
            sleep(1);

            $product->stock -= $data->quantity;

            $product->save();


            return $product;
        });
    }

    public function delete(Product $product): void
    {
        $product->delete();
        PopularProductService::evictPopularProducts();
    }

    public function adjustStock(int $productId, int $delta, string $reason, ?int $referenceId = null): void
    {
        DB::transaction(function () use ($productId, $delta, $reason, $referenceId) {
            $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

            $newStock = $product->stock + $delta;
            if ($newStock < 0) {
                throw new Exception("Not enough stock for product {$product->name}");
            }

            $product->stock = $newStock;
            $product->save();

            StockMovement::create([
                'product_id' => $productId,
                'quantity_change' => $delta,
                'reason' => $reason,
                'reference_id' => $referenceId,
            ]);
        });
    }

    // part-2-6
    public function popular()
    {
        $result = Product::query()
            ->select([
                'products.id',
                'products.name',
                'products.price',
                'products.is_active',
                'products.image_url',
                'products.category_id'
            ])
            ->selectRaw('SUM(order_items.quantity) as sold_quantity')
            ->selectRaw('COUNT(DISTINCT order_items.order_id) as orders_count')
            ->selectRaw('SUM(order_items.quantity * products.price) as estimated_revenue')
            ->join('order_items', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->with('category:id,name')
            ->where('products.is_active', true)
            ->where('orders.status', 'completed')
            ->where('orders.payment_status', 'paid')
            ->groupBy(
                'products.id',
                'products.category_id',
                'products.price',
                'products.name',
                'products.is_active',
                'products.image_url',
            )
            ->orderByDesc('sold_quantity')
            ->limit(15)->get();

        return $result;
    }
}
