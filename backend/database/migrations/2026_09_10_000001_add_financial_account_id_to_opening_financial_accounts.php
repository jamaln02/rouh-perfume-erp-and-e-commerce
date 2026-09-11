<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('opening_balance_financial_accounts', 'financial_account_id')) {
            Schema::table('opening_balance_financial_accounts', function (Blueprint $table): void {
                $table->unsignedBigInteger('financial_account_id')->nullable()->after('opening_balance_id');
                $table->foreign('financial_account_id', 'obfa_fin_account_fk')
                    ->references('id')->on('financial_accounts')->nullOnDelete();
                $table->index(['opening_balance_id', 'financial_account_id'], 'obfa_opening_account_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('opening_balance_financial_accounts', 'financial_account_id')) {
            Schema::table('opening_balance_financial_accounts', function (Blueprint $table): void {
                $table->dropForeign('obfa_fin_account_fk');
                $table->dropIndex('obfa_opening_account_idx');
                $table->dropColumn('financial_account_id');
            });
        }
    }
};
