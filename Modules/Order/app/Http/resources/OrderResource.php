<?php

namespace Modules\Order\Http\resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'total' => (float) $this->total,
            'status' => $this->status,
        ];
    }
}
