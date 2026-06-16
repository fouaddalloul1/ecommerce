<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\MainResource;
use Illuminate\Support\Facades\Log;
use Modules\Product\Data\DecreaseProductStockData;
use Modules\Product\Data\DestroyProductData;
use Modules\Product\Http\Requests\IndexProductRequest;
use Modules\Product\Http\Requests\StoreProductRequest;
use Modules\Product\Data\IndexProductData;
use Modules\Product\Data\ShowProductData;
use Modules\Product\Data\StoreProductData;
use Modules\Product\Data\UpdateProductData;
use Modules\Product\Http\Requests\DecreaseProductStockRequest;
use Modules\Product\Http\Requests\DestroyProductRequest;
use Modules\Product\Http\Requests\ShowProductRequest;
use Modules\Product\Http\Requests\UpdateProductRequest;
use Modules\Product\Repositories\ProductRepository;
use Modules\Product\Http\Resources\ProductResource;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Modules\Product\Http\Requests\PopularProductsRequest;
use Modules\Product\Http\Resources\PopularProductResource;
use Modules\Product\Services\PopularProductService;
use OpenApi\Annotations as OA;

class ProductController extends Controller
{
    public function __construct(
        private ProductRepository $repository,
        private PopularProductService $popularProductService
    ) {}

    /** 
     * @OA\Get(
     *     path="/api/v1/products",
     *     tags={"Products"},
     *     summary="List products with filters",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         required=false,
     *         description="Search by product name",
     *         @OA\Schema(type="string", example="shirt")
     *     ),
     *
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         required=false,
     *         description="Filter by category ID",
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of products",
     *
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Success"
     *             ),
     *
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="category_id", type="integer", example=3),
     *                     @OA\Property(property="name", type="string", example="Blue Shirt"),
     *                     @OA\Property(property="description", type="string", example="Cotton shirt"),
     *                     @OA\Property(property="price", type="number", example=19.99),
     *                     @OA\Property(property="stock", type="integer", example=50),
     *                     @OA\Property(property="size", type="string", example="L"),
     *                     @OA\Property(property="image_url", type="string", example="https://example.com/image.jpg"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", example="2024-01-01 12:00:00"),
     *                     @OA\Property(property="updated_at", type="string", example="2024-01-01 12:00:00")
     *                 )
     *             ),
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(IndexProductRequest $request): MainResource
    {
        $data = IndexProductData::from($request->validated());

        $products = $this->repository->index($data);

        return MainResource::make(ProductResource::collection($products), null, ResponseAlias::HTTP_OK);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/{id}",
     *     tags={"Products"},
     *     summary="Show product details",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer", example=12)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product details",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=12),
     *                 @OA\Property(property="category_id", type="integer", example=3),
     *                 @OA\Property(property="name", type="string", example="Product Name"),
     *                 @OA\Property(property="description", type="string", example="Product description"),
     *                 @OA\Property(property="price", type="number", example=199.99),
     *                 @OA\Property(property="stock", type="integer", example=50),
     *                 @OA\Property(property="size", type="string", example="XL"),
     *                 @OA\Property(property="image_url", type="string", example="https://example.com/image.png"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", example="2024-01-01 10:00:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2024-01-02 12:00:00")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function show(ShowProductRequest $request): MainResource
    {
        $data = ShowProductData::from($request->validated());

        return MainResource::make(new ProductResource($this->repository->show($data)));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/products",
     *     tags={"Products"},
     *     summary="Create a new product",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","price","category_id"},
     *
     *             @OA\Property(property="name", type="string", example="Blue Shirt"),
     *             @OA\Property(property="description", type="string", example="Cotton shirt"),
     *             @OA\Property(property="price", type="number", example=19.99),
     *             @OA\Property(property="stock", type="integer", example=50),
     *             @OA\Property(property="size", type="string", example="L"),
     *             @OA\Property(property="image_url", type="string", example="https://example.com/image.jpg"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="category_id", type="integer", example=3)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Product created",
     *
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product created"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Blue Shirt"),
     *                 @OA\Property(property="description", type="string", example="Cotton shirt"),
     *                 @OA\Property(property="price", type="number", example=19.99),
     *                 @OA\Property(property="stock", type="integer", example=50),
     *                 @OA\Property(property="size", type="string", example="L"),
     *                 @OA\Property(property="image_url", type="string", example="https://example.com/image.jpg"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="category_id", type="integer", example=3),
     *                 @OA\Property(property="created_at", type="string", example="2026-05-05 13:46:52"),
     *                 @OA\Property(property="updated_at", type="string", example="2026-05-05 13:46:52")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function store(StoreProductRequest $request): MainResource
    {
        $data = StoreProductData::from($request->validated());

        $product = $this->repository->create($data);

        return MainResource::make(
            null,
            'Product created',
            ResponseAlias::HTTP_CREATED
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/products/update/{id}",
     *     tags={"Products"},
     *     summary="Update product",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer", example=9)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="New Product Name"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="price", type="number", example=199.99),
     *             @OA\Property(property="stock", type="integer", example=50),
     *             @OA\Property(property="size", type="string", example="XL"),
     *             @OA\Property(property="image_url", type="string", example="https://example.com/image.png"),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="category_id", type="integer", example=3)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Updated"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=9),
     *                 @OA\Property(property="category_id", type="integer", example=3),
     *                 @OA\Property(property="name", type="string", example="New Product Name"),
     *                 @OA\Property(property="description", type="string", example="Updated description"),
     *                 @OA\Property(property="price", type="number", example=199.99),
     *                 @OA\Property(property="stock", type="integer", example=50),
     *                 @OA\Property(property="size", type="string", example="XL"),
     *                 @OA\Property(property="image_url", type="string", example="https://example.com/image.png"),
     *                 @OA\Property(property="is_active", type="boolean", example=true),
     *                 @OA\Property(property="created_at", type="string", example="2024-01-01 10:00:00"),
     *                 @OA\Property(property="updated_at", type="string", example="2024-01-02 12:00:00")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Product not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function update(UpdateProductRequest $request): MainResource
    {
        $data = UpdateProductData::from($request->validated());
        $product = $this->repository->update($data);
        return MainResource::make(new ProductResource($product), 'Product updated');
    }


    /**
     * @OA\Put(
     *     path="/api/v1/products/decrease-stock/{id}",
     *     tags={"Products"},
     *     summary="Decrease product stock",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Product ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"quantity"},
     *             @OA\Property(
     *                 property="quantity",
     *                 type="integer",
     *                 example=2
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Stock updated successfully"
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function decreaseStock(
        DecreaseProductStockRequest $request
    ): MainResource {

        $data = DecreaseProductStockData::from(
            $request->validated()
        );

        $product = $this->repository->decreaseStock($data);

        return MainResource::make(
            new ProductResource($product),
            'Stock decreased successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/products/{id}",
     *     tags={"Products"},
     *     summary="Delete a product",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the product to delete",
     *         @OA\Schema(type="integer", example=5)
     *     ),
     *
     *     @OA\Response(
     *         response=204,
     *         description="Product deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Product deleted"),
     *             @OA\Property(property="data", type="null", example=null)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */

    public function destroy(DestroyProductRequest $request): MainResource
    {
        $data = DestroyProductData::from($request->validated());

        $this->repository->delete($data->product);

        return MainResource::make(null, 'Product deleted', ResponseAlias::HTTP_OK);
    }



    public function byCategoryId(IndexProductRequest $request, int $categoryId): MainResource
    {
        $data = IndexProductData::from(array_merge($request->validated(), ['category_id' => $categoryId]));
        $products = $this->repository->index($data);

        return MainResource::make(ProductResource::collection($products), null, ResponseAlias::HTTP_OK);
    }

    public function byCategorySlug(IndexProductRequest $request, string $slug): MainResource
    {
        // repository will resolve slug -> category_id
        $data = IndexProductData::from($request->validated());
        $products = $this->repository->indexByCategorySlug($data, $slug);

        return MainResource::make(ProductResource::collection($products), null, ResponseAlias::HTTP_OK);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/products/popular",
     *     tags={"Products"},
     *     summary="Get popular products",
     *     description="Returns the most popular products based on total sold quantity from order items. This endpoint is cached using Redis.",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Popular products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="category_id", type="integer", example=3),
     *                     @OA\Property(property="name", type="string", example="Blue Shirt"),
     *                     @OA\Property(property="description", type="string", example="Cotton shirt"),
     *                     @OA\Property(property="price", type="number", example=19.99),
     *                     @OA\Property(property="stock", type="integer", example=50),
     *                     @OA\Property(property="size", type="string", example="L"),
     *                     @OA\Property(property="image_url", type="string", example="https://example.com/image.jpg"),
     *                     @OA\Property(property="is_active", type="boolean", example=true),
     *                     @OA\Property(property="created_at", type="string", example="2026-05-05 13:46:52"),
     *                     @OA\Property(property="updated_at", type="string", example="2026-05-05 13:46:52"),
     *                     @OA\Property(property="sold_quantity", type="integer", example=42),
     *                     @OA\Property(property="orders_count", type="integer", example=15),
     *                     @OA\Property(property="estimated_revenue", type="number", example=839.58)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    // part-2-6
   public function popular(): MainResource
{
    $products = $this->popularProductService->popular();

    return MainResource::make(
        $products,
        null,
        ResponseAlias::HTTP_OK
    );
}
}
