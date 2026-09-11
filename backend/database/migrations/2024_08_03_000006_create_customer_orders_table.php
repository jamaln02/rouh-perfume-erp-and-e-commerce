<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->text('customer_address')->nullable();
            $table->string('city')->nullable();
            $table->enum('order_type', ['standard', 'custom'])->default('custom');
            $table->enum('status', ['pending', 'confirmed', 'in_production', 'ready', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->decimal('total_cost', 12, 2)->default(0); // Material + packaging cost
            $table->decimal('total_price', 12, 2)->default(0); // With 200% markup
            $table->decimal('packaging_cost', 12, 2)->default(0); // Box + bag cost
            $table->decimal('profit', 12, 2)->default(0);
            $table->decimal('markup_percentage', 5, 2)->default(200);
            $table->enum('payment_method', ['cash', 'card', 'bank_transfer'])->default('cash');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('order_date')->nullable();
            $table->timestamp('ready_date')->nullable();
            $table->timestamps();
            
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->index('status');
            $table->index('order_date');
            $table->index('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_orders');
    }
};