<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'invoice_path')) {
                $table->string('invoice_path')->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('orders', 'invoice_generated_at')) {
                $table->timestamp('invoice_generated_at')->nullable()->after('invoice_path');
            }
            if (! Schema::hasColumn('orders', 'notification_sent_at')) {
                $table->timestamp('notification_sent_at')->nullable()->after('invoice_generated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['invoice_path', 'invoice_generated_at', 'notification_sent_at']);
        });
    }
};
