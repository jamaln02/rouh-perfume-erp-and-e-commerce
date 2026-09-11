<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->timestamp('production_date')->nullable();
            $table->enum('status', ['planned', 'in_progress', 'completed', 'cancelled'])->default('planned');
            $table->json('oils_used')->nullable(); // [{"oil_id": 1, "grams_used": 50}, ...]
            $table->decimal('alcohol_used_ml', 10, 2)->default(0);
            $table->json('packaging_used')->nullable(); // [{"type": "bottle", "bottle_id": 1, "quantity": 5}, {"type": "box", "box_id": 1, "quantity": 5}, {"type": "bag", "bag_id": 1, "quantity": 2}]
            $table->integer('bottles_produced')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->foreign('order_id')->references('id')->on('customer_orders')->cascadeOnDelete();
            $table->index('status');
            $table->index('production_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_records');
    }
};