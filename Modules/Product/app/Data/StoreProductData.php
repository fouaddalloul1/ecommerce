<?php

namespace Modules\Product\Data;

use Spatie\LaravelData\Data;

class StoreProductData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public float $price,
        public ?int $stock,
        public ?string $size,
        public ?string $image_url,
        public ?bool $is_active,
        public int $category_id,
    ) {}
}
