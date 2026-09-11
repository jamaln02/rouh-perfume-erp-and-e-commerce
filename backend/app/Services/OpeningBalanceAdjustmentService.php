<?php

namespace App\Services;

use App\Models\OpeningBalance;
use App\Models\OpeningBalanceAdjustment;
use App\Models\OpeningBalanceFinancialAccount;
use App\Models\OpeningBalanceInventory;
use App\Models\OpeningBalancePayableReceivable;
use App\Models\Material;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OpeningBalanceAdjustmentService
{
    public function approve(OpeningBalanceAdjustment $adjustment, int $actorId): OpeningBalanceAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actorId): OpeningBalanceAdjustment {
            $adj = OpeningBalanceAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
            if (!$adj->isPending()) {
                throw new RuntimeException('Adjustment is not pending.');
            }

            $opening = OpeningBalance::query()->lockForUpdate()->findOrFail($adj->opening_balance_id);
            if (!$opening->isConfirmed()) {
                throw new RuntimeException('The opening balance must remain confirmed while approving an adjustment.');
            }
            if (!$adj->reference_id) {
                throw new RuntimeException('A confirmed opening-balance adjustment must reference an existing row.');
            }

            $delta = 0.0;
            $target = null;
            $beforeValues = (array) ($adj->original_values ?? []);

            switch ($adj->adjustment_type) {
                case 'inventory':
                    $target = OpeningBalanceInventory::query()
                        ->where('opening_balance_id', $opening->id)
                        ->whereKey($adj->reference_id)
                        ->lockForUpdate()->firstOrFail();
                    $beforeValues = $this->assertSnapshotMatches($this->snapshotTarget($target), $beforeValues);
                    $beforeTotal = (float) $target->total_value;
                    $safe = $this->safeValues($adj->new_values, [
                        'quantity', 'unit_cost', 'currency', 'exchange_rate', 'supplier', 'notes',
                    ]);
                    $target->fill($safe);
                    $target->save();
                    $delta = round((float) $target->total_value - $beforeTotal, 4);
                    $this->syncMaterialInventory($target, (float) $beforeValues['quantity'], $beforeTotal);
                    break;

                case 'financial':
                    $target = OpeningBalanceFinancialAccount::query()
                        ->where('opening_balance_id', $opening->id)
                        ->whereKey($adj->reference_id)
                        ->lockForUpdate()->firstOrFail();
                    $beforeValues = $this->assertSnapshotMatches($this->snapshotTarget($target), $beforeValues);
                    $beforeBalance = (float) $target->balance_syp;
                    $safe = $this->safeValues($adj->new_values, [
                        'balance', 'currency', 'exchange_rate', 'bank_name', 'account_number', 'notes',
                    ]);
                    $target->fill($safe);
                    $target->save();
                    $delta = round((float) $target->balance_syp - $beforeBalance, 4);
                    break;

                case 'payable':
                case 'receivable':
                    $target = OpeningBalancePayableReceivable::query()
                        ->where('opening_balance_id', $opening->id)
                        ->whereKey($adj->reference_id)
                        ->lockForUpdate()->firstOrFail();
                    $beforeValues = $this->assertSnapshotMatches($this->snapshotTarget($target), $beforeValues);
                    if ((string) $target->type !== $adj->adjustment_type) {
                        throw new RuntimeException('The payable/receivable type cannot be changed after opening-balance confirmation.');
                    }
                    $beforeAmount = (float) $target->amount_syp;
                    $safe = $this->safeValues($adj->new_values, [
                        'party_name', 'party_name_ar', 'contact_person', 'phone', 'email',
                        'amount', 'currency', 'exchange_rate', 'due_date', 'description', 'notes',
                    ]);
                    $target->fill($safe);
                    $target->save();
                    $delta = round((float) $target->amount_syp - $beforeAmount, 4);
                    break;

                default:
                    throw new RuntimeException('Unsupported opening-balance adjustment type.');
            }

            $journal = app(FinancePostingService::class)->postOpeningBalanceAdjustment(
                $adj,
                (string) $adj->adjustment_type,
                $delta
            );

            if ($adj->adjustment_type === 'inventory' && abs($delta) <= 0.005) {
                // A cost-only/identity correction can legitimately have no GL delta.
                // The material cost basis is still synchronized above.
            }

            $this->recalculateTotals($opening);

            $adj->update([
                'original_values' => $beforeValues,
                'status' => 'approved',
                'approved_by' => $actorId,
                'approved_at' => now(),
                'adjusted_at' => now(),
                'journal_entry_id' => $journal?->id,
            ]);

            return $adj->fresh(['journal', 'openingBalance']);
        });
    }

    private function snapshotTarget(object $target): array
    {
        if ($target instanceof OpeningBalanceInventory) {
            return [
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
            ];
        }
        if ($target instanceof OpeningBalanceFinancialAccount) {
            return [
                'balance' => (float) $target->balance,
                'currency' => (string) $target->currency,
                'exchange_rate' => (float) $target->exchange_rate,
                'account_type' => (string) $target->account_type,
                'account_name' => (string) $target->account_name,
                'updated_at' => optional($target->updated_at)->toISOString(),
            ];
        }
        if ($target instanceof OpeningBalancePayableReceivable) {
            return [
                'type' => (string) $target->type,
                'amount' => (float) $target->amount,
                'currency' => (string) $target->currency,
                'exchange_rate' => (float) $target->exchange_rate,
                'amount_syp' => (float) $target->amount_syp,
                'party_name' => $target->party_name,
                'party_name_ar' => $target->party_name_ar,
                'due_date' => optional($target->due_date)->toDateString(),
                'updated_at' => optional($target->updated_at)->toISOString(),
            ];
        }
        return $target->toArray();
    }

    private function assertSnapshotMatches(array $current, array $snapshot): array
    {
        if (!$snapshot) {
            throw new RuntimeException('Adjustment snapshot is missing. Create a new request from the current opening balance.');
        }
        foreach ($snapshot as $key => $expected) {
            if (!array_key_exists($key, $current)) continue;
            $actual = $current[$key];
            if (!$this->sameValue($actual, $expected)) {
                throw new RuntimeException('The opening-balance row changed after the adjustment request was created. Reject this stale request and submit a new one.');
            }
        }
        return $snapshot;
    }

    private function sameValue($left, $right): bool
    {
        if (is_numeric($left) && is_numeric($right)) {
            return abs((float) $left - (float) $right) <= 0.0001;
        }
        if (($left instanceof \DateTimeInterface) || ($right instanceof \DateTimeInterface)) {
            return (string) $left === (string) $right;
        }
        return (string) ($left ?? '') === (string) ($right ?? '');
    }

    private function safeValues(?array $values, array $allowed): array
    {
        $values = (array) $values;
        $unknown = array_diff(array_keys($values), $allowed);
        if ($unknown) {
            throw new RuntimeException('Unsupported fields in opening-balance adjustment: ' . implode(', ', $unknown));
        }
        return $values;
    }

    private function syncMaterialInventory(OpeningBalanceInventory $target, float $oldQty, float $oldOpeningValue): void
    {
        if (!$target->material_id) return;
        $material = Material::query()->whereKey($target->material_id)->lockForUpdate()->first();
        if (!$material) return;

        $deltaQty = round((float) $target->quantity - $oldQty, 4);
        $deltaValue = round((float) $target->total_value - $oldOpeningValue, 4);
        $currentStock = (float) $material->current_stock;
        $currentValue = round($currentStock * (float) $material->avg_unit_cost, 4);
        $newStock = round($currentStock + $deltaQty, 4);
        $newValue = round($currentValue + $deltaValue, 4);

        if ($newStock < -0.0001) {
            throw new RuntimeException('This adjustment would make material stock negative. Create a controlled inventory correction instead.');
        }
        if ($newValue < -0.01) {
            throw new RuntimeException('This adjustment would make the inventory value negative. Create a controlled inventory correction instead.');
        }

        $newStock = max(0.0, $newStock);
        $newValue = max(0.0, $newValue);
        $newAvg = $newStock > 0 ? round($newValue / $newStock, 6) : 0.0;

        if (abs($deltaQty) > 0.0001 || abs($deltaValue) > 0.0001) {
            \App\Models\InventoryMovement::create([
                'material_id' => $material->id,
                'movement_type' => abs($deltaQty) > 0.0001 ? ($deltaQty > 0 ? 'OPENING_ADJUSTMENT_IN' : 'OPENING_ADJUSTMENT_OUT') : 'VALUE_ADJUSTMENT',
                'quantity' => abs($deltaQty),
                'unit' => $material->base_unit,
                'quantity_base' => abs($deltaQty),
                'previous_stock' => $currentStock,
                'new_stock' => $newStock,
                'unit_cost' => abs($deltaQty) > 0.0001 ? abs($deltaValue / $deltaQty) : $newAvg,
                'total_cost' => abs($deltaValue),
                'reference_type' => 'opening_balance_adjustment',
                'reference_id' => (string) $target->id,
                'notes' => 'Approved opening balance correction',
                'meta' => ['delta_qty' => $deltaQty, 'delta_value' => $deltaValue],
                'movement_date' => now(),
                'created_by' => request()->attributes->get('authUser')?->id,
            ]);
        }

        $material->current_stock = $newStock;
        $material->avg_unit_cost = $newAvg;
        $material->save();
    }

    private function recalculateTotals(OpeningBalance $opening): void
    {
        $opening->update([
            'total_inventory_value' => $opening->inventory()->sum('total_value'),
            'total_cash_balance' => $opening->financialAccounts()->where('account_type', 'cash')->sum('balance_syp'),
            'total_bank_balance' => $opening->financialAccounts()->where('account_type', 'bank')->sum('balance_syp'),
            'total_payables' => $opening->payablesReceivables()->where('type', 'payable')->sum('amount_syp'),
            'total_receivables' => $opening->payablesReceivables()->where('type', 'receivable')->sum('amount_syp'),
        ]);
    }
}
