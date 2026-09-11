<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Add currency columns if they don't exist
            if (!Schema::hasColumn('sales', 'exchange_rate')) {
                $table->decimal('exchange_rate', 10, 2)->nullable()->after('currency');
            }
            if (!Schema::hasColumn('sales', 'total_price_syp')) {
                $table->decimal('total_price_syp', 15, 2)->nullable()->after('exchange_rate');
            }
            if (!Schema::hasColumn('sales', 'cost_syp')) {
                $table->decimal('cost_syp', 15, 2)->nullable()->after('cost');
            }
            if (!Schema::hasColumn('sales', 'profit_syp')) {
                $table->decimal('profit_syp', 15, 2)->nullable()->after('profit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Remove only the new columns we added
            if (Schema::hasColumn('sales', 'exchange_rate')) {
                $table->dropColumn('exchange_rate');
            }
            if (Schema::hasColumn('sales', 'total_price_syp')) {
                $table->dropColumn('total_price_syp');
            }
            if (Schema::hasColumn('sales', 'cost_syp')) {
                $table->dropColumn('cost_syp');
            }
            if (Schema::hasColumn('sales', 'profit_syp')) {
                $table->dropColumn('profit_syp');
            }
        });
    }
};