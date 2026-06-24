<?php

namespace Modules\Order\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'total' => (float) $this->total,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'async_processing' => [
                'invoice_generated' => $this->invoice_generated_at !== null,
                'invoice_generated_at' => $this->invoice_generated_at?->toDateTimeString(),
                'invoice_sent' => $this->invoice_sent_at !== null,
                'invoice_sent_at' => $this->invoice_sent_at?->toDateTimeString(),
                'notification_sent' => $this->notification_sent_at !== null,
                'notification_sent_at' => $this->notification_sent_at?->toDateTimeString(),
            ],
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(fn ($item): array => [
                    'id' => (int) $item->id,
                    'product_id' => (int) $item->product_id,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => $item->unit_price !== null ? (float) $item->unit_price : null,
                    'line_total' => $item->line_total !== null ? (float) $item->line_total : null,
                ]);
            }),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
