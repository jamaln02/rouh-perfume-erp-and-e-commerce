<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alcohol_inventory', function (Blueprint $table) {
            $table->id();
            $table->decimal('price_per_liter', 8, 2)->default(350); // 350 SYP per liter
            $table->decimal('current_stock_ml', 10, 2)->default(0);
            $table->decimal('min_stock_ml', 10, 2)->default(0);
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alcohol_inventory');
    }
};