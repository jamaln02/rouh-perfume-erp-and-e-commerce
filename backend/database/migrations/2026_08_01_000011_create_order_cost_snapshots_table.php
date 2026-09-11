<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_cost_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 64);
            $table->foreignId('order_consumption_id')->nullable()->constrained('order_consumptions')->nullOnDelete();
            $table->decimal('revenue_subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_revenue', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('payment_fees', 15, 2)->default(0);
            $table->decimal('other_costs', 15, 2)->default(0);
            $table->decimal('material_cogs', 15, 2)->default(0);
            $table->decimal('packaging_cogs', 15, 2)->default(0);
            $table->decimal('total_cogs', 15, 2)->default(0);
            $table->decimal('gross_profit', 15, 2)->default(0);
            $table->decimal('net_profit', 15, 2)->default(0);
            $table->string('currency', 10)->default('SYP');
            $table->decimal('exchange_rate', 15, 4)->default(1);
            $table->boolean('is_final')->default(false);
            $table->timestamp('computed_at')->useCurrent();
            $table->foreignId('computed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->index(['order_id', 'computed_at']);
            $table->index('is_final');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_cost_snapshots');
    }
};
