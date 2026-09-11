<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->nullable()->unique();
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->string('material_category', 20); // raw | packaging
            $table->string('subcategory', 80)->nullable();
            $table->string('base_unit', 20); // g, ml, L, kg, pcs
            $table->boolean('track_fractional')->default(true);
            $table->decimal('current_stock', 15, 4)->default(0);
            $table->decimal('min_stock', 15, 4)->default(0);
            $table->decimal('avg_unit_cost', 15, 4)->default(0);
            $table->string('currency', 10)->default('SYP');
            $table->decimal('exchange_rate', 15, 4)->default(1);
            $table->string('supplier_name', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['material_category', 'is_active']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
