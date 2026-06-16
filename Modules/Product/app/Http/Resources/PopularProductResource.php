<?php

namespace Modules\Product\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

class PopularProductResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'id' => $this->id,

            'category' => $this->relationLoaded('category') ? [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ] : null,

            'name' => $this->name,
            'price' => (float) $this->price,
            'image_url' => $this->image_url,
            'is_active' => (bool) $this->is_active,

            'popularity' => [
                'sold_quantity' => (int) $this->sold_quantity,
                'orders_count' => (int) $this->orders_count,
                'estimated_revenue' => (float) $this->estimated_revenue,
            ],
        ];
    }
}
