<?php

namespace Modules\Product\Data;

use Modules\Product\Models\Product;
use Spatie\LaravelData\Data;

class ShowProductData extends Data
{
    public function __construct(
        public Product $product,
    ) {}
}
