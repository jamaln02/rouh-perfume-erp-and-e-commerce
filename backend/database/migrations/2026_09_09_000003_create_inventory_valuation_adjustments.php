<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('inventory_valuation_adjustments')) return;

        Schema::create('inventory_valuation_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained('materials')->cascadeOnDelete();
            $table->date('adjustment_date');
            $table->decimal('carrying_value_before', 18, 2);
            $table->decimal('nrv_value', 18, 2);
            $table->decimal('adjustment_amount', 18, 2);
            $table->enum('type', ['write_down'])->default('write_down');
            $table->enum('status', ['posted', 'voided'])->default('posted');
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(
                ['material_id', 'adjustment_date'],
                'iva_mat_date_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_valuation_adjustments');
    }
};
