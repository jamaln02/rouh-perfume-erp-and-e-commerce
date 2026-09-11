<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('recipe_id');
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('expected_qty', 15, 4);
            $table->string('unit', 20);
            $table->string('consumption_rule_type', 30)->default('fixed'); // fixed|ratio|packaging_rule
            $table->json('rule_config')->nullable();
            $table->boolean('is_optional')->default(false);
            $table->boolean('allow_manual_override')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('recipe_id')->references('id')->on('recipes')->cascadeOnDelete();
            $table->index(['recipe_id', 'sort_order']);
            $table->index('material_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
    }
};
