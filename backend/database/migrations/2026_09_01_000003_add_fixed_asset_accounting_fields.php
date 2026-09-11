<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fixed_assets')) {
            Schema::table('fixed_assets', function (Blueprint $table) {
                if (!Schema::hasColumn('fixed_assets', 'payment_method')) $table->string('payment_method', 50)->nullable()->after('invoice_number');
                if (!Schema::hasColumn('fixed_assets', 'paid_amount')) $table->decimal('paid_amount', 18, 2)->default(0)->after('payment_method');
                if (!Schema::hasColumn('fixed_assets', 'total_estimated_units')) $table->decimal('total_estimated_units', 18, 4)->nullable()->after('useful_life_years');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('fixed_assets')) {
            Schema::table('fixed_assets', function (Blueprint $table) {
                if (Schema::hasColumn('fixed_assets', 'paid_amount')) $table->dropColumn('paid_amount');
                if (Schema::hasColumn('fixed_assets', 'payment_method')) $table->dropColumn('payment_method');
                if (Schema::hasColumn('fixed_assets', 'total_estimated_units')) $table->dropColumn('total_estimated_units');
            });
        }
    }
};
