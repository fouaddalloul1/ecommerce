<?php
namespace Modules\Category\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // allow only authenticated users if you want:
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:191',
            'slug' => 'required|string|max:191|unique:categories,slug',
            'description' => 'sometimes|string|nullable',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
