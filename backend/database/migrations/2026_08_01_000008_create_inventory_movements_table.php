<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->string('movement_type', 40); // PURCHASE_IN|ORDER_CONSUMPTION|ADJUSTMENT_IN|ADJUSTMENT_OUT|OPENING_BALANCE|RETURN_IN|WASTE_OUT
            $table->decimal('quantity', 15, 4);
            $table->string('unit', 20);
            $table->decimal('quantity_base', 15, 4);
            $table->decimal('previous_stock', 15, 4);
            $table->decimal('new_stock', 15, 4);
            $table->decimal('unit_cost', 15, 4)->nullable();
            $table->decimal('total_cost', 15, 4)->nullable();
            $table->string('reference_type', 80)->nullable();
            $table->string('reference_id', 80)->nullable();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('movement_date')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['material_id', 'movement_date']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('movement_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
