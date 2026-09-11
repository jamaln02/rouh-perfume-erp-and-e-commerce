<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_blends', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->boolean('is_public')->default(false); // Available for customers
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->decimal('base_cost_per_bottle', 10, 2)->default(0); // Material cost
            $table->decimal('packaging_cost_per_bottle', 10, 2)->default(0); // Bottle + box cost
            $table->decimal('total_cost_per_bottle', 10, 2)->default(0); // Base + packaging
            $table->decimal('selling_price', 10, 2)->default(0); // With 200% markup
            $table->decimal('markup_percentage', 5, 2)->default(200); // Default 200%
            $table->string('bottle_size_ml')->default('50ml'); // Default size
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('created_by_admin_id')->references('id')->on('users')->nullOnDelete();
            $table->index('is_public');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_blends');
    }
};