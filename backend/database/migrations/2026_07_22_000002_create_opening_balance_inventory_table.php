<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opening_balance_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opening_balance_id')->constrained('opening_balances')->onDelete('cascade');
            
            // Material identification
            $table->string('material_name');
            $table->string('material_name_ar')->nullable();
            $table->enum('material_type', ['perfume_oil', 'alcohol', 'bottle', 'cap', 'sprayer', 'box', 'shopping_bag', 'other_packaging', 'other_raw_material']);
            $table->string('unit'); // grams, liters, pieces, etc.
            
            // Quantity and cost
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('currency')->default('SYP');
            $table->decimal('exchange_rate', 10, 2)->default(13500);
            $table->decimal('total_value', 15, 2)->default(0);
            
            // Additional info
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index('opening_balance_id');
            $table->index('material_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balance_inventory');
    }
};