<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate raw_materials to materials table
        $rawMaterials = DB::table('raw_materials')->get();

        foreach ($rawMaterials as $material) {
            // Check if already migrated to avoid duplicates
            $existing = DB::table('materials')
                ->where('code', 'RM-' . $material->id)
                ->first();

            if (!$existing) {
                // Preserve original unit from raw_materials
                $unit = $material->unit ?? 'pcs';
                // Use SYP-specific cost if available, otherwise use generic cost
                $cost = $material->cost_per_unit_syp ?? $material->cost_per_unit ?? 0;

                DB::table('materials')->insert([
                    'code' => 'RM-' . $material->id,
                    'name' => $material->name,
                    'name_ar' => $material->name_ar,
                    'material_category' => 'other',
                    'subcategory' => 'general',
                    'base_unit' => $unit,
                    'track_fractional' => true, // Assume fractional for other materials
                    'current_stock' => $material->current_stock,
                    'min_stock' => $material->min_stock,
                    'avg_unit_cost' => $cost,
                    'currency' => 'SYP',
                    'exchange_rate' => $material->exchange_rate ?? 1,
                    'supplier_name' => $material->supplier,
                    'is_active' => $material->is_active ?? true,
                    'notes' => $material->notes ? $material->notes . "\n\nMigrated from raw_materials (ID: {$material->id}). Original unit: {$unit}, Type: {$material->type}" : "Migrated from raw_materials (ID: {$material->id}). Original unit: {$unit}, Type: {$material->type}",
                    'created_by' => null,
                    'created_at' => $material->created_at,
                    'updated_at' => $material->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove migrated raw materials from materials
        DB::table('materials')
            ->where('material_category', 'other')
            ->where('notes', 'like', '%Migrated from raw_materials%')
            ->delete();
    }
};
