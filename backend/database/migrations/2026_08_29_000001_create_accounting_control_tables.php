<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('accounting_periods')) {
            Schema::create('accounting_periods', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 20)->default('open')->index();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('closed_at')->nullable();
                $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reopened_at')->nullable();
                $table->timestamps();
                $table->index(['period_start', 'period_end']);
            });
        }

        if (!Schema::hasTable('account_reconciliations')) {
            Schema::create('account_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('financial_account_id')->constrained('financial_accounts')->cascadeOnDelete();
                $table->date('reconciliation_date');
                $table->decimal('book_balance', 15, 2);
                $table->decimal('statement_balance', 15, 2);
                $table->decimal('difference', 15, 2);
                $table->string('status', 20)->default('difference')->index();
                $table->text('notes')->nullable();
                $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['financial_account_id','reconciliation_date'], 'acct_recon_account_date_idx');
            });
        }

        if (Schema::hasTable('financial_transactions') && !Schema::hasColumn('financial_transactions','accounting_period_id')) {
            Schema::table('financial_transactions', function (Blueprint $table) {
                $table->foreignId('accounting_period_id')->nullable()->after('account_id')->constrained('accounting_periods')->nullOnDelete();
                $table->index('accounting_period_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('financial_transactions') && Schema::hasColumn('financial_transactions','accounting_period_id')) {
            Schema::table('financial_transactions', function (Blueprint $table) {
                $table->dropForeign(['accounting_period_id']);
                $table->dropColumn('accounting_period_id');
            });
        }
        Schema::dropIfExists('account_reconciliations');
        Schema::dropIfExists('accounting_periods');
    }
};
