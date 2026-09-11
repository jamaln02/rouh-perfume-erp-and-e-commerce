<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packaging_inventory', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['bottle', 'box', 'bag', 'sachet']);
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('size_ml')->nullable(); // For bottles: 3ml, 5ml, 10ml, etc.
            $table->string('dimensions')->nullable(); // For boxes/bags
            $table->decimal('price', 8, 2)->default(0); // Varies: sachets 65, bottles 25, boxes vary
            $table->integer('current_stock')->default(0);
            $table->integer('min_stock')->default(0);
            $table->string('supplier')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging_inventory');
    }
};