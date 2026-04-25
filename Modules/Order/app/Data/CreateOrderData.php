<?php

namespace Modules\Order\Data;

use App\Data\SpatieData;

class CreateOrderData extends SpatieData
{

    public function __construct(
        public array $items,
        public int $user_id
    ) {}
}
