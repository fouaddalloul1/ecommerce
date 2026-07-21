<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailySalesReport extends Model
{
    protected $fillable = [
        'report_date',
        'period_start',
        'period_end',
        'total_orders',
        'total_sales',
        'average_order_value',
        'top_product_name',
        'top_product_quantity',
        'bottom_product_name',
        'bottom_product_quantity',
        'processed_rows',
        'chunk_size',
        'status',
        'pdf_path',
        'error_message',
        'started_at',
        'processed_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'total_sales' => 'decimal:2',
        'average_order_value' => 'decimal:2',
        'started_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function productStats(): HasMany
    {
        return $this->hasMany(DailyProductStat::class, 'daily_report_id');
    }
}
