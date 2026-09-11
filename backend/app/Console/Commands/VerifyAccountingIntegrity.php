<?php

namespace App\Console\Commands;

use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\InventoryCostingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyAccountingIntegrity extends Command
{
    protected $signature = 'rouh:verify-accounting {--as-of= : Optional YYYY-MM-DD balance-sheet cut-off} {--check-inventory : Reconcile the unified inventory ledger to material balances and GL inventory}';
    protected $description = 'Verify double-entry balance, source idempotency, and financial account balance integrity.';

    public function handle(): int
    {
        $failed = false;
        $this->info('ROUH accounting integrity check');

        $unbalanced = DB::table('journal_entries as je')
            ->join('journal_lines as jl', 'jl.journal_entry_id', '=', 'je.id')
            ->whereIn('je.status', ['posted', 'reversed'])
            ->select('je.id', 'je.journal_number')
            ->groupBy('je.id', 'je.journal_number')
            ->havingRaw('ABS(SUM(jl.base_debit) - SUM(jl.base_credit)) > 0.005')
            ->count();
        $this->line('• Unbalanced journal entries: ' . $unbalanced);
        if ($unbalanced > 0) $failed = true;

        $missingLines = JournalEntry::whereIn('status', ['posted', 'reversed'])
            ->whereDoesntHave('lines')->count();
        $this->line('• Posted/reversed journals without lines: ' . $missingLines);
        if ($missingLines > 0) $failed = true;

        $duplicateSources = DB::table('journal_entries')
            ->whereIn('status', ['posted', 'reversed'])
            ->whereNotNull('source_type')->whereNotNull('source_id')->whereNotNull('entry_kind')
            ->select('source_type', 'source_id', 'entry_kind')
            ->groupBy('source_type', 'source_id', 'entry_kind')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        $this->line('• Duplicate source postings (source/type/id/kind): ' . $duplicateSources);
        if ($duplicateSources > 0) $failed = true;

        $orphanLines = JournalLine::whereDoesntHave('entry')->count();
        $this->line('• Orphan journal lines: ' . $orphanLines);
        if ($orphanLines > 0) $failed = true;

        $accountBalanceMismatches = 0;
        $accounts = FinancialAccount::where('is_active', true)->get();
        foreach ($accounts as $account) {
            $query = JournalLine::query()->where('financial_account_id', $account->id)->whereHas('entry', function ($q) {
                $q->whereIn('status', ['posted', 'reversed']);
            });
            if ($this->option('as-of')) {
                $query->whereHas('entry', fn($q) => $q->whereDate('entry_date', '<=', $this->option('as-of')));
            }
            $debit = (float)$query->sum('base_debit');
            $credit = (float)$query->sum('base_credit');
            $expected = $account->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;
            if (!$this->option('as-of') && abs($expected - (float)$account->current_balance) > 0.02) {
                $accountBalanceMismatches++;
            }
        }
        $this->line('• Financial account current-balance mismatches: ' . $accountBalanceMismatches);
        if ($accountBalanceMismatches > 0) $failed = true;

        $totals = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->whereIn('je.status', ['posted', 'reversed'])
            ->selectRaw('COALESCE(SUM(jl.base_debit),0) debit, COALESCE(SUM(jl.base_credit),0) credit')
            ->first();
        $difference = round((float)$totals->debit - (float)$totals->credit, 2);
        $this->line('• Global journal difference: ' . number_format($difference, 2));
        if (abs($difference) > 0.005) $failed = true;

        if ($this->option('check-inventory')) {
            $inventoryValue = 0.0;
            $inventoryStockMismatches = 0;
            $inventoryAvgMismatches = 0;

            $costing = app(InventoryCostingService::class);
            foreach (\App\Models\Material::where('is_active', true)->get() as $material) {
                $projection = $costing->project((int) $material->id);
                if (abs($projection['stock'] - (float) $material->current_stock) > 0.02) $inventoryStockMismatches++;
                if (abs($projection['avg_cost'] - (float) $material->avg_unit_cost) > 0.02 && $projection['stock'] > 0.02) $inventoryAvgMismatches++;
                $inventoryValue += (float) $material->current_stock * (float) $material->avg_unit_cost;
            }

            $inventoryGl = (float)(JournalLine::query()->whereHas('account', fn($q) => $q->where('code','1200'))
                ->whereHas('entry', fn($q) => $q->whereIn('status',['posted','reversed']))
                ->selectRaw('COALESCE(SUM(base_debit - base_credit),0) as total')->value('total'));
            $inventoryDifference = round($inventoryValue - $inventoryGl, 2);
            $this->line('• Inventory stock reconstruction mismatches: ' . $inventoryStockMismatches);
            $this->line('• Inventory average-cost mismatches: ' . $inventoryAvgMismatches);
            $this->line('• Inventory value vs GL 1200 difference: ' . number_format($inventoryDifference, 2));
            // Tolerance is proportional (0.05 per 100k of inventory value) to
            // absorb per-row rounding drift between the 2-decimal financial
            // postings and the higher-precision weighted-average unit costs.
            $tolerance = max(0.05, abs($inventoryValue) * 0.0000005);
            if ($inventoryStockMismatches > 0 || $inventoryAvgMismatches > 0 || abs($inventoryDifference) > $tolerance) $failed = true;
        }

        if ($failed) {
            $this->error('ACCOUNTING INTEGRITY CHECK FAILED. Do not release to production.');
            return self::FAILURE;
        }

        $this->info('ACCOUNTING INTEGRITY CHECK PASSED.');
        return self::SUCCESS;
    }
}
