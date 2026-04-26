<?php

namespace Modules\Order\Data;

use Spatie\LaravelData\Data;

class CreateOrderData extends Data
{

    public function __construct(
        public array $items,
        public int $user_id
    ) {}
}
