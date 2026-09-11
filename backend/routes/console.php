<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('rouh:create-admin {--email=} {--name=ROUH Owner} {--password=}', function () {
    if (!is_string($this->option('email')) || trim($this->option('email')) === '') { $this->error('The --email option is required.'); return 1; }
    if (!is_string($this->option('password')) || strlen($this->option('password')) < 12) { $this->error('The --password option is required and must be at least 12 characters.'); return 1; }
    $user = User::query()->updateOrCreate(
        ['email' => (string) $this->option('email')],
        [
            'name' => (string) $this->option('name'),
            'password' => Hash::make((string) $this->option('password')),
        ]
    );

    DB::table('profiles')->updateOrInsert(
        ['id' => (string) $user->id],
        [
            'full_name' => (string) $this->option('name'),
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    DB::table('user_roles')->updateOrInsert(
        ['user_id' => (string) $user->id],
        [
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $this->info('Admin user ready: '.$user->email.' (ID '.$user->id.')');
})->purpose('Create or update a Rouh admin user');

Artisan::command('rouh:accounting-audit', function () {
    $errors = 0;
    $warnings = 0;

    $this->info('ROUH accounting readiness audit (read-only)');
    $this->line(str_repeat('─', 70));

    $requiredCodes = ['1000','1010','1020','1100','1200','1210','1220','1500','1590','2000','2100','2200','3000','3100','4000','4010','4020','5000','5100','5250','5290'];
    $missing = collect($requiredCodes)->reject(fn ($code) => \App\Models\FinancialAccount::where('code', $code)->where('is_active', true)->exists())->values();
    if ($missing->isNotEmpty()) {
        $errors++;
        $this->error('Missing active core accounts: '.$missing->implode(', '));
    } else {
        $this->info('✓ Core chart of accounts present');
    }

    $unbalanced = \App\Models\JournalEntry::query()
        ->whereIn('status', ['posted','reversed'])
        ->withSum('lines as debit_total', 'base_debit')
        ->withSum('lines as credit_total', 'base_credit')
        ->get()
        ->filter(fn ($j) => abs((float)$j->debit_total - (float)$j->credit_total) > 0.005);
    if ($unbalanced->isNotEmpty()) {
        $errors++;
        $this->error('Unbalanced journal entries: '.$unbalanced->pluck('journal_number')->implode(', '));
    } else {
        $this->info('✓ All posted/reversed journal entries are balanced');
    }

    $duplicates = \App\Models\JournalEntry::query()
        ->whereNotNull('source_type')->whereNotNull('source_id')->whereNotNull('entry_kind')
        ->selectRaw('source_type, source_id, entry_kind, COUNT(*) as c')
        ->groupBy('source_type','source_id','entry_kind')
        ->having('c','>',1)->get();
    if ($duplicates->isNotEmpty()) {
        $errors++;
        $this->error('Duplicate source postings detected: '.$duplicates->count());
    } else {
        $this->info('✓ No duplicate source postings detected');
    }

    $costing = app(\App\Services\InventoryCostingService::class);
    $inventoryErrors = 0;
    \App\Models\Material::query()->where('is_active', true)->chunkById(200, function ($materials) use ($costing, &$inventoryErrors) {
        foreach ($materials as $material) {
            try {
                $projection = $costing->project((int)$material->id);
                if (abs((float)$projection['stock'] - (float)$material->current_stock) > 0.0005 || abs((float)$projection['avg_cost'] - (float)$material->avg_unit_cost) > 0.01) {
                    $inventoryErrors++;
                }
            } catch (\Throwable $e) {
                $inventoryErrors++;
            }
        }
    });
    if ($inventoryErrors > 0) {
        $errors++;
        $this->error("Inventory ledger/current-stock mismatches: {$inventoryErrors}");
    } else {
        $this->info('✓ Inventory movement ledger reconciles to current material stock/cost');
    }

    $pendingWithInvoice = \App\Models\Sale::query()
        ->whereNotNull('order_id')
        ->where('revenue_status', 'pending')
        ->whereIn('sale_status', ['active'])
        ->whereExists(function ($q) { $q->from('journal_entries')->whereColumn('journal_entries.source_id','sales.id')->where('journal_entries.source_type','sale')->where('journal_entries.entry_kind','sale_invoice')->whereIn('journal_entries.status',['posted','reversed']); })
        ->count();
    if ($pendingWithInvoice > 0) {
        $warnings++;
        $this->warn("Made-to-order sales marked pending but already journaled: {$pendingWithInvoice}. Review legacy data before go-live.");
    } else {
        $this->info('✓ No pending made-to-order sale has a sale journal');
    }

    $recognizedMissing = \App\Models\Sale::query()
        ->where(function ($q) { $q->whereNull('revenue_status')->orWhere('revenue_status','recognized'); })
        ->whereIn('sale_status', ['active'])
        ->whereNotExists(function ($q) { $q->from('journal_entries')->whereColumn('journal_entries.source_id','sales.id')->where('journal_entries.source_type','sale')->where('journal_entries.entry_kind','sale_invoice')->whereIn('journal_entries.status',['posted','reversed']); })
        ->count();
    if ($recognizedMissing > 0) {
        $errors++;
        $this->error("Recognized/active sales without sale journal: {$recognizedMissing}");
    } else {
        $this->info('✓ Active recognized sales have sale postings');
    }

    $unsafeIncomeTax = \App\Models\TaxConfiguration::query()->where('tax_type','income_tax')->where('post_to_ledger',true)->count();
    if ($unsafeIncomeTax > 0) {
        $errors++;
        $this->error("Income-tax configurations incorrectly marked for transaction posting: {$unsafeIncomeTax}");
    } else {
        $this->info('✓ Income tax is not configured as a per-transaction sales tax');
    }

    $openings = \App\Models\OpeningBalance::query()->whereIn('status',['confirmed','locked'])->count();
    if ($openings !== 1) {
        $warnings++;
        $this->warn("Confirmed/locked opening balances: {$openings}. Expected exactly one for a fresh go-live database.");
    } else {
        $this->info('✓ Exactly one confirmed/locked opening balance');
    }

    $this->line(str_repeat('─', 70));
    $this->line("Errors: {$errors} | Warnings: {$warnings}");
    if ($errors > 0) {
        $this->error('Accounting audit FAILED. Do not use the reports as the final financial source until errors are resolved.');
        return 1;
    }
    $this->info('Accounting audit PASSED with no critical consistency errors.');
    return 0;
})->purpose('Run a read-only accounting and inventory go-live consistency audit');
