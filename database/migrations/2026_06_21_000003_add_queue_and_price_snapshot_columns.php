<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)->nullable()->after('quantity');
            }

            if (! Schema::hasColumn('order_items', 'line_total')) {
                $table->decimal('line_total', 14, 2)->nullable()->after('unit_price');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'invoice_sent_at')) {
                $table->timestamp('invoice_sent_at')->nullable()->after('invoice_generated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'invoice_sent_at')) {
                $table->dropColumn('invoice_sent_at');
            }
        });

        Schema::table('order_items', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('order_items', 'line_total')) {
                $columns[] = 'line_total';
            }

            if (Schema::hasColumn('order_items', 'unit_price')) {
                $columns[] = 'unit_price';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
