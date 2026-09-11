<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $material = DB::table('materials')
                ->where('material_category', 'alcohol')
                ->where('name_ar', 'كحول ايثانول')
                ->orderBy('id')
                ->first();

            if (!$material || strtolower((string) ($material->base_unit ?? '')) !== 'l') {
                return;
            }

            // Safe only for the pre-go-live alcohol record: no live quantity has yet
            // been recognized in current_stock. Historical opening rows/movements are
            // converted as a unit-preserving transformation (1 L = 1000 mL).
            if ((float) $material->current_stock > 0.000001) {
                return;
            }

            DB::table('opening_balance_inventory')
                ->where('material_id', $material->id)
                ->whereRaw('LOWER(unit) = ?', ['l'])
                ->get()
                ->each(function ($row): void {
                    DB::table('opening_balance_inventory')
                        ->where('id', $row->id)
                        ->update([
                            'unit' => 'mL',
                            'quantity' => ((float) $row->quantity) * 1000,
                            'unit_cost' => ((float) $row->unit_cost) / 1000,
                            'updated_at' => now(),
                        ]);
                });

            DB::table('inventory_movements')
                ->where('material_id', $material->id)
                ->whereRaw('LOWER(unit) = ?', ['l'])
                ->get()
                ->each(function ($row): void {
                    DB::table('inventory_movements')
                        ->where('id', $row->id)
                        ->update([
                            'unit' => 'mL',
                            'quantity' => ((float) $row->quantity) * 1000,
                            'quantity_base' => ((float) $row->quantity_base) * 1000,
                            'previous_stock' => ((float) $row->previous_stock) * 1000,
                            'new_stock' => ((float) $row->new_stock) * 1000,
                            'unit_cost' => $row->unit_cost === null ? null : ((float) $row->unit_cost) / 1000,
                            'updated_at' => now(),
                        ]);
                });

            DB::table('materials')
                ->where('id', $material->id)
                ->update([
                    'base_unit' => 'mL',
                    'track_fractional' => 1,
                    'avg_unit_cost' => ((float) $material->avg_unit_cost) / 1000,
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        // Deliberately non-reversible: restoring L could destroy later mL history.
    }
};
