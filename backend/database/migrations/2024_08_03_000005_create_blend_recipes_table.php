<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blend_recipes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('blend_id');
            $table->unsignedBigInteger('essential_oil_id');
            $table->decimal('percentage', 5, 2); // Percentage of total volume
            $table->decimal('absolute_grams', 10, 2)->nullable(); // Absolute grams if fixed recipe
            $table->decimal('alcohol_ml', 10, 2)->nullable(); // Alcohol amount for this oil
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('blend_id')->references('id')->on('custom_blends')->cascadeOnDelete();
            $table->foreign('essential_oil_id')->references('id')->on('essential_oils')->cascadeOnDelete();
            $table->index('blend_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blend_recipes');
    }
};