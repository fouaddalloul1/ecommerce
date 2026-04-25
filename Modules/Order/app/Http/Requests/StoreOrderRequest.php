<?php

namespace Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Product\Models\Product;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check(); // only authenticated users can create orders
    }

    public function rules(): array
    {

        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:100',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items');


            // get all product ids from request
            $productIds = collect($items)
                ->pluck('product_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();


            $existingCount = Product::whereIn('id', $productIds)->count();

            // compare request ids count vs db count
            if (count($productIds) !== $existingCount) {
                $validator->errors()->add(
                    'items',
                    'One or more selected products do not exist.'
                );
            }
        });
    }
}
