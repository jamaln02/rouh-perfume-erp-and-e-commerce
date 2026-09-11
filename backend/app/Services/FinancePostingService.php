<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\FinancialPayment;
use App\Models\FinancialTransaction;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationEntry;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\OpeningBalance;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockCount;
use App\Models\TaxConfiguration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FinancePostingService
{
    public const SYP = 'SYP';

    public function ensureCoreAccounts(): void
    {
        $accounts = [
            ['code' => '1000', 'name' => 'Cash', 'name_ar' => 'الصندوق', 'account_type' => 'cash', 'account_group' => 'asset', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '1010', 'name' => 'Bank Transfer', 'name_ar' => 'الحساب البنكي', 'account_type' => 'bank', 'account_group' => 'asset', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '1020', 'name' => 'Sham Cash', 'name_ar' => 'شام كاش', 'account_type' => 'payment_gateway', 'account_group' => 'asset', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'name_ar' => 'ذمم العملاء', 'account_type' => 'other', 'account_group' => 'asset', 'normal_balance' => 'debit', 'is_control_account' => true],
            ['code' => '1200', 'name' => 'Inventory - Materials', 'name_ar' => 'مخزون المواد', 'account_type' => 'other', 'account_group' => 'asset', 'normal_balance' => 'debit', 'is_control_account' => true],
            ['code' => '1210', 'name' => 'Inventory - Finished Goods', 'name_ar' => 'مخزون المنتجات الجاهزة', 'account_type' => 'other', 'account_group' => 'asset', 'normal_balance' => 'debit', 'is_control_account' => true],
            ['code' => '1220', 'name' => 'Inventory - Work in Progress', 'name_ar' => 'مخزون تحت التشغيل', 'account_type' => 'other', 'account_group' => 'asset', 'normal_balance' => 'debit', 'is_control_account' => true],
            ['code' => '1500', 'name' => 'Fixed Assets', 'name_ar' => 'الأصول الثابتة', 'account_type' => 'other', 'account_group' => 'asset', 'normal_balance' => 'debit', 'is_control_account' => true],
            ['code' => '1590', 'name' => 'Accumulated Depreciation', 'name_ar' => 'مجمع الإهلاك', 'account_type' => 'other', 'account_group' => 'contra_asset', 'normal_balance' => 'credit', 'is_control_account' => true],
            ['code' => '2000', 'name' => 'Accounts Payable', 'name_ar' => 'ذمم الموردين', 'account_type' => 'other', 'account_group' => 'liability', 'normal_balance' => 'credit', 'is_control_account' => true],
            ['code' => '2200', 'name' => 'Customer Deposits', 'name_ar' => 'دفعات مقدمة من العملاء', 'account_type' => 'other', 'account_group' => 'liability', 'normal_balance' => 'credit', 'is_control_account' => true],
            ['code' => '2100', 'name' => 'Tax Payable', 'name_ar' => 'ضرائب مستحقة', 'account_type' => 'other', 'account_group' => 'liability', 'normal_balance' => 'credit', 'is_control_account' => true],
            ['code' => '3000', 'name' => 'Owner Capital', 'name_ar' => 'رأس مال المالك', 'account_type' => 'other', 'account_group' => 'equity', 'normal_balance' => 'credit', 'is_control_account' => true],
            ['code' => '3100', 'name' => 'Retained Earnings', 'name_ar' => 'الأرباح المحتجزة', 'account_type' => 'other', 'account_group' => 'equity', 'normal_balance' => 'credit', 'is_control_account' => true],
            ['code' => '3990', 'name' => 'Opening Balance Equity', 'name_ar' => 'تسوية الأرصدة الافتتاحية', 'account_type' => 'other', 'account_group' => 'equity', 'normal_balance' => 'credit', 'is_control_account' => true],
            ['code' => '4000', 'name' => 'Sales Revenue', 'name_ar' => 'إيرادات المبيعات', 'account_type' => 'other', 'account_group' => 'revenue', 'normal_balance' => 'credit', 'is_control_account' => false],
            ['code' => '4010', 'name' => 'Shipping Revenue', 'name_ar' => 'إيرادات الشحن', 'account_type' => 'other', 'account_group' => 'revenue', 'normal_balance' => 'credit', 'is_control_account' => false],
            ['code' => '4020', 'name' => 'Inventory Adjustment Gain', 'name_ar' => 'أرباح فروقات الجرد', 'account_type' => 'other', 'account_group' => 'other_income', 'normal_balance' => 'credit', 'is_control_account' => false],
            ['code' => '5000', 'name' => 'Cost of Goods Sold', 'name_ar' => 'تكلفة البضاعة المباعة', 'account_type' => 'other', 'account_group' => 'cogs', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '5100', 'name' => 'Depreciation Expense', 'name_ar' => 'مصروف الإهلاك', 'account_type' => 'other', 'account_group' => 'expense', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '5200', 'name' => 'Expense - Rent', 'name_ar' => 'مصروف الإيجار', 'account_type' => 'other', 'account_group' => 'expense', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '5210', 'name' => 'Expense - Salaries', 'name_ar' => 'مصروف الرواتب', 'account_type' => 'other', 'account_group' => 'expense', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '5220', 'name' => 'Expense - Utilities', 'name_ar' => 'مصروف الخدمات', 'account_type' => 'other', 'account_group' => 'expense', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '5230', 'name' => 'Expense - Marketing', 'name_ar' => 'مصروف التسويق', 'account_type' => 'other', 'account_group' => 'expense', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '5240', 'name' => 'Expense - Transport', 'name_ar' => 'مصروف النقل', 'account_type' => 'other', 'account_group' => 'expense', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '5250', 'name' => 'Expense - Inventory Shrinkage', 'name_ar' => 'مصروف عجز المخزون', 'account_type' => 'other', 'account_group' => 'expense', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '5260', 'name' => 'Expense - Inventory Valuation Write-down', 'name_ar' => 'مصروف انخفاض قيمة المخزون', 'account_type' => 'other', 'account_group' => 'expense', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '5290', 'name' => 'Expense - Other', 'name_ar' => 'مصروفات أخرى', 'account_type' => 'other', 'account_group' => 'expense', 'normal_balance' => 'debit', 'is_control_account' => false],
            ['code' => '5300', 'name' => 'Gain/Loss on Asset Disposal', 'name_ar' => 'ربح/خسارة استبعاد الأصول', 'account_type' => 'other', 'account_group' => 'other_income', 'normal_balance' => 'credit', 'is_control_account' => false],
        ];

        foreach ($accounts as $account) {
            $model = FinancialAccount::where('code', $account['code'])->first();
            if (!$model) {
                $model = FinancialAccount::where('name', $account['name'])->first();
            }
            if (!$model) {
                $model = new FinancialAccount();
                $model->opening_balance = 0;
                $model->current_balance = 0;
            }
            $model->fill(array_merge($account, [
                'currency' => $model->currency ?: self::SYP,
                'is_active' => true,
                'is_system' => true,
            ]));
            $model->save();
        }
    }

    public function postSale(Sale $sale, ?string $entryDateOverride = null): void
    {
        $this->ensureCoreAccounts();
        if (($sale->sale_status ?? 'active') === 'voided') {
            $this->reverseSource('sale', (string) $sale->id, 'sale_invoice');
            return;
        }

        DB::transaction(function () use ($sale, $entryDateOverride): void {
            $existing = $this->findJournal('sale', (string) $sale->id, 'sale_invoice');
            if ($existing) {
                $this->assertSaleMatchesJournal($sale, $existing);
                return;
            }

            $entryDate = Carbon::parse($entryDateOverride ?: $sale->sale_date)->toDateString();
            $this->assertOpenPeriod($entryDate);

            $baseAmount = $this->money($sale->total_price_syp ?? $sale->total_price);
            if ($baseAmount <= 0) return;

            $transactionTax = TaxConfiguration::query()
                ->activeForDate($entryDate)
                ->where('post_to_ledger', true)
                ->whereIn('tax_type', ['vat', 'sales_tax'])
                ->where('applicable_to', 'all_revenue')
                ->orderByDesc('effective_date')->orderByDesc('id')->first();

            $taxAmount = 0.0;
            $netRevenue = $baseAmount;
            $invoiceTotal = $baseAmount;
            if ($transactionTax) {
                if ($transactionTax->calculation_method === 'fixed_amount') {
                    $taxAmount = $this->money($transactionTax->fixed_amount);
                } elseif ((float) $transactionTax->rate > 0) {
                    $rate = (float) $transactionTax->rate / 100;
                    $taxAmount = $transactionTax->tax_inclusive
                        ? $this->money($baseAmount - ($baseAmount / (1 + $rate)))
                        : $this->money($baseAmount * $rate);
                }
                $taxAmount = max(0.0, $taxAmount);
                if ($transactionTax->tax_inclusive) {
                    $taxAmount = min($baseAmount, $taxAmount);
                    $netRevenue = $this->money($baseAmount - $taxAmount);
                    $invoiceTotal = $baseAmount;
                } else {
                    $netRevenue = $baseAmount;
                    $invoiceTotal = $this->money($baseAmount + $taxAmount);
                }
            }

            // Customer deposits are receipts already recorded against the order.
            // They reduce the remaining receivable; they are not new cash at invoice time.
            $paidGross = min($invoiceTotal, max(0.0, $this->money($sale->paid_amount)));
            $prepaid = 0.0;
            $depositAllocations = [];
            if ($sale->order_id && $paidGross > 0) {
                $remainingPrepaid = $paidGross;
                $deposits = FinancialPayment::query()
                    ->where('order_id', $sale->order_id)
                    ->whereNull('sale_id')
                    ->where('direction', 'receipt')
                    ->where('status', 'posted')
                    ->orderBy('payment_date')->orderBy('id')
                    ->lockForUpdate()->get();
                foreach ($deposits as $deposit) {
                    if ($remainingPrepaid <= 0.005) break;
                    $available = $this->money($deposit->amount_base);
                    if ($available <= 0) continue;
                    $apply = min($available, $remainingPrepaid);
                    $depositAllocations[] = ['payment' => $deposit, 'available' => $available, 'apply' => $apply];
                    $prepaid = $this->money($prepaid + $apply);
                    $remainingPrepaid = $this->money($remainingPrepaid - $apply);
                }
            }
            $paidAtSale = $this->money($paidGross - $prepaid);
            $receivable = $this->money($invoiceTotal - $paidGross);

            $shippingRevenue = 0.0;
            if ($sale->order_id) {
                $grossShipping = max(0.0, $this->money(DB::table('orders')->where('id', $sale->order_id)->value('shipping_fee') ?? 0));
                if ($grossShipping > 0) {
                    $shippingRevenue = $transactionTax && $transactionTax->tax_inclusive && $baseAmount > 0
                        ? $this->money($grossShipping * ($netRevenue / $baseAmount))
                        : $grossShipping;
                    $shippingRevenue = min($netRevenue, $shippingRevenue);
                }
            }
            $productRevenue = $this->money($netRevenue - $shippingRevenue);

            $lines = [];
            if ($prepaid > 0) {
                $lines[] = ['account' => $this->account('2200'), 'debit' => $prepaid, 'credit' => 0, 'description' => 'Customer deposit applied to sale', 'counterparty_type' => $sale->customer_id ? 'customer' : null, 'counterparty_id' => $sale->customer_id];
            }
            if ($paidAtSale > 0) {
                $lines[] = ['account' => $this->accountByPaymentMethod($sale->payment_method), 'debit' => $paidAtSale, 'credit' => 0, 'description' => 'Initial sale payment', 'counterparty_type' => $sale->customer_id ? 'customer' : null, 'counterparty_id' => $sale->customer_id];
            }
            if ($receivable > 0) {
                $lines[] = ['account' => $this->account('1100'), 'debit' => $receivable, 'credit' => 0, 'description' => 'Customer receivable', 'counterparty_type' => $sale->customer_id ? 'customer' : null, 'counterparty_id' => $sale->customer_id];
            }
            if ($productRevenue > 0) {
                $lines[] = ['account' => $this->account('4000'), 'debit' => 0, 'credit' => $productRevenue, 'description' => 'Sale revenue'];
            }
            if ($shippingRevenue > 0) {
                $lines[] = ['account' => $this->account('4010'), 'debit' => 0, 'credit' => $shippingRevenue, 'description' => 'Customer shipping revenue'];
            }
            if ($taxAmount > 0) {
                $lines[] = ['account' => $this->account('2100'), 'debit' => 0, 'credit' => $taxAmount, 'description' => 'Output transaction tax payable'];
            }

            // For made-to-order production, material consumption remains in WIP until
            // revenue is recognized. Direct finished-goods sales relieve FG immediately.
            if (!$this->hasOrderConsumption($sale) && $this->money($sale->cost_syp ?? $sale->cost) > 0) {
                $cogs = $this->money($sale->cost_syp ?? $sale->cost);
                $lines[] = ['account' => $this->account('5000'), 'debit' => $cogs, 'credit' => 0, 'description' => 'Direct sale cost'];
                $lines[] = ['account' => $this->account('1210'), 'debit' => 0, 'credit' => $cogs, 'description' => 'Finished goods issued'];
            } elseif ($this->hasOrderConsumption($sale)) {
                $wipCost = $this->money(DB::table('order_consumptions')->where('order_id', $sale->order_id)->where('status', 'completed')->sum('total_material_cost'));
                if ($wipCost > 0) {
                    $lines[] = ['account' => $this->account('5000'), 'debit' => $wipCost, 'credit' => 0, 'description' => 'MTO material cost recognized with revenue'];
                    $lines[] = ['account' => $this->account('1220'), 'debit' => 0, 'credit' => $wipCost, 'description' => 'Transfer work in progress to cost of sales'];
                }
            }

            $this->createJournal([
                'entry_date' => $entryDate,
                'description' => 'Sale ' . ($sale->invoice_number ?: '#' . $sale->id),
                'source_type' => 'sale',
                'source_id' => (string) $sale->id,
                'entry_kind' => 'sale_invoice',
                'metadata' => [
                    'invoice_number' => $sale->invoice_number,
                    'payment_status' => $sale->payment_status,
                    'made_to_order' => $this->hasOrderConsumption($sale),
                    'tax_configuration_id' => $transactionTax?->id,
                    'tax_amount' => $taxAmount,
                    'tax_inclusive' => (bool) ($transactionTax?->tax_inclusive ?? true),
                ],
            ], $lines);

            $sale->forceFill([
                'revenue_status' => 'recognized',
                'revenue_recognized_at' => $sale->revenue_recognized_at ?: now(),
            ])->saveQuietly();

            foreach ($depositAllocations as $allocation) {
                /** @var FinancialPayment $deposit */
                $deposit = $allocation['payment'];
                $available = (float) $allocation['available'];
                $apply = (float) $allocation['apply'];
                if ($apply <= 0.005) continue;

                if (abs($apply - $available) <= 0.005) {
                    $deposit->sale_id = $sale->id;
                    $deposit->save();
                    continue;
                }

                $originalJournal = $deposit->journal_entry_id
                    ? JournalEntry::find($deposit->journal_entry_id)
                    : $this->findJournal('financial_payment', (string) $deposit->id, 'customer_deposit');
                if ($originalJournal && $originalJournal->status === 'posted') {
                    $this->reverseJournal($originalJournal, 'Split customer deposit during invoice allocation');
                }

                $ratio = $apply / $available;
                $appliedAmount = $this->money((float) $deposit->amount * $ratio);
                $remainingAmount = $this->money((float) $deposit->amount - $appliedAmount);
                $applied = FinancialPayment::create([
                    'payment_number' => 'DEP-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(7)),
                    'direction' => 'receipt', 'amount' => $appliedAmount,
                    'currency' => $deposit->currency ?: self::SYP,
                    'exchange_rate' => $deposit->exchange_rate ?: 1,
                    'amount_base' => $this->money($apply),
                    'financial_account_id' => $deposit->financial_account_id,
                    'sale_id' => $sale->id, 'order_id' => (string) $sale->order_id,
                    'payment_method' => $deposit->payment_method, 'reference' => $deposit->reference,
                    'notes' => trim(($deposit->notes ?: '') . ' | Applied to invoice ' . ($sale->invoice_number ?: $sale->id) . '. Cash receipt was already posted in the original deposit journal; no new cash movement is created.'),
                    'payment_date' => $deposit->payment_date, 'status' => 'posted', 'created_by' => $deposit->created_by,
                ]);

                $remainder = FinancialPayment::create([
                    'payment_number' => 'DEP-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(7)),
                    'direction' => 'receipt', 'amount' => $remainingAmount,
                    'currency' => $deposit->currency ?: self::SYP,
                    'exchange_rate' => $deposit->exchange_rate ?: 1,
                    'amount_base' => $this->money($available - $apply),
                    'financial_account_id' => $deposit->financial_account_id,
                    'sale_id' => null, 'order_id' => (string) $sale->order_id,
                    'payment_method' => $deposit->payment_method, 'reference' => $deposit->reference,
                    'notes' => trim(($deposit->notes ?: '') . ' | Unapplied customer deposit remainder after invoice allocation. Original receipt date: ' . $deposit->payment_date),
                    'payment_date' => $deposit->payment_date, 'status' => 'posted', 'created_by' => $deposit->created_by,
                ]);
                $this->postOrderDeposit($remainder);

                $deposit->status = 'split';
                $deposit->notes = trim(($deposit->notes ?: '') . ' | Split into applied receipt ' . $applied->payment_number . ' and remaining deposit ' . $remainder->payment_number . '.');
                $deposit->save();
            }
        });
    }

    public function reverseMaterialConsumption(string $orderConsumptionId, string $reason = 'Material consumption reversed'): ?JournalEntry
    {
        $entry = $this->findJournal('order_consumption', $orderConsumptionId, 'material_cogs');
        if (!$entry) return null;
        return $this->reverseJournal($entry, $reason);
    }

    public function postMaterialConsumption(string $orderConsumptionId, float $materialCost, ?string $orderId = null): void
    {
        if ($materialCost <= 0) return;
        $this->ensureCoreAccounts();
        DB::transaction(function () use ($orderConsumptionId, $materialCost, $orderId): void {
            if ($this->findJournal('order_consumption', $orderConsumptionId, 'material_cogs')) return;
            $preparedAt = DB::table('order_consumptions')->where('id', $orderConsumptionId)->value('prepared_at');
            $date = $preparedAt ? Carbon::parse($preparedAt)->toDateString() : now()->toDateString();
            $this->assertOpenPeriod($date);
            $cost = $this->money($materialCost);
            $this->createJournal([
                'entry_date' => $date,
                'description' => 'Material consumption for made-to-order ' . $orderConsumptionId,
                'source_type' => 'order_consumption',
                'source_id' => $orderConsumptionId,
                'entry_kind' => 'material_cogs',
                'metadata' => ['order_id' => $orderId, 'amount' => $cost],
            ], [
                ['account' => $this->account('1220'), 'debit' => $cost, 'credit' => 0, 'description' => 'Material cost transferred to work in progress'],
                ['account' => $this->account('1200'), 'debit' => 0, 'credit' => $cost, 'description' => 'Raw materials consumed into production'],
            ]);
        });
    }

    public function postExpense(Expense $expense): void
    {
        $this->ensureCoreAccounts();
        DB::transaction(function () use ($expense): void {
            if ($this->findJournal('expense', (string) $expense->id, 'expense')) {
                return;
            }
            $date = Carbon::parse($expense->expense_date)->toDateString();
            $this->assertOpenPeriod($date);
            $amount = $this->money($expense->amount_syp ?? $expense->amount);
            if ($amount <= 0) return;
            $debitAccount = $this->expenseAccount($expense->category);
            $isCredit = $this->normalizePaymentMethod($expense->payment_method) === 'credit';
            $creditAccount = $isCredit ? $this->account('2000') : $this->accountByPaymentMethod($expense->payment_method);

            $this->createJournal([
                'entry_date' => $date,
                'description' => 'Expense: ' . $expense->description,
                'source_type' => 'expense',
                'source_id' => (string) $expense->id,
                'entry_kind' => 'expense',
                'metadata' => ['category' => $expense->category, 'payment_method' => $expense->payment_method],
            ], [
                ['account' => $debitAccount, 'debit' => $amount, 'credit' => 0, 'description' => $expense->description],
                ['account' => $creditAccount, 'debit' => 0, 'credit' => $amount, 'description' => $isCredit ? 'Supplier/vendor payable' : 'Expense payment'],
            ]);
        });
    }

    public function postPurchase(Purchase $purchase): void
    {
        $this->ensureCoreAccounts();
        DB::transaction(function () use ($purchase): void {
            if (($purchase->status ?? 'confirmed') === 'voided') {
                $this->reverseSource('purchase', (string) $purchase->id, 'purchase');
                return;
            }
            if ($this->findJournal('purchase', (string) $purchase->id, 'purchase')) return;
            $date = Carbon::parse($purchase->purchase_date)->toDateString();
            $this->assertOpenPeriod($date);
            $amount = $this->money($purchase->total_cost_syp ?? $purchase->total_cost);
            if ($amount <= 0) return;
            $lines = [
                ['account' => $this->account('1200'), 'debit' => $amount, 'credit' => 0, 'description' => 'Materials received into inventory'],
                ['account' => $this->account('2000'), 'debit' => 0, 'credit' => $amount, 'description' => 'Supplier payable', 'counterparty_type' => $purchase->supplier_id ? 'supplier' : null, 'counterparty_id' => $purchase->supplier_id],
            ];

            $this->createJournal([
                'entry_date' => $date,
                'description' => 'Purchase ' . ($purchase->invoice_number ?: '#' . $purchase->id),
                'source_type' => 'purchase',
                'source_id' => (string) $purchase->id,
                'entry_kind' => 'purchase',
                'metadata' => ['invoice_number' => $purchase->invoice_number, 'supplier' => $purchase->supplier],
            ], $lines);
        });
    }

    public function postOrderDeposit(FinancialPayment $payment): void
    {
        $this->ensureCoreAccounts();
        if ($this->findJournal('financial_payment', (string) $payment->id, 'customer_deposit')) return;
        $date = Carbon::parse($payment->payment_date)->toDateString();
        $this->assertOpenPeriod($date);
        $amount = $this->money($payment->amount_base);
        if ($amount <= 0) throw new RuntimeException('Deposit amount must be greater than zero.');
        $journal = $this->createJournal([
            'entry_date' => $date,
            'description' => 'Customer deposit ' . $payment->payment_number,
            'source_type' => 'financial_payment',
            'source_id' => (string) $payment->id,
            'entry_kind' => 'customer_deposit',
            'metadata' => ['order_id' => $payment->order_id, 'payment_method' => $payment->payment_method],
        ], [
            ['account' => $payment->account ?: $this->accountByPaymentMethod($payment->payment_method), 'debit' => $amount, 'credit' => 0, 'description' => 'Customer deposit received'],
            ['account' => $this->account('2200'), 'debit' => 0, 'credit' => $amount, 'description' => 'Customer advance liability', 'counterparty_type' => $payment->order_id ? 'order' : null, 'counterparty_id' => $payment->order_id],
        ]);
        $payment->journal_entry_id = $journal->id;
        $payment->save();
    }

    public function postSalePayment(FinancialPayment $payment, Sale $sale): void
    {
        $this->ensureCoreAccounts();
        if ($this->findJournal('financial_payment', (string) $payment->id, 'settlement')) return;
        $date = Carbon::parse($payment->payment_date)->toDateString();
        $this->assertOpenPeriod($date);
        $amount = $this->money($payment->amount_base);
        if ($amount <= 0) throw new RuntimeException('Payment amount must be greater than zero.');
        $journal = $this->createJournal([
            'entry_date' => $date,
            'description' => 'Customer payment ' . $payment->payment_number,
            'source_type' => 'financial_payment',
            'source_id' => (string) $payment->id,
            'entry_kind' => 'settlement',
            'metadata' => ['sale_id' => $sale->id, 'order_id' => $sale->order_id, 'payment_method' => $payment->payment_method],
        ], [
            ['account' => $payment->account ?: $this->accountByPaymentMethod($payment->payment_method), 'debit' => $amount, 'credit' => 0, 'description' => 'Customer cash receipt', 'counterparty_type' => $sale->customer_id ? 'customer' : null, 'counterparty_id' => $sale->customer_id],
            ['account' => $this->account('1100'), 'debit' => 0, 'credit' => $amount, 'description' => 'Accounts receivable settlement', 'counterparty_type' => $sale->customer_id ? 'customer' : null, 'counterparty_id' => $sale->customer_id],
        ]);
        $payment->journal_entry_id = $journal->id;
        $payment->save();
    }

    public function postPurchasePayment(FinancialPayment $payment, Purchase $purchase): void
    {
        $this->ensureCoreAccounts();
        if ($this->findJournal('financial_payment', (string) $payment->id, 'settlement')) return;
        $date = Carbon::parse($payment->payment_date)->toDateString();
        $this->assertOpenPeriod($date);
        $amount = $this->money($payment->amount_base);
        $journal = $this->createJournal([
            'entry_date' => $date,
            'description' => 'Supplier payment ' . $payment->payment_number,
            'source_type' => 'financial_payment',
            'source_id' => (string) $payment->id,
            'entry_kind' => 'settlement',
            'metadata' => ['purchase_id' => $purchase->id, 'payment_method' => $payment->payment_method],
        ], [
            ['account' => $this->account('2000'), 'debit' => $amount, 'credit' => 0, 'description' => 'Accounts payable settlement', 'counterparty_type' => $purchase->supplier_id ? 'supplier' : null, 'counterparty_id' => $purchase->supplier_id],
            ['account' => $payment->account ?: $this->accountByPaymentMethod($payment->payment_method), 'debit' => 0, 'credit' => $amount, 'description' => 'Supplier payment', 'counterparty_type' => $purchase->supplier_id ? 'supplier' : null, 'counterparty_id' => $purchase->supplier_id],
        ]);
        $payment->journal_entry_id = $journal->id;
        $payment->save();
    }

    public function postInventoryValuationWriteDown(\App\Models\Material $material, float $nrvValue, string $adjustmentDate, ?string $reason = null, ?int $actorId = null): ?\App\Models\InventoryValuationAdjustment
    {
        $this->ensureCoreAccounts();
        return DB::transaction(function () use ($material, $nrvValue, $adjustmentDate, $reason, $actorId): ?\App\Models\InventoryValuationAdjustment {
            $date = Carbon::parse($adjustmentDate)->toDateString();
            $this->assertOpenPeriod($date);
            $locked = \App\Models\Material::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
            $qty = max(0.0, (float) $locked->current_stock);
            $carrying = $this->money($qty * (float) $locked->avg_unit_cost);
            $nrv = $this->money(max(0.0, $nrvValue));
            if ($carrying <= 0 || $nrv >= $carrying - 0.005) return null;
            $adjustment = $this->money($carrying - $nrv);

            $row = \App\Models\InventoryValuationAdjustment::create([
                'material_id' => $locked->id,
                'adjustment_date' => $date,
                'carrying_value_before' => $carrying,
                'nrv_value' => $nrv,
                'adjustment_amount' => $adjustment,
                'type' => 'write_down',
                'status' => 'posted',
                'created_by' => $actorId ?: auth()->id(),
                'reason' => $reason ?: 'Inventory written down to net realizable value.',
            ]);

            $journal = $this->createJournal([
                'entry_date' => $date,
                'description' => 'Inventory NRV write-down - ' . ($locked->name_ar ?: $locked->name),
                'source_type' => 'inventory_valuation_adjustment',
                'source_id' => (string) $row->id,
                'entry_kind' => 'inventory_nrv_write_down',
                'metadata' => ['material_id' => $locked->id, 'carrying_value_before' => $carrying, 'nrv_value' => $nrv, 'adjustment_amount' => $adjustment],
            ], [
                ['account' => $this->account('5260'), 'debit' => $adjustment, 'credit' => 0, 'description' => 'Inventory valuation write-down to NRV'],
                ['account' => $this->account('1200'), 'debit' => 0, 'credit' => $adjustment, 'description' => 'Reduce inventory carrying amount to NRV'],
            ]);

            \App\Models\InventoryMovement::create([
                'material_id' => $locked->id,
                'movement_type' => 'VALUE_ADJUSTMENT',
                'quantity' => 0,
                'unit' => $locked->base_unit,
                'quantity_base' => 0,
                'previous_stock' => $qty,
                'new_stock' => $qty,
                'unit_cost' => $qty > 0 ? round($nrv / $qty, 6) : 0,
                'total_cost' => $adjustment,
                'reference_type' => 'inventory_valuation_adjustment',
                'reference_id' => (string) $row->id,
                'notes' => 'Inventory carrying value reduced to NRV.',
                'meta' => ['delta_value' => -$adjustment, 'carrying_value_before' => $carrying, 'nrv_value' => $nrv],
                'movement_date' => $date . ' 00:00:00',
                'created_by' => $actorId ?: auth()->id(),
            ]);

            $locked->avg_unit_cost = $qty > 0 ? round($nrv / $qty, 6) : 0;
            $locked->save();
            $row->journal_entry_id = $journal->id;
            $row->save();
            return $row->fresh(['material','journal']);
        });
    }

    public function postStockCount(StockCount $stockCount): ?JournalEntry
    {
        $this->ensureCoreAccounts();

        return DB::transaction(function () use ($stockCount): ?JournalEntry {
            $stockCount->loadMissing('items');
            if ($stockCount->journal_entry_id) {
                return $stockCount->journal()->first();
            }

            $date = Carbon::parse($stockCount->count_date)->toDateString();
            $this->assertOpenPeriod($date);
            $lines = [];

            foreach ($stockCount->items as $item) {
                $variance = $this->money($item->variance_qty);
                if (abs($variance) <= 0.005) continue;
                $material = $item->material ?: \App\Models\Material::find($item->material_id);
                $unitCost = $material ? $this->money($material->avg_unit_cost) : 0.0;
                $cost = $this->money(abs($variance) * $unitCost);
                if ($cost <= 0) continue;

                if ($variance > 0) {
                    $lines[] = [
                        'account' => $this->account('1200'), 'debit' => $cost, 'credit' => 0,
                        'description' => 'Inventory increase from physical count: ' . ($material->name ?? ('Material #' . $item->material_id)),
                    ];
                    $lines[] = [
                        'account' => $this->account('4020'), 'debit' => 0, 'credit' => $cost,
                        'description' => 'Inventory count gain',
                    ];
                } else {
                    $lines[] = [
                        'account' => $this->account('5250'), 'debit' => $cost, 'credit' => 0,
                        'description' => 'Inventory shrinkage from physical count: ' . ($material->name ?? ('Material #' . $item->material_id)),
                    ];
                    $lines[] = [
                        'account' => $this->account('1200'), 'debit' => 0, 'credit' => $cost,
                        'description' => 'Inventory decrease from physical count',
                    ];
                }
            }

            if (!$lines) return null;
            $journal = $this->createJournal([
                'entry_date' => $date,
                'description' => 'Physical inventory count ' . ($stockCount->count_code ?: '#' . $stockCount->id),
                'source_type' => 'stock_count',
                'source_id' => (string) $stockCount->id,
                'entry_kind' => 'stock_count_adjustment',
                'metadata' => ['count_code' => $stockCount->count_code],
            ], $lines);

            $stockCount->journal_entry_id = $journal->id;
            $stockCount->save();
            return $journal;
        });
    }

    public function postAssetAcquisition(FixedAsset $asset, ?string $paymentMethod = null, ?float $paidAmount = null): void
    {
        $this->ensureCoreAccounts();
        if ($this->findJournal('fixed_asset', (string) $asset->id, 'acquisition')) return;
        $date = Carbon::parse($asset->purchase_date)->toDateString();
        $this->assertOpenPeriod($date);
        $amount = $this->money($asset->purchase_cost);
        if ($amount <= 0) return;
        $paid = min($amount, max(0.0, $this->money($paidAmount ?? ($paymentMethod && $paymentMethod !== 'credit' ? $amount : 0))));
        $remaining = $this->money($amount - $paid);
        $lines = [['account' => $this->assetAccount($asset), 'debit' => $amount, 'credit' => 0, 'description' => 'Fixed asset acquired']];
        if ($paid > 0) $lines[] = ['account' => $this->accountByPaymentMethod($paymentMethod), 'debit' => 0, 'credit' => $paid, 'description' => 'Asset purchase payment'];
        if ($remaining > 0) $lines[] = ['account' => $this->account('2000'), 'debit' => 0, 'credit' => $remaining, 'description' => 'Asset supplier payable'];
        $this->createJournal([
            'entry_date' => $date,
            'description' => 'Fixed asset acquisition ' . $asset->asset_number,
            'source_type' => 'fixed_asset',
            'source_id' => (string) $asset->id,
            'entry_kind' => 'acquisition',
        ], $lines);
    }

    public function postAssetDisposal(FixedAsset $asset): void
    {
        $this->ensureCoreAccounts();
        if ($this->findJournal('fixed_asset', (string)$asset->id, 'disposal')) return;
        $date = Carbon::parse($asset->disposal_date ?: now())->toDateString();
        $this->assertOpenPeriod($date);
        $cost = $this->money($asset->purchase_cost);
        $accumulated = $this->money(
            (float) ($asset->opening_accumulated_depreciation ?? 0)
            + (float) $asset->depreciationEntries()->where('status', 'posted')->sum('amount')
        );
        $book = max(0.0, $cost - $accumulated);
        $proceeds = max(0.0, $this->money($asset->disposal_value));
        if ($cost <= 0) return;
        $gainLoss = $this->roundMoney($proceeds - $book);
        $lines = [];
        if ($accumulated > 0.005) {
            $lines[] = ['account' => $this->account('1590'), 'debit' => $accumulated, 'credit' => 0, 'description' => 'Remove accumulated depreciation'];
        }
        if ($proceeds > 0) {
            $lines[] = ['account' => $this->account('1000'), 'debit' => $proceeds, 'credit' => 0, 'description' => 'Asset disposal proceeds'];
        }
        $lines[] = ['account' => $this->account('1500'), 'debit' => 0, 'credit' => $cost, 'description' => 'Remove asset cost'];
        if ($gainLoss > 0) {
            $lines[] = ['account' => $this->account('5300'), 'debit' => 0, 'credit' => $gainLoss, 'description' => 'Gain on asset disposal'];
        } elseif ($gainLoss < 0) {
            $lines[] = ['account' => $this->account('5300'), 'debit' => abs($gainLoss), 'credit' => 0, 'description' => 'Loss on asset disposal'];
        }
        $this->createJournal([
            'entry_date' => $date, 'description' => 'Fixed asset disposal ' . $asset->asset_number,
            'source_type' => 'fixed_asset', 'source_id' => (string)$asset->id, 'entry_kind' => 'disposal',
            'metadata' => ['disposal_value' => $proceeds, 'book_value_before_disposal' => $book, 'gain_loss' => $gainLoss],
        ], $lines);
    }

    public function postDepreciation(FixedAssetDepreciationEntry $entry): void
    {
        $this->ensureCoreAccounts();
        if ($entry->journal_entry_id) return;
        $asset = $entry->asset()->firstOrFail();
        $date = Carbon::parse($entry->period_end)->toDateString();
        $this->assertOpenPeriod($date);
        $amount = $this->money($entry->amount);
        if ($amount <= 0) return;
        $journal = $this->createJournal([
            'entry_date' => $date,
            'description' => 'Depreciation ' . $asset->asset_number,
            'source_type' => 'fixed_asset_depreciation',
            'source_id' => (string) $entry->id,
            'entry_kind' => 'depreciation',
        ], [
            ['account' => $this->account('5100'), 'debit' => $amount, 'credit' => 0, 'description' => 'Depreciation expense'],
            ['account' => $this->account('1590'), 'debit' => 0, 'credit' => $amount, 'description' => 'Accumulated depreciation'],
        ]);
        $entry->journal_entry_id = $journal->id;
        $entry->save();
    }

    public function postOpeningBalance(OpeningBalance $openingBalance): JournalEntry
    {
        $this->ensureCoreAccounts();
        return DB::transaction(function () use ($openingBalance): JournalEntry {
            if ($openingBalance->journal_entry_id) return $openingBalance->journal()->firstOrFail();
            $date = $openingBalance->opening_balance_date->toDateString();
            $this->assertOpenPeriod($date);
            $lines = [];
            $assets = 0.0;
            $liabilities = 0.0;

            foreach ($openingBalance->financialAccounts as $row) {
                $amount = $this->money($row->balance_syp);
                if ($amount <= 0) continue;
                $account = $this->findOrCreateOpeningFinancialAccount($row);
                $lines[] = ['account' => $account, 'debit' => $amount, 'credit' => 0, 'description' => 'Opening financial balance: ' . $row->account_name];
                $assets += $amount;
            }
            foreach ($openingBalance->inventory as $row) {
                $amount = $this->money($row->total_value);
                if ($amount <= 0) continue;
                $lines[] = ['account' => $this->account('1200'), 'debit' => $amount, 'credit' => 0, 'description' => 'Opening inventory: ' . $row->material_name];
                $assets += $amount;

                // Synchronize the opening quantity with the same unified inventory ledger
                // used by purchases, consumption, returns and stock counts.
                $material = $row->material_id
                    ? \App\Models\Material::query()->where('is_active', true)->find($row->material_id)
                    : \App\Models\Material::query()
                        ->where('is_active', true)
                        ->where(function ($q) use ($row) {
                            $q->where('name', $row->material_name);
                            if (!empty($row->material_name_ar)) {
                                $q->orWhere('name_ar', $row->material_name_ar);
                            }
                        })
                        ->first();

                if ($material) {
                    $exists = \App\Models\InventoryMovement::where('reference_type', 'opening_balance_inventory')
                        ->where('reference_id', (string) $row->id)
                        ->exists();

                    // Skip the inventory-side movement when the material already
                    // carries this opening quantity from the approved physical
                    // count import (rouh:import-opening-inventory). Posting must
                    // only add the financial value; adding the quantity again
                    // would double the stock.
                    $alreadyImported = \App\Models\InventoryMovement::where('material_id', $material->id)
                        ->whereRaw('LOWER(movement_type) = ?', ['opening_balance'])
                        ->exists();

                    if (!$exists && !$alreadyImported) {
                        // Inventory quantities are physical units and retain six decimals;
                        // only monetary values are rounded to currency precision.
                        $qty = round((float) $row->quantity, 6);
                        $unitCost = $qty > 0 ? round($amount / $qty, 4) : 0.0;
                        $lockedMaterial = \App\Models\Material::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
                        $previousStock = round((float) $lockedMaterial->current_stock, 6);
                        $previousAvgCost = round((float) $lockedMaterial->avg_unit_cost, 4);
                        $newStock = round($previousStock + $qty, 6);
                        $newAvgCost = $newStock > 0
                            ? $this->money((($previousStock * $previousAvgCost) + $amount) / $newStock)
                            : 0.0;

                        \App\Models\InventoryMovement::create([
                            'material_id' => $lockedMaterial->id,
                            'movement_type' => 'OPENING_BALANCE',
                            'quantity' => $qty,
                            'unit' => $lockedMaterial->base_unit,
                            'quantity_base' => $qty,
                            'previous_stock' => $previousStock,
                            'new_stock' => $newStock,
                            'unit_cost' => $unitCost,
                            'total_cost' => $amount,
                            'reference_type' => 'opening_balance_inventory',
                            'reference_id' => (string) $row->id,
                            'notes' => 'Opening inventory balance',
                            'movement_date' => $date . ' 00:00:00',
                            'created_by' => auth()->id(),
                        ]);
                        $lockedMaterial->current_stock = $newStock;
                        $lockedMaterial->avg_unit_cost = $newAvgCost;
                        $lockedMaterial->save();
                    } elseif ($alreadyImported) {
                        // The approved opening import creates the opening ledger movement but
                        // intentionally leaves material balances untouched until confirmation.
                        // At confirmation, synchronize the live material balance to that opening snapshot.
                        $qty = round((float) $row->quantity, 6);
                        $openingUnitCost = $qty > 0 ? round($amount / $qty, 4) : 0.0;
                        $lockedMaterial = \App\Models\Material::query()->whereKey($material->id)->lockForUpdate()->firstOrFail();
                        $lockedMaterial->current_stock = $qty;
                        $lockedMaterial->avg_unit_cost = $openingUnitCost;
                        $lockedMaterial->save();
                    }
                }
            }
            foreach ($openingBalance->fixedAssets as $row) {
                $amount = $this->money($row->total_value);
                if ($amount <= 0) continue;
                $accumulated = min($amount - $this->money($row->salvage_value), max(0.0, $this->money($row->accumulated_depreciation)));
                $netBookValue = $this->money($amount - $accumulated);
                $lines[] = ['account' => $this->account('1500'), 'debit' => $amount, 'credit' => 0, 'description' => 'Opening fixed asset: ' . $row->name_ar];
                if ($accumulated > 0) {
                    $lines[] = ['account' => $this->account('1590'), 'debit' => 0, 'credit' => $accumulated, 'description' => 'Opening accumulated depreciation: ' . $row->name_ar];
                }
                $assets += $netBookValue;
                if (!$row->fixed_asset_id) {
                    $lifeMonths = max(1, (int) $row->useful_life_months);
                    $lifeYears = (int) ceil($lifeMonths / 12);
                    $inServiceDate = $row->in_service_date ? Carbon::parse($row->in_service_date)->toDateString() : $date;
                    $asset = \App\Models\FixedAsset::create([
                        'name' => $row->name, 'name_ar' => $row->name_ar,
                        'asset_number' => 'FA-OB-' . $openingBalance->id . '-' . $row->id,
                        'category' => $row->category, 'description' => 'Recognized from opening balance',
                        'purchase_cost' => $amount, 'purchase_date' => $inServiceDate, 'in_service_date' => $inServiceDate, 'supplier' => null,
                        'invoice_number' => null, 'depreciation_method' => 'straight_line',
                        'useful_life_years' => $lifeYears, 'salvage_value' => min($amount, (float) $row->salvage_value),
                        'opening_accumulated_depreciation' => $accumulated,
                        'depreciation_start_date' => $inServiceDate, 'status' => 'active',
                        'location' => 'ROUH Workshop', 'serial_number' => null,
                        'notes' => trim((string) $row->notes),
                    ]);
                    $row->fixed_asset_id = $asset->id; $row->save();
                }
            }
            foreach ($openingBalance->payablesReceivables as $row) {
                $amount = $this->money($row->amount_syp);
                if ($amount <= 0) continue;
                if ($row->type === 'receivable') {
                    $lines[] = ['account' => $this->account('1100'), 'debit' => $amount, 'credit' => 0, 'description' => 'Opening receivable: ' . $row->party_name];
                    $assets += $amount;
                } else {
                    $lines[] = ['account' => $this->account('2000'), 'debit' => 0, 'credit' => $amount, 'description' => 'Opening payable: ' . $row->party_name];
                    $liabilities += $amount;
                }
            }
            $equity = $this->money($assets - $liabilities);
            if ($equity > 0) {
                $lines[] = ['account' => $this->account('3000'), 'debit' => 0, 'credit' => $equity, 'description' => 'Owner opening capital'];
            } elseif ($equity < 0) {
                $lines[] = ['account' => $this->account('3000'), 'debit' => abs($equity), 'credit' => 0, 'description' => 'Owner opening capital adjustment'];
            }
            $journal = $this->createJournal([
                'entry_date' => $date,
                'description' => 'Opening balance ' . $date,
                'source_type' => 'opening_balance',
                'source_id' => (string) $openingBalance->id,
                'entry_kind' => 'opening',
            ], $lines);
            $openingBalance->journal_entry_id = $journal->id;
            $openingBalance->save();
            return $journal;
        });
    }

    /**
     * When an MTO sale is voided/reissued, payments that were already applied
     * to the old invoice must survive as customer advances until the replacement
     * invoice is posted. The original payment record/journal remains auditable;
     * the settlement is reversed and a new deposit receipt is created.
     */
    public function reclassifySalePaymentsToCustomerDeposits(Sale $sale): void
    {
        if (!$sale->order_id) return;

        $payments = FinancialPayment::query()
            ->where('sale_id', $sale->id)
            ->where('direction', 'receipt')
            ->where('status', 'posted')
            ->lockForUpdate()
            ->get();

        foreach ($payments as $payment) {
            $journal = $payment->journal_entry_id
                ? JournalEntry::find($payment->journal_entry_id)
                : $this->findJournal('financial_payment', (string) $payment->id, 'settlement');
            if ($journal && $journal->status === 'posted') {
                $this->reverseJournal($journal, 'MTO invoice voided/reissued; payment retained as customer advance');
            }

            $deposit = FinancialPayment::create([
                'payment_number' => 'DEP-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(7)),
                'direction' => 'receipt',
                'amount' => $this->money($payment->amount),
                'currency' => $payment->currency ?: self::SYP,
                'exchange_rate' => $payment->exchange_rate ?: 1,
                'amount_base' => $this->money($payment->amount_base),
                'financial_account_id' => $payment->financial_account_id,
                'sale_id' => null,
                'purchase_id' => null,
                'expense_id' => null,
                'order_id' => (string) $sale->order_id,
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
                'notes' => trim(($payment->notes ?: '') . ' | Reclassified as customer deposit after MTO invoice reissue from sale #' . $sale->id . '. Original receipt date: ' . $payment->payment_date),
                'payment_date' => now()->toDateString(),
                'status' => 'posted',
                'created_by' => $payment->created_by,
            ]);
            $this->postOrderDeposit($deposit);

            $payment->status = 'reclassified';
            $payment->notes = trim(($payment->notes ?: '') . ' | Reclassified to deposit #' . $deposit->payment_number . ' because sale #' . $sale->id . ' was voided/reissued.');
            $payment->save();
        }
    }

    public function reverseExistingPostings(string $referenceType, string $referenceId): void
    {
        $kinds = match ($referenceType) {
            'sale' => ['sale_invoice'],
            'purchase' => ['purchase'],
            'expense' => ['expense'],
            default => [],
        };
        foreach ($kinds as $kind) $this->reverseSource($referenceType, $referenceId, $kind);
    }

    public function reverseExistingSalePostings(int $saleId): void
    {
        $this->reverseSource('sale', (string) $saleId, 'sale_invoice');
    }

    public function reverseMaterialConsumptionPosting(string $orderConsumptionId): void
    {
        $this->reverseSource('order_consumption', $orderConsumptionId, 'material_cogs');
    }

    public function postMaterialConsumptionAdjustment(string $adjustmentId, float $deltaCost, string $orderConsumptionId, ?string $orderId = null): void
    {
        if (abs($deltaCost) <= 0.005) return;
        $this->ensureCoreAccounts();
        if ($this->findJournal('consumption_adjustment', $adjustmentId, 'material_cogs_adjustment')) return;
        $cost = $this->money(abs($deltaCost));
        $date = now()->toDateString();
        $this->assertOpenPeriod($date);
        $increase = $deltaCost > 0;
        $journal = $this->createJournal([
            'entry_date' => $date,
            'description' => 'Consumption cost adjustment ' . $adjustmentId,
            'source_type' => 'consumption_adjustment',
            'source_id' => $adjustmentId,
            'entry_kind' => 'material_cogs_adjustment',
            'metadata' => ['order_id' => $orderId, 'order_consumption_id' => $orderConsumptionId, 'delta_cost' => $deltaCost],
        ], $increase ? [
            ['account' => $this->account('5000'), 'debit' => $cost, 'credit' => 0, 'description' => 'Additional material COGS from approved correction'],
            ['account' => $this->account('1200'), 'debit' => 0, 'credit' => $cost, 'description' => 'Additional inventory issue from approved correction'],
        ] : [
            ['account' => $this->account('1200'), 'debit' => $cost, 'credit' => 0, 'description' => 'Inventory restored by approved correction'],
            ['account' => $this->account('5000'), 'debit' => 0, 'credit' => $cost, 'description' => 'COGS reversed by approved correction'],
        ]);
    }

    public function voidSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale): void {
            if ($sale->order_id) {
                // MTO payments must survive invoice reissue as customer advances.
                $this->reclassifySalePaymentsToCustomerDeposits($sale);
            } else {
                // A direct-sale void is treated as a true cash/payment reversal:
                // reverse every posted customer receipt attached to the sale.
                $payments = FinancialPayment::query()
                    ->where('sale_id', $sale->id)
                    ->where('direction', 'receipt')
                    ->where('status', 'posted')
                    ->lockForUpdate()
                    ->get();
                foreach ($payments as $payment) {
                    $journal = $payment->journal_entry_id
                        ? JournalEntry::find($payment->journal_entry_id)
                        : $this->findJournal('financial_payment', (string) $payment->id, 'settlement');
                    if ($journal && $journal->status === 'posted') {
                        $this->reverseJournal($journal, 'Sale voided; customer receipt reversed');
                    }
                    $payment->status = 'refunded';
                    $payment->notes = trim(($payment->notes ?: '') . ' | Receipt reversed because sale #' . $sale->id . ' was voided.');
                    $payment->save();
                }
            }

            $this->reverseSource('sale', (string) $sale->id, 'sale_invoice');
            $sale->sale_status = 'voided';
            $sale->saveQuietly();
        });
    }

    public function createJournal(array $header, array $rawLines): JournalEntry
    {
        return DB::transaction(function () use ($header, $rawLines): JournalEntry {
            $lines = [];
            foreach ($rawLines as $raw) {
                $debit = $this->money($raw['debit'] ?? 0);
                $credit = $this->money($raw['credit'] ?? 0);
                if (($debit > 0) === ($credit > 0)) {
                    throw new RuntimeException('Each journal line must contain either a debit or a credit amount.');
                }
                $lines[] = [
                    'account' => $raw['account'],
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $raw['description'] ?? null,
                    'counterparty_type' => $raw['counterparty_type'] ?? null,
                    'counterparty_id' => $raw['counterparty_id'] ?? null,
                ];
            }

            if (!$lines) throw new RuntimeException('Journal entry must contain at least one line.');
            $totalDebit = $this->roundMoney(array_sum(array_column($lines, 'debit')));
            $totalCredit = $this->roundMoney(array_sum(array_column($lines, 'credit')));
            if (abs($totalDebit - $totalCredit) > 0.005) {
                throw new RuntimeException("Unbalanced journal entry: debit {$totalDebit}, credit {$totalCredit}.");
            }

            $period = AccountingPeriod::query()
                ->whereDate('period_start', '<=', $header['entry_date'])
                ->whereDate('period_end', '>=', $header['entry_date'])
                ->whereIn('status', ['open', 'reopened'])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            if (!$period) {
                $closed = AccountingPeriod::whereDate('period_start', '<=', $header['entry_date'])
                    ->whereDate('period_end', '>=', $header['entry_date'])
                    ->where('status', 'closed')
                    ->exists();
                if ($closed) throw new RuntimeException('The accounting period containing ' . $header['entry_date'] . ' is closed. Reopen it only after an approved correction.');
                throw new RuntimeException('No open accounting period covers ' . $header['entry_date'] . '. Create/open the period before posting.');
            }

            if (!empty($header['source_type']) && !empty($header['source_id']) && !empty($header['entry_kind'])) {
                $existing = $this->findJournal($header['source_type'], (string) $header['source_id'], $header['entry_kind']);
                if ($existing) return $existing->load('lines');
            }

            $entry = JournalEntry::create([
                'journal_number' => $this->nextJournalNumber($header['entry_date']),
                'entry_date' => $header['entry_date'],
                'description' => $header['description'],
                'source_type' => $header['source_type'] ?? null,
                'source_id' => isset($header['source_id']) ? (string) $header['source_id'] : null,
                'entry_kind' => $header['entry_kind'] ?? 'general',
                'status' => 'posted',
                'accounting_period_id' => $period->id,
                'reversal_of_id' => $header['reversal_of_id'] ?? null,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
                'created_by' => auth()->id(),
                'metadata' => $header['metadata'] ?? null,
            ]);

            foreach ($lines as $line) {
                /** @var FinancialAccount $account */
                $account = FinancialAccount::whereKey($line['account']->id)->lockForUpdate()->firstOrFail();
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'financial_account_id' => $account->id,
                    'description' => $line['description'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'currency' => self::SYP,
                    'exchange_rate' => 1,
                    'base_debit' => $line['debit'],
                    'base_credit' => $line['credit'],
                    'counterparty_type' => $line['counterparty_type'],
                    'counterparty_id' => $line['counterparty_id'],
                ]);

                $this->applyAccountBalance($account, $line['debit'], $line['credit']);
                $this->mirrorLegacyTransaction($entry, $account, $line['debit'], $line['credit'], $line['description']);
            }

            return $entry->load('lines');
        });
    }

    public function reverseJournal(JournalEntry $entry, ?string $reason = null): JournalEntry
    {
        return DB::transaction(function () use ($entry, $reason): JournalEntry {
            if ($entry->status === 'reversed') {
                $existing = $entry->reversedBy()->where('entry_kind', 'reversal')->latest('id')->first();
                if ($existing) return $existing;
                throw new RuntimeException('Journal is already reversed.');
            }
            $reversal = $entry->reversedBy()->where('entry_kind', 'reversal')->first();
            if ($reversal) return $reversal;
            $lines = $entry->lines()->with('account')->get()->map(function (JournalLine $line) {
                return ['account' => $line->account, 'debit' => (float)$line->credit, 'credit' => (float)$line->debit, 'description' => 'Reversal: ' . ($line->description ?? '') , 'counterparty_type' => $line->counterparty_type, 'counterparty_id' => $line->counterparty_id];
            })->all();
            $reversal = $this->createJournal([
                'entry_date' => now()->toDateString(),
                'description' => 'Reversal of ' . $entry->journal_number . ($reason ? ' — ' . $reason : ''),
                'source_type' => 'journal_entry',
                'source_id' => (string)$entry->id,
                'entry_kind' => 'reversal',
                'reversal_of_id' => $entry->id,
                'metadata' => ['reversal_of' => $entry->id, 'reason' => $reason],
            ], $lines);
            $entry->update(['status' => 'reversed']);
            return $reversal;
        });
    }

    public function trialBalance(?string $startDate = null, ?string $endDate = null): array
    {
        $query = JournalLine::query()->whereHas('entry', function ($q) use ($startDate, $endDate) {
            $q->whereIn('status', ['posted', 'reversed']);
            if ($startDate) $q->whereDate('entry_date', '>=', $startDate);
            if ($endDate) $q->whereDate('entry_date', '<=', $endDate);
        })->with('account:id,code,name,name_ar,account_group,normal_balance');

        $rows = [];
        foreach ($query->get()->groupBy('financial_account_id') as $accountId => $lines) {
            $account = $lines->first()->account;
            $debit = $this->roundMoney($lines->sum(fn ($l) => (float)$l->base_debit));
            $credit = $this->roundMoney($lines->sum(fn ($l) => (float)$l->base_credit));
            $rows[] = ['account_id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'name_ar' => $account->name_ar, 'account_group' => $account->account_group, 'normal_balance' => $account->normal_balance, 'debit' => $debit, 'credit' => $credit, 'balance' => $account->normal_balance === 'credit' ? $this->roundMoney($credit - $debit) : $this->roundMoney($debit - $credit)];
        }
        usort($rows, fn ($a, $b) => strcmp((string)$a['code'], (string)$b['code']));
        return ['rows' => $rows, 'total_debit' => $this->roundMoney(array_sum(array_column($rows, 'debit'))), 'total_credit' => $this->roundMoney(array_sum(array_column($rows, 'credit'))), 'difference' => $this->roundMoney(array_sum(array_column($rows, 'debit')) - array_sum(array_column($rows, 'credit')))];
    }

    public function financialStatements(?string $startDate = null, ?string $endDate = null): array
    {
        $pnlTb = $this->trialBalance($startDate, $endDate);
        $balanceTb = $this->trialBalance(null, $endDate);
        $pnl = ['revenue' => 0.0, 'other_income' => 0.0, 'cogs' => 0.0, 'expenses' => 0.0, 'net_profit' => 0.0];
        $bs = ['assets' => 0.0, 'liabilities' => 0.0, 'equity' => 0.0];

        foreach ($pnlTb['rows'] as $row) {
            switch ($row['account_group']) {
                case 'revenue': $pnl['revenue'] += $row['balance']; break;
                case 'other_income': $pnl['other_income'] += $row['balance']; break;
                case 'cogs': $pnl['cogs'] += $row['balance']; break;
                case 'expense': $pnl['expenses'] += $row['balance']; break;
            }
        }
        $cumulativeNetProfit = 0.0;
        foreach ($balanceTb['rows'] as $row) {
            switch ($row['account_group']) {
                case 'revenue': $cumulativeNetProfit += $row['balance']; break;
                case 'other_income': $cumulativeNetProfit += $row['balance']; break;
                case 'cogs': $cumulativeNetProfit -= $row['balance']; break;
                case 'expense': $cumulativeNetProfit -= $row['balance']; break;
            }
        }
        $cumulativeNetProfit = $this->roundMoney($cumulativeNetProfit);
        foreach ($balanceTb['rows'] as $row) {
            switch ($row['account_group']) {
                case 'asset':
                    $bs['assets'] += $row['balance'];
                    break;
                case 'contra_asset':
                    $bs['assets'] -= $row['balance'];
                    break;
                case 'liability':
                    $bs['liabilities'] += $row['balance'];
                    break;
                case 'equity':
                    $bs['equity'] += $row['balance'];
                    break;
            }
        }
        $pnl['net_profit'] = $this->roundMoney($pnl['revenue'] + $pnl['other_income'] - $pnl['cogs'] - $pnl['expenses']);
        // The balance sheet is cumulative through endDate. Because ROUH does not
        // automatically post year-end closing entries, all historical nominal-account
        // balances must be represented in equity here, while P&L remains period-specific.
        $bs['equity'] = $this->roundMoney($bs['equity'] + $cumulativeNetProfit);
        $bs['balance_difference'] = $this->roundMoney($bs['assets'] - $bs['liabilities'] - $bs['equity']);
        $cashFlow = $this->cashFlowStatement($startDate, $endDate);
        return ['trial_balance' => $pnlTb, 'balance_sheet_trial_balance' => $balanceTb, 'profit_and_loss' => $pnl, 'balance_sheet' => $bs, 'cash_flow' => $cashFlow];
    }

    public function recognizeSale(Sale $sale, ?string $recognitionDate = null): void
    {
        $fresh = Sale::query()->lockForUpdate()->findOrFail($sale->id);
        if (($fresh->sale_status ?? 'active') === 'voided') {
            throw new RuntimeException('Voided sales cannot be recognized.');
        }
        if (($fresh->revenue_status ?? 'recognized') === 'recognized' && $this->findJournal('sale', (string)$fresh->id, 'sale_invoice')) {
            return;
        }
        $this->postSale($fresh, $recognitionDate ?: now()->toDateString());
    }

    public function cashFlowStatement(?string $startDate = null, ?string $endDate = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->toDateString() : null;
        $end = $endDate ? Carbon::parse($endDate)->toDateString() : now()->toDateString();
        $cashAccountIds = FinancialAccount::query()
            ->where('is_active', true)
            ->whereIn('account_type', ['cash', 'bank', 'payment_gateway'])
            ->pluck('id');

        $query = JournalEntry::query()
            ->whereIn('status', ['posted','reversed'])
            ->whereHas('lines', fn($q) => $q->whereIn('financial_account_id', $cashAccountIds))
            ->with(['lines.account:id,code,account_group']);
        if ($start) $query->whereDate('entry_date', '>=', $start);
        if ($end) $query->whereDate('entry_date', '<=', $end);

        $operating = 0.0; $investing = 0.0; $financing = 0.0;
        foreach ($query->orderBy('entry_date')->orderBy('id')->get() as $entry) {
            $cashLines = $entry->lines->whereIn('financial_account_id', $cashAccountIds->all());
            $net = round($cashLines->sum(fn($l) => (float)$l->base_debit - (float)$l->base_credit), 2);
            if (abs($net) <= 0.005) continue;
            $nonCash = $entry->lines->whereNotIn('financial_account_id', $cashAccountIds->all());
            $groups = $nonCash->map(fn($l) => (string)($l->account?->account_group))->unique()->values()->all();
            $codes = $nonCash->map(fn($l) => (string)($l->account?->code))->all();
            if (in_array('1500', $codes, true) || in_array('1590', $codes, true) || in_array('5300', $codes, true)) {
                $investing += $net;
            } elseif (in_array('equity', $groups, true) || in_array($entry->entry_kind, ['capital','owner_contribution','owner_drawings','financing'], true)) {
                $financing += $net;
            } else {
                $operating += $net;
            }
        }

        $openingDate = $start ? Carbon::parse($start)->subDay()->toDateString() : null;
        $opening = 0.0;
        if ($openingDate) {
            $openingRows = $this->trialBalance(null, $openingDate)['rows'];
            foreach ($openingRows as $row) {
                if (in_array($row['code'], ['1000','1010','1020'], true)) $opening += (float)$row['balance'];
            }
        }
        $closingRows = $this->trialBalance(null, $end)['rows'];
        $closing = 0.0;
        foreach ($closingRows as $row) {
            if (in_array($row['code'], ['1000','1010','1020'], true)) $closing += (float)$row['balance'];
        }
        return [
            'operating' => round($operating, 2),
            'investing' => round($investing, 2),
            'financing' => round($financing, 2),
            'net_change' => round($operating + $investing + $financing, 2),
            'opening_cash_and_equivalents' => round($opening, 2),
            'closing_cash_and_equivalents' => round($closing, 2),
            'reconciliation_difference' => round($closing - $opening - ($operating + $investing + $financing), 2),
        ];
    }

    public function periodForDate($date): ?AccountingPeriod
    {
        return AccountingPeriod::whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date)
            ->whereIn('status', ['open', 'reopened'])
            ->orderByDesc('id')
            ->first();
    }

    public function assertOpenPeriod($date): AccountingPeriod
    {
        $period = $this->periodForDate($date);
        if ($period) return $period;
        $closed = AccountingPeriod::whereDate('period_start', '<=', $date)->whereDate('period_end', '>=', $date)->where('status', 'closed')->exists();
        if ($closed) throw new RuntimeException('The accounting period containing ' . $date . ' is closed. Reopen it only after an approved correction.');
        throw new RuntimeException('No open accounting period covers ' . $date . '.');
    }

    private function findJournal(string $sourceType, string $sourceId, string $kind): ?JournalEntry
    {
        return JournalEntry::where('source_type', $sourceType)->where('source_id', $sourceId)->where('entry_kind', $kind)->first();
    }

    private function reverseSource(string $sourceType, string $sourceId, string $kind): void
    {
        $entry = $this->findJournal($sourceType, $sourceId, $kind);
        if ($entry) $this->reverseJournal($entry, 'Source document voided/changed');
    }

    private function assertSaleMatchesJournal(Sale $sale, JournalEntry $entry): void
    {
        $expected = $this->money($sale->total_price_syp ?? $sale->total_price);
        $postedProductRevenue = $this->money($entry->lines()->whereHas('account', fn($q) => $q->where('code', '4000'))->sum('credit'));
        $postedShippingRevenue = $this->money($entry->lines()->whereHas('account', fn($q) => $q->where('code', '4010'))->sum('credit'));
        $postedTax = $this->money($entry->lines()->whereHas('account', fn($q) => $q->where('code', '2100'))->sum('credit'));
        $posted = $this->money($postedProductRevenue + $postedShippingRevenue + $postedTax);
        $metadata = (array) ($entry->metadata ?? []);
        $expectedGross = $expected;
        if (!empty($metadata['tax_configuration_id']) && (($metadata['tax_inclusive'] ?? true) === false)) {
            $expectedGross = $this->money($expected + $postedTax);
        }
        if (abs($expectedGross - $posted) > 0.005) throw new RuntimeException('Posted sale cannot be modified. Void the posted sale and create a corrected document.');
    }

    private function hasOrderConsumption(Sale $sale): bool
    {
        if (!$sale->order_id) return false;
        return DB::table('order_consumptions')->where('order_id', $sale->order_id)->where('status', '!=', 'cancelled')->exists();
    }

    private function account(string $code): FinancialAccount
    {
        return FinancialAccount::where('code', $code)->where('is_active', true)->lockForUpdate()->firstOrFail();
    }

    private function accountByPaymentMethod(?string $method): FinancialAccount
    {
        return match ($this->normalizePaymentMethod($method)) {
            'cash', 'cash_on_delivery' => $this->account('1000'),
            'sham_cash' => $this->account('1020'),
            'bank_transfer' => $this->account('1010'),
            default => throw new RuntimeException('Unsupported payment method. Use cash, cash_on_delivery, sham_cash, bank_transfer, or credit where applicable.'),
        };
    }

    private function expenseAccount(?string $category): FinancialAccount
    {
        $normalized = strtolower(trim((string)$category));
        return match (true) {
            str_contains($normalized, 'rent') || str_contains($normalized, 'إيجار') => $this->account('5200'),
            str_contains($normalized, 'salary') || str_contains($normalized, 'wage') || str_contains($normalized, 'رواتب') => $this->account('5210'),
            str_contains($normalized, 'utilit') || str_contains($normalized, 'electric') || str_contains($normalized, 'فواتير') => $this->account('5220'),
            str_contains($normalized, 'market') || str_contains($normalized, 'تسويق') => $this->account('5230'),
            str_contains($normalized, 'transport') || str_contains($normalized, 'مواصل') => $this->account('5240'),
            default => $this->account('5290'),
        };
    }

    private function assetAccount(FixedAsset $asset): FinancialAccount
    {
        return $this->account('1500');
    }

    private function normalizePaymentMethod(?string $method): string
    {
        return str_replace(['-', ' '], '_', strtolower(trim((string)$method)));
    }

    private function money($value): float { return round((float)$value, 2); }
    private function roundMoney($value): float { return round((float)$value, 2); }

    private function nextJournalNumber(string $date): string
    {
        $prefix = 'JE-' . Carbon::parse($date)->format('Ymd') . '-';
        $last = JournalEntry::where('journal_number', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('journal_number');
        $next = $last ? ((int) substr($last, -6) + 1) : 1;
        return $prefix . str_pad((string) $next, 6, '0', STR_PAD_LEFT);
    }

    private function applyAccountBalance(FinancialAccount $account, float $debit, float $credit): void
    {
        $account = FinancialAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
        $delta = $account->normal_balance === 'credit' ? ($credit - $debit) : ($debit - $credit);
        $account->current_balance = $this->money($account->current_balance) + $delta;
        $account->save();
    }

    private function mirrorLegacyTransaction(JournalEntry $entry, FinancialAccount $account, float $debit, float $credit, ?string $description): void
    {
        $direction = $account->normal_balance === 'credit'
            ? ($credit > 0 ? 'in' : 'out')
            : ($debit > 0 ? 'in' : 'out');
        $amount = $debit > 0 ? $debit : $credit;
        FinancialTransaction::create([
            'txn_code' => 'GL-' . $entry->journal_number . '-' . Str::lower(Str::random(5)),
            'transaction_type' => $entry->entry_kind,
            'direction' => $direction,
            'amount' => $amount,
            'currency' => self::SYP,
            'exchange_rate' => 1,
            'amount_base' => $amount,
            'account_id' => $account->id,
            'accounting_period_id' => $entry->accounting_period_id,
            'reference_type' => 'journal_entry',
            'reference_id' => (string)$entry->id,
            'description' => $description ?: $entry->description,
            'meta' => ['journal_number' => $entry->journal_number],
            'transaction_date' => $entry->entry_date->copy()->endOfDay(),
            'created_by' => auth()->id(),
        ]);
    }

    public function openingFinancialAccountFor($row): FinancialAccount
    {
        $this->ensureCoreAccounts();
        return $this->findOrCreateOpeningFinancialAccount($row);
    }

    public function postOpeningBalanceAdjustment(\App\Models\OpeningBalanceAdjustment $adjustment, string $type, float $delta): ?JournalEntry
    {
        if (abs($delta) <= 0.005) {
            return null;
        }

        $this->ensureCoreAccounts();
        $date = now()->toDateString();
        $this->assertOpenPeriod($date);

        $assetAccount = match ($type) {
            'inventory' => $this->account('1200'),
            'financial' => $this->openingFinancialAccountFor(
                \App\Models\OpeningBalanceFinancialAccount::findOrFail($adjustment->reference_id)
            ),
            'receivable' => $this->account('1100'),
            'payable' => $this->account('2000'),
            default => throw new RuntimeException('Unsupported opening-balance adjustment type.'),
        };

        $amount = $this->money(abs($delta));
        $increase = $delta > 0;
        if ($type === 'payable') {
            $lines = $increase
                ? [
                    ['account' => $this->account('3000'), 'debit' => $amount, 'credit' => 0, 'description' => 'Opening payable adjustment'],
                    ['account' => $assetAccount, 'debit' => 0, 'credit' => $amount, 'description' => 'Opening payable increase'],
                ]
                : [
                    ['account' => $assetAccount, 'debit' => $amount, 'credit' => 0, 'description' => 'Opening payable reduction'],
                    ['account' => $this->account('3000'), 'debit' => 0, 'credit' => $amount, 'description' => 'Opening equity adjustment'],
                ];
        } else {
            $lines = $increase
                ? [
                    ['account' => $assetAccount, 'debit' => $amount, 'credit' => 0, 'description' => 'Opening balance adjustment'],
                    ['account' => $this->account('3000'), 'debit' => 0, 'credit' => $amount, 'description' => 'Owner opening capital'],
                ]
                : [
                    ['account' => $this->account('3000'), 'debit' => $amount, 'credit' => 0, 'description' => 'Opening balance equity reduction'],
                    ['account' => $assetAccount, 'debit' => 0, 'credit' => $amount, 'description' => 'Opening balance adjustment'],
                ];
        }

        return $this->createJournal([
            'entry_date' => $date,
            'description' => 'Approved opening balance adjustment #' . $adjustment->id,
            'source_type' => 'opening_balance_adjustment',
            'source_id' => (string) $adjustment->id,
            'entry_kind' => 'opening_adjustment',
            'metadata' => ['adjustment_id' => $adjustment->id, 'adjustment_type' => $type, 'delta' => $delta],
        ], $lines);
    }

    private function findOrCreateOpeningFinancialAccount($row): FinancialAccount
    {
        if (!empty($row->financial_account_id)) {
            $linked = FinancialAccount::query()
                ->whereKey($row->financial_account_id)
                ->where('is_active', true)
                ->where('account_group', 'asset')
                ->first();
            if (!$linked) {
                throw new RuntimeException('The selected opening financial account is inactive or invalid.');
            }
            return $linked;
        }

        $type = $row->account_type;
        $code = match ($type) {
            'cash' => '1000',
            'bank' => '1010',
            'payment_gateway' => '1020',
            default => null,
        };
        if ($code) return $this->account($code);
        return FinancialAccount::firstOrCreate(
            ['name' => $row->account_name],
            ['name_ar' => $row->account_name_ar, 'account_type' => $type, 'currency' => self::SYP, 'opening_balance' => 0, 'current_balance' => 0, 'is_active' => true, 'account_group' => 'asset', 'normal_balance' => 'debit']
        )->fresh();
    }
}
