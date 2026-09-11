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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('name_ar', 200);
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->decimal('price', 12, 2);
            $table->string('image_url', 1000)->nullable();
            $table->uuid('category_id')->nullable();
            $table->string('fragrance', 50)->default('oriental');
            $table->json('sizes')->nullable();
            $table->boolean('featured')->default(false);
            $table->boolean('is_new')->default(false);
            $table->boolean('best_seller')->default(false);
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            $table->index('featured');
            $table->index('best_seller');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
