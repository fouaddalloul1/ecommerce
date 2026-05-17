<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Category\Models\Category;

class Product extends Model
{
    protected $table = 'products';
    protected $guarded = [];

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'stock',
        'size',
        'image_url',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
