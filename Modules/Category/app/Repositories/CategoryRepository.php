<?php
namespace Modules\Category\Repositories;

use Modules\Category\Data\IndexCategoryData;
use Modules\Category\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryRepository
{
    public function index(IndexCategoryData $data): LengthAwarePaginator
    {
        $query = Category::query();

        if (!is_null($data->is_active)) {
            $query->where('is_active', $data->is_active);
        }

        if ($data->q) {
            $query->where('name', 'like', '%' . $data->q . '%');
        }

        return $query->orderBy('name')->paginate($data->per_page, ['*'], 'page', $data->page);
    }

    public function findById(int $id): Category
    {
        return Category::findOrFail($id);
    }

    public function create(array $payload): Category
    {
        return Category::create($payload);
    }

    public function update(Category $category, array $payload): Category
    {
        $category->update($payload);
        return $category;
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
