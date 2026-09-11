<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('materials')) {
            return;
        }

        $rows = [
            [
                'code' => 'PKG-EMPTY-GLASS-250G',
                'name' => 'Empty Glass Bottle 250g',
                'name_ar' => 'فارغة زجاج 250 جرام',
                'material_category' => 'packaging',
                'subcategory' => 'bottle',
                'base_unit' => 'pcs',
                'track_fractional' => false,
                'min_stock' => 0,
            ],
            [
                'code' => 'PKG-EMPTY-ALUMINUM-250G',
                'name' => 'Empty Aluminum Bottle 250g',
                'name_ar' => 'فارغة المنيوم 250 جرام',
                'material_category' => 'packaging',
                'subcategory' => 'bottle',
                'base_unit' => 'pcs',
                'track_fractional' => false,
                'min_stock' => 0,
            ],
        ];

        foreach ($rows as $row) {
            if (DB::table('materials')->where('name_ar', $row['name_ar'])->exists()) {
                continue;
            }

            DB::table('materials')->insert([
                ...$row,
                'current_stock' => 0,
                'avg_unit_cost' => 0,
                'currency' => 'SYP',
                'exchange_rate' => 1,
                'supplier_name' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: these are master-data rows required by
        // the approved opening count. Removing them on rollback could orphan
        // opening-balance history in an existing production database.
    }
};
