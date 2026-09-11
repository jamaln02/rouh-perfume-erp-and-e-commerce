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
        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->string('source', 20)->default('quiz');
            $table->unsignedBigInteger('coupon_id')->nullable();
            $table->string('code', 32);
            $table->string('user_id', 64)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('order_id', 64)->nullable();
            $table->unsignedTinyInteger('discount_percent')->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();

            $table->index('code');
            $table->index('order_id');
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_redemptions');
    }
};
