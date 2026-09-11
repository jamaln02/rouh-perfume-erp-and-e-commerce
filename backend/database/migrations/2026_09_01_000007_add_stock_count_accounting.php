<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_counts') && !Schema::hasColumn('stock_counts', 'journal_entry_id')) {
            Schema::table('stock_counts', function (Blueprint $table) {
                $table->foreignId('journal_entry_id')->nullable()->after('status')->constrained('journal_entries')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_counts') && Schema::hasColumn('stock_counts', 'journal_entry_id')) {
            Schema::table('stock_counts', function (Blueprint $table) {
                $table->dropForeign(['journal_entry_id']);
                $table->dropColumn('journal_entry_id');
            });
        }
    }
};
