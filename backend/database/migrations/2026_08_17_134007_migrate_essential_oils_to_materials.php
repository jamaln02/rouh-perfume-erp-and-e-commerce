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
        // Migrate essential_oils to materials table
        $essentialOils = DB::table('essential_oils')->get();

        foreach ($essentialOils as $oil) {
            // Check if already migrated to avoid duplicates
            $existing = DB::table('materials')
                ->where('code', 'EO-' . $oil->id)
                ->first();

            if (!$existing) {
                DB::table('materials')->insert([
                    'code' => 'EO-' . $oil->id,
                    'name' => $oil->name,
                    'name_ar' => $oil->name_ar,
                    'material_category' => 'perfume_oil',
                    'subcategory' => 'general',
                    'base_unit' => 'g',
                    'track_fractional' => true,
                    'current_stock' => $oil->current_stock_grams,
                    'min_stock' => $oil->min_stock_grams,
                    'avg_unit_cost' => $oil->price_per_gram,
                    'currency' => 'SYP',
                    'exchange_rate' => 1,
                    'supplier_name' => $oil->supplier,
                    'is_active' => $oil->is_active,
                    'notes' => $oil->notes ? $oil->notes . "\n\nMigrated from essential_oils (ID: {$oil->id}). Container type: {$oil->container_type}" : "Migrated from essential_oils (ID: {$oil->id}). Container type: {$oil->container_type}",
                    'created_by' => null,
                    'created_at' => $oil->created_at,
                    'updated_at' => $oil->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove migrated essential oils from materials
        DB::table('materials')
            ->where('material_category', 'perfume_oil')
            ->where('notes', 'like', '%Migrated from essential_oils%')
            ->delete();
    }
};
