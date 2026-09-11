<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('opening_balance_adjustments') && !Schema::hasColumn('opening_balance_adjustments', 'adjusted_at')) {
            Schema::table('opening_balance_adjustments', function (Blueprint $table) {
                $table->timestamp('adjusted_at')->nullable()->after('approved_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('opening_balance_adjustments') && Schema::hasColumn('opening_balance_adjustments', 'adjusted_at')) {
            Schema::table('opening_balance_adjustments', function (Blueprint $table) {
                $table->dropColumn('adjusted_at');
            });
        }
    }
};
