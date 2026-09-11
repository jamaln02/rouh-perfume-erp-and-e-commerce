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
        // Migrate alcohol_inventory to materials table
        $alcoholRecords = DB::table('alcohol_inventory')->get();

        foreach ($alcoholRecords as $alcohol) {
            // Check if already migrated to avoid duplicates
            $existing = DB::table('materials')
                ->where('code', 'ALC-' . $alcohol->id)
                ->first();

            if (!$existing) {
                // Convert price per liter to price per ml
                $costPerMl = $alcohol->price_per_liter / 1000;

                DB::table('materials')->insert([
                    'code' => 'ALC-' . $alcohol->id,
                    'name' => 'Alcohol',
                    'name_ar' => 'الكحول',
                    'material_category' => 'alcohol',
                    'subcategory' => 'general',
                    'base_unit' => 'ml',
                    'track_fractional' => true,
                    'current_stock' => $alcohol->current_stock_ml,
                    'min_stock' => $alcohol->min_stock_ml,
                    'avg_unit_cost' => $costPerMl,
                    'currency' => 'SYP',
                    'exchange_rate' => 1,
                    'supplier_name' => $alcohol->supplier,
                    'is_active' => $alcohol->is_active,
                    'notes' => $alcohol->notes ? $alcohol->notes . "\n\nMigrated from alcohol_inventory (ID: {$alcohol->id}). Original price: {$alcohol->price_per_liter} SYP/liter" : "Migrated from alcohol_inventory (ID: {$alcohol->id}). Original price: {$alcohol->price_per_liter} SYP/liter",
                    'created_by' => null,
                    'created_at' => $alcohol->created_at,
                    'updated_at' => $alcohol->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove migrated alcohol from materials
        DB::table('materials')
            ->where('material_category', 'alcohol')
            ->where('notes', 'like', '%Migrated from alcohol_inventory%')
            ->delete();
    }
};
