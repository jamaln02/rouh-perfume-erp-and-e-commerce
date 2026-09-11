<?php

namespace App\Services;

class OrderConsumptionService
{
    /**
     * Build expected consumption for an order item based on a recipe and quantity.
     */
    public function buildExpectedConsumption(array $payload): array
    {
        $orderItem = $payload['order_item'] ?? [];
        $recipe = $payload['recipe'] ?? [];
        $quantity = (int) ($orderItem['quantity'] ?? 1);

        $items = [];

        foreach ($recipe['items'] ?? [] as $item) {
            $expectedQty = (float) ($item['expected_qty'] ?? 0);
            $ruleType = $item['consumption_rule_type'] ?? 'fixed';

            if ($ruleType === 'packaging_rule') {
                $config = is_array($item['rule_config'] ?? null) ? $item['rule_config'] : [];
                $perUnit = (float) ($config['per_unit'] ?? 1);
                $rounded = (int) ceil($quantity / $perUnit);
                $expectedQty = (float) $rounded;
            } else {
                $expectedQty = $expectedQty * $quantity;
            }

            $items[] = [
                'material_id' => $item['material_id'] ?? null,
                'material_name' => $item['material_name'] ?? null,
                'expected_qty' => $expectedQty,
                'unit' => $item['unit'] ?? 'pcs',
                'consumption_rule_type' => $ruleType,
                'is_packaging' => (bool) ($item['is_packaging'] ?? false),
                'is_optional' => (bool) ($item['is_optional'] ?? false),
                'notes' => $item['notes'] ?? null,
            ];
        }

        return [
            'order_item_quantity' => $quantity,
            'items' => $items,
        ];
    }

    /**
     * Confirm actual consumption and prepare the payload that the controller can persist.
     */
    public function confirm(array $payload): array
    {
        $orderId = $payload['order_id'] ?? null;
        $consumptionItems = $payload['consumption_items'] ?? [];

        if (!$orderId) {
            return ['ok' => false, 'message' => 'Order id is required.'];
        }

        $created = [];

        foreach ($consumptionItems as $item) {
            $created[] = [
                'material_id' => $item['material_id'] ?? null,
                'expected_qty' => (float) ($item['expected_qty'] ?? 0),
                'actual_qty' => (float) ($item['actual_qty'] ?? 0),
                'variance_qty' => (float) (($item['actual_qty'] ?? 0) - ($item['expected_qty'] ?? 0)),
                'unit' => $item['unit'] ?? 'pcs',
                'notes' => $item['notes'] ?? null,
            ];
        }

        return [
            'ok' => true,
            'order_id' => $orderId,
            'items' => $created,
        ];
    }
}
