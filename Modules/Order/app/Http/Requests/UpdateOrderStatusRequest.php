<?php
namespace Modules\Order\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // allow admins or the owner depending on your auth rules
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:pending,paid,shipped,completed,cancelled',
            'payment_status' => 'sometimes|string|in:unpaid,paid,refunded',
        ];
    }
}
