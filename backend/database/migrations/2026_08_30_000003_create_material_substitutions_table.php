<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the material_substitutions table.
 *
 * Every time a production worker substitutes one raw material for another
 * during order preparation (e.g. swapping an out-of-stock oil for an
 * equivalent), a row is written here. This provides a full audit trail of
 * every substitution: who did it, which order, which original material was
 * replaced, which replacement was used, and the quantities involved.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_substitutions', function (Blueprint $table) {
            $table->id();
            // order_consumptions.id is BIGINT; match the referenced column type.
            $table->unsignedBigInteger('order_consumption_id')->nullable();
            $table->string('order_id', 64)->nullable()->index();
            $table->foreignId('original_material_id')->constrained('materials')->restrictOnDelete();
            $table->foreignId('replacement_material_id')->constrained('materials')->restrictOnDelete();
            $table->decimal('original_qty', 15, 4)->nullable();
            $table->decimal('replacement_qty', 15, 4)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('reason', 255)->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('order_consumption_id')->references('id')->on('order_consumptions')->cascadeOnDelete();
            $table->index(['original_material_id', 'replacement_material_id'], 'ms_materials_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_substitutions');
    }
};
