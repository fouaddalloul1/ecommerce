<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_sales_reports', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->unique();
            $table->dateTime('period_start');
            $table->dateTime('period_end');

            $table->unsignedBigInteger('total_orders')->default(0);
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->decimal('average_order_value', 14, 2)->default(0);

            $table->string('top_product_name')->nullable();
            $table->unsignedBigInteger('top_product_quantity')->default(0);
            $table->string('bottom_product_name')->nullable();
            $table->unsignedBigInteger('bottom_product_quantity')->default(0);

            $table->unsignedBigInteger('processed_rows')->default(0);
            $table->unsignedInteger('chunk_size')->default(500);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])
                ->default('pending')
                ->index();
            $table->string('pdf_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_reports');
    }
};
