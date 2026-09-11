<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('opening_balance_inventory', 'material_id')) {
            Schema::table('opening_balance_inventory', function (Blueprint $table) {
                $table->foreignId('material_id')->nullable()->after('opening_balance_id')->constrained('materials')->nullOnDelete();
                $table->index('material_id');
            });
        }

        // Best-effort backfill for legacy draft/opening rows; exact material_id is used for all new rows.
        if (Schema::hasTable('materials')) {
            DB::table('opening_balance_inventory as obi')
                ->whereNull('obi.material_id')
                ->orderBy('obi.id')
                ->get(['obi.id', 'obi.material_name', 'obi.material_name_ar'])
                ->each(function ($row) {
                    $material = DB::table('materials')
                        ->where('is_active', true)
                        ->where(function ($q) use ($row) {
                            $q->where('name', $row->material_name);
                            if (!empty($row->material_name_ar)) {
                                $q->orWhere('name_ar', $row->material_name_ar);
                            }
                        })
                        ->first(['id']);
                    if ($material) {
                        DB::table('opening_balance_inventory')->where('id', $row->id)->update(['material_id' => $material->id]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('opening_balance_inventory', 'material_id')) {
            Schema::table('opening_balance_inventory', function (Blueprint $table) {
                $table->dropForeign(['material_id']);
                $table->dropIndex(['material_id']);
                $table->dropColumn('material_id');
            });
        }
    }
};
