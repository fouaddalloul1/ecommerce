<?php

namespace Modules\Product\Data;

use Modules\Product\Models\Product;
use Spatie\LaravelData\Data;

class DecreaseProductStockData extends Data
{
    public function __construct(
        public Product $product,
        public int $quantity,
    ) {}
}