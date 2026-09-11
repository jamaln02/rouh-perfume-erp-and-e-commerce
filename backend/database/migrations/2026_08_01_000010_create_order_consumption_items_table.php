<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_consumption_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_consumption_id')->constrained('order_consumptions')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('expected_qty', 15, 4)->default(0);
            $table->decimal('actual_qty', 15, 4)->default(0);
            $table->decimal('variance_qty', 15, 4)->default(0);
            $table->string('unit', 20);
            $table->string('variance_classification', 40)->nullable(); // normal_waste|abnormal_waste|spillage|production_loss|measurement_difference|other
            $table->boolean('is_packaging')->default(false);
            $table->boolean('is_optional')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_consumption_id', 'material_id']);
            $table->index('variance_classification');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_consumption_items');
    }
};
