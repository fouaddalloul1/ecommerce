<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Product\Models\Product;

class DecreaseProductStockRequest extends FormRequest
{
    private ?Product $product = null;

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            $id = $this->route('id');

            $this->product = Product::find($id);

            if (!$this->product) {
                $validator->errors()->add(
                    'product',
                    'Product not found'
                );

                return;
            }

            if ($this->product->stock < $this->quantity) {
                $validator->errors()->add(
                    'stock',
                    'Insufficient stock'
                );
            }
        });
    }

    public function validated($key = null, $default = null)
    {
        return array_merge(
            parent::validated($key, $default),
            [
                'product' => $this->product
            ]
        );
    }
}