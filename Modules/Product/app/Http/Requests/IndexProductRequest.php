<?php
namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'q' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
