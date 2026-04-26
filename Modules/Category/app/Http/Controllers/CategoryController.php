<?php

namespace Modules\Category\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\MainResource;
use Modules\Category\Http\Requests\IndexCategoryRequest;
use Modules\Category\Http\Requests\StoreCategoryRequest;
use Modules\Category\Data\IndexCategoryData;
use Modules\Category\Repositories\CategoryRepository;
use Modules\Category\Http\Resources\CategoryResource;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class CategoryController extends Controller
{
    public function __construct(private CategoryRepository $repository) {}

    public function index(IndexCategoryRequest $request): MainResource
    {
        $data = IndexCategoryData::from($request->validated());
        $categories = $this->repository->index($data);

        return MainResource::make(CategoryResource::collection($categories), null, ResponseAlias::HTTP_OK);
    }

    public function show(int $id): MainResource
    {
        $category = $this->repository->findById($id);
        return MainResource::make(new CategoryResource($category));
    }

    public function store(StoreCategoryRequest $request): MainResource
    {
        $payload = $request->validated();
        $category = $this->repository->create($payload);

        return MainResource::make(new CategoryResource($category), 'Category created', ResponseAlias::HTTP_CREATED);
    }

    public function update(StoreCategoryRequest $request, int $id): MainResource
    {
        $category = $this->repository->findById($id);
        $category = $this->repository->update($category, $request->validated());

        return MainResource::make(new CategoryResource($category), 'Category updated');
    }

    public function destroy(int $id): MainResource
    {
        $category = $this->repository->findById($id);
        $this->repository->delete($category);

        return MainResource::make(null, 'Category deleted', ResponseAlias::HTTP_NO_CONTENT);
    }
}
