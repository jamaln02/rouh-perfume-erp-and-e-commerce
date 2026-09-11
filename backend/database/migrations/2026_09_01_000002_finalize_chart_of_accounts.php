<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_accounts') && Schema::hasColumn('financial_accounts', 'code')) {
            // Normalize duplicate legacy codes before adding the unique constraint.
            $duplicates = DB::table('financial_accounts')
                ->select('code')
                ->whereNotNull('code')
                ->groupBy('code')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('code');
            foreach ($duplicates as $code) {
                $ids = DB::table('financial_accounts')->where('code', $code)->orderBy('id')->pluck('id')->all();
                foreach (array_slice($ids, 1) as $id) {
                    DB::table('financial_accounts')->where('id', $id)->update(['code' => 'LEG' . str_pad((string) $id, 10, '0', STR_PAD_LEFT), 'updated_at' => now()]);
                }
            }
            try {
                Schema::table('financial_accounts', function (Blueprint $table) { $table->unique('code', 'financial_accounts_code_unique'); });
            } catch (Throwable $e) {
                // Existing installations may already have the index; leave them untouched.
            }
        }
        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->index(['accounting_period_id', 'entry_date'], 'journal_period_date_idx');
                $table->index(['status', 'entry_date'], 'journal_status_date_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('journal_entries')) {
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->dropIndex('journal_period_date_idx');
                $table->dropIndex('journal_status_date_idx');
            });
        }
        if (Schema::hasTable('financial_accounts') && Schema::hasColumn('financial_accounts', 'code')) {
            Schema::table('financial_accounts', function (Blueprint $table) {
                try { $table->dropUnique('financial_accounts_code_unique'); } catch (Throwable $e) {}
            });
        }
    }
};
