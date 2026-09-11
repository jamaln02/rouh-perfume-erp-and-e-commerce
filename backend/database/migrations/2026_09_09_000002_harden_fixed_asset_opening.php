<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('fixed_assets')) {
            Schema::table('fixed_assets', function (Blueprint $table) {
                if (!Schema::hasColumn('fixed_assets', 'opening_accumulated_depreciation')) {
                    $table->decimal('opening_accumulated_depreciation', 15, 2)->default(0)->after('salvage_value');
                }
                if (!Schema::hasColumn('fixed_assets', 'in_service_date')) {
                    $table->date('in_service_date')->nullable()->after('purchase_date');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fixed_assets')) {
            Schema::table('fixed_assets', function (Blueprint $table) {
                if (Schema::hasColumn('fixed_assets', 'opening_accumulated_depreciation')) $table->dropColumn('opening_accumulated_depreciation');
                if (Schema::hasColumn('fixed_assets', 'in_service_date')) $table->dropColumn('in_service_date');
            });
        }
    }
};
