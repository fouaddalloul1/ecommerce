<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Modules\Product\Models\Product;

class DestroyProductRequest extends FormRequest
{
    private ?Product $product;

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            Log::info('read the produc');

            $id = $this->route('id');
            
            Log::info('read the product id : ' . $id);

            $this->product = Product::find($id);


            if (!$this->product) {
                $validator->errors()->add('id', 'The selected product does not exist.');
            }
        });
    }

    public function validated($key = null, $default = null)
    {
        return ['product' => $this->product];
    }
}
