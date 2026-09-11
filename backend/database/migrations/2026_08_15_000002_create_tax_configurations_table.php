<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->enum('tax_type', ['vat', 'income_tax', 'sales_tax', 'other']);
            $table->decimal('rate', 5, 2); // Percentage (e.g., 15.00 for 15%)
            $table->boolean('is_active')->default(true);
            $table->date('effective_date');
            $table->date('expiry_date')->nullable();
            $table->string('description')->nullable();
            $table->enum('calculation_method', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('fixed_amount', 15, 2)->nullable();
            $table->enum('applicable_to', ['all_revenue', 'profit', 'specific_categories'])->default('all_revenue');
            $table->json('applicable_categories')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_configurations');
    }
};
