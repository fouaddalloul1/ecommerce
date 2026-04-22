<?php
namespace Modules\Order\Data;

use Spatie\DataTransferObject\DataTransferObject;

class CreateOrderData extends DataTransferObject
{
    public int $user_id;
    public array $items; // each item: ['product_id'=>int,'quantity'=>int,'meta'=>array|null]
    public ?float $shipping;
    public ?string $currency;
    public ?array $shipping_address;
    public ?array $billing_address;

    public static function from(array $validated): self
    {
        return new self($validated);
    }
}
