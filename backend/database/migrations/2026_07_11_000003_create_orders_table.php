<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->string('id', 64)->primary();
            $table->string('user_id', 64)->nullable();
            $table->string('customer_name', 200);
            $table->string('customer_phone', 20)->index();
            $table->text('customer_address');
            $table->string('city', 100);
            $table->decimal('total', 12, 2);
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->string('payment_method', 50)->default('cash_on_delivery');
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->string('coupon_code', 32)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
