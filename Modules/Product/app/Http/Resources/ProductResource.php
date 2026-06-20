<?php
namespace Modules\Product\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category?->id,
                    'name' => $this->category?->name,
                ];
            }),
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float)$this->price,
            'stock' => (int)$this->stock,
            'color' => $this->color,
            'size' => $this->size,
            'image_url' => $this->image_url,
            'is_active' => (bool)$this->is_active,
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
