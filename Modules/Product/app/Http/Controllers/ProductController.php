<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\MainResource;
use Modules\Product\Http\Requests\IndexProductRequest;
use Modules\Product\Http\Requests\StoreProductRequest;
use Modules\Product\Data\IndexProductData;
use Modules\Product\Repositories\ProductRepository;
use Modules\Product\Http\Resources\ProductResource;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Modules\Product\Models\Product;

class ProductController extends Controller
{
    public function __construct(private ProductRepository $repository) {}

    public function index(IndexProductRequest $request): MainResource
    {
        $data = IndexProductData::from($request->validated());
        $products = $this->repository->index($data);

        return MainResource::make(ProductResource::collection($products), null, ResponseAlias::HTTP_OK);
    }

    public function show(int $id): MainResource
    {
        $product = $this->repository->show($id);
        return MainResource::make(new ProductResource($product));
    }

    public function store(StoreProductRequest $request): MainResource
    {
        $payload = $request->validated();
        $product = $this->repository->create($payload);

        return MainResource::make(new ProductResource($product), 'Product created', ResponseAlias::HTTP_CREATED);
    }

    public function update(StoreProductRequest $request, int $id): MainResource
    {
        $product = $this->repository->show($id);
        $product = $this->repository->update($product, $request->validated());

        return MainResource::make(new ProductResource($product), 'Product updated');
    }

    public function destroy(int $id): MainResource
    {
        $product = $this->repository->show($id);
        $this->repository->delete($product);

        return MainResource::make(null, 'Product deleted', ResponseAlias::HTTP_NO_CONTENT);
    }
    // inside ProductController class

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
}
