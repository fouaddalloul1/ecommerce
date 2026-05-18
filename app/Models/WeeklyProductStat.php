<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeeklyProductStat extends Model
{
    protected $fillable = [
        'weekly_report_id', 'product_id', 'product_name',
        'quantity_sold', 'total_revenue', 'rank'
    ];

    public function report()
    {
        return $this->belongsTo(WeeklySalesReport::class, 'weekly_report_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
