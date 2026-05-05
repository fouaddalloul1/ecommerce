<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:191'],
            'description' => ['sometimes', 'string', 'nullable'],
            'price'       => ['required', 'numeric', 'min:0'],
            'stock'       => ['sometimes', 'integer', 'min:0'],
            'size'        => ['sometimes', 'string', 'nullable'],
            'image_url'   => ['sometimes', 'url', 'nullable'],
            'is_active'   => ['sometimes', 'boolean'],
            'category_id' => ['required', 'integer'],
        ];
    }
}
