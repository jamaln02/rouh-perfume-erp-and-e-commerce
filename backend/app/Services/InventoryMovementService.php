<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\OrderConsumption;
use App\Services\FinancePostingService;
use Illuminate\Support\Facades\DB;

class InventoryMovementService
{
    public function __construct(private readonly UnitConversionService $units)
    {
    }

    /**
     * Create inventory movements for order consumption
     * Uses row-level locking for concurrent safety
     */
    public function createConsumptionMovements(string $orderConsumptionId): void
    {
        DB::transaction(function () use ($orderConsumptionId): void {
            $consumption = OrderConsumption::lockForUpdate()->findOrFail($orderConsumptionId);
            $consumption->load(['items.material']);
            $materialCost = 0.0;

            foreach ($consumption->items as $item) {
                $material = Material::where('id', $item->material_id)->lockForUpdate()->first();
                if (!$material) {
                    throw new \RuntimeException("Material not found: {$item->material_id}");
                }

                $actualQty = (float) $item->actual_qty;
                $actualQtyBase = $this->units->convert($actualQty, (string) $item->unit, (string) $material->base_unit);
                if ($actualQtyBase === null) {
                    throw new \RuntimeException(
                        "Unit mismatch for {$material->name}: {$item->unit} cannot be converted to {$material->base_unit}."
                    );
                }
                if ($actualQtyBase < 0) {
                    throw new \RuntimeException('Consumption quantity cannot be negative.');
                }

                $previousStock = (float) $material->current_stock;
                $newStock = $previousStock - $actualQtyBase;
                if ($newStock < -0.0001) {
                    throw new \RuntimeException(
                        "Insufficient stock for {$material->name}. Available: {$previousStock}, Required: {$actualQty}. Cannot go negative."
                    );
                }
                $newStock = max(0.0, $newStock);

                // The movement captures the exact moving-average cost at the moment
                // of consumption. This value is later used for an exact reversal.
                $lineCost = round((float) $material->avg_unit_cost * $actualQtyBase, 2);
                $materialCost += $lineCost;

                InventoryMovement::create([
                    'material_id' => $material->id,
                    'movement_type' => 'consumption',
                    'quantity' => $actualQtyBase,
                    'unit' => $material->base_unit,
                    'quantity_base' => $actualQtyBase,
                    'previous_stock' => $previousStock,
                    'new_stock' => $newStock,
                    'unit_cost' => round((float) $material->avg_unit_cost, 6),
                    'total_cost' => $lineCost,
                    'reference_type' => 'order_consumption',
                    'reference_id' => $consumption->id,
                    'notes' => 'Order preparation consumption',
                    'movement_date' => $consumption->prepared_at ?: now(),
                    'created_by' => $this->requireActorId(),
                ]);

                $material->current_stock = $newStock;
                $material->save();
            }

            $consumption->total_material_cost = round($materialCost, 4);
            $consumption->total_production_cost = round($materialCost + (float) $consumption->labor_cost + (float) $consumption->electricity_cost + (float) $consumption->overhead_cost, 4);
            $consumption->save();

            // Keep inventory mutation and its financial COGS journal in one atomic
            // unit. Nested DB::transaction calls are safe inside an existing
            // Laravel transaction and become savepoints where supported.
            if ($materialCost > 0) {
                app(FinancePostingService::class)->postMaterialConsumption(
                    $consumption->id,
                    $materialCost,
                    $consumption->order_id ? (string) $consumption->order_id : null
                );
            }
        });
    }


    /**
     * Cancel a production/order-consumption event atomically. Inventory is
     * restored at the exact unit cost captured by the original movements and
     * the material COGS journal is reversed rather than deleted.
     */
    public function cancelConsumption(string $orderConsumptionId, string $reason = 'Production cancellation'): void
    {
        DB::transaction(function () use ($orderConsumptionId, $reason): void {
            $consumption = OrderConsumption::lockForUpdate()->findOrFail($orderConsumptionId);
            if ($consumption->status === 'cancelled') return;
            if ($consumption->status === 'completed') {
                throw new \RuntimeException('Completed production cannot be cancelled. Create an approved correction/return instead.');
            }

            $movements = InventoryMovement::where('reference_type', 'order_consumption')
                ->where('reference_id', $consumption->id)
                ->whereIn('movement_type', ['consumption'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $alreadyReversed = InventoryMovement::where('reference_type', 'order_consumption_cancellation')
                ->where('reference_id', $consumption->id)
                ->exists();

            if (!$alreadyReversed) {
                foreach ($movements as $movement) {
                    $qty = (float) ($movement->quantity_base ?? $movement->quantity ?? 0);
                    if ($qty <= 0 || !$movement->material_id) continue;
                    $material = Material::whereKey($movement->material_id)->lockForUpdate()->firstOrFail();
                    $previous = (float) $material->current_stock;
                    $previousAvg = (float) $material->avg_unit_cost;
                    $returnedValue = max(0.0, (float) $movement->total_cost);
                    $newStock = $previous + $qty;
                    $newValue = ($previous * $previousAvg) + $returnedValue;
                    $newAvg = $newStock > 0 ? round($newValue / $newStock, 6) : 0.0;
                    InventoryMovement::create([
                        'material_id' => $material->id,
                        'movement_type' => 'consumption_reversal',
                        'quantity' => $qty,
                        'unit' => $material->base_unit,
                        'quantity_base' => $qty,
                        'previous_stock' => $previous,
                        'new_stock' => $newStock,
                        'unit_cost' => (float) $movement->unit_cost,
                        'total_cost' => (float) $movement->total_cost,
                        'reference_type' => 'order_consumption_cancellation',
                        'reference_id' => (string) $consumption->id,
                        'notes' => $reason,
                        'movement_date' => now(),
                        'created_by' => $this->requireActorId(),
                    ]);
                    $material->current_stock = $newStock;
                    $material->avg_unit_cost = $newAvg;
                    $material->save();
                }
            }

            app(FinancePostingService::class)->reverseMaterialConsumption(
                (string) $consumption->id,
                $reason
            );

            $consumption->update([
                'status' => 'cancelled',
                'confirmed_at' => null,
                'confirmed_by' => null,
            ]);
        });
    }

    /**
     * Reverse a completed Made-to-Order consumption as an approved order
     * cancellation/reissue. The historical consumption remains immutable.
     */
    public function reverseCompletedConsumption(string $orderConsumptionId, string $reason = 'Order cancellation'): void
    {
        DB::transaction(function () use ($orderConsumptionId, $reason): void {
            $consumption = OrderConsumption::lockForUpdate()->findOrFail($orderConsumptionId);
            if ($consumption->status === 'cancelled') return;

            $movements = InventoryMovement::where('reference_type', 'order_consumption')
                ->where('reference_id', $consumption->id)
                ->where('movement_type', 'consumption')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $alreadyReversed = InventoryMovement::where('reference_type', 'order_consumption_cancellation')
                ->where('reference_id', (string) $consumption->id)
                ->exists();

            if (!$alreadyReversed) {
                foreach ($movements as $movement) {
                    $qty = (float) ($movement->quantity_base ?? $movement->quantity ?? 0);
                    if ($qty <= 0 || !$movement->material_id) continue;
                    $material = Material::whereKey($movement->material_id)->lockForUpdate()->firstOrFail();
                    $previous = (float) $material->current_stock;
                    $previousAvg = (float) $material->avg_unit_cost;
                    $returnedValue = max(0.0, (float) $movement->total_cost);
                    $newStock = $previous + $qty;
                    $newValue = ($previous * $previousAvg) + $returnedValue;
                    $newAvg = $newStock > 0 ? round($newValue / $newStock, 6) : 0.0;
                    InventoryMovement::create([
                        'material_id' => $material->id,
                        'movement_type' => 'consumption_reversal',
                        'quantity' => $qty,
                        'unit' => $movement->unit ?: $material->base_unit,
                        'quantity_base' => $qty,
                        'previous_stock' => $previous,
                        'new_stock' => $newStock,
                        'unit_cost' => (float) $movement->unit_cost,
                        'total_cost' => (float) $movement->total_cost,
                        'reference_type' => 'order_consumption_cancellation',
                        'reference_id' => (string) $consumption->id,
                        'notes' => $reason,
                        'movement_date' => now(),
                        'created_by' => $this->requireActorId(),
                    ]);
                    $material->current_stock = $newStock;
                    $material->avg_unit_cost = $newAvg;
                    $material->save();
                }
            }

            app(FinancePostingService::class)->reverseMaterialConsumption(
                (string) $consumption->id,
                $reason
            );

            $consumption->update([
                'status' => 'cancelled',
                'confirmed_at' => null,
                'confirmed_by' => null,
                'notes' => trim(($consumption->notes ?? '') . "\n" . $reason),
            ]);
        });
    }

    private function requireActorId(): int
    {
        $actorId = (int) (request()->attributes->get('authUser')?->id ?? 0);
        if ($actorId <= 0) {
            throw new \RuntimeException('Authenticated staff context is required for inventory movements.');
        }
        return $actorId;
    }

    /**
     * Calculate total material cost from actual consumption
     */
    public function calculateTotalMaterialCost(string $orderConsumptionId): float
    {
        $totalCost = (float) InventoryMovement::query()
            ->where('reference_type', 'order_consumption')
            ->where('reference_id', $orderConsumptionId)
            ->where('movement_type', 'consumption')
            ->sum('total_cost');
        return round($totalCost, 4);
    }
}
