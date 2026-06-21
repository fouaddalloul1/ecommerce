<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_product_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_id')
                ->constrained('daily_sales_reports')
                ->cascadeOnDelete();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('product_name');
            $table->unsignedBigInteger('quantity_sold')->default(0);
            $table->decimal('total_revenue', 14, 2)->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->timestamps();

            $table->unique(['daily_report_id', 'product_id']);
            $table->index(['daily_report_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_product_stats');
    }
};
