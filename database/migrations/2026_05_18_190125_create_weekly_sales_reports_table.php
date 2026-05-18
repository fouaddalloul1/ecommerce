<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_sales_reports', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->integer('week_number'); // رقم الأسبوع (1-52)
            $table->date('week_start_date');
            $table->date('week_end_date');

            // مؤشرات المبيعات
            $table->integer('total_orders')->default(0);
            $table->decimal('total_sales', 12, 2)->default(0);
            $table->decimal('average_order_value', 12, 2)->default(0);

            // أفضل وأسوأ المنتجات
            $table->string('top_product_name')->nullable();
            $table->integer('top_product_quantity')->default(0);
            $table->string('bottom_product_name')->nullable();
            $table->integer('bottom_product_quantity')->default(0);

            // حالة التقرير
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('pdf_path')->nullable(); // رابط ملف PDF
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            // فهرس لضمان عدم التكرار (Idempotency)
            $table->unique(['year', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_sales_reports');
    }
};
