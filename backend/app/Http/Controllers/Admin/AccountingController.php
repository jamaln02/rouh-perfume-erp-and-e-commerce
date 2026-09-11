<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\AccountReconciliation;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Services\FinancePostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingController extends Controller
{
    public function accounts(): JsonResponse
    {
        $accounts = FinancialAccount::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id','code','name','name_ar','account_type','account_group','normal_balance','currency','current_balance','is_system','is_control_account']);
        return response()->json(['ok' => true, 'accounts' => $accounts]);
    }

    public function storeAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'account_type' => ['required', 'in:cash,bank,payment_gateway,other'],
            'currency' => ['required', 'in:SYP,USD'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:100'],
        ]);

        app(\App\Services\FinancePostingService::class)->ensureCoreAccounts();

        $prefix = match ($data['account_type']) {
            'cash' => 1050,
            'bank' => 1060,
            'payment_gateway' => 1070,
            default => 1800,
        };
        $code = $prefix;
        while (FinancialAccount::where('code', (string) $code)->exists()) {
            $code++;
            if ($code > $prefix + 99) {
                return response()->json(['ok' => false, 'message' => 'No available account code remains in this account range.'], 422);
            }
        }

        $account = FinancialAccount::create([
            'code' => (string) $code,
            'name' => trim($data['name']),
            'name_ar' => trim($data['name_ar'] ?? '') ?: null,
            'account_type' => $data['account_type'],
            'currency' => $data['currency'],
            'account_group' => 'asset',
            'normal_balance' => 'debit',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_active' => true,
            'is_system' => false,
            'is_control_account' => false,
            'bank_name' => $data['bank_name'] ?? null,
            'account_number' => $data['account_number'] ?? null,
            'created_by' => (int) ($request->attributes->get('authUser')?->id ?? 0),
        ]);

        return response()->json(['ok' => true, 'account' => $account], 201);
    }

    public function ledger(Request $request, int $accountId): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['nullable','date'],
            'end_date' => ['nullable','date','after_or_equal:start_date'],
        ]);
        $account = FinancialAccount::findOrFail($accountId);
        $start = $data['start_date'] ?? null;
        $end = $data['end_date'] ?? now()->toDateString();

        $base = JournalLine::query()
            ->where('financial_account_id', $account->id)
            ->whereHas('entry', fn($q) => $q->whereIn('status',['posted','reversed']));
        if ($start) $base->whereHas('entry', fn($q) => $q->whereDate('entry_date','<',$start));
        $opening = (float) $base->sum(DB::raw('base_debit - base_credit'));
        if ($account->normal_balance === 'credit') $opening = -$opening;

        $query = JournalLine::with(['entry:id,journal_number,entry_date,description,status'])
            ->where('financial_account_id', $account->id)
            ->whereHas('entry', function($q) use ($start,$end) {
                $q->whereIn('status',['posted','reversed']);
                if ($start) $q->whereDate('entry_date','>=',$start);
                if ($end) $q->whereDate('entry_date','<=',$end);
            })
            ->join('journal_entries as je','je.id','=','journal_lines.journal_entry_id')
            ->orderBy('je.entry_date')->orderBy('je.id')->orderBy('journal_lines.id')
            ->select('journal_lines.*');
        $rows=[]; $balance=$opening;
        foreach ($query->get() as $line) {
            $debit=(float)$line->base_debit; $credit=(float)$line->base_credit;
            $delta=$account->normal_balance === 'credit' ? ($credit-$debit) : ($debit-$credit);
            $balance=round($balance+$delta,2);
            $rows[]=['journal_entry_id'=>$line->journal_entry_id,'journal_number'=>$line->entry?->journal_number,'entry_date'=>$line->entry?->entry_date?->toDateString(),'description'=>$line->description ?: $line->entry?->description,'debit'=>round($debit,2),'credit'=>round($credit,2),'balance'=>$balance,'status'=>$line->entry?->status];
        }
        return response()->json(['ok'=>true,'account'=>$account,'opening_balance'=>round($opening,2),'closing_balance'=>round($balance,2),'rows'=>$rows]);
    }

    public function storeJournal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entry_date' => ['required','date'],
            'description' => ['required','string','max:500'],
            'lines' => ['required','array','min:2'],
            'lines.*.account_id' => ['required','integer','exists:financial_accounts,id'],
            'lines.*.debit' => ['nullable','numeric','min:0'],
            'lines.*.credit' => ['nullable','numeric','min:0'],
            'lines.*.description' => ['nullable','string','max:500'],
        ]);
        $lines=[];
        foreach ($data['lines'] as $line) {
            $debit=round((float)($line['debit'] ?? 0),2); $credit=round((float)($line['credit'] ?? 0),2);
            if (($debit > 0 && $credit > 0) || ($debit <= 0 && $credit <= 0)) return response()->json(['ok'=>false,'message'=>'Each journal line must contain either debit or credit, not both.'],422);
            $account=FinancialAccount::findOrFail($line['account_id']);
            $lines[]=['account'=>$account,'debit'=>$debit,'credit'=>$credit,'description'=>$line['description'] ?? null];
        }
        try {
            $journal=app(FinancePostingService::class)->createJournal([
                'entry_date'=>$data['entry_date'],
                'description'=>$data['description'],
                'source_type'=>null,
                'source_id'=>null,
                'entry_kind'=>'general',
                'metadata'=>['manual'=>true],
            ],$lines);
            $request->attributes->set('audit.reason','Manual journal posted');
            return response()->json(['ok'=>true,'journal'=>$journal->load('lines.account','period')],201);
        } catch (\Throwable $e) { report($e); return response()->json(['ok'=>false,'message'=>app()->environment('local') ? $e->getMessage() : 'The accounting operation could not be completed safely.'],422); }
    }

    public function reverseJournal(Request $request, int $id): JsonResponse
    {
        try {
            $entry=JournalEntry::findOrFail($id);
            $reversal=app(FinancePostingService::class)->reverseJournal($entry,$request->input('reason') ?: 'Manual accounting reversal');
            $request->attributes->set('audit.reason','Journal reversed');
            return response()->json(['ok'=>true,'journal'=>$reversal->load('lines.account','period')]);
        } catch (\Throwable $e) { report($e); return response()->json(['ok'=>false,'message'=>app()->environment('local') ? $e->getMessage() : 'The accounting operation could not be completed safely.'],422); }
    }

    public function periods(): JsonResponse
    {
        $rows = AccountingPeriod::query()
            ->with(['closer:id,name', 'reopener:id,name'])
            ->orderByDesc('period_end')
            ->limit(100)
            ->get();

        return response()->json(['ok' => true, 'periods' => $rows]);
    }

    public function storePeriod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $overlap = AccountingPeriod::query()
            ->whereIn('status', ['open', 'closed', 'reopened'])
            ->whereDate('period_start', '<=', $data['period_end'])
            ->whereDate('period_end', '>=', $data['period_start'])
            ->exists();

        if ($overlap) {
            return response()->json(['ok' => false, 'message' => 'The accounting period overlaps an existing period.'], 422);
        }

        $period = AccountingPeriod::create([
            ...$data,
            'name' => $data['name'] ?? sprintf('%s → %s', $data['period_start'], $data['period_end']),
            'status' => 'open',
            'created_by' => $request->attributes->get('authUser')?->id,
        ]);

        return response()->json(['ok' => true, 'period' => $period], 201);
    }

    public function closePeriod(Request $request, int $id): JsonResponse
    {
        $period = AccountingPeriod::withCount(['journalEntries'])->findOrFail($id);
        if ($period->status === 'closed') {
            return response()->json(['ok' => false, 'message' => 'Period is already closed.'], 422);
        }
        if ($period->status !== 'open' && $period->status !== 'reopened') {
            return response()->json(['ok' => false, 'message' => 'Only open or reopened periods can be closed.'], 422);
        }

        $pendingCorrections = DB::table('consumption_adjustment_requests')
            ->where('status', 'pending')
            ->whereBetween('created_at', [$period->period_start->startOfDay(), $period->period_end->endOfDay()])
            ->count();
        if ($pendingCorrections > 0) {
            return response()->json(['ok' => false, 'message' => 'Resolve all pending consumption corrections before closing this period.'], 422);
        }

        $unbalanced = DB::table('journal_entries as je')
            ->join('journal_lines as jl', 'jl.journal_entry_id', '=', 'je.id')
            ->where('je.accounting_period_id', $period->id)
            ->where('je.status', 'posted')
            ->select('je.id')
            ->groupBy('je.id')
            ->havingRaw('ABS(SUM(jl.base_debit) - SUM(jl.base_credit)) > 0.005')
            ->count();
        if ($unbalanced > 0) {
            return response()->json(['ok' => false, 'message' => 'The period contains unbalanced journal entries. Correct them before closing.'], 422);
        }

        $tb = app(FinancePostingService::class)->trialBalance($period->period_start->toDateString(), $period->period_end->toDateString());
        if (abs((float)$tb['difference']) > 0.005) {
            return response()->json(['ok' => false, 'message' => 'Trial balance is not balanced. Difference: ' . $tb['difference']], 422);
        }

        $request->attributes->set('audit.reason', 'Accounting period closed after control validation');
        $request->attributes->set('audit.after', $period->toArray());
        DB::transaction(function () use ($period, $request) {
            $period->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $request->attributes->get('authUser')?->id,
            ]);
        });

        return response()->json(['ok' => true, 'period' => $period->fresh(), 'transaction_count' => $period->journalEntries()->count()]);
    }

    public function reopenPeriod(Request $request, int $id): JsonResponse
    {
        $period = AccountingPeriod::findOrFail($id);
        if ($period->status !== 'closed') {
            return response()->json(['ok' => false, 'message' => 'Only closed periods can be reopened.'], 422);
        }

        $old = $period->toArray();
        $period->update([
            'status' => 'reopened',
            'reopened_at' => now(),
            'reopened_by' => $request->attributes->get('authUser')?->id,
        ]);
        $request->attributes->set('audit.before', $old);
        $request->attributes->set('audit.after', $period->fresh()->toArray());
        $request->attributes->set('audit.reason', 'Accounting period reopened by owner');

        return response()->json(['ok' => true, 'period' => $period->fresh()]);
    }

    public function reconciliations(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'reconciliations' => AccountReconciliation::with(['account:id,name,name_ar', 'reconciler:id,name'])
                ->orderByDesc('reconciliation_date')
                ->limit(100)
                ->get(),
        ]);
    }

    public function reconciliationOptions(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'accounts' => FinancialAccount::query()
                ->where('is_active', true)
                ->whereIn('account_type', ['cash', 'bank', 'payment_gateway', 'other'])
                ->orderBy('name')
                ->get(['id', 'name', 'name_ar', 'account_type', 'currency', 'current_balance']),
        ]);
    }

    public function storeReconciliation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'reconciliation_date' => ['required', 'date'],
            'statement_balance' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $account = FinancialAccount::findOrFail($data['financial_account_id']);
        $movement = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'je.id', '=', 'jl.journal_entry_id')
            ->where('jl.financial_account_id', $account->id)
            ->whereDate('je.entry_date', '<=', $data['reconciliation_date'])
            ->whereIn('je.status', ['posted', 'reversed'])
            ->selectRaw('COALESCE(SUM(jl.base_debit - jl.base_credit), 0) AS net_movement')
            ->value('net_movement');
        // Opening balances are journalized into the GL. Do not add the legacy
        // financial_accounts.opening_balance again or reconciliation double-counts them.
        $bookBalance = (float) $movement;
        $statement = (float) $data['statement_balance'];
        $difference = round($statement - $bookBalance, 2);

        $row = AccountReconciliation::create([
            'financial_account_id' => $account->id,
            'reconciliation_date' => $data['reconciliation_date'],
            'book_balance' => $bookBalance,
            'statement_balance' => $statement,
            'difference' => $difference,
            'status' => abs($difference) < 0.01 ? 'matched' : 'difference',
            'notes' => $data['notes'] ?? null,
            'reconciled_by' => $request->attributes->get('authUser')?->id,
        ]);

        return response()->json(['ok' => true, 'reconciliation' => $row->load('account:id,name,name_ar')], 201);
    }
    public function trialBalance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $result = app(FinancePostingService::class)->trialBalance($data['start_date'] ?? null, $data['end_date'] ?? null);
        return response()->json(['ok' => true, ...$result]);
    }

    public function statements(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        return response()->json(['ok' => true, ...app(FinancePostingService::class)->financialStatements($data['start_date'] ?? null, $data['end_date'] ?? null)]);
    }

    public function journals(Request $request): JsonResponse
    {
        $query = JournalEntry::with(['lines.account:id,code,name,name_ar,account_group,normal_balance', 'period:id,name'])
            ->orderByDesc('entry_date')->orderByDesc('id');
        if ($request->filled('start_date')) $query->whereDate('entry_date', '>=', $request->date('start_date'));
        if ($request->filled('end_date')) $query->whereDate('entry_date', '<=', $request->date('end_date'));
        return response()->json(['ok' => true, 'journals' => $query->limit(200)->get()]);
    }

    public function controlHealth(Request $request): JsonResponse
    {
        $tb = app(FinancePostingService::class)->trialBalance($request->input('start_date'), $request->input('end_date'));
        $openPeriods = AccountingPeriod::whereIn('status', ['open','reopened'])->count();
        $posted = JournalEntry::where('status', 'posted')->count();
        $reversed = JournalEntry::where('status', 'reversed')->count();
        return response()->json([
            'ok' => true,
            'healthy' => abs((float)$tb['difference']) <= 0.005,
            'trial_balance_difference' => $tb['difference'],
            'open_periods' => $openPeriods,
            'posted_journals' => $posted,
            'reversed_journals' => $reversed,
        ]);
    }

}
