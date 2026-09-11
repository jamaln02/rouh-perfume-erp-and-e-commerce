<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('financial_accounts', 'code')) {
            Schema::table('financial_accounts', function (Blueprint $table) {
                $table->string('code', 20)->nullable()->after('id');
                $table->string('account_group', 30)->nullable()->after('account_type');
                $table->string('normal_balance', 10)->nullable()->after('account_group');
                $table->boolean('is_control_account')->default(false)->after('is_active');
                $table->boolean('is_system')->default(false)->after('is_control_account');
            });
        }

        // Preserve existing legacy account rows by assigning the new stable codes before the unique index is added.
        $legacyCodes = [
            'Cash' => ['1000', 'asset', 'debit'],
            'Bank Transfer' => ['1010', 'asset', 'debit'],
            'Sham Cash' => ['1020', 'asset', 'debit'],
            'Accounts Receivable' => ['1100', 'asset', 'debit'],
            'Accounts Payable' => ['2000', 'liability', 'credit'],
        ];
        foreach ($legacyCodes as $name => [$code, $group, $normal]) {
            $rows = DB::table('financial_accounts')->where('name', $name)->whereNull('code')->orderBy('id')->get();
            $first = true;
            foreach ($rows as $row) {
                // Only the first legacy row gets the canonical system code.
                // Duplicate legacy accounts receive deterministic legacy codes so
                // the new unique index cannot fail on existing installations.
                $assignedCode = $first ? $code : ('LEG' . str_pad((string) $row->id, 10, '0', STR_PAD_LEFT));
                DB::table('financial_accounts')->where('id', $row->id)->update([
                    'code' => $assignedCode,
                    'account_group' => $group,
                    'normal_balance' => $normal,
                    'updated_at' => now(),
                ]);
                $first = false;
            }
        }

        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->string('journal_number', 40)->unique();
                $table->date('entry_date')->index();
                $table->string('description', 500);
                $table->string('source_type', 80)->nullable();
                $table->string('source_id', 80)->nullable();
                $table->string('entry_kind', 50)->default('general');
                $table->string('status', 20)->default('posted')->index();
                $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['source_type', 'source_id']);
                $table->index(['entry_kind', 'status']);
                $table->unique(['source_type', 'source_id', 'entry_kind'], 'journal_source_kind_unique');
            });
        }

        if (!Schema::hasTable('journal_lines')) {
            Schema::create('journal_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
                $table->foreignId('financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
                $table->string('description', 500)->nullable();
                $table->decimal('debit', 18, 2)->default(0);
                $table->decimal('credit', 18, 2)->default(0);
                $table->string('currency', 10)->default('SYP');
                $table->decimal('exchange_rate', 18, 6)->default(1);
                $table->decimal('base_debit', 18, 2)->default(0);
                $table->decimal('base_credit', 18, 2)->default(0);
                $table->string('counterparty_type', 40)->nullable();
                $table->string('counterparty_id', 80)->nullable();
                $table->timestamps();
                $table->index(['financial_account_id', 'created_at']);
                $table->index(['counterparty_type', 'counterparty_id']);
            });
        }

        if (!Schema::hasTable('financial_payments')) {
            Schema::create('financial_payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_number', 40)->unique();
                $table->string('direction', 20); // receipt|payment
                $table->decimal('amount', 18, 2);
                $table->string('currency', 10)->default('SYP');
                $table->decimal('exchange_rate', 18, 6)->default(1);
                $table->decimal('amount_base', 18, 2);
                $table->foreignId('financial_account_id')->constrained('financial_accounts')->restrictOnDelete();
                $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
                $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
                $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
                $table->string('order_id', 80)->nullable();
                $table->string('payment_method', 50)->nullable();
                $table->string('reference', 120)->nullable();
                $table->text('notes')->nullable();
                $table->date('payment_date');
                $table->string('status', 20)->default('posted');
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['direction', 'payment_date']);
                $table->index(['sale_id', 'purchase_id', 'expense_id']);
            });
        }

        if (!Schema::hasColumn('fixed_asset_depreciation_entries', 'journal_entry_id')) {
            Schema::table('fixed_asset_depreciation_entries', function (Blueprint $table) {
                $table->foreignId('journal_entry_id')->nullable()->after('status')->constrained('journal_entries')->restrictOnDelete();
            });
        }

        if (!Schema::hasColumn('opening_balances', 'journal_entry_id')) {
            Schema::table('opening_balances', function (Blueprint $table) {
                $table->foreignId('journal_entry_id')->nullable()->after('status')->constrained('journal_entries')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('opening_balances', 'journal_entry_id')) {
            Schema::table('opening_balances', fn (Blueprint $table) => $table->dropForeign(['journal_entry_id'])->dropColumn('journal_entry_id'));
        }
        if (Schema::hasColumn('fixed_asset_depreciation_entries', 'journal_entry_id')) {
            Schema::table('fixed_asset_depreciation_entries', fn (Blueprint $table) => $table->dropForeign(['journal_entry_id'])->dropColumn('journal_entry_id'));
        }
        Schema::dropIfExists('financial_payments');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        if (Schema::hasColumn('financial_accounts', 'code')) {
            Schema::table('financial_accounts', function (Blueprint $table) {
                $table->dropColumn(['code', 'account_group', 'normal_balance', 'is_control_account', 'is_system']);
            });
        }
    }
};
