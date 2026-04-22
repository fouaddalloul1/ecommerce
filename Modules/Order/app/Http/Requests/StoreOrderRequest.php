<?php
namespace Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.meta' => 'sometimes|array',
            'shipping' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:10',
            'shipping_address' => 'sometimes|array',
            'billing_address' => 'sometimes|array',
        ];
    }
}
