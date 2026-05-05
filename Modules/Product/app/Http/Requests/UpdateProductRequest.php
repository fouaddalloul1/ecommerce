<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;

class UpdateProductRequest extends FormRequest
{
    private ?Product $product;

    public function rules(): array
    {
        Log::info('Request data : ', $this->all());

        return [
            'name'        => ['sometimes', 'string'],
            'description' => ['sometimes', 'string', 'nullable'],
            'price'       => ['sometimes', 'numeric'],
            'size'        => ['sometimes', 'string', 'nullable'],
            'image_url'   => ['sometimes', 'string', 'nullable'],
            'is_active'   => ['sometimes', 'boolean'],
            'category_id' => ['sometimes', 'integer'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $id = $this->route('id');

            $this->product = Product::findOrFail($id);

            if (!$this->product) {
                $validator->errors()->add('product_id', 'The selected product does not exist.');
            }
        });
    }

    public function validated($key = null, $default = null)
    {
        return array_merge(
            parent::validated($key, $default),
            ['product' => $this->product]
        );
    }
}
