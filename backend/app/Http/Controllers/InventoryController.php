<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\FinishedProductInventory;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Expense;
use App\Models\OpeningBalance;
use App\Models\FixedAsset;
use App\Models\FixedAssetDepreciationEntry;
use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\TaxConfiguration;
use App\Models\FinancialAccount;
use App\Models\FinancialPayment;
use App\Models\JournalLine;
use App\Models\InventoryMovement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\FinancePostingService;

class InventoryController extends Controller
{
    // Unified materials endpoints kept for compatibility with existing admin UI.
    public function getMaterials()
    {
        $materials = Material::query()
            ->where('is_active', true)
            ->orderBy('material_category')
            ->orderBy('name')
            ->get();

        if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
            $materials->transform(function (Material $material) {
                $row = $material->toArray();
                foreach (['avg_unit_cost', 'currency', 'exchange_rate', 'supplier_name', 'notes'] as $field) {
                    unset($row[$field]);
                }
                return $row;
            });
        }

        return response()->json($materials);
    }

    public function getMaterialsByCategory(string $category)
    {
        $materials = Material::query()
            ->where('is_active', true)
            ->where('material_category', $category)
            ->orderBy('name')
            ->get();

        if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
            $materials->transform(function (Material $material) {
                $row = $material->toArray();
                foreach (['avg_unit_cost', 'currency', 'exchange_rate', 'supplier_name', 'notes'] as $field) {
                    unset($row[$field]);
                }
                return $row;
            });
        }

        return response()->json($materials);
    }

    public function storeMaterial(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:100|unique:materials,code',
            'name' => 'required|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'material_category' => 'required|string|max:100',
            'subcategory' => 'nullable|string|max:100',
            'base_unit' => 'required|string|max:20',
            'track_fractional' => 'nullable|boolean',
            'min_stock' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|in:SYP,USD,EUR',
            'exchange_rate' => 'nullable|numeric|min:0',
            'supplier_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:4000',
        ]);

        $validated['is_active'] = true;
        $validated['currency'] = $validated['currency'] ?? 'SYP';
        $validated['exchange_rate'] = $validated['exchange_rate'] ?? 1;
        $validated['track_fractional'] = (bool) ($validated['track_fractional'] ?? true);
        $validated['created_by'] = (int) ($request->attributes->get('authUser')?->id ?? 0) ?: null;

        $validated['current_stock'] = 0;
        $validated['avg_unit_cost'] = 0;
        $material = Material::create($validated);
        return response()->json($material, 201);
    }

    public function updateMaterial(Request $request, int $id)
    {
        $material = Material::findOrFail($id);

        $validated = $request->validate([
            'code' => 'sometimes|string|max:100|unique:materials,code,' . $id,
            'name' => 'sometimes|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'material_category' => 'sometimes|string|max:100',
            'subcategory' => 'nullable|string|max:100',
            'base_unit' => 'sometimes|string|max:20',
            'track_fractional' => 'nullable|boolean',
            'min_stock' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|in:SYP,USD,EUR',
            'exchange_rate' => 'sometimes|numeric|min:0',
            'supplier_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:4000',
            'is_active' => 'sometimes|boolean',
        ]);

        $material->update($validated);
        return response()->json($material->fresh());
    }

    public function deleteMaterial(int $id)
    {
        $material = Material::findOrFail($id);

        if ((float) $material->current_stock > 0.0001) {
            return response()->json([
                'ok' => false,
                'message' => 'Cannot deactivate a material with remaining stock. Perform a stock adjustment first.',
            ], 409);
        }

        $material->is_active = false;
        $material->save();

        return response()->json(['ok' => true, 'message' => 'Material deactivated']);
    }

    public function getLowStockMaterials()
    {
        $materials = Material::query()
            ->where('is_active', true)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->orderByRaw('(min_stock - current_stock) desc')
            ->orderBy('name')
            ->get();

        if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
            $materials->transform(function (Material $material) {
                $row = $material->toArray();
                foreach (['avg_unit_cost', 'currency', 'exchange_rate', 'supplier_name', 'notes'] as $field) {
                    unset($row[$field]);
                }
                return $row;
            });
        }

        return response()->json($materials);
    }

    public function getMaterialMovements(int $materialId)
    {
        Material::whereKey($materialId)->firstOrFail();

        $isEmployee = !\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'));

        $movements = InventoryMovement::query()
            ->where('material_id', $materialId)
            ->orderByDesc('movement_date')
            ->orderByDesc('id')
            ->get()
            ->map(function (InventoryMovement $movement) use ($isEmployee) {
                $type = strtoupper((string) $movement->movement_type);
                $normalizedType = match ($type) {
                    'PURCHASE_IN', 'PURCHASE' => 'purchase',
                    'OPENING_BALANCE' => 'opening',
                    'ADJUSTMENT_IN', 'ADJUSTMENT_OUT', 'CONSUMPTION_ADJUSTMENT', 'CONSUMPTION_ADJUSTMENT_REVERSAL' => 'adjustment',
                    'CONSUMPTION' => 'consumption',
                    'CONSUMPTION_REVERSAL' => 'consumption_reversal',
                    default => strtolower((string) $movement->movement_type),
                };

                $row = $movement->toArray();
                $row['movement_type'] = $normalizedType;
                if ($isEmployee) {
                    unset($row['unit_cost'], $row['total_cost']);
                }

                return $row;
            })
            ->values();

        return response()->json(['movements' => $movements]);
    }

    // Finished Products Inventory
    public function getFinishedProductsInventory()
    {
        $inventory = FinishedProductInventory::with('product')->get();
        if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
            $inventory->transform(function ($row) { $data=$row->toArray(); foreach(['cost_per_unit'] as $field) unset($data[$field]); return $data; });
        }
        return response()->json($inventory);
    }

    public function storeFinishedProductInventory(Request $request)
    {
        return response()->json([
            'message' => 'ROUH is Made-to-Order: finished-goods stock is not maintained as an independent inventory source. Use the order preparation workflow.'
        ], 409);
    }

    public function updateFinishedProductInventory(Request $request, $id)
    {
        return response()->json([
            'message' => 'ROUH is Made-to-Order: finished-goods inventory is controlled by order preparation and cannot be edited from the legacy endpoint.'
        ], 409);
    }

    public function deleteFinishedProductInventory($id)
    {
        return response()->json([
            'message' => 'Finished-goods inventory records cannot be hard-deleted from the legacy endpoint.'
        ], 409);
    }

    // Purchases
    public function getPurchases()
    {
        $purchases = Purchase::with('material')->orderByDesc('purchase_date')->orderByDesc('id')->get();
        return response()->json($purchases);
    }

    public function storePurchase(Request $request)
    {
        $validated = $request->validate([
            'material_id' => 'required|exists:materials,id',
            'quantity' => 'required|numeric|gt:0',
            'cost_per_unit' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
            'supplier' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'payment_method' => 'nullable|in:cash,cash_on_delivery,sham_cash,bank_transfer,credit',
            'paid_amount' => 'nullable|numeric|min:0',
        ]);

        $actorId = (int) ($request->attributes->get('authUser')?->id ?? 0);

        $purchaseDate = Carbon::parse($validated['purchase_date'])->toDateString();
        $lastMovementDate = InventoryMovement::query()->max('movement_date');
        if ($lastMovementDate && $purchaseDate < Carbon::parse($lastMovementDate)->toDateString()) {
            return response()->json(['message' => 'Backdated purchases are blocked after later inventory activity. Use the current open date or a controlled reversal/replay process.'], 422);
        }

        $requestedPaidAmount = max(0.0, (float) ($validated['paid_amount'] ?? 0));
        $estimatedTotalCost = (float) $validated['quantity'] * (float) $validated['cost_per_unit'];
        if ($requestedPaidAmount > $estimatedTotalCost + 0.005) {
            return response()->json(['message' => 'Paid amount cannot exceed the purchase total.'], 422);
        }
        if ($requestedPaidAmount > 0.005 && empty($validated['payment_method'])) {
            return response()->json(['message' => 'Payment method is required when a purchase is created with a payment.'], 422);
        }
        if (($validated['payment_method'] ?? null) === 'credit' && $requestedPaidAmount > 0.005) {
            return response()->json(['message' => 'Credit purchases cannot include an immediate payment. Record the payment separately.'], 422);
        }

        try {
            $purchase = DB::transaction(function () use ($validated, $actorId) {
                /** @var Material $material */
                $material = Material::whereKey($validated['material_id'])->lockForUpdate()->firstOrFail();
                $qty = (float) $validated['quantity'];
                $unitCost = (float) $validated['cost_per_unit'];
                $oldStock = (float) $material->current_stock;
                $oldAvg = (float) $material->avg_unit_cost;
                $totalCost = $qty * $unitCost;
                $newStock = $oldStock + $qty;
                $newAvg = $newStock > 0 ? (($oldStock * $oldAvg) + $totalCost) / $newStock : $unitCost;

                $purchase = Purchase::create([
                    'material_id' => $material->id,
                    'raw_material_id' => null,
                    'item_name' => $material->name,
                    'item_type' => 'raw_material',
                    'quantity' => $qty,
                    'unit' => $material->base_unit,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'currency' => 'SYP',
                    'exchange_rate' => 1,
                    'total_cost_syp' => $totalCost,
                    'purchase_date' => $validated['purchase_date'],
                    'supplier' => $validated['supplier'] ?? null,
                    'invoice_number' => $validated['invoice_number'] ?? null,
                    'payment_method' => $validated['payment_method'] ?? null,
                    'status' => 'confirmed',
                    'payment_status' => 'unpaid',
                    'paid_amount' => 0,
                    'remaining_amount' => $totalCost,
                    'created_by' => $actorId ?: null,
                    'confirmed_by' => $actorId ?: null,
                    'confirmed_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]);

                $material->current_stock = $newStock;
                $material->avg_unit_cost = $newAvg;
                $material->save();

                InventoryMovement::create([
                    'material_id' => $material->id,
                    'movement_type' => 'PURCHASE_IN',
                    'quantity' => $qty,
                    'unit' => $material->base_unit,
                    'quantity_base' => $qty,
                    'previous_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'unit_cost' => $unitCost,
                    'total_cost' => $totalCost,
                    'reference_type' => 'purchase',
                    'reference_id' => (string) $purchase->id,
                    'notes' => $validated['notes'] ?? 'Purchase received',
                    'movement_date' => $purchase->purchase_date->copy()->endOfDay(),
                    'created_by' => $actorId ?: null,
                ]);

                $posting = app(FinancePostingService::class);
                $posting->postPurchase($purchase->fresh());

                $initialPaidAmount = min($totalCost, max(0.0, (float) ($validated['paid_amount'] ?? 0)));
                if ($initialPaidAmount > 0.005) {
                    $accountCode = match ($validated['payment_method']) {
                        'cash', 'cash_on_delivery' => '1000',
                        'sham_cash' => '1020',
                        'bank_transfer' => '1010',
                        default => throw new \RuntimeException('Unsupported purchase payment method.'),
                    };
                    $account = FinancialAccount::where('code', $accountCode)->lockForUpdate()->firstOrFail();
                    $payment = FinancialPayment::create([
                        'payment_number' => 'PAY-' . now()->format('YmdHis') . '-' . random_int(100, 999),
                        'idempotency_key' => 'purchase:' . $purchase->id . ':initial',
                        'direction' => 'payment',
                        'amount' => $initialPaidAmount,
                        'currency' => 'SYP',
                        'exchange_rate' => 1,
                        'amount_base' => $initialPaidAmount,
                        'financial_account_id' => $account->id,
                        'purchase_id' => $purchase->id,
                        'payment_method' => $validated['payment_method'],
                        'reference' => $purchase->invoice_number,
                        'notes' => 'Initial payment recorded with purchase.',
                        'payment_date' => now()->toDateString(),
                        'status' => 'posted',
                        'created_by' => $actorId ?: null,
                    ]);
                    $posting->postPurchasePayment($payment->fresh(['account']), $purchase->fresh());
                    $purchase->paid_amount = $initialPaidAmount;
                    $purchase->remaining_amount = round(max(0.0, $totalCost - $initialPaidAmount), 2);
                    $purchase->payment_status = $purchase->remaining_amount <= 0.005 ? 'paid' : 'partial';
                    $purchase->save();
                }
                return $purchase->fresh();
            });

            return response()->json($purchase->load('material'), 201);
        } catch (\Throwable $e) {
            Log::error('Purchase creation failed', ['message' => $e->getMessage(), 'user_id' => $actorId]);
            return response()->json(['message' => app()->environment('local') ? $e->getMessage() : 'Failed to create purchase.'], 422);
        }
    }

    public function updatePurchase(Request $request, $id)
    {
        $validated = $request->validate([
            'material_id' => 'required|exists:materials,id',
            'quantity' => 'required|numeric|gt:0',
            'cost_per_unit' => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
            'supplier' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'payment_method' => 'nullable|in:cash,cash_on_delivery,sham_cash,bank_transfer,credit',
        ]);

        $actorId = (int) ($request->attributes->get('authUser')?->id ?? 0);

        if (\App\Models\JournalEntry::where('source_type', 'purchase')->where('source_id', (string)$id)->where('entry_kind', 'purchase')->where('status', 'posted')->exists()) {
            return response()->json(['message' => 'Posted purchases are immutable. Void the purchase and create a corrected document.'], 409);
        }

        try {
            $purchase = DB::transaction(function () use ($validated, $id, $actorId) {
                $purchase = Purchase::whereKey($id)->lockForUpdate()->firstOrFail();
                if (($purchase->status ?? 'confirmed') !== 'draft') {
                    throw new \RuntimeException('Only draft purchases can be edited. Posted or inventory-affecting purchases must be voided and recreated.');
                }
                $movement = InventoryMovement::where('reference_type', 'purchase')
                    ->where('reference_id', (string) $purchase->id)
                    ->lockForUpdate()->first();
                if ($movement) {
                    throw new \RuntimeException('This purchase already has an inventory movement and is no longer editable.');
                }

                $newMaterial = Material::whereKey($validated['material_id'])->lockForUpdate()->firstOrFail();
                $purchase->update([
                    'material_id' => $newMaterial->id,
                    'raw_material_id' => null,
                    'item_name' => $newMaterial->name,
                    'unit' => $newMaterial->base_unit,
                    'quantity' => (float) $validated['quantity'],
                    'unit_cost' => (float) $validated['cost_per_unit'],
                    'total_cost' => (float) $validated['quantity'] * (float) $validated['cost_per_unit'],
                    'total_cost_syp' => (float) $validated['quantity'] * (float) $validated['cost_per_unit'],
                    'purchase_date' => $validated['purchase_date'],
                    'supplier' => $validated['supplier'] ?? null,
                    'invoice_number' => $validated['invoice_number'] ?? null,
                    'payment_method' => $validated['payment_method'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);
                return $purchase->fresh();
            });
            return response()->json($purchase->load('material'));
        } catch (\Throwable $e) {
            Log::error('Purchase update failed', ['message' => $e->getMessage(), 'purchase_id' => $id, 'user_id' => $actorId]);
            return response()->json(['message' => app()->environment('local') ? $e->getMessage() : 'Failed to update purchase safely.'], 422);
        }
    }

    public function deletePurchase($id)
    {
        $actorId = (int) (request()->attributes->get('authUser')?->id ?? 0);
        try {
            $result = DB::transaction(function () use ($id, $actorId) {
                $purchase = Purchase::whereKey($id)->lockForUpdate()->firstOrFail();
                if (($purchase->status ?? 'confirmed') === 'voided') {
                    return ['purchase_id' => $purchase->id, 'status' => 'voided'];
                }
                $hasPostedPayments = FinancialPayment::query()
                    ->where('purchase_id', $purchase->id)
                    ->where('direction', 'payment')
                    ->whereIn('status', ['posted', 'reclassified'])
                    ->exists();
                if ((float) $purchase->paid_amount > 0.005 || $hasPostedPayments) {
                    throw new \RuntimeException('This purchase has recorded payments. Record and reconcile the supplier refund first; the purchase cannot be voided as if the cash had already returned.');
                }

                $material = $purchase->material_id ? Material::whereKey($purchase->material_id)->lockForUpdate()->first() : null;
                $qty = (float)$purchase->quantity;
                if ($material && (float)$material->current_stock + 0.0001 < $qty) {
                    throw new \RuntimeException('This purchase cannot be voided because part or all of the received material has already been consumed. Record a controlled inventory adjustment instead.');
                }

                if ($material && $qty > 0) {
                    $stock = (float) $material->current_stock;
                    $voidValue = round($qty * (float) $purchase->unit_cost, 4);
                    $newStock = round(max(0.0, $stock - $qty), 4);
                    $movement = InventoryMovement::create([
                        'material_id' => $material->id,
                        'movement_type' => 'RETURN_OUT',
                        'quantity' => $qty,
                        'unit' => $material->base_unit,
                        'quantity_base' => $qty,
                        'previous_stock' => $stock,
                        'new_stock' => $newStock,
                        'unit_cost' => (float) $purchase->unit_cost,
                        'total_cost' => $voidValue,
                        'reference_type' => 'purchase_void',
                        'reference_id' => (string) $purchase->id,
                        'notes' => 'Purchase void reversal; original receipt excluded from future ledger valuation replay.',
                        'meta' => ['purchase_id' => $purchase->id, 'voids_purchase_movement' => true],
                        'movement_date' => now(),
                        'created_by' => $actorId ?: null,
                    ]);

                    // Mark the original receipt as voided in the audit metadata.
                    $originalMovement = InventoryMovement::query()
                        ->where('reference_type', 'purchase')
                        ->where('reference_id', (string) $purchase->id)
                        ->orderBy('id')
                        ->first();
                    if ($originalMovement) {
                        $meta = is_array($originalMovement->meta) ? $originalMovement->meta : [];
                        $meta['voided'] = true;
                        $meta['voided_at'] = now()->toIso8601String();
                        $meta['voided_by_purchase_void_movement_id'] = $movement->id;
                        $originalMovement->meta = $meta;
                        $originalMovement->save();
                    }

                    $projection = app(\App\Services\InventoryCostingService::class)->project($material->id);
                    $material->current_stock = $projection['stock'];
                    $material->avg_unit_cost = $projection['avg_cost'];
                    $material->save();
                }
                $purchase->status = 'voided';
                $purchase->payment_status = 'unpaid';
                $purchase->paid_amount = 0;
                $purchase->remaining_amount = max(0.0, (float) $purchase->total_cost_syp);
                $purchase->saveQuietly();
                $entry = \App\Models\JournalEntry::where('source_type','purchase')->where('source_id',(string)$purchase->id)->where('entry_kind','purchase')->first();
                if ($entry && $entry->status === 'posted') app(FinancePostingService::class)->reverseJournal($entry, 'Purchase voided');
                return ['purchase_id' => $purchase->id, 'status' => 'voided'];
            });
            return response()->json(['message' => 'Purchase voided and reversed successfully.', 'purchase' => $result]);
        } catch (\Throwable $e) {
            Log::error('Purchase void failed', ['purchase_id' => $id, 'message' => $e->getMessage(), 'user_id' => $actorId]);
            return response()->json(['message' => app()->environment('local') ? $e->getMessage() : 'Failed to void purchase.'], 422);
        }
    }

    public function recordPurchasePayment(Request $request, $id): JsonResponse
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 120) {
            return response()->json(['ok' => false, 'message' => 'A unique Idempotency-Key header is required for payment requests.'], 422);
        }

        $validated = $request->validate([
            'paid_amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|in:cash,cash_on_delivery,sham_cash,bank_transfer',
            'notes' => 'nullable|string|max:1000',
        ]);

        $result = DB::transaction(function () use ($id, $validated, $idempotencyKey): Purchase {
            /** @var Purchase $purchase */
            $purchase = Purchase::lockForUpdate()->findOrFail($id);
            $existingPayment = FinancialPayment::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existingPayment) {
                if ((int) $existingPayment->purchase_id !== (int) $purchase->id) {
                    throw new \RuntimeException('This Idempotency-Key is already assigned to another payment.');
                }
                return $purchase->fresh();
            }
            if (($purchase->status ?? 'confirmed') === 'voided') {
                throw new \RuntimeException('Voided purchases cannot receive payments.');
            }
            $total = round((float) ($purchase->total_cost_syp ?? $purchase->total_cost), 2);
            $currentPaid = round((float) $purchase->paid_amount, 2);
            $remaining = round(max(0.0, $total - $currentPaid), 2);
            $amount = round((float) $validated['paid_amount'], 2);
            if ($amount <= 0 || $amount > $remaining + 0.005) {
                throw new \RuntimeException('Payment amount exceeds the remaining supplier balance.');
            }

            $account = FinancialAccount::whereIn('name', match ($validated['payment_method']) {
                'cash', 'cash_on_delivery' => ['Cash'],
                'sham_cash' => ['Sham Cash'],
                'bank_transfer' => ['Bank Transfer'],
                default => [],
            })->lockForUpdate()->firstOrFail();

            $payment = FinancialPayment::create([
                'payment_number' => 'PAY-' . now()->format('YmdHis') . '-' . random_int(100, 999),
                'idempotency_key' => $idempotencyKey,
                'direction' => 'payment',
                'amount' => $amount,
                'currency' => 'SYP',
                'exchange_rate' => 1,
                'amount_base' => $amount,
                'financial_account_id' => $account->id,
                'purchase_id' => $purchase->id,
                'payment_method' => $validated['payment_method'],
                'reference' => $purchase->invoice_number,
                'notes' => $validated['notes'] ?? null,
                'payment_date' => now()->toDateString(),
                'status' => 'posted',
                'created_by' => request()->attributes->get('authUser')?->id,
            ]);

            app(FinancePostingService::class)->postPurchasePayment($payment->fresh(['account']), $purchase->fresh());

            $newPaid = round($currentPaid + $amount, 2);
            $purchase->update([
                'paid_amount' => $newPaid,
                'remaining_amount' => round(max(0.0, $total - $newPaid), 2),
                'payment_status' => $newPaid >= $total - 0.005 ? 'paid' : 'partial',
                'payment_method' => $validated['payment_method'],
            ]);

            return $purchase->fresh();
        });

        return response()->json(['ok' => true, 'purchase' => $result->load('material')]);
    }

    // Sales
    public function getSales()
    {
        $sales = Sale::with(['product', 'customer', 'order'])
            ->orderByDesc('sale_date')
            ->orderByDesc('id')
            ->get();
        return response()->json($sales);
    }

    public function storeSale(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'size_type' => 'required|string|max:50',
            'size_ml' => 'nullable|integer',
            'quantity' => 'required|integer|min:1',
            'unit_price' => 'required|numeric|min:0',
            'sale_source' => 'required|string|max:50',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:30',
            'sale_date' => 'required|date',
            'payment_method' => 'required|in:cash,sham_cash,bank_transfer,credit',
            'paid_amount' => 'nullable|numeric|min:0',
            'invoice_number' => 'nullable|string|max:80|unique:sales,invoice_number',
            'notes' => 'nullable|string|max:2000',
        ]);

        $actorId = (int) ($request->attributes->get('authUser')?->id ?? 0);
        try {
            $sale = app(\App\Services\DirectFinishedGoodsSaleService::class)->create($validated, $actorId ?: null);
            $request->attributes->set('audit.reason', 'Created direct finished-goods sale through controlled inventory workflow.');
            return response()->json($sale->load(['product', 'customer', 'order']), 201);
        } catch (\Throwable $e) {
            Log::error('Direct finished-goods sale failed', ['message' => $e->getMessage(), 'user_id' => $actorId]);
            return response()->json(['ok' => false, 'message' => app()->environment('local') ? $e->getMessage() : 'Failed to create the sale safely.'], 422);
        }
    }

    public function recordSalePayment(Request $request, $id)
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 120) {
            return response()->json(['ok' => false, 'message' => 'A unique Idempotency-Key header is required for payment requests.'], 422);
        }

        $validated = $request->validate([
            'paid_amount' => 'required|numeric|gt:0',
            'payment_method' => 'required|in:cash,cash_on_delivery,sham_cash,bank_transfer',
            'payment_status' => 'nullable|in:unpaid,partial,paid,refunded',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $result = DB::transaction(function () use ($id, $validated, $idempotencyKey): Sale {
                $sale = Sale::lockForUpdate()->findOrFail($id);
                $existingPayment = FinancialPayment::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($existingPayment) {
                    if ((int) $existingPayment->sale_id !== (int) $sale->id) {
                        throw new \RuntimeException('This Idempotency-Key is already assigned to another payment.');
                    }
                    return $sale->fresh();
                }
                if (($sale->sale_status ?? 'active') === 'voided') {
                    throw new \RuntimeException('Voided sales cannot receive payments.');
                }
                $total = round((float) $sale->total_price_syp, 2);
                $currentPaid = round((float) $sale->paid_amount, 2);
                $remaining = round(max(0.0, $total - $currentPaid), 2);
                $amount = round((float) $validated['paid_amount'], 2);
                if ($amount <= 0 || $amount > $remaining + 0.005) {
                    throw new \RuntimeException('Payment amount exceeds the remaining balance.');
                }

                if (($sale->revenue_status ?? 'recognized') === 'recognized') {
                    app(FinancePostingService::class)->postSale($sale->fresh());
                }
                $accountName = match ($validated['payment_method']) {
                    'cash', 'cash_on_delivery' => 'Cash',
                    'sham_cash' => 'Sham Cash',
                    'bank_transfer' => 'Bank Transfer',
                    default => throw new \RuntimeException('Unsupported payment method.'),
                };
                $account = FinancialAccount::where('name', $accountName)->lockForUpdate()->firstOrFail();
                $newPaid = round($currentPaid + $amount, 2);
                $newRemaining = round(max(0.0, $total - $newPaid), 2);
                $status = $newRemaining <= 0.005 ? 'paid' : 'partial';

                $payment = FinancialPayment::create([
                    'payment_number' => 'RCPT-' . now()->format('YmdHis') . '-' . random_int(100,999),
                    'idempotency_key' => $idempotencyKey,
                    'direction' => 'receipt',
                    'amount' => $amount,
                    'currency' => 'SYP',
                    'exchange_rate' => 1,
                    'amount_base' => $amount,
                    'financial_account_id' => $account->id,
                    'sale_id' => (($sale->revenue_status ?? 'recognized') === 'recognized') ? $sale->id : null,
                    'order_id' => $sale->order_id,
                    'payment_method' => $validated['payment_method'],
                    'reference' => $sale->invoice_number,
                    'notes' => $validated['notes'] ?? null,
                    'payment_date' => now()->toDateString(),
                    'status' => 'posted',
                    'created_by' => request()->attributes->get('authUser')?->id,
                ]);

                if (($sale->revenue_status ?? 'recognized') === 'recognized') {
                    app(FinancePostingService::class)->postSalePayment($payment->fresh(['account']), $sale->fresh());
                } else {
                    app(FinancePostingService::class)->postOrderDeposit($payment->fresh(['account']));
                }

                $sale->update([
                    'paid_amount' => $newPaid,
                    'remaining_amount' => $newRemaining,
                    'payment_method' => $validated['payment_method'],
                    'payment_status' => $status,
                    'notes' => trim(($sale->notes ? $sale->notes . ' | ' : '') . ($validated['notes'] ? 'Payment: ' . $validated['notes'] : 'Payment receipt ' . $payment->payment_number)),
                ]);

                if ($sale->order_id) {
                    DB::table('orders')->where('id', $sale->order_id)->update([
                        'payment_method' => $sale->payment_method,
                        'payment_status' => $sale->payment_status,
                        'paid_amount' => $sale->paid_amount,
                        'remaining_amount' => $sale->remaining_amount,
                        'updated_at' => now(),
                    ]);
                }
                return $sale->fresh();
            });
            return response()->json($result->load(['customer', 'order']));
        } catch (\Throwable $e) {
            Log::error('Sale payment failed', ['sale_id' => $id, 'message' => $e->getMessage()]);
            return response()->json(['ok' => false, 'message' => app()->environment('local') ? $e->getMessage() : 'Failed to register payment safely.'], 422);
        }
    }

    public function updateSale(Request $request, $id)
    {
        return response()->json([
            'ok' => false,
            'message' => 'Posted sales are immutable. Void the sale through the controlled workflow and create a corrected sale.',
        ], 409);
    }

    public function deleteSale($id)
    {
        $actorId = (int) (request()->attributes->get('authUser')?->id ?? 0);
        try {
            app(\App\Services\DirectFinishedGoodsSaleService::class)->void(Sale::findOrFail($id), $actorId ?: null);
            request()->attributes->set('audit.reason', 'Voided direct finished-goods sale through controlled reversal workflow.');
            return response()->json(['ok' => true, 'message' => 'Sale voided and finished-goods stock/accounting reversed.']);
        } catch (\Throwable $e) {
            Log::error('Sale void failed', ['sale_id' => $id, 'message' => $e->getMessage(), 'user_id' => $actorId]);
            return response()->json(['ok' => false, 'message' => app()->environment('local') ? $e->getMessage() : 'Failed to void the sale safely.'], 422);
        }
    }

    // Expenses
    public function getExpenses()
    {
        $expenses = Expense::orderBy('expense_date', 'desc')->get();
        return response()->json($expenses);
    }

    public function storeExpense(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'description' => 'required|string',
            'amount' => 'required|numeric',
            'expense_date' => 'required|date',
            'payment_method' => 'required|in:cash,sham_cash,bank_transfer,credit',
            'rent_duration_days' => 'nullable|integer|min:0',
            'vendor' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Always SYP; amount_syp is handled by the Expense model boot/saving hook.
        $validated['currency'] = 'SYP';
        $validated['exchange_rate'] = 1;
        $validated['amount_syp'] = $validated['amount'];

        try {
            $expense = DB::transaction(function () use ($validated) {
                $expense = Expense::create($validated);
                (new FinancePostingService())->postExpense($expense);
                $expense->refresh();
                if ($expense->status === 'draft') { $expense->status = 'posted'; $expense->save(); }
                return $expense;
            });
            return response()->json($expense, 201);
        } catch (\Throwable $e) {
            Log::error('Expense creation failed', ['message' => $e->getMessage()]);
            return response()->json(['message' => app()->environment('local') ? $e->getMessage() : 'Failed to create expense.'], 422);
        }
    }

    public function updateExpense(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);
        if (\App\Models\JournalEntry::where('source_type', 'expense')->where('source_id', (string) $expense->id)->where('entry_kind', 'expense')->exists()) {
            return response()->json(['message' => 'Posted expenses are immutable. Create a correcting entry instead.'], 409);
        }
        $validated = $request->validate([
            'category' => 'string',
            'description' => 'string',
            'amount' => 'numeric',
            'expense_date' => 'date',
            'payment_method' => 'required|in:cash,sham_cash,bank_transfer,credit',
            'rent_duration_days' => 'nullable|integer|min:0',
            'vendor' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        // Always SYP; amount_syp is handled by the Expense model boot/saving hook.
        $validated['currency'] = 'SYP';
        $validated['exchange_rate'] = 1;
        $validated['amount_syp'] = $validated['amount'] ?? $expense->amount;

        try {
            DB::transaction(function () use ($expense, $validated): void {
                $expense->update($validated);
                (new FinancePostingService())->postExpense($expense->fresh());
                $expense->refresh();
                if ($expense->status === 'draft') { $expense->status = 'posted'; $expense->save(); }
            });
            return response()->json($expense->fresh());
        } catch (\Throwable $e) {
            Log::error('Expense update failed', ['message' => $e->getMessage(), 'expense_id' => $id]);
            return response()->json(['message' => app()->environment('local') ? $e->getMessage() : 'Failed to update expense.'], 422);
        }
    }

    public function deleteExpense(Request $request, $id)
    {
        $expense = Expense::findOrFail($id);
        if (($expense->status ?? 'posted') === 'voided') {
            return response()->json(['message' => 'Expense is already voided.'], 409);
        }
        try {
            DB::transaction(function () use ($expense, $request): void {
                $entry = \App\Models\JournalEntry::where('source_type', 'expense')->where('source_id', (string)$expense->id)->where('entry_kind', 'expense')->first();
                if ($entry && $entry->status === 'posted') {
                    app(FinancePostingService::class)->reverseJournal($entry, 'Expense voided');
                }
                $expense->status = 'voided';
                $expense->voided_at = now();
                $expense->voided_by = $request->attributes->get('authUser')?->id;
                $expense->void_reason = 'Voided through admin action';
                $expense->save();
            });
            return response()->json(['message' => 'Expense voided/reversed successfully.']);
        } catch (\Throwable $e) {
            Log::error('Expense void failed', ['message' => $e->getMessage(), 'expense_id' => $id]);
            return response()->json(['message' => app()->environment('local') ? $e->getMessage() : 'Failed to void expense.'], 422);
        }
    }

    public function writeDownInventoryToNrv(Request $request)
    {
        $validated = $request->validate([
            'material_id' => ['required','integer','exists:materials,id'],
            'nrv_value' => ['required','numeric','min:0'],
            'adjustment_date' => ['required','date'],
            'reason' => ['nullable','string','max:1000'],
        ]);
        $actorId = $request->attributes->get('authUser')?->id;
        try {
            $result = app(FinancePostingService::class)->postInventoryValuationWriteDown(
                Material::query()->where('is_active', true)->findOrFail((int)$validated['material_id']),
                (float)$validated['nrv_value'],
                (string)$validated['adjustment_date'],
                $validated['reason'] ?? null,
                $actorId ? (int)$actorId : null,
            );
            if (!$result) {
                return response()->json(['ok'=>true,'message'=>'No write-down required; NRV is not below the current carrying value.','adjustment'=>null]);
            }
            $request->attributes->set('audit.reason', $validated['reason'] ?? 'Inventory written down to net realizable value.');
            return response()->json(['ok'=>true,'adjustment'=>$result], 201);
        } catch (\Throwable $e) {
            Log::error('Inventory NRV write-down failed', ['message'=>$e->getMessage(),'material_id'=>$validated['material_id']]);
            return response()->json(['ok'=>false,'message'=>app()->environment('local') ? $e->getMessage() : 'تعذر تسجيل انخفاض قيمة المخزون.'],422);
        }
    }

    // Stock counts / stock requests
    public function storeStockCount(Request $request)
    {
        $validated = $request->validate([
            'count_date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|integer|exists:materials,id|distinct',
            'items.*.counted_qty' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string|max:80',
        ]);

        $userId = (string)($request->attributes->get('authUser')?->id ?? '');
        if ($userId === '') { return response()->json(['message' => 'Authenticated user is required.'], 401); }
        $countDate = Carbon::parse($validated['count_date'])->toDateString();
        $lastMovementDate = InventoryMovement::query()->max('movement_date');
        if ($lastMovementDate && $countDate < Carbon::parse($lastMovementDate)->toDateString()) {
            return response()->json(['message' => 'Backdated stock counts are blocked when later inventory movements exist.'], 422);
        }
        try {
            $count = DB::transaction(function () use ($validated, $userId) {
            $countCode = 'CNT-' . now()->format('Ymd-His') . '-' . random_int(100, 999);
            $stockCount = StockCount::create([
                'count_code' => $countCode,
                'count_date' => $validated['count_date'],
                'status' => 'confirmed',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $userId,
                'confirmed_by' => $userId,
                'confirmed_at' => now(),
            ]);

            foreach ($validated['items'] as $row) {
                $material = Material::whereKey($row['material_id'])->lockForUpdate()->firstOrFail();
                $systemQty = (float)$material->current_stock;
                $countedQty = (float)$row['counted_qty'];
                $variance = $countedQty - $systemQty;
                $newStock = $countedQty;

                $movement = InventoryMovement::create([
                    'material_id' => $material->id,
                    'movement_type' => $variance >= 0 ? 'ADJUSTMENT_IN' : 'ADJUSTMENT_OUT',
                    'quantity' => abs($variance),
                    'unit' => $material->base_unit,
                    'quantity_base' => $variance,
                    'previous_stock' => $systemQty,
                    'new_stock' => $newStock,
                    'unit_cost' => $material->avg_unit_cost,
                    'total_cost' => abs($variance) * (float)$material->avg_unit_cost,
                    'reference_type' => 'stock_count',
                    'reference_id' => $stockCount->id,
                    'notes' => $row['reason'] ?? 'Physical stock count adjustment',
                    'movement_date' => Carbon::parse($validated['count_date'])->endOfDay(),
                    'created_by' => $userId,
                ]);

                StockCountItem::create([
                    'stock_count_id' => $stockCount->id,
                    'material_id' => $material->id,
                    'system_qty' => $systemQty,
                    'counted_qty' => $countedQty,
                    'variance_qty' => $variance,
                    'unit' => $material->base_unit,
                    'reason' => $row['reason'] ?? null,
                    'notes' => null,
                    'adjustment_movement_id' => $movement->id,
                ]);

                $material->current_stock = $newStock;
                $material->save();
            }
            app(\App\Services\FinancePostingService::class)->postStockCount($stockCount->fresh(['items.material']));
            return $stockCount->load(['items.material', 'journal']);
        });

            return response()->json(['ok'=>true,'stock_count'=>$count], 201);
        } catch (\Throwable $e) {
            Log::error('Stock count failed', ['message' => $e->getMessage(), 'user_id' => $userId]);
            return response()->json(['ok'=>false,'message'=> app()->environment('local') ? ('تعذر تسجيل الجرد: ' . $e->getMessage()) : 'تعذر تسجيل الجرد. تحقق من البيانات وحاول مرة أخرى.'], 422);
        }
    }

    public function getStockCounts()
    {
        $counts = StockCount::with('items.material')->orderByDesc('count_date')->orderByDesc('id')->limit(100)->get();
        return response()->json($counts);
    }

    public function getStockRequests(Request $request)
    {
        $query = DB::table('stock_requests')
            ->leftJoin('materials','materials.id','=','stock_requests.material_id')
            ->leftJoin('users as requesters','requesters.id','=','stock_requests.requested_by')
            ->leftJoin('users as approvers','approvers.id','=','stock_requests.approved_by')
            ->leftJoin('users as rejecters','rejecters.id','=','stock_requests.rejected_by')
            ->leftJoin('users as fulfillers','fulfillers.id','=','stock_requests.fulfilled_by')
            ->select(
                'stock_requests.*',
                'materials.name as material_name',
                'materials.name_ar as material_name_ar',
                'requesters.name as requested_by_name',
                'approvers.name as approved_by_name',
                'rejecters.name as rejected_by_name',
                'fulfillers.name as fulfilled_by_name'
            )
            ->orderByDesc('stock_requests.created_at');

        if ($request->attributes->get('authRole') === 'employee') {
            $userId = (string)($request->attributes->get('authUser')?->id ?? '');
            $query->where('stock_requests.requested_by', $userId);
        }

        return response()->json($query->get());
    }

    public function storeStockRequest(Request $request)
    {
        $validated = $request->validate([
            'material_id' => 'required|integer|exists:materials,id',
            'requested_qty' => 'required|numeric|min:0.0001',
            'priority' => 'required|in:normal,high,urgent',
            'reason' => 'nullable|string|max:1000',
        ]);
        $userId = (string)($request->attributes->get('authUser')?->id ?? '');
        if ($userId === '') { return response()->json(['message' => 'Authenticated user is required.'], 401); }
        $id = DB::table('stock_requests')->insertGetId([
            'material_id' => $validated['material_id'],
            'requested_qty' => $validated['requested_qty'],
            'priority' => $validated['priority'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
            'requested_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return response()->json(['ok'=>true,'id'=>$id], 201);
    }

    public function updateStockRequest(Request $request, string $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'notes' => 'nullable|string|max:1000',
            'reason' => 'nullable|string|max:1000',
        ]);
        $row = DB::table('stock_requests')->where('id', $id)->first();
        if (!$row) return response()->json(['ok' => false, 'message' => 'Stock request not found'], 404);
        $actorId = (int) ($request->attributes->get('authUser')?->id ?? 0);
        $reason = trim((string) ($validated['reason'] ?? $validated['notes'] ?? ''));
        if ($validated['status'] === 'rejected' && $reason === '') {
            return response()->json(['ok' => false, 'message' => 'A rejection reason is required.'], 422);
        }
        if ($row->status === 'fulfilled') return response()->json(['ok' => false, 'message' => 'A fulfilled request cannot be changed.'], 409);
        if ($row->status === 'rejected' && $validated['status'] !== 'rejected') return response()->json(['ok' => false, 'message' => 'A rejected request cannot be reopened.'], 409);
        if ($row->status === 'approved' && $validated['status'] === 'pending') return response()->json(['ok' => false, 'message' => 'An approved request cannot be moved back to pending.'], 409);
        $before = (array) $row;
        $update = ['status' => $validated['status'], 'notes' => $validated['notes'] ?? $row->notes, 'updated_at' => now()];
        if ($validated['status'] === 'approved') { $update['approved_by'] = $actorId ?: null; $update['approved_at'] = now(); }
        if ($validated['status'] === 'rejected') { $update['rejected_by'] = $actorId ?: null; $update['rejected_at'] = now(); $update['rejection_reason'] = $reason; }
        DB::table('stock_requests')->where('id', $id)->update($update);
        $after = (array) DB::table('stock_requests')->where('id', $id)->first();
        $request->attributes->set('audit.before', $before);
        $request->attributes->set('audit.after', $after);
        $request->attributes->set('audit.reason', $reason);
        return response()->json(['ok' => true, 'message' => $validated['status'] === 'approved' ? 'Stock request approved' : 'Stock request rejected']);
    }

    public function receiveStockRequest(Request $request, string $id)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|min:0.0001',
            'unit_cost' => 'required|numeric|min:0',
            'supplier' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'purchase_date' => 'required|date',
            'payment_method' => 'nullable|in:cash,cash_on_delivery,sham_cash,bank_transfer,credit',
            'paid_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);
        $actorId = (int) ($request->attributes->get('authUser')?->id ?? 0);
        if ($actorId <= 0) return response()->json(['ok' => false, 'message' => 'Authenticated user required.'], 401);
        $requestRow = DB::table('stock_requests')->where('id', $id)->first(['requested_qty','status']);
        if (!$requestRow) return response()->json(['ok' => false, 'message' => 'Stock request not found.'], 404);
        if ($requestRow->status !== 'approved') return response()->json(['ok' => false, 'message' => 'Only approved stock requests can be received.'], 409);
        $requestedPaidAmount = max(0.0, (float) ($validated['paid_amount'] ?? 0));
        $estimatedTotalCost = (float) $validated['quantity'] * (float) $validated['unit_cost'];
        if ($requestedPaidAmount > $estimatedTotalCost + 0.005) {
            return response()->json(['ok' => false, 'message' => 'Paid amount cannot exceed the purchase total.'], 422);
        }
        if ($requestedPaidAmount > 0.005 && empty($validated['payment_method'])) {
            return response()->json(['ok' => false, 'message' => 'Payment method is required when a purchase is partially or fully paid.'], 422);
        }
        if (($validated['payment_method'] ?? null) === 'credit' && $requestedPaidAmount > 0.005) {
            return response()->json(['ok' => false, 'message' => 'Credit purchases cannot include an immediate payment. Record the payment separately.'], 422);
        }
        if ((float) $validated['quantity'] < (float) $requestRow->requested_qty) {
            return response()->json(['ok' => false, 'message' => 'Received quantity cannot be less than the approved requested quantity.'], 422);
        }
        try {
            $result = DB::transaction(function () use ($validated, $id, $actorId) {
                $req = DB::table('stock_requests')->where('id', $id)->lockForUpdate()->first();
                if (!$req) throw new \RuntimeException('Stock request not found.');
                if ($req->status !== 'approved') throw new \RuntimeException('Only approved stock requests can be received.');
                $material = Material::whereKey($req->material_id)->lockForUpdate()->firstOrFail();
                $qty = (float) $validated['quantity'];
                $cost = (float) $validated['unit_cost'];
                $oldStock = (float) $material->current_stock;
                $oldCost = (float) $material->avg_unit_cost;
                $newStock = $oldStock + $qty;
                $newAvg = $newStock > 0 ? (($oldStock * $oldCost) + ($qty * $cost)) / $newStock : $cost;
                $totalCost = $qty * $cost;
                $purchase = Purchase::create([
                    'material_id' => $material->id,
                    'item_name' => $material->name,
                    'item_type' => 'raw_material',
                    'quantity' => $qty,
                    'unit' => $material->base_unit,
                    'unit_cost' => $cost,
                    'total_cost' => $totalCost,
                    'total_cost_syp' => $totalCost,
                    'purchase_date' => $validated['purchase_date'],
                    'supplier' => $validated['supplier'] ?? null,
                    'invoice_number' => $validated['invoice_number'] ?? null,
                    'payment_method' => $validated['payment_method'] ?? null,
                    'status' => 'confirmed',
                    'payment_status' => 'unpaid',
                    'paid_amount' => 0,
                    'remaining_amount' => $totalCost,
                    'created_by' => $actorId,
                    'confirmed_by' => $actorId,
                    'confirmed_at' => now(),
                    'notes' => $validated['notes'] ?? null,
                ]);
                $material->current_stock = $newStock;
                $material->avg_unit_cost = $newAvg;
                $material->save();
                $movement = \App\Models\InventoryMovement::create([
                    'material_id' => $material->id,
                    'movement_type' => 'PURCHASE_IN',
                    'quantity' => $qty,
                    'unit' => $material->base_unit,
                    'quantity_base' => $qty,
                    'previous_stock' => $oldStock,
                    'new_stock' => $newStock,
                    'unit_cost' => $cost,
                    'total_cost' => $totalCost,
                    'reference_type' => 'purchase',
                    'reference_id' => (string) $purchase->id,
                    'notes' => $validated['notes'] ?? 'Received from approved stock request',
                    'movement_date' => Carbon::parse($validated['purchase_date'])->endOfDay(),
                    'created_by' => $actorId,
                ]);
                $posting = app(FinancePostingService::class);
                $posting->postPurchase($purchase);

                $initialPaidAmount = min($totalCost, max(0.0, (float) ($validated['paid_amount'] ?? 0)));
                if ($initialPaidAmount > 0.005) {
                    $accountCode = match ($validated['payment_method']) {
                        'cash', 'cash_on_delivery' => '1000',
                        'sham_cash' => '1020',
                        'bank_transfer' => '1010',
                        default => throw new \RuntimeException('Unsupported purchase payment method.'),
                    };
                    $account = FinancialAccount::where('code', $accountCode)->lockForUpdate()->firstOrFail();
                    $payment = FinancialPayment::create([
                        'payment_number' => 'PAY-' . now()->format('YmdHis') . '-' . random_int(100, 999),
                        'idempotency_key' => 'purchase:' . $purchase->id . ':initial',
                        'direction' => 'payment',
                        'amount' => $initialPaidAmount,
                        'currency' => 'SYP',
                        'exchange_rate' => 1,
                        'amount_base' => $initialPaidAmount,
                        'financial_account_id' => $account->id,
                        'purchase_id' => $purchase->id,
                        'payment_method' => $validated['payment_method'],
                        'reference' => $purchase->invoice_number,
                        'notes' => 'Initial payment recorded with purchase receipt.',
                        'payment_date' => now()->toDateString(),
                        'status' => 'posted',
                        'created_by' => $actorId,
                    ]);
                    $posting->postPurchasePayment($payment->fresh(['account']), $purchase->fresh());
                    $purchase->paid_amount = $initialPaidAmount;
                    $purchase->remaining_amount = round(max(0.0, $totalCost - $initialPaidAmount), 2);
                    $purchase->payment_status = $purchase->remaining_amount <= 0.005 ? 'paid' : 'partial';
                    $purchase->save();
                }

                DB::table('stock_requests')->where('id', $id)->update([
                    'status' => 'fulfilled',
                    'fulfilled_by' => $actorId,
                    'fulfilled_at' => now(),
                    'purchase_id' => $purchase->id,
                    'updated_at' => now(),
                ]);
                return ['purchase_id' => $purchase->id, 'movement_id' => $movement->id];
            });
            $request->attributes->set('audit.reason', 'Received approved stock request and recorded purchase.');
            return response()->json(['ok' => true, 'message' => 'Stock received successfully', 'data' => $result], 201);
        } catch (\Throwable $e) {
            Log::error('Stock request receipt failed', ['message' => $e->getMessage(), 'stock_request_id' => $id, 'user_id' => $actorId]);
            return response()->json(['ok' => false, 'message' => app()->environment('local') ? $e->getMessage() : 'تعذر استلام البضاعة. تحقق من البيانات وحاول مرة أخرى.'], 422);
        }
    }

    // Financial Dashboard Data
    public function getFinancialDashboard(Request $request)
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->string('start_date'))->toDateString()
            : (string) (OpeningBalance::query()->whereIn('status', ['confirmed','locked'])->orderBy('opening_balance_date')->value('opening_balance_date') ?? '');
        if ($startDate === '') {
            return response()->json(['error' => 'أنشئ الرصيد الافتتاحي أولاً لتحديد بداية النظام المحاسبي.'], 422);
        }
        $endDate = $request->filled('end_date') ? Carbon::parse($request->string('end_date'))->toDateString() : now()->toDateString();
        if ($endDate < $startDate) {
            return response()->json(['error' => 'تاريخ نهاية التقرير يجب أن يكون بعد أو يساوي تاريخ البداية.'], 422);
        }

        $gl = app(FinancePostingService::class);
        $statements = $gl->financialStatements($startDate, $endDate);
        $periodRows = $statements['trial_balance']['rows'] ?? [];
        $balanceRows = collect($statements['balance_sheet_trial_balance']['rows'] ?? []);
        $accountBalance = static function ($code) use ($balanceRows) { $row = $balanceRows->firstWhere('code', $code); return (float)($row['balance'] ?? 0); };

        $openingRows = $gl->trialBalance(null, Carbon::parse($startDate)->subDay()->toDateString())['rows'] ?? [];
        $openingInventoryValue = 0.0;
        foreach ($openingRows as $row) {
            if (($row['code'] ?? null) === '1200') { $openingInventoryValue = (float)$row['balance']; break; }
        }

        $daily = $this->glBreakdown($startDate, $endDate, 'daily');
        $weekly = $this->glBreakdown($startDate, $endDate, 'weekly');
        $monthly = $this->glBreakdown($startDate, $endDate, 'monthly');
        $yearly = $this->glBreakdown($startDate, $endDate, 'yearly');
        $periodPurchases = Purchase::whereBetween('purchase_date', [$startDate, $endDate])
            ->where(fn($q) => $q->whereNull('status')->orWhere('status','!=','voided'))
            ->select(DB::raw('DATE(purchase_date) as label'), DB::raw('SUM(total_cost_syp) as total'))
            ->groupBy(DB::raw('DATE(purchase_date)'))->orderBy(DB::raw('DATE(purchase_date)'))->get();
        $dateBreakdown = [
            'daily' => [...$daily, 'purchases' => $this->relabelBreakdown($periodPurchases, $startDate, $endDate, 'daily')],
            'weekly' => [...$weekly, 'purchases' => $this->purchasePeriodBreakdown($startDate, $endDate, 'weekly')],
            'monthly' => [...$monthly, 'purchases' => $this->purchasePeriodBreakdown($startDate, $endDate, 'monthly')],
            'yearly' => [...$yearly, 'purchases' => $this->purchasePeriodBreakdown($startDate, $endDate, 'yearly')],
        ];

        $activeSale = fn($q) => $q->where(function($qq){ $qq->whereNull('sale_status')->orWhere('sale_status','active'); });
        $sales = Sale::whereBetween('sale_date', [$startDate, $endDate])->where($activeSale)
            ->select('sale_source', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_price_syp) as total'))
            ->groupBy('sale_source')->get();
        $salesPayment = Sale::whereBetween('sale_date', [$startDate, $endDate])->where($activeSale)
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_price_syp) as total'), DB::raw('SUM(paid_amount) as paid'), DB::raw('SUM(remaining_amount) as remaining'))
            ->groupBy('payment_method')->get();
        $salesStatus = Sale::whereBetween('sale_date', [$startDate, $endDate])->where($activeSale)
            ->select('payment_status', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_price_syp) as total'), DB::raw('SUM(paid_amount) as paid'), DB::raw('SUM(remaining_amount) as remaining'))
            ->groupBy('payment_status')->get();
        $expensesByCategory = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->where(fn($q) => $q->whereNull('status')->orWhere('status','!=','voided'))
            ->select('category', DB::raw('SUM(amount_syp) as total'))->groupBy('category')->get();

        $incomeTaxConfig = TaxConfiguration::query()->activeForDate($endDate)->where('tax_type','income_tax')->where('applicable_to','profit')->orderByDesc('effective_date')->first();
        $incomeTaxEstimate = $incomeTaxConfig && $statements['profit_and_loss']['net_profit'] > 0
            ? $incomeTaxConfig->calculateTax((float)$statements['profit_and_loss']['net_profit']) : 0.0;

        return response()->json([
            'totalInventoryValue' => $accountBalance('1200') + $accountBalance('1210') + $accountBalance('1220'),
            'totalCashBalance' => $accountBalance('1000'),
            'totalBankBalance' => $accountBalance('1010'),
            'shamCashBalance' => $accountBalance('1020'),
            'totalPayables' => $accountBalance('2000'),
            'totalReceivables' => $accountBalance('1100'),
            'netFinancialPosition' => $statements['balance_sheet']['assets'] - $statements['balance_sheet']['liabilities'],
            'accounting' => $statements,
            'revenue' => $statements['profit_and_loss']['revenue'],
            'expenses' => $statements['profit_and_loss']['expenses'],
            'purchases' => (float) Purchase::whereBetween('purchase_date', [$startDate,$endDate])->where(fn($q)=>$q->whereNull('status')->orWhere('status','!=','voided'))->sum('total_cost_syp'),
            'cogs' => $statements['profit_and_loss']['cogs'],
            'openingInventoryValue' => $openingInventoryValue,
            'closingInventoryValue' => $accountBalance('1200') + $accountBalance('1210') + $accountBalance('1220'),
            'manufacturingWages' => 0,
            'adminWages' => 0,
            'operatingExpenses' => $statements['profit_and_loss']['expenses'],
            'depreciation' => (float)(collect($periodRows)->firstWhere('code','5100')['balance'] ?? 0),
            'totalTax' => (float)(collect($periodRows)->firstWhere('code','2100')['balance'] ?? 0),
            'netProfitAfterIncomeTaxEstimate' => round($statements['profit_and_loss']['net_profit'] - $incomeTaxEstimate, 2),
            'incomeTaxEstimate' => round($incomeTaxEstimate,2),
            'preTaxProfit' => $statements['profit_and_loss']['revenue'] + $statements['profit_and_loss']['other_income'] - $statements['profit_and_loss']['cogs'] - $statements['profit_and_loss']['expenses'],
            'netProfit' => $statements['profit_and_loss']['net_profit'],
            'opening_balance' => $this->currentOpeningBalanceSummary(),
            'sales_by_source' => $sales,
            'sales_by_payment_method' => $salesPayment,
            'sales_by_payment_status' => $salesStatus,
            'expenses_by_category' => $expensesByCategory,
            'low_stock_raw_materials' => Material::where('is_active', true)->whereColumn('current_stock','<=','min_stock')->get(),
            'low_stock_finished_products' => FinishedProductInventory::whereColumn('current_stock','<=','min_stock')->get(),
            'breakdown' => $dateBreakdown,
            'cash_flow' => $statements['cash_flow'],
            'date_range' => ['start_date'=>$startDate,'end_date'=>$endDate],
        ]);
    }

    private function currentOpeningBalanceSummary(): ?array
    {
        $opening = OpeningBalance::whereIn('status',['confirmed','locked'])->orderByDesc('opening_balance_date')->orderByDesc('id')->first();
        if (!$opening) return null;
        return [
            'id'=>$opening->id,
            'opening_balance_date'=>Carbon::parse((string)$opening->opening_balance_date)->toDateString(),
            'status'=>$opening->status,
        ];
    }

    private function glBreakdown(string $startDate, string $endDate, string $period): array
    {
        $expr = match($period) {
            'daily' => 'DATE(je.entry_date)',
            'weekly' => "DATE_FORMAT(je.entry_date, '%x-W%v')",
            'monthly' => "DATE_FORMAT(je.entry_date, '%Y-%m')",
            default => "DATE_FORMAT(je.entry_date, '%Y')",
        };
        $rows = JournalLine::query()
            ->join('journal_entries as je','je.id','=','journal_lines.journal_entry_id')
            ->join('financial_accounts as fa','fa.id','=','journal_lines.financial_account_id')
            ->where('je.status','posted')
            ->whereDate('je.entry_date','>=',$startDate)
            ->whereDate('je.entry_date','<=',$endDate)
            ->groupBy(DB::raw($expr))
            ->selectRaw("{$expr} as label")
            ->selectRaw("SUM(CASE WHEN fa.account_group = 'revenue' THEN journal_lines.base_credit - journal_lines.base_debit ELSE 0 END) as revenue")
            ->selectRaw("SUM(CASE WHEN fa.account_group = 'other_income' THEN journal_lines.base_credit - journal_lines.base_debit ELSE 0 END) as other_income")
            ->selectRaw("SUM(CASE WHEN fa.account_group = 'cogs' THEN journal_lines.base_debit - journal_lines.base_credit ELSE 0 END) as cogs")
            ->selectRaw("SUM(CASE WHEN fa.account_group = 'expense' THEN journal_lines.base_debit - journal_lines.base_credit ELSE 0 END) as expenses")
            ->selectRaw("SUM(CASE WHEN fa.code='5100' THEN journal_lines.base_debit - journal_lines.base_credit ELSE 0 END) as depreciation")
            ->orderBy(DB::raw($expr))->get();
        return [
            'revenue'=>$rows->map(fn($r)=>['label'=>$r->label,'total'=>round((float)$r->revenue,2)])->values(),
            'expenses'=>$rows->map(fn($r)=>['label'=>$r->label,'total'=>round((float)$r->expenses,2)])->values(),
            'other_income'=>$rows->map(fn($r)=>['label'=>$r->label,'total'=>round((float)$r->other_income,2)])->values(),
            'cogs'=>$rows->map(fn($r)=>['label'=>$r->label,'total'=>round((float)$r->cogs,2)])->values(),
            'depreciation'=>$rows->map(fn($r)=>['label'=>$r->label,'total'=>round((float)$r->depreciation,2)])->values(),
        ];
    }

    private function purchasePeriodBreakdown(string $startDate,string $endDate,string $period)
    {
        $expr=match($period){
            'weekly'=>"DATE_FORMAT(purchase_date, '%x-W%v')",
            'monthly'=>"DATE_FORMAT(purchase_date, '%Y-%m')",
            'yearly'=>"DATE_FORMAT(purchase_date, '%Y')",
            default=>"DATE(purchase_date)",
        };
        return Purchase::whereBetween('purchase_date',[$startDate,$endDate])->where(fn($q)=>$q->whereNull('status')->orWhere('status','!=','voided'))
            ->selectRaw("{$expr} as label, SUM(total_cost_syp) as total")
            ->groupBy(DB::raw($expr))->orderBy(DB::raw($expr))->get();
    }

    private function relabelBreakdown($rows,string $startDate,string $endDate,string $period)
    {
        if ($period==='daily') return $rows;
        return $this->purchasePeriodBreakdown($startDate,$endDate,$period);
    }

    // Fixed Assets Management
    public function getFixedAssets()
    {
        $assets = FixedAsset::all();
        return response()->json($assets);
    }

    public function storeFixedAsset(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string', 'name_ar' => 'nullable|string',
            'asset_number' => 'required|string|unique:fixed_assets,asset_number',
            'category' => 'required|in:machinery,equipment,vehicle,furniture,electronics,building,land,other',
            'description' => 'nullable|string', 'purchase_cost' => 'required|numeric|gt:0',
            'quantity' => 'nullable|integer|min:1', 'purchase_date' => 'required|date',
            'supplier' => 'nullable|string', 'invoice_number' => 'nullable|string',
            'payment_method' => 'required|in:cash,sham_cash,bank_transfer,credit',
            'paid_amount' => 'nullable|numeric|min:0',
            'depreciation_method' => 'required|in:straight_line,declining_balance,units_of_production',
            'useful_life_years' => 'required|integer|min:1', 'total_estimated_units' => 'nullable|numeric|gt:0',
            'salvage_value' => 'nullable|numeric|min:0', 'depreciation_start_date' => 'nullable|date',
            'location' => 'nullable|string', 'serial_number' => 'nullable|string', 'notes' => 'nullable|string',
        ]);
        if ($validated['depreciation_method'] === 'units_of_production' && empty($validated['total_estimated_units'])) {
            return response()->json(['message' => 'Total estimated production units are required for units-of-production depreciation.'], 422);
        }
        $validated['paid_amount'] = round((float)($validated['paid_amount'] ?? ($validated['payment_method'] !== 'credit' ? $validated['purchase_cost'] : 0)), 2);
        if ($validated['paid_amount'] > (float)$validated['purchase_cost']) return response()->json(['message' => 'Paid amount cannot exceed asset purchase cost.'], 422);
        try {
            $asset = DB::transaction(function () use ($validated) {
                $asset = FixedAsset::create($validated);
                app(FinancePostingService::class)->postAssetAcquisition($asset->fresh(), $validated['payment_method'], $validated['paid_amount']);
                return $asset->fresh();
            });
            return response()->json($asset, 201);
        } catch (\Throwable $e) {
            Log::error('Fixed asset creation failed', ['message' => $e->getMessage()]);
            return response()->json(['message' => app()->environment('local') ? $e->getMessage() : 'Failed to create fixed asset.'], 422);
        }
    }

    public function updateFixedAsset(Request $request, $id)
    {
        $asset = FixedAsset::findOrFail($id);
        $posted = \App\Models\JournalEntry::where('source_type','fixed_asset')->where('source_id',(string)$asset->id)->where('entry_kind','acquisition')->where('status','posted')->exists();
        $validated = $request->validate([
            'name' => 'sometimes|string', 'name_ar' => 'nullable|string',
            'asset_number' => 'sometimes|string|unique:fixed_assets,asset_number,' . $id,
            'category' => 'sometimes|in:machinery,equipment,vehicle,furniture,electronics,building,land,other',
            'description' => 'nullable|string', 'purchase_cost' => 'sometimes|numeric|gt:0', 'purchase_date' => 'sometimes|date',
            'supplier' => 'nullable|string', 'invoice_number' => 'nullable|string', 'payment_method' => 'sometimes|in:cash,sham_cash,bank_transfer,credit',
            'paid_amount' => 'nullable|numeric|min:0', 'depreciation_method' => 'sometimes|in:straight_line,declining_balance,units_of_production',
            'useful_life_years' => 'sometimes|integer|min:1', 'total_estimated_units' => 'nullable|numeric|gt:0', 'salvage_value' => 'nullable|numeric|min:0',
            'depreciation_start_date' => 'nullable|date', 'status' => 'sometimes|in:active,disposed,sold,lost',
            'disposal_date' => 'nullable|date', 'disposal_value' => 'nullable|numeric|min:0', 'location' => 'nullable|string', 'serial_number' => 'nullable|string', 'notes' => 'nullable|string',
        ]);
        $financialFields = ['purchase_cost','purchase_date','payment_method','paid_amount','depreciation_method','useful_life_years','total_estimated_units','salvage_value'];
        if ($posted && array_intersect(array_keys($validated), $financialFields)) {
            return response()->json(['message' => 'Posted fixed assets are immutable for financial fields. Use a controlled disposal/correction workflow.'], 409);
        }
        if (($validated['depreciation_method'] ?? $asset->depreciation_method) === 'units_of_production' && empty($validated['total_estimated_units'] ?? $asset->total_estimated_units)) {
            return response()->json(['message' => 'Total estimated production units are required for units-of-production depreciation.'], 422);
        }
        $wasActive = ($asset->status ?? 'active') === 'active';
        $newStatus = $validated['status'] ?? $asset->status;
        $wasDisposed = in_array(($asset->status ?? 'active'), ['disposed','sold','lost'], true);
        if ($wasDisposed && isset($validated['status']) && $validated['status'] === 'active') {
            return response()->json(['message' => 'A disposed fixed asset cannot be reopened as active. Record a controlled correction/reversal instead.'], 409);
        }
        try {
            $updated = DB::transaction(function () use ($asset, $validated, $posted, $wasActive, $newStatus) {
                $asset->update($validated);
                if ($posted && $wasActive && in_array($newStatus, ['disposed','sold','lost'], true)) {
                    app(FinancePostingService::class)->postAssetDisposal($asset->fresh());
                }
                return $asset->fresh();
            });
            return response()->json($updated);
        } catch (\Throwable $e) {
            Log::error('Fixed asset update failed', ['message'=>$e->getMessage(),'asset_id'=>$id]);
            return response()->json(['message'=>app()->environment('local') ? $e->getMessage() : 'Failed to update fixed asset.'], 422);
        }
    }

    public function deleteFixedAsset($id)
    {
        $asset = FixedAsset::findOrFail($id);
        try {
            DB::transaction(function () use ($asset) {
                $entry = \App\Models\JournalEntry::where('source_type','fixed_asset')->where('source_id',(string)$asset->id)->where('entry_kind','acquisition')->first();
                if ($entry && $entry->status === 'posted') {
                    throw new \RuntimeException('A posted fixed asset cannot be deleted. Record disposal instead.');
                }
                $asset->delete();
            });
            return response()->json(['message' => 'Fixed asset deleted']);
        } catch (\Throwable $e) {
            return response()->json(['message' => app()->environment('local') ? $e->getMessage() : 'Failed to delete fixed asset.'], 422);
        }
    }

    public function getDepreciationEntries(Request $request)
    {
        $query = FixedAssetDepreciationEntry::with('asset')->orderByDesc('period_end');
        if ($request->filled('asset_id')) $query->where('fixed_asset_id', $request->integer('asset_id'));
        return response()->json($query->get());
    }

    public function previewDepreciation(Request $request)
    {
        $validated = $request->validate([
            'start_date'=>'required|date',
            'end_date'=>'required|date|after_or_equal:start_date',
            'production_units'=>'nullable|array',
            'production_units.*'=>'nullable|numeric|gt:0',
        ]);
        $units = $validated['production_units'] ?? [];
        $assets = FixedAsset::where('status','active')->whereNotNull('depreciation_start_date')->get();
        $rows = $assets->map(function ($asset) use ($validated, $units) {
            $productionUnits = $units[(string)$asset->id] ?? $units[$asset->id] ?? null;
            $amount = round($asset->calculateDepreciationForPeriod($validated['start_date'], $validated['end_date'], $productionUnits !== null ? (float)$productionUnits : null), 2);
            return [
                'asset_id'=>$asset->id, 'asset_number'=>$asset->asset_number, 'name'=>$asset->name, 'method'=>$asset->depreciation_method,
                'amount'=>$amount, 'annual'=>round($asset->calculateAnnualDepreciation(),2), 'monthly'=>round($asset->calculateMonthlyDepreciation(),2),
                'requires_production_units'=>$asset->depreciation_method === 'units_of_production',
                'production_units'=>$productionUnits,
            ];
        })->filter(fn($r)=>$r['amount']>0 || $r['requires_production_units'])->values();
        return response()->json(['start_date'=>$validated['start_date'],'end_date'=>$validated['end_date'],'entries'=>$rows,'total'=>round($rows->sum('amount'),2)]);
    }

    public function postDepreciation(Request $request)
    {
        $validated = $request->validate([
            'start_date'=>'required|date', 'end_date'=>'required|date|after_or_equal:start_date', 'notes'=>'nullable|string',
            'production_units'=>'nullable|array', 'production_units.*'=>'nullable|numeric|gt:0',
        ]);
        return DB::transaction(function () use ($validated, $request) {
            $assets = FixedAsset::where('status','active')->whereNotNull('depreciation_start_date')->lockForUpdate()->get();
            $created = [];
            foreach ($assets as $asset) {
                $productionUnits = $validated['production_units'][(string)$asset->id] ?? $validated['production_units'][$asset->id] ?? null;
                $amount = round($asset->calculateDepreciationForPeriod($validated['start_date'],$validated['end_date'], $productionUnits !== null ? (float)$productionUnits : null),2);
                if ($amount <= 0) continue;
                $overlap = FixedAssetDepreciationEntry::where('fixed_asset_id', $asset->id)
                    ->where('status', 'posted')
                    ->whereDate('period_start', '<=', $validated['end_date'])
                    ->whereDate('period_end', '>=', $validated['start_date'])
                    ->exists();
                if ($overlap) continue;
                $entry = FixedAssetDepreciationEntry::create([
                    'fixed_asset_id'=>$asset->id,'period_start'=>$validated['start_date'],'period_end'=>$validated['end_date'],
                    'amount'=>$amount,'method'=>$asset->depreciation_method,'production_units'=>$productionUnits,'status'=>'posted','created_by'=>$request->attributes->get('authUser')?->id,'notes'=>$validated['notes'] ?? null,
                ]);
                app(FinancePostingService::class)->postDepreciation($entry->fresh(['asset']));
                $created[] = $entry->fresh();
            }
            return response()->json(['ok'=>true,'created_count'=>count($created),'total'=>round(collect($created)->sum('amount'),2)]);
        });
    }

    // Tax Configuration Management
    public function getTaxConfigurations()
    {
        $taxes = TaxConfiguration::all();
        return response()->json($taxes);
    }

    public function storeTaxConfiguration(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'name_ar' => 'nullable|string',
            'tax_type' => 'required|in:vat,income_tax,sales_tax,other',
            'rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'post_to_ledger' => 'boolean',
            'tax_inclusive' => 'boolean',
            'effective_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'description' => 'nullable|string',
            'calculation_method' => 'required|in:percentage,fixed_amount',
            'fixed_amount' => 'nullable|numeric|min:0',
            'applicable_to' => 'required|in:all_revenue,profit,specific_categories',
            'applicable_categories' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $tax = TaxConfiguration::create($validated);
        return response()->json($tax, 201);
    }

    public function updateTaxConfiguration(Request $request, $id)
    {
        $tax = TaxConfiguration::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'name_ar' => 'nullable|string',
            'tax_type' => 'sometimes|in:vat,income_tax,sales_tax,other',
            'rate' => 'sometimes|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'post_to_ledger' => 'boolean',
            'tax_inclusive' => 'boolean',
            'effective_date' => 'sometimes|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'description' => 'nullable|string',
            'calculation_method' => 'sometimes|in:percentage,fixed_amount',
            'fixed_amount' => 'nullable|numeric|min:0',
            'applicable_to' => 'sometimes|in:all_revenue,profit,specific_categories',
            'applicable_categories' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $tax->update($validated);
        return response()->json($tax);
    }

    public function deleteTaxConfiguration($id)
    {
        $tax = TaxConfiguration::findOrFail($id);
        $tax->delete();
        return response()->json(['message' => 'Tax configuration deleted']);
    }
}
