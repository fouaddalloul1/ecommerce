<?php

namespace Modules\Product\Data;

use Modules\Product\Models\Product;
use Spatie\LaravelData\Data;

class UpdateProductData extends Data
{
    public function __construct(
        public Product $product,
        public ?string $name,
        public ?int $stock,
        public ?string $description,
        public ?float $price,
        public ?string $size,
        public ?string $image_url,
        public ?bool $is_active,
        public ?int $category_id,
    ) {}
}
