<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Collapse duplicate ethanol/alcohol material rows into one canonical
     * inventory material and normalize all dependent quantities to millilitres.
     *
     * This migration is intentionally data-preserving:
     * - recipe quantities are converted before duplicate recipe rows are merged;
     * - opening-balance rows and inventory movements are re-pointed and converted;
     * - duplicate material records are deactivated, not hard-deleted, so historical
     *   references remain valid.
     */
    public function up(): void
    {
        if (!Schema::hasTable('materials')) {
            return;
        }

        DB::transaction(function (): void {
            $alcohols = DB::table('materials')
                ->where('material_category', 'alcohol')
                ->orderByRaw("CASE WHEN code = 'ALC-ETHANOL-1L' THEN 0 WHEN name_ar = 'كحول ايثانول' THEN 1 ELSE 2 END")
                ->orderBy('id')
                ->get();

            if ($alcohols->isEmpty()) {
                return;
            }

            $canonical = $alcohols->first();
            $canonicalFactor = $this->mlFactor((string) ($canonical->base_unit ?? 'mL'));

            // Ensure the canonical record is always the single active ethanol record
            // and uses mL as its storage unit. Convert its stored stock/cost first so
            // changing the unit does not silently change valuation.
            DB::table('materials')->where('id', $canonical->id)->update([
                'code' => 'ALC-ETHANOL-1L',
                'name' => 'Ethanol Alcohol 1L',
                'name_ar' => 'كحول ايثانول',
                'material_category' => 'alcohol',
                'subcategory' => 'ethanol',
                'base_unit' => 'mL',
                'track_fractional' => 1,
                'current_stock' => round((float) $canonical->current_stock * $canonicalFactor, 4),
                'min_stock' => round((float) $canonical->min_stock * $canonicalFactor, 4),
                'avg_unit_cost' => $canonicalFactor > 0 ? round((float) $canonical->avg_unit_cost / $canonicalFactor, 4) : (float) $canonical->avg_unit_cost,
                'is_active' => 1,
                'updated_at' => now(),
            ]);

            $duplicateIds = $alcohols->pluck('id')->filter(
                fn ($id) => (int) $id !== (int) $canonical->id
            )->map(fn ($id) => (int) $id)->values()->all();

            foreach ($duplicateIds as $duplicateId) {
                $this->mergeRecipeItems((int) $duplicateId, (int) $canonical->id);
                $this->repointSimpleReferences('product_material_mappings', 'material_id', $duplicateId, $canonical->id, ['product_id', 'material_id']);
                $this->repointSimpleReferences('opening_balance_inventory', 'material_id', $duplicateId, $canonical->id);
                $this->repointInventoryMovements($duplicateId, (int) $canonical->id);
                $this->repointSimpleReferences('order_consumption_items', 'material_id', $duplicateId, $canonical->id);
                $this->repointSimpleReferences('stock_requests', 'material_id', $duplicateId, $canonical->id);
                $this->repointSimpleReferences('stock_count_items', 'material_id', $duplicateId, $canonical->id);
                $this->repointSubstitutions($duplicateId, (int) $canonical->id);

                DB::table('materials')->where('id', $duplicateId)->update([
                    'is_active' => 0,
                    'updated_at' => now(),
                ]);
            }

            // Normalize all remaining ethanol-dependent quantities and units, including
            // historical rows that already referenced the canonical material.
            $this->normalizeCanonicalRows((int) $canonical->id);

            // Re-seed current stock from the merged records. Duplicate rows are logical
            // duplicates of the same material; their quantities are therefore combined.
            $stock = DB::table('materials')
                ->where('id', $canonical->id)
                ->value('current_stock');

            $duplicateStock = 0.0;
            foreach ($duplicateIds as $duplicateId) {
                $row = $alcohols->firstWhere('id', $duplicateId);
                if ($row) {
                    $duplicateStock += $this->toMl((float) ($row->current_stock ?? 0), (string) ($row->base_unit ?? 'mL'));
                }
            }

            if (abs($duplicateStock) > 0.0000001) {
                DB::table('materials')->where('id', $canonical->id)->update([
                    'current_stock' => round((float) $stock + $duplicateStock, 4),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Deliberately non-reversible: historical recipe/movement references have been
        // normalized to the canonical ethanol material.
    }

    private function mergeRecipeItems(int $duplicateId, int $canonicalId): void
    {
        if (!Schema::hasTable('recipe_items')) {
            return;
        }

        $rows = DB::table('recipe_items')
            ->where('material_id', $duplicateId)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $qtyMl = $this->toMl((float) $row->expected_qty, (string) $row->unit);
            $existing = DB::table('recipe_items')
                ->where('recipe_id', $row->recipe_id)
                ->where('material_id', $canonicalId)
                ->first();

            if ($existing) {
                $existingQtyMl = $this->toMl((float) $existing->expected_qty, (string) $existing->unit);
                DB::table('recipe_items')->where('id', $existing->id)->update([
                    'expected_qty' => round($existingQtyMl + $qtyMl, 4),
                    'unit' => 'mL',
                    'updated_at' => now(),
                ]);
                DB::table('recipe_items')->where('id', $row->id)->delete();
            } else {
                DB::table('recipe_items')->where('id', $row->id)->update([
                    'material_id' => $canonicalId,
                    'expected_qty' => round($qtyMl, 4),
                    'unit' => 'mL',
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function repointInventoryMovements(int $duplicateId, int $canonicalId): void
    {
        if (!Schema::hasTable('inventory_movements')) {
            return;
        }

        DB::table('inventory_movements')
            ->where('material_id', $duplicateId)
            ->get()
            ->each(function ($row) use ($canonicalId): void {
                $unit = (string) ($row->unit ?? 'mL');
                $factor = $this->mlFactor($unit);

                DB::table('inventory_movements')->where('id', $row->id)->update([
                    'material_id' => $canonicalId,
                    'quantity' => round((float) $row->quantity * $factor, 4),
                    'unit' => 'mL',
                    'quantity_base' => round((float) $row->quantity_base * $factor, 4),
                    'previous_stock' => round((float) $row->previous_stock * $factor, 4),
                    'new_stock' => round((float) $row->new_stock * $factor, 4),
                    'unit_cost' => $row->unit_cost === null ? null : round((float) $row->unit_cost / $factor, 4),
                    'total_cost' => $row->total_cost === null ? null : round((float) $row->total_cost, 4),
                    'updated_at' => now(),
                ]);
            });
    }

    private function normalizeCanonicalRows(int $canonicalId): void
    {
        if (Schema::hasTable('recipe_items')) {
            DB::table('recipe_items')
                ->where('material_id', $canonicalId)
                ->get()
                ->each(function ($row): void {
                    $unit = (string) $row->unit;
                    if (strtolower($unit) !== 'ml') {
                        DB::table('recipe_items')->where('id', $row->id)->update([
                            'expected_qty' => round($this->toMl((float) $row->expected_qty, $unit), 4),
                            'unit' => 'mL',
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('recipe_items')->where('id', $row->id)->update([
                            'unit' => 'mL',
                            'updated_at' => now(),
                        ]);
                    }
                });
        }

        if (Schema::hasTable('opening_balance_inventory')) {
            DB::table('opening_balance_inventory')
                ->where('material_id', $canonicalId)
                ->get()
                ->each(function ($row): void {
                    $unit = (string) ($row->unit ?? 'mL');
                    if (strtolower($unit) !== 'ml') {
                        DB::table('opening_balance_inventory')->where('id', $row->id)->update([
                            'quantity' => round($this->toMl((float) $row->quantity, $unit), 4),
                            'unit' => 'mL',
                            'unit_cost' => $row->unit_cost === null ? null : round((float) $row->unit_cost / $this->mlFactor($unit), 4),
                            'updated_at' => now(),
                        ]);
                    } else {
                        DB::table('opening_balance_inventory')->where('id', $row->id)->update([
                            'unit' => 'mL',
                            'updated_at' => now(),
                        ]);
                    }
                });
        }

        if (Schema::hasTable('inventory_movements')) {
            DB::table('inventory_movements')
                ->where('material_id', $canonicalId)
                ->whereRaw("LOWER(unit) <> 'ml'")
                ->get()
                ->each(function ($row): void {
                    $factor = $this->mlFactor((string) $row->unit);
                    DB::table('inventory_movements')->where('id', $row->id)->update([
                        'quantity' => round((float) $row->quantity * $factor, 4),
                        'unit' => 'mL',
                        'quantity_base' => round((float) $row->quantity_base * $factor, 4),
                        'previous_stock' => round((float) $row->previous_stock * $factor, 4),
                        'new_stock' => round((float) $row->new_stock * $factor, 4),
                        'unit_cost' => $row->unit_cost === null ? null : round((float) $row->unit_cost / $factor, 4),
                        'updated_at' => now(),
                    ]);
                });
        }

        // order_consumption_items has a direct material_id reference.
        if (Schema::hasTable('order_consumption_items') && Schema::hasColumn('order_consumption_items', 'material_id')) {
            DB::table('order_consumption_items')
                ->where('material_id', $canonicalId)
                ->when(Schema::hasColumn('order_consumption_items', 'unit'), function ($q) {
                    $q->whereRaw("LOWER(unit) <> 'ml'");
                })
                ->get()
                ->each(function ($row): void {
                    $unit = (string) ($row->unit ?? 'mL');
                    $factor = $this->mlFactor($unit);
                    $updates = ['unit' => 'mL', 'updated_at' => now()];
                    foreach (['expected_qty', 'actual_qty', 'variance_qty', 'original_qty', 'replacement_qty'] as $field) {
                        if (Schema::hasColumn('order_consumption_items', $field) && isset($row->{$field})) {
                            $updates[$field] = round((float) $row->{$field} * $factor, 4);
                        }
                    }
                    DB::table('order_consumption_items')->where('id', $row->id)->update($updates);
                });
        }

        // material_substitutions does NOT have material_id. It tracks the source and
        // replacement materials separately, so normalize quantities only when either
        // side is the canonical ethanol material.
        if (Schema::hasTable('material_substitutions')) {
            DB::table('material_substitutions')
                ->where(function ($q) use ($canonicalId): void {
                    $q->where('original_material_id', $canonicalId)
                        ->orWhere('replacement_material_id', $canonicalId);
                })
                ->when(Schema::hasColumn('material_substitutions', 'unit'), function ($q) {
                    $q->whereRaw("LOWER(unit) <> 'ml'");
                })
                ->get()
                ->each(function ($row): void {
                    $unit = (string) ($row->unit ?? 'mL');
                    $factor = $this->mlFactor($unit);
                    $updates = ['unit' => 'mL', 'updated_at' => now()];

                    // Convert the quantity that belongs to ethanol. If both sides are
                    // ethanol, both quantities use the same unit conversion.
                    if ((int) $row->original_material_id === $canonicalId && isset($row->original_qty)) {
                        $updates['original_qty'] = round((float) $row->original_qty * $factor, 4);
                    }
                    if ((int) $row->replacement_material_id === $canonicalId && isset($row->replacement_qty)) {
                        $updates['replacement_qty'] = round((float) $row->replacement_qty * $factor, 4);
                    }

                    DB::table('material_substitutions')->where('id', $row->id)->update($updates);
                });
        }
    }

    private function repointSimpleReferences(string $table, string $column, int $fromId, int $toId, array $uniqueColumns = []): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)
            ->where($column, $fromId)
            ->get()
            ->each(function ($row) use ($table, $column, $fromId, $toId, $uniqueColumns): void {
                if ($uniqueColumns === [] || !$this->conflictsWithCanonical($table, $row, $column, $toId, $uniqueColumns)) {
                    DB::table($table)->where('id', $row->id)->update([
                        $column => $toId,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table($table)->where('id', $row->id)->delete();
                }
            });
    }

    private function conflictsWithCanonical(string $table, object $row, string $column, int $toId, array $uniqueColumns): bool
    {
        if ($table !== 'product_material_mappings') {
            return false;
        }

        return DB::table($table)
            ->where('product_id', $row->product_id)
            ->where('material_id', $toId)
            ->exists();
    }

    private function repointSubstitutions(int $duplicateId, int $canonicalId): void
    {
        if (!Schema::hasTable('material_substitutions')) {
            return;
        }

        DB::table('material_substitutions')->where('original_material_id', $duplicateId)->update([
            'original_material_id' => $canonicalId,
            'updated_at' => now(),
        ]);

        DB::table('material_substitutions')->where('replacement_material_id', $duplicateId)->update([
            'replacement_material_id' => $canonicalId,
            'updated_at' => now(),
        ]);
    }

    private function mlFactor(string $unit): float
    {
        return strtolower(trim($unit)) === 'l' ? 1000.0 : 1.0;
    }

    private function toMl(float $qty, string $unit): float
    {
        return $qty * $this->mlFactor($unit);
    }
};
