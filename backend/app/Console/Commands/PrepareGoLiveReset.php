<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrepareGoLiveReset extends Command
{
    protected $signature = 'rouh:prepare-go-live-reset
        {--force : Confirm that all operational/test data should be removed}
        {--customers : Also remove customer master records and reset customer-facing test data}';

    protected $description = 'Clear operational/test transactions before go-live while preserving ERP master data and configuration.';

    private array $transactionTables = [
        // Child tables / ledgers first.
        'journal_lines',
        'financial_payments',
        'audit_logs',
        'api_tokens',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'stock_count_items',
        'stock_counts',
        'fixed_asset_depreciation_entries',
        'opening_balance_adjustments',
        'order_items',
        'order_cost_snapshots',
        'order_consumption_items',
        'order_consumptions',
        'consumption_adjustment_requests',
        'inventory_valuation_adjustments',
        'inventory_movements',
        'finished_goods_sale_movements',
        'opening_balance_financial_accounts',
        'opening_balance_inventory',
        'opening_balance_payables_receivables',
        'opening_balance_fixed_assets',
        'financial_transactions',
        'coupon_redemptions',
        'quiz_discounts',
        'loyalty_transactions',
        'account_reconciliations',

        // Document/master-linked transactions.
        'finished_products_inventory',
        'fixed_assets',
        'sales',
        'purchases',
        'expenses',
        'production_records',
        'customer_orders',
        'orders',
        'stock_requests',
        'opening_balances',
        'journal_entries',
        'accounting_periods',
    ];
    public function handle(): int
    {
        if (!$this->option('force')) {
            $this->error('Nothing was changed. Re-run with --force after taking a full database backup.');
            return self::INVALID;
        }

        $includeCustomers = (bool) $this->option('customers');
        // Reviews/favorites are customer-facing test activity, not customer master data.
        // Always clear them; --customers controls only the customer master rows.
        $customerTables = $includeCustomers ? ['favorites', 'reviews', 'customers'] : ['favorites', 'reviews'];

        $this->disableForeignKeys();
        try {
            DB::transaction(function () use ($customerTables): void {
                foreach (array_merge($this->transactionTables, $customerTables) as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->delete();
                    }
                }

                if (Schema::hasTable('financial_accounts')) {
                    DB::table('financial_accounts')->update([
                        'opening_balance' => 0,
                        'current_balance' => 0,
                        'updated_at' => now(),
                    ]);
                }

                if (Schema::hasTable('materials')) {
                    DB::table('materials')->update([
                        'current_stock' => 0,
                        'avg_unit_cost' => 0,
                        'updated_at' => now(),
                    ]);
                }

                if (Schema::hasTable('customers')) {
                    DB::table('customers')->update([
                        'total_orders' => 0,
                        'total_spent' => 0,
                        'loyalty_points' => 0,
                        'last_purchase_date' => null,
                        'updated_at' => now(),
                    ]);
                }
            });
        } finally {
            $this->enableForeignKeys();
        }

        $this->info('Go-live reset completed. Master product/material/configuration records were preserved.');
        $this->line('Next: enter the final physical inventory, expenses, opening balances, then verify the trial balance before publishing.');
        return self::SUCCESS;
    }

    private function disableForeignKeys(): void
    {
        match (DB::getDriverName()) {
            'mysql' => DB::statement('SET FOREIGN_KEY_CHECKS=0'),
            'sqlite' => DB::statement('PRAGMA foreign_keys = OFF'),
            default => null,
        };
    }

    private function enableForeignKeys(): void
    {
        match (DB::getDriverName()) {
            'mysql' => DB::statement('SET FOREIGN_KEY_CHECKS=1'),
            'sqlite' => DB::statement('PRAGMA foreign_keys = ON'),
            default => null,
        };
    }
}
