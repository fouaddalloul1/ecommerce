<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\Product;

class DailyProductStat extends Model
{
    protected $fillable = [
        'daily_report_id',
        'product_id',
        'product_name',
        'quantity_sold',
        'total_revenue',
        'rank',
    ];

    protected $casts = [
        'quantity_sold' => 'integer',
        'total_revenue' => 'decimal:2',
        'rank' => 'integer',
    ];

    public function report(): BelongsTo
    {
        return $this->belongsTo(DailySalesReport::class, 'daily_report_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
