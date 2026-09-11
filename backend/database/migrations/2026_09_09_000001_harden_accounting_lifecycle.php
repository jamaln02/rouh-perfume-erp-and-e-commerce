<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (!Schema::hasColumn('sales', 'revenue_status')) {
                    $table->string('revenue_status', 24)->default('recognized')->index();
                }
                if (!Schema::hasColumn('sales', 'revenue_recognized_at')) {
                    $table->timestamp('revenue_recognized_at')->nullable()->index();
                }
            });
        }

        if (Schema::hasTable('tax_configurations')) {
            Schema::table('tax_configurations', function (Blueprint $table) {
                if (!Schema::hasColumn('tax_configurations', 'post_to_ledger')) {
                    $table->boolean('post_to_ledger')->default(false)->after('is_active');
                }
                if (!Schema::hasColumn('tax_configurations', 'tax_inclusive')) {
                    $table->boolean('tax_inclusive')->default(true)->after('post_to_ledger');
                }
            });
        }

        if (Schema::hasTable('opening_balance_fixed_assets')) {
            Schema::table('opening_balance_fixed_assets', function (Blueprint $table) {
                if (!Schema::hasColumn('opening_balance_fixed_assets', 'in_service_date')) {
                    $table->date('in_service_date')->nullable()->after('useful_life_months');
                }
                if (!Schema::hasColumn('opening_balance_fixed_assets', 'accumulated_depreciation')) {
                    $table->decimal('accumulated_depreciation', 18, 2)->default(0)->after('salvage_value');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('opening_balance_fixed_assets')) {
            Schema::table('opening_balance_fixed_assets', function (Blueprint $table) {
                if (Schema::hasColumn('opening_balance_fixed_assets', 'in_service_date')) $table->dropColumn('in_service_date');
                if (Schema::hasColumn('opening_balance_fixed_assets', 'accumulated_depreciation')) $table->dropColumn('accumulated_depreciation');
            });
        }
        if (Schema::hasTable('tax_configurations')) {
            Schema::table('tax_configurations', function (Blueprint $table) {
                if (Schema::hasColumn('tax_configurations', 'post_to_ledger')) $table->dropColumn('post_to_ledger');
                if (Schema::hasColumn('tax_configurations', 'tax_inclusive')) $table->dropColumn('tax_inclusive');
            });
        }
        if (Schema::hasTable('sales')) {
            Schema::table('sales', function (Blueprint $table) {
                if (Schema::hasColumn('sales', 'revenue_status')) $table->dropColumn('revenue_status');
                if (Schema::hasColumn('sales', 'revenue_recognized_at')) $table->dropColumn('revenue_recognized_at');
            });
        }
    }
};
