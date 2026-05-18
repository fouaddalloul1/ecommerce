<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklySalesReport extends Model
{
    protected $fillable = [
        'year',
        'week_number',
        'week_start_date',
        'week_end_date',
        'total_orders',
        'total_sales',
        'average_order_value',
        'top_product_name',
        'top_product_quantity',
        'bottom_product_name',
        'bottom_product_quantity',
        'status',
        'pdf_path',
        'processed_at'
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'processed_at' => 'datetime',
        'total_sales' => 'decimal:2',
        'average_order_value' => 'decimal:2',
    ];

    public function productStats()
    {
        return $this->hasMany(WeeklyProductStat::class, 'weekly_report_id');
    }
}
