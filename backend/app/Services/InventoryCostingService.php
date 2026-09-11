<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\Purchase;
use App\Support\InventoryMovementTypes;

class InventoryCostingService
{
    /**
     * Pure calculation helper retained for unit-test/backward compatibility.
     * Each transaction amount is treated as total cost for the supplied quantity.
     */
    public function calculateWeightedAverageCost(array $payload): array
    {
        $totalQty = 0.0;
        $totalValue = 0.0;
        foreach (($payload['transactions'] ?? []) as $tx) {
            $qty = (float) ($tx['quantity'] ?? 0);
            $value = (float) ($tx['cost_syp'] ?? 0);
            if ($qty <= 0) continue;
            $totalQty += $qty;
            $totalValue += $value;
        }
        return [
            'total_quantity' => $totalQty,
            'total_cost_syp' => $totalValue,
            'avg_unit_cost' => $totalQty > 0 ? $totalValue / $totalQty : 0.0,
        ];
    }

    /**
     * Calculate total cost of goods consumed for an order.
     * Each item must provide actual_qty and unit_cost.
     */
    public function calculateOrderCogs(array $payload): array
    {
        $totalCogs = 0.0;
        $items = [];

        foreach (($payload['items'] ?? []) as $item) {
            $qty = (float) ($item['actual_qty'] ?? $item['quantity'] ?? 0);
            $unitCost = (float) ($item['unit_cost'] ?? 0);
            if ($qty < 0) {
                throw new \RuntimeException('Actual consumption quantity cannot be negative.');
            }

            $lineCogs = round($qty * $unitCost, 4);
            $totalCogs += $lineCogs;
            $items[] = [
                'actual_qty' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => $lineCogs,
            ];
        }

        return [
            'items' => $items,
            'total_cogs' => round($totalCogs, 4),
        ];
    }

    /**
     * Rebuild a material from the inventory movement ledger using moving
     * weighted-average costing. This preserves opening balances and all
     * subsequent consumption/adjustment effects.
     */
    public function rebuild(int $materialId): Material
    {
        if (!config('rouh.ledger_rebuild.allow_mutation', false)) {
            throw new \RuntimeException('Historical ledger rebuild is disabled. Use the inventory reconciliation workflow with an explicit production override.');
        }
        $projection = $this->project($materialId);
        /** @var Material $material */
        $material = Material::whereKey($materialId)->lockForUpdate()->firstOrFail();
        $material->current_stock = $projection['stock'];
        $material->avg_unit_cost = $projection['avg_cost'];
        $material->save();
        return $material->fresh();
    }

    public function project(int $materialId): array
    {
        $stock = 0.0;
        $avgCost = 0.0;
        $voidedPurchaseIds = Purchase::query()
            ->where('material_id', $materialId)
            ->where('status', 'voided')
            ->pluck('id')
            ->mapWithKeys(static fn ($id) => [(string) $id => true])
            ->all();
        $movements = InventoryMovement::where('material_id', $materialId)
            ->orderByRaw('COALESCE(movement_date, created_at) asc')
            ->orderBy('id')
            ->get(['quantity_base', 'quantity', 'movement_type', 'unit_cost', 'reference_type', 'reference_id', 'meta']);

        foreach ($movements as $movement) {
            $referenceType = strtolower(trim((string) $movement->reference_type));
            $referenceId = (string) $movement->reference_id;
            $meta = is_array($movement->meta) ? $movement->meta : [];
            if (($referenceType === 'purchase' && isset($voidedPurchaseIds[$referenceId]))
                || $referenceType === 'purchase_void'
                || !empty($meta['voids_purchase_movement'])) {
                continue;
            }
            $type = strtoupper((string) $movement->movement_type);
            if ($type === 'VALUE_ADJUSTMENT') {
                // VALUE_ADJUSTMENT intentionally carries zero quantity; its financial
                // effect is stored in meta.delta_value and changes carrying value/avg cost.
                $meta = is_array($movement->meta) ? $movement->meta : [];
                $deltaValue = (float) ($meta['delta_value'] ?? 0);
                if (abs($deltaValue) > 0.0001 && $stock > 0) {
                    $currentValue = $stock * $avgCost;
                    $newValue = $currentValue + $deltaValue;
                    if ($newValue < -0.0001) {
                        throw new \RuntimeException("Inventory ledger value for material {$materialId} would become negative.");
                    }
                    $avgCost = max(0.0, $newValue) / $stock;
                }
                continue;
            }

            $qty = abs((float) ($movement->quantity_base ?? $movement->quantity ?? 0));
            if ($qty <= 0) continue;
            $direction = InventoryMovementTypes::direction($type);
            $in = $direction === 'in';
            $out = $direction === 'out';
            if (!$in && !$out) continue;
            if ($in) {
                $value = ($stock * $avgCost) + ($qty * (float) ($movement->unit_cost ?? 0));
                $stock += $qty;
                $avgCost = $stock > 0 ? $value / $stock : 0.0;
            } else {
                $stock -= $qty;
                if ($stock < -0.0001) throw new \RuntimeException("Inventory ledger for material {$materialId} would become negative.");
                $stock = max(0.0, $stock);
            }
        }
        return ['stock' => round($stock, 4), 'avg_cost' => round($avgCost, 6)];
    }
}
