<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            // products.id is a UUID; this column was historically added by later
            // migrations but must exist from creation on MySQL.
            $table->uuid('product_id')->nullable()->index();
            $table->unsignedBigInteger('blend_id')->nullable(); // If using predefined blend
            $table->string('bottle_type'); // e.g., "custom", "predefined"
            $table->string('bottle_size_ml'); // 3ml, 5ml, 10ml, etc.
            $table->json('oil_mix')->nullable(); // Custom mix: [{"oil_id": 1, "grams": 5, "percentage": 50}, ...]
            $table->integer('quantity')->default(1);
            $table->decimal('unit_cost', 10, 2)->default(0); // Cost per bottle
            $table->decimal('unit_price', 10, 2)->default(0); // Selling price per bottle
            $table->decimal('line_total', 12, 2)->default(0); // unit_price * quantity
            $table->decimal('line_cost', 12, 2)->default(0); // unit_cost * quantity
            $table->json('packaging_items')->nullable(); // [{"type": "bottle", "bottle_id": 1, "quantity": 1}, {"type": "box", "box_id": 1, "quantity": 1}, {"type": "bag", "bag_id": 1, "quantity": 1}]
            $table->decimal('packaging_cost', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('customer_orders')->cascadeOnDelete();
            $table->foreign('blend_id')->references('id')->on('custom_blends')->nullOnDelete();
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};