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
        Schema::create('finished_products_inventory', function (Blueprint $table) {
            $table->id();
            $table->uuid('product_id');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->string('size_type'); // 100ml, 50ml, 12g, 1g, 3ml, 5ml, 10ml, 15ml, 30ml, crystal
            $table->integer('current_stock')->default(0);
            $table->integer('min_stock')->default(0);
            $table->decimal('cost_per_unit', 10, 2)->default(0);
            $table->decimal('selling_price', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finished_products_inventory');
    }
};
