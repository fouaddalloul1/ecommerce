<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_product_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_report_id')->constrained('weekly_sales_reports')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('product_name');
            $table->integer('quantity_sold')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->integer('rank')->nullable(); // ترتيب المنتج من حيث الأكثر مبيعاً
            $table->timestamps();

            // منع تكرار المنتج في نفس التقرير
            $table->unique(['weekly_report_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_product_stats');
    }
};
