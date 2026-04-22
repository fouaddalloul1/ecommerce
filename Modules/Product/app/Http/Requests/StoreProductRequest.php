<?php
namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        // change to auth check if needed
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('id');

        return [
            'category_id' => 'required|integer|exists:categories,id',
            'name' => 'required|string|max:191',
            'sku' => [
                'sometimes','nullable','string','max:191',
                Rule::unique('products','sku')->ignore($productId),
            ],
            'description' => 'sometimes|string|nullable',
            'selling_price' => 'required|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'color' => 'sometimes|string|nullable',
            'size' => 'sometimes|string|nullable',
            'image_url' => 'sometimes|url|nullable',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
