<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ar')->nullable();
            $table->string('asset_number')->unique();
            $table->enum('category', ['machinery', 'equipment', 'vehicle', 'furniture', 'electronics', 'building', 'land', 'other']);
            $table->string('description')->nullable();
            $table->decimal('purchase_cost', 15, 2);
            $table->date('purchase_date');
            $table->string('supplier')->nullable();
            $table->string('invoice_number')->nullable();
            $table->enum('depreciation_method', ['straight_line', 'declining_balance', 'units_of_production'])->default('straight_line');
            $table->integer('useful_life_years')->default(5);
            $table->decimal('salvage_value', 15, 2)->default(0);
            $table->date('depreciation_start_date')->nullable();
            $table->enum('status', ['active', 'disposed', 'sold', 'lost'])->default('active');
            $table->date('disposal_date')->nullable();
            $table->decimal('disposal_value', 15, 2)->nullable();
            $table->string('location')->nullable();
            $table->string('serial_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
