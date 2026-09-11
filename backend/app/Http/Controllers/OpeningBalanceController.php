<?php

namespace App\Http\Controllers;

use App\Models\OpeningBalance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\FinancePostingService;

class OpeningBalanceController extends Controller
{
    public function index()
    {
        $openingBalances = OpeningBalance::with([
                'creator', 'confirmer', 'inventory', 'financialAccounts.financialAccount', 'payablesReceivables', 'fixedAssets'
            ])
            ->orderBy('opening_balance_date', 'desc')
            ->get()
            ->map(function (OpeningBalance $opening): OpeningBalance {
                // Keep draft balances immediately visible in the admin UI without
                // waiting for the final posting totals. Confirmed totals remain
                // stored on the opening-balance header for audit/reporting.
                $opening->setAttribute('draft_inventory_value', (float) $opening->inventory->sum('total_value'));
                $opening->setAttribute('draft_fixed_assets_value', (float) $opening->fixedAssets->sum('total_value'));
                $opening->setAttribute('draft_fixed_assets_accumulated_depreciation', (float) $opening->fixedAssets->sum('accumulated_depreciation'));
                $opening->setAttribute('draft_fixed_assets_net_book_value', max(0.0, (float) $opening->fixedAssets->sum('total_value') - (float) $opening->fixedAssets->sum('accumulated_depreciation')));
                $netFixedAssets = max(0.0, (float) $opening->fixedAssets->sum('total_value') - (float) $opening->fixedAssets->sum('accumulated_depreciation'));
                $financialAssets = (float) $opening->financialAccounts->sum('balance_syp');
                $receivables = (float) $opening->payablesReceivables->where('type','receivable')->sum('amount_syp');
                $payables = (float) $opening->payablesReceivables->where('type','payable')->sum('amount_syp');
                $opening->setAttribute('draft_total_assets', (float) $opening->inventory->sum('total_value') + $netFixedAssets + $financialAssets + $receivables);
                $opening->setAttribute('draft_owner_capital', (float) $opening->inventory->sum('total_value') + $netFixedAssets + $financialAssets + $receivables - $payables);
                $opening->setAttribute('draft_cash_balance', (float) $opening->financialAccounts->where('account_type', 'cash')->sum('balance_syp'));
                $opening->setAttribute('draft_bank_balance', (float) $opening->financialAccounts->where('account_type', 'bank')->sum('balance_syp'));
                $opening->setAttribute('draft_payables', (float) $opening->payablesReceivables->where('type', 'payable')->sum('amount_syp'));
                $opening->setAttribute('draft_receivables', (float) $opening->payablesReceivables->where('type', 'receivable')->sum('amount_syp'));
                return $opening;
            });

        return response()->json($openingBalances);
    }

    public function show($id)
    {
        $openingBalance = OpeningBalance::with([
            'inventory',
            'fixedAssets',
            'financialAccounts.financialAccount',
            'payablesReceivables',
            'adjustments.adjustedBy',
            'adjustments.approvedBy',
            'creator',
            'confirmer'
        ])->findOrFail($id);

        return response()->json($openingBalance);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'opening_balance_date' => ['required','date','unique:opening_balances,opening_balance_date'],
            'notes' => 'nullable|string',
        ]);
        try {
            $actorId = (int) ($request->attributes->get('authUser')?->id ?? auth()->id() ?? 0);
            if ($actorId <= 0) throw new \RuntimeException('Authenticated user context is required.');
            $openingBalance = OpeningBalance::create([
                'opening_balance_date' => $validated['opening_balance_date'], 'status' => 'draft',
                'created_by' => $actorId, 'notes' => $validated['notes'] ?? null,
            ]);
            return response()->json($openingBalance, 201);
        } catch (\Throwable $e) {
            report($e); return response()->json(['error' => 'Failed to create opening balance'], 500);
        }
    }

    public function importGoLiveData(Request $request, $id)
    {
        $opening = OpeningBalance::with(['inventory','fixedAssets'])->findOrFail($id);
        if (!$opening->canBeEdited()) return response()->json(['error'=>'Opening balance is no longer editable'], 403);
        try {
            DB::transaction(function () use ($opening): void {
                $inventoryPath = database_path('seeders/data/rouh_opening_inventory.csv');
                $assetsPath = database_path('seeders/data/rouh_opening_assets.csv');
                $inventory = $this->readApprovedInventoryCsv($inventoryPath);
                $materials = \App\Models\Material::whereIn('name_ar', array_keys($inventory))->where('is_active',true)->get()->keyBy('name_ar');

                // The approved physical count is authoritative for opening stock.
                // If a material exists in the count but is absent from the catalog,
                // create a minimal master record from the count instead of rejecting
                // the whole import. Never post it as a purchase or supplier payable.
                foreach ($inventory as $row) {
                    $material = $materials->get($row['name_ar']);
                    if (!$material) {
                        $material = \App\Models\Material::create([
                            'code' => 'OPEN-' . strtoupper(substr(md5($row['name_ar']), 0, 10)),
                            'name' => $row['name_ar'],
                            'name_ar' => $row['name_ar'],
                            'material_category' => $row['material_category'] ?? 'other',
                            'subcategory' => $row['subcategory'] ?? null,
                            'base_unit' => $row['base_unit'],
                            'track_fractional' => $row['base_unit'] !== 'pcs',
                            'min_stock' => 0,
                            'currency' => 'SYP',
                            'exchange_rate' => 1,
                            'supplier_name' => $row['supplier'] ?? null,
                            'notes' => 'Created from approved physical opening count; no purchase transaction created.',
                            'current_stock' => 0,
                            'avg_unit_cost' => 0,
                            'is_active' => true,
                        ]);
                        $materials->put($row['name_ar'], $material);
                    }

                    // Packaging/container rows such as "حنجور زيت 12 جرام" are
                    // counted by piece; the gram value belongs to the container name,
                    // not its inventory unit. For existing catalog data, correct this
                    // deterministic packaging classification instead of rejecting a
                    // valid physical count.
                    $expectedUnit = $row['base_unit'];
                    if (($row['material_category'] ?? null) === 'packaging' && $expectedUnit === 'pcs' && $material->base_unit !== 'pcs') {
                        $material->update([
                            'material_category' => 'packaging',
                            'base_unit' => 'pcs',
                            'track_fractional' => false,
                            'subcategory' => $row['subcategory'] ?? $material->subcategory,
                        ]);
                    }

                    if ($material->base_unit !== $expectedUnit) {
                        throw new \RuntimeException('وحدة غير متطابقة للمادة '.$row['name_ar'].': الملف='.$expectedUnit.'، النظام='.$material->base_unit);
                    }
                }

                $opening->inventory()->delete();
                foreach ($inventory as $row) {
                    $m=$materials[$row['name_ar']];
                    $opening->inventory()->create([
                        'material_id'=>$m->id,'material_name'=>$m->name,'material_name_ar'=>$m->name_ar,
                        'material_type'=>$this->openingMaterialType($m->material_category, $m->subcategory),'unit'=>$m->base_unit,
                        'quantity'=>$row['quantity'],'unit_cost'=>$row['unit_cost'],
                        'notes'=>'Imported from approved physical opening count. Source category='.$row['material_category'].'.','currency'=>'SYP','exchange_rate'=>1,
                        'supplier'=>$row['supplier']
                    ]);
                }

                $opening->fixedAssets()->delete();
                if (is_file($assetsPath) && ($fh=fopen($assetsPath,'r')) !== false) {
                    $header=array_map('trim', fgetcsv($fh) ?: []); $map=array_flip($header);
                    while (($r=fgetcsv($fh)) !== false) {
                        if (!array_filter($r,fn($v)=>trim((string)$v)!=='')) continue;
                        $nameAr=trim((string)$r[$map['name_ar']]); $cat=trim((string)$r[$map['category']]);
                        $qty=(float)$r[$map['quantity']]; $unit=(float)$r[$map['purchase_cost']]; $life=(int)$r[$map['useful_life_months']];
                        if ($nameAr==='' || $qty<=0 || $unit<0 || $life<=0) throw new \RuntimeException('Invalid opening fixed asset row: '.$nameAr);
                        $inService = isset($map['in_service_date']) && trim((string)($r[$map['in_service_date']] ?? '')) !== '' ? trim((string)$r[$map['in_service_date']]) : $opening->opening_balance_date;
                        $salvage = isset($map['salvage_value']) ? max(0, (float)($r[$map['salvage_value']] ?? 0)) : 0;
                        $accum = isset($map['accumulated_depreciation']) ? max(0, (float)($r[$map['accumulated_depreciation']] ?? 0)) : 0;
                        if ($accum > max(0, $qty*$unit-$salvage)) $accum = max(0, $qty*$unit-$salvage);
                        $opening->fixedAssets()->create(['name'=>$nameAr,'name_ar'=>$nameAr,'category'=>$cat?:'equipment','quantity'=>$qty,'unit_cost'=>$unit,'useful_life_months'=>$life,'in_service_date'=>$inService,'salvage_value'=>$salvage,'accumulated_depreciation'=>$accum,'notes'=>'Operational tools included in owner opening capital.']);
                    }
                    fclose($fh);
                }
            });
            $fresh=$opening->fresh(['inventory','fixedAssets']);
            return response()->json([
                'ok'=>true,'inventory_count'=>$fresh->inventory->count(),'inventory_value'=>(float)$fresh->inventory->sum('total_value'),
                'fixed_asset_count'=>$fresh->fixedAssets->count(),'fixed_asset_value'=>(float)$fresh->fixedAssets->sum('total_value'),
                'total_opening_assets'=>(float)$fresh->inventory->sum('total_value')+(float)$fresh->fixedAssets->sum('total_value'),
                'inventory'=>$fresh->inventory,'fixed_assets'=>$fresh->fixedAssets,
            ]);
        } catch (\Throwable $e) { report($e); return response()->json(['ok'=>false,'error'=>$e->getMessage()],422); }
    }

    private function readApprovedInventoryCsv(string $path): array
    {
        if (!is_file($path)) throw new \RuntimeException('Approved opening inventory file not found.');
        $fh=fopen($path,'r'); $header=array_map('trim', fgetcsv($fh) ?: []); $map=array_flip($header);
        foreach (['name_ar','current_stock','avg_unit_cost','supplier_name','base_unit'] as $required) if (!array_key_exists($required,$map)) throw new \RuntimeException('Missing approved inventory column: '.$required);
        $out=[];
        $duplicateNames=[];
        while (($r=fgetcsv($fh))!==false) {
            if (!array_filter($r,fn($v)=>trim((string)$v)!=='')) continue;
            $name=trim((string)$r[$map['name_ar']]); $qty=(float)$r[$map['current_stock']]; $cost=(float)$r[$map['avg_unit_cost']];
            $baseUnit=trim((string)$r[$map['base_unit']]);
            $category=isset($map['material_category']) ? trim((string)$r[$map['material_category']]) : null;
            if ($name==='' || $qty<0 || $cost<0 || $baseUnit==='') throw new \RuntimeException('Invalid inventory row: '.$name);
            if (isset($out[$name])) $duplicateNames[]=$name;
            $out[$name]=['name_ar'=>$name,'quantity'=>$qty,'unit_cost'=>$cost,'supplier'=>trim((string)$r[$map['supplier_name']]) ?: null,'base_unit'=>$baseUnit,'material_category'=>$category];
        }
        fclose($fh);
        if ($duplicateNames) throw new \RuntimeException('Duplicate material names in approved inventory file: '.implode('، ', array_values(array_unique($duplicateNames))));
        if (!$out) throw new \RuntimeException('Approved inventory file contains no rows.');
        return $out;
    }

    public function update(Request $request, $id)
    {
        $openingBalance = OpeningBalance::findOrFail($id);

        if (!$openingBalance->canBeEdited()) {
            return response()->json(['error' => 'Opening balance cannot be edited'], 403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $openingBalance->update($validated);

        return response()->json($openingBalance);
    }

    public function confirm(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            /** @var OpeningBalance $openingBalance */
            $openingBalance = OpeningBalance::query()->lockForUpdate()->findOrFail($id);
            if ($openingBalance->isConfirmed()) {
                DB::rollBack();
                return response()->json(['error' => 'Opening balance already confirmed'], 403);
            }

            $actorId = $request->attributes->get('authUser')?->id ?? auth()->id();
            if (!$actorId) {
                throw new \RuntimeException('Authenticated user context is required.');
            }

            // Calculate totals from the locked opening-balance snapshot.
            $totalInventoryValue = $openingBalance->inventory()->sum('total_value');
            $totalFixedAssetsValue = $openingBalance->fixedAssets()->sum('total_value');
            $totalCashBalance = $openingBalance->financialAccounts()
                ->where('account_type', 'cash')
                ->sum('balance_syp');
            $totalBankBalance = $openingBalance->financialAccounts()
                ->where('account_type', 'bank')
                ->sum('balance_syp');
            $totalPayables = $openingBalance->payablesReceivables()
                ->where('type', 'payable')
                ->sum('amount_syp');
            $totalReceivables = $openingBalance->payablesReceivables()
                ->where('type', 'receivable')
                ->sum('amount_syp');

            $openingBalance->update([
                'status' => 'confirmed',
                'confirmed_by' => $actorId,
                'confirmed_at' => now(),
                'total_inventory_value' => $totalInventoryValue,
                'total_fixed_assets_value' => $totalFixedAssetsValue,
                'total_cash_balance' => $totalCashBalance,
                'total_bank_balance' => $totalBankBalance,
                'total_payables' => $totalPayables,
                'total_receivables' => $totalReceivables,
            ]);

            $journal = app(FinancePostingService::class)->postOpeningBalance($openingBalance->fresh(['inventory','financialAccounts','payablesReceivables','fixedAssets']));
            $openingBalance->update(['status' => 'confirmed', 'journal_entry_id' => $journal->id]);

            DB::commit();

            return response()->json($openingBalance->fresh(['journal']));
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return response()->json(['error' => 'Failed to confirm opening balance'], 500);
        }
    }

    public function lock(Request $request, $id)
    {
        $openingBalance = OpeningBalance::findOrFail($id);

        if (!$openingBalance->isConfirmed()) {
            return response()->json(['error' => 'Opening balance must be confirmed before locking'], 403);
        }

        $openingBalance->update(['status' => 'locked']);

        return response()->json($openingBalance);
    }

    public function destroy($id)
    {
        $openingBalance = OpeningBalance::findOrFail($id);

        if ($openingBalance->isConfirmed()) {
            return response()->json(['error' => 'Cannot delete confirmed opening balance'], 403);
        }

        $openingBalance->delete();

        return response()->json(['message' => 'Opening balance deleted']);
    }

    // Inventory Items
    public function addInventoryItem(Request $request, $id)
    {
        $openingBalance = OpeningBalance::findOrFail($id);

        if (!$openingBalance->canBeEdited()) {
            return response()->json(['error' => 'Cannot edit confirmed opening balance'], 403);
        }

        $validated = $request->validate([
            'material_id' => 'nullable|integer|exists:materials,id',
            'material_name' => 'required|string',
            'material_name_ar' => 'nullable|string',
            'material_type' => 'required|in:perfume_oil,alcohol,bottle,cap,sprayer,box,shopping_bag,other_packaging,other_raw_material',
            'unit' => 'required|string',
            'quantity' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'currency' => 'required|string|in:SYP,USD',
            'exchange_rate' => 'required|numeric|min:0',
            'supplier' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if (!empty($validated['material_id'])) {
            $material = \App\Models\Material::findOrFail($validated['material_id']);
            $validated['material_name'] = $material->name;
            $validated['material_name_ar'] = $material->name_ar;
            $validated['material_type'] = $this->openingMaterialType($material->material_category);
            $validated['unit'] = $material->base_unit;
        }

        $inventoryItem = $openingBalance->inventory()->create($validated);

        return response()->json($inventoryItem, 201);
    }

    public function updateInventoryItem(Request $request, $openingBalanceId, $itemId)
    {
        $openingBalance = OpeningBalance::findOrFail($openingBalanceId);

        if (!$openingBalance->canBeEdited()) {
            return response()->json(['error' => 'Cannot edit confirmed opening balance'], 403);
        }

        $inventoryItem = $openingBalance->inventory()->findOrFail($itemId);

        $validated = $request->validate([
            'material_name' => 'sometimes|string',
            'material_name_ar' => 'nullable|string',
            'material_type' => 'sometimes|in:perfume_oil,alcohol,bottle,cap,sprayer,box,shopping_bag,other_packaging,other_raw_material',
            'unit' => 'sometimes|string',
            'quantity' => 'sometimes|numeric|min:0',
            'unit_cost' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|in:SYP,USD',
            'exchange_rate' => 'sometimes|numeric|min:0',
            'supplier' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $inventoryItem->update($validated);

        return response()->json($inventoryItem);
    }

    public function deleteInventoryItem($openingBalanceId, $itemId)
    {
        $openingBalance = OpeningBalance::findOrFail($openingBalanceId);

        if (!$openingBalance->canBeEdited()) {
            return response()->json(['error' => 'Cannot edit confirmed opening balance'], 403);
        }

        $inventoryItem = $openingBalance->inventory()->findOrFail($itemId);
        $inventoryItem->delete();

        return response()->json(['message' => 'Inventory item deleted']);
    }

    private function openingMaterialType(?string $category, ?string $subcategory = null): string
    {
        return match ($category) {
            'perfume_oil' => 'perfume_oil',
            'alcohol' => 'alcohol',
            'bottle' => 'bottle',
            'cap' => 'cap',
            'sprayer' => 'sprayer',
            'box' => 'box',
            'shopping_bag' => 'shopping_bag',
            'packaging' => match ($subcategory) {
                'bottle' => 'bottle',
                'box' => 'box',
                'bag' => 'shopping_bag',
                default => 'other_packaging',
            },
            default => 'other_raw_material',
        };
    }

    // Financial Accounts
    public function addFinancialAccount(Request $request, $id)
    {
        $openingBalance = OpeningBalance::findOrFail($id);
        if (!$openingBalance->canBeEdited()) {
            return response()->json(['error' => 'Cannot edit confirmed opening balance'], 403);
        }

        $validated = $request->validate([
            'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'balance' => ['required', 'numeric', 'min:0'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
            'currency' => ['nullable', 'in:SYP,USD'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $account = \App\Models\FinancialAccount::query()
            ->whereKey($validated['financial_account_id'])
            ->where('is_active', true)
            ->where('account_group', 'asset')
            ->first();
        if (!$account) {
            return response()->json(['error' => 'Selected financial account is not an active asset account.'], 422);
        }

        $duplicate = $openingBalance->financialAccounts()
            ->where('financial_account_id', $account->id)
            ->first();
        if ($duplicate) {
            $duplicate->update([
                'balance' => $validated['balance'],
                'exchange_rate' => $validated['exchange_rate'] ?? 1,
                'currency' => $validated['currency'] ?? $account->currency ?? 'SYP',
                'notes' => $validated['notes'] ?? null,
                'account_name' => $account->name,
                'account_name_ar' => $account->name_ar,
                'account_type' => $account->account_type,
            ]);
            return response()->json($duplicate->fresh('financialAccount'));
        }

        $row = $openingBalance->financialAccounts()->create([
            'financial_account_id' => $account->id,
            'account_name' => $account->name,
            'account_name_ar' => $account->name_ar,
            'account_type' => $account->account_type,
            'currency' => $validated['currency'] ?? $account->currency ?? 'SYP',
            'balance' => $validated['balance'],
            'exchange_rate' => $validated['exchange_rate'] ?? 1,
            'bank_name' => $account->bank_name,
            'account_number' => $account->account_number,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json($row->fresh('financialAccount'), 201);
    }

    public function updateFinancialAccount(Request $request, $openingBalanceId, $accountId)
    {
        $openingBalance = OpeningBalance::findOrFail($openingBalanceId);

        if (!$openingBalance->canBeEdited()) {
            return response()->json(['error' => 'Cannot edit confirmed opening balance'], 403);
        }

        $financialAccount = $openingBalance->financialAccounts()->findOrFail($accountId);

        $validated = $request->validate([
            'account_name' => 'sometimes|string',
            'account_name_ar' => 'nullable|string',
            'account_type' => 'sometimes|in:cash,bank,payment_gateway,other',
            'currency' => 'sometimes|string|in:SYP,USD',
            'balance' => 'sometimes|numeric|min:0',
            'exchange_rate' => 'sometimes|numeric|min:0',
            'bank_name' => 'nullable|string',
            'account_number' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $financialAccount->update($validated);

        return response()->json($financialAccount);
    }

    public function deleteFinancialAccount($openingBalanceId, $accountId)
    {
        $openingBalance = OpeningBalance::findOrFail($openingBalanceId);

        if (!$openingBalance->canBeEdited()) {
            return response()->json(['error' => 'Cannot edit confirmed opening balance'], 403);
        }

        $financialAccount = $openingBalance->financialAccounts()->findOrFail($accountId);
        $financialAccount->delete();

        return response()->json(['message' => 'Financial account deleted']);
    }

    // Payables/Receivables
    public function addPayableReceivable(Request $request, $id)
    {
        $openingBalance = OpeningBalance::findOrFail($id);

        if (!$openingBalance->canBeEdited()) {
            return response()->json(['error' => 'Cannot edit confirmed opening balance'], 403);
        }

        $validated = $request->validate([
            'party_name' => 'required|string',
            'party_name_ar' => 'nullable|string',
            'type' => 'required|in:payable,receivable',
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|in:SYP,USD',
            'exchange_rate' => 'required|numeric|min:0',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $payableReceivable = $openingBalance->payablesReceivables()->create($validated);

        return response()->json($payableReceivable, 201);
    }

    public function updatePayableReceivable(Request $request, $openingBalanceId, $prId)
    {
        $openingBalance = OpeningBalance::findOrFail($openingBalanceId);

        if (!$openingBalance->canBeEdited()) {
            return response()->json(['error' => 'Cannot edit confirmed opening balance'], 403);
        }

        $payableReceivable = $openingBalance->payablesReceivables()->findOrFail($prId);

        $validated = $request->validate([
            'party_name' => 'sometimes|string',
            'party_name_ar' => 'nullable|string',
            'type' => 'sometimes|in:payable,receivable',
            'contact_person' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|in:SYP,USD',
            'exchange_rate' => 'sometimes|numeric|min:0',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $payableReceivable->update($validated);

        return response()->json($payableReceivable);
    }

    public function deletePayableReceivable($openingBalanceId, $prId)
    {
        $openingBalance = OpeningBalance::findOrFail($openingBalanceId);

        if (!$openingBalance->canBeEdited()) {
            return response()->json(['error' => 'Cannot edit confirmed opening balance'], 403);
        }

        $payableReceivable = $openingBalance->payablesReceivables()->findOrFail($prId);
        $payableReceivable->delete();

        return response()->json(['message' => 'Payable/Receivable deleted']);
    }

    // Adjustments
    public function requestAdjustment(Request $request, $id)
    {
        $openingBalance = OpeningBalance::findOrFail($id);

        if (!$openingBalance->isConfirmed()) {
            return response()->json(['error' => 'Opening balance must be confirmed before requesting adjustments'], 403);
        }

        $validated = $request->validate([
            'adjustment_type' => 'required|in:inventory,financial,payable,receivable',
            'reference_id' => 'required|string',
            'reference_type' => 'nullable|string|max:120',
            'new_values' => 'required|array',
            'reason' => 'required|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $target = match ($validated['adjustment_type']) {
            'inventory' => $openingBalance->inventory()->find($validated['reference_id']),
            'financial' => $openingBalance->financialAccounts()->find($validated['reference_id']),
            'payable', 'receivable' => $openingBalance->payablesReceivables()->find($validated['reference_id']),
            default => null,
        };
        if (!$target) {
            return response()->json(['error' => 'The adjustment reference does not exist in this opening balance.'], 422);
        }

        if (in_array($validated['adjustment_type'], ['payable', 'receivable'], true)
            && (string) $target->type !== $validated['adjustment_type']) {
            return response()->json(['error' => 'Adjustment type does not match the referenced payable/receivable.'], 422);
        }

        $original = match ($validated['adjustment_type']) {
            'inventory' => [
                'quantity' => (float) $target->quantity,
                'unit_cost' => (float) $target->unit_cost,
                'currency' => (string) $target->currency,
                'exchange_rate' => (float) $target->exchange_rate,
                'material_id' => $target->material_id,
                'unit' => (string) $target->unit,
                'supplier' => $target->supplier,
                'notes' => $target->notes,
                'total_value' => (float) $target->total_value,
                'updated_at' => optional($target->updated_at)->toISOString(),
            ],
            'financial' => [
                'balance' => (float) $target->balance,
                'currency' => (string) $target->currency,
                'exchange_rate' => (float) $target->exchange_rate,
                'account_type' => (string) $target->account_type,
                'account_name' => (string) $target->account_name,
                'updated_at' => optional($target->updated_at)->toISOString(),
            ],
            'payable', 'receivable' => [
                'type' => (string) $target->type,
                'amount' => (float) $target->amount,
                'currency' => (string) $target->currency,
                'exchange_rate' => (float) $target->exchange_rate,
                'amount_syp' => (float) $target->amount_syp,
                'party_name' => $target->party_name,
                'party_name_ar' => $target->party_name_ar,
                'due_date' => optional($target->due_date)->toDateString(),
                'updated_at' => optional($target->updated_at)->toISOString(),
            ],
        };

        $actorId = (int) ($request->attributes->get('authUser')?->id ?? 0);
        if ($actorId <= 0) {
            return response()->json(['error' => 'Authenticated user is required.'], 401);
        }

        $adjustment = $openingBalance->adjustments()->create([
            'adjustment_type' => $validated['adjustment_type'],
            'reference_id' => $validated['reference_id'],
            'reference_type' => $validated['reference_type'] ?? get_class($target),
            'original_values' => $original,
            'new_values' => $validated['new_values'],
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'adjusted_by' => $actorId,
            'status' => 'pending',
        ]);

        return response()->json($adjustment, 201);
    }

    public function approveAdjustment(Request $request, $openingBalanceId, $adjustmentId)
    {
        $openingBalance = OpeningBalance::findOrFail($openingBalanceId);
        $adjustment = $openingBalance->adjustments()->findOrFail($adjustmentId);
        if (!$adjustment->isPending()) {
            return response()->json(['error' => 'Adjustment is not pending'], 403);
        }

        $actorId = (int) ($request->attributes->get('authUser')?->id ?? 0);
        if ($actorId <= 0) {
            return response()->json(['error' => 'Authenticated user is required.'], 401);
        }

        try {
            $approved = app(\App\Services\OpeningBalanceAdjustmentService::class)->approve($adjustment, $actorId);
            return response()->json($approved);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'error' => app()->environment('local') ? $e->getMessage() : 'Failed to approve opening balance adjustment safely.',
            ], 422);
        }
    }

    public function rejectAdjustment(Request $request, $openingBalanceId, $adjustmentId)
    {
        $openingBalance = OpeningBalance::findOrFail($openingBalanceId);
        $adjustment = $openingBalance->adjustments()->findOrFail($adjustmentId);

        if (!$adjustment->isPending()) {
            return response()->json(['error' => 'Adjustment is not pending'], 403);
        }

        $actorId = (int) ($request->attributes->get('authUser')?->id ?? 0);
        if ($actorId <= 0) {
            return response()->json(['error' => 'Authenticated user is required.'], 401);
        }

        $adjustment->update([
            'status' => 'rejected',
            'approved_by' => $actorId,
            'approved_at' => now(),
            'adjusted_at' => now(),
        ]);

        return response()->json($adjustment);
    }
}