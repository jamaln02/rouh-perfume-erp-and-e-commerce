<?php

namespace App\Services;

class InventoryStockService
{
    /**
     * Validate that requested consumption quantities do not exceed the current stock of each material.
     *
     * @param array<int, array<string, mixed>> $consumptionItems
     * @param array<int, array<string, mixed>|object> $materials
     * @return array{ok: bool, errors: array<int, array<string, mixed>>}
     */
    public function validateAvailability(array $consumptionItems, array $materials): array
    {
        $errors = [];

        foreach ($consumptionItems as $item) {
            $materialId = $item['material_id'] ?? null;
            if (!$materialId) {
                continue;
            }

            $material = null;
            foreach ($materials as $entry) {
                $id = $entry['id'] ?? ($entry->id ?? null);
                if ((string) $id === (string) $materialId) {
                    $material = $entry;
                    break;
                }
            }

            if (!$material) {
                continue;
            }

            $availableStock = (float) ($material['current_stock'] ?? ($material->current_stock ?? 0));
            $requestedQty = (float) ($item['actual_qty'] ?? ($item['expected_qty'] ?? 0));

            if ($requestedQty > $availableStock) {
                $errors[] = [
                    'material_id' => $materialId,
                    'material_name' => $material['name'] ?? ($material->name ?? 'Material'),
                    'requested_qty' => $requestedQty,
                    'available_stock' => $availableStock,
                ];
            }
        }

        return [
            'ok' => empty($errors),
            'errors' => $errors,
        ];
    }
}
