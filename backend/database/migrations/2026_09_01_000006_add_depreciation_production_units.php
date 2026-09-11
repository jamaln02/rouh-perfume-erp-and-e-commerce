<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (Schema::hasTable('fixed_asset_depreciation_entries') && !Schema::hasColumn('fixed_asset_depreciation_entries', 'production_units')) {
            Schema::table('fixed_asset_depreciation_entries', function (Blueprint $table) {
                $table->decimal('production_units', 18, 4)->nullable()->after('amount');
            });
        }
    }
    public function down(): void {
        if (Schema::hasTable('fixed_asset_depreciation_entries') && Schema::hasColumn('fixed_asset_depreciation_entries', 'production_units')) {
            Schema::table('fixed_asset_depreciation_entries', fn(Blueprint $table) => $table->dropColumn('production_units'));
        }
    }
};
