<?php

namespace Modules\Product\Data;

use Modules\Product\Models\Product;
use Spatie\LaravelData\Data;

class DestroyProductData extends Data
{
    public function __construct(
        public Product $product,
    ) {}
}
