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
        // Migrate packaging_inventory to materials table
        $packagingRecords = DB::table('packaging_inventory')->get();

        foreach ($packagingRecords as $packaging) {
            // Check if already migrated to avoid duplicates
            $existing = DB::table('materials')
                ->where('code', 'PKG-' . $packaging->id)
                ->first();

            if (!$existing) {
                // Map packaging type to subcategory
                $subcategory = $packaging->type; // bottle, box, bag, sachet

                // Build notes with size information
                $notes = $packaging->notes;
                if ($packaging->size_ml) {
                    $notes .= ($notes ? "\n\n" : "") . "Size: {$packaging->size_ml}ml";
                }
                if ($packaging->dimensions) {
                    $notes .= ($notes ? "\n\n" : "") . "Dimensions: {$packaging->dimensions}";
                }
                $notes .= "\n\nMigrated from packaging_inventory (ID: {$packaging->id}). Type: {$packaging->type}";

                DB::table('materials')->insert([
                    'code' => 'PKG-' . $packaging->id,
                    'name' => $packaging->name,
                    'name_ar' => $packaging->name_ar,
                    'material_category' => 'packaging',
                    'subcategory' => $subcategory,
                    'base_unit' => 'pcs',
                    'track_fractional' => false,
                    'current_stock' => $packaging->current_stock,
                    'min_stock' => $packaging->min_stock,
                    'avg_unit_cost' => $packaging->price,
                    'currency' => 'SYP',
                    'exchange_rate' => 1,
                    'supplier_name' => $packaging->supplier,
                    'is_active' => $packaging->is_active,
                    'notes' => $notes,
                    'created_by' => null,
                    'created_at' => $packaging->created_at,
                    'updated_at' => $packaging->updated_at,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove migrated packaging from materials
        DB::table('materials')
            ->where('material_category', 'packaging')
            ->where('notes', 'like', '%Migrated from packaging_inventory%')
            ->delete();
    }
};
