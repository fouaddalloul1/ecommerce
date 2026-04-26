<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();


            $table->decimal('total', 12, 2);


            $table->string('status')->default('pending');
            // pending, paid, shipped, completed, cancelled

            $table->string('payment_status')->default('unpaid');
            // unpaid, paid, refunded


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
