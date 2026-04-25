<?php

namespace Modules\Order\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [

            // 'id' => $this->id,
            // 'order_number' => $this->order_number,
            // 'subtotal' => (float)$this->subtotal,
            // 'shipping' => (float)$this->shipping,
            // 'total' => (float)$this->total,
            // 'currency' => $this->currency,
            // 'status' => $this->status,
            // 'payment_status' => $this->payment_status,
            // 'shipping_address' => $this->shipping_address,
            // 'billing_address' => $this->billing_address,
            // 'items' => $this->items->map(fn($i) => [
            //     'id' => $i->id,
            //     'product_id' => $i->product_id,
            //     'product_name' => $i->product_name,
            //     'sku' => $i->sku,
            //     'unit_price' => (float)$i->unit_price,
            //     'quantity' => (int)$i->quantity,
            //     'line_total' => (float)$i->line_total,
            //     'meta' => $i->meta,
            // ]),
            // 'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
