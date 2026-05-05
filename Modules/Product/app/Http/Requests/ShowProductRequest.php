<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Product\Models\Product;

class ShowProductRequest extends FormRequest
{
    private ?Product $product = null;

    public function rules(): array
    {
        return []; // GET request → no body
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            // نأخذ id من URL فقط
            $id = $this->route('id');

            $this->product = Product::find($id);

            if (!$this->product) {
                $validator->errors()->add('id', 'The selected product does not exist.');
            }
        });
    }

    public function validated($key = null, $default = null)
    {
        return [
            'product' => $this->product
        ];
    }
}
