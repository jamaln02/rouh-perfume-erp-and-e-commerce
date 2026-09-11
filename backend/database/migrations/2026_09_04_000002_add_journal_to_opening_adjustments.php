<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('opening_balance_adjustments') && !Schema::hasColumn('opening_balance_adjustments', 'journal_entry_id')) {
            Schema::table('opening_balance_adjustments', function (Blueprint $table): void {
                $table->foreignId('journal_entry_id')->nullable()->after('status')->constrained('journal_entries')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('opening_balance_adjustments', 'journal_entry_id')) {
            Schema::table('opening_balance_adjustments', function (Blueprint $table): void {
                $table->dropForeign(['journal_entry_id']);
                $table->dropColumn('journal_entry_id');
            });
        }
    }
};
