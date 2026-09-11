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
        Schema::create('bundles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('name_ar', 200);
            $table->text('description')->nullable();
            $table->text('description_ar')->nullable();
            $table->string('image_url', 1000)->nullable();
            $table->decimal('original_price', 12, 2);
            $table->decimal('bundle_price', 12, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->integer('stock')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->index('active');
            $table->index('starts_at');
            $table->index('ends_at');
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bundle_id');
            $table->uuid('product_id')->nullable();
            $table->string('product_name', 255);
            $table->string('size', 50)->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 12, 2);
            $table->timestamps();

            $table->foreign('bundle_id')->references('id')->on('bundles')->onDelete('cascade');
            $table->index('bundle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
        Schema::dropIfExists('bundles');
    }
};
