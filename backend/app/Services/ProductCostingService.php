<?php

namespace App\Services;

use App\Models\Material;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;

/**
 * Calculates the standard estimated cost of a sellable product variant.
 *
 * Cost is always based on the active recipe and the current weighted-average
 * cost stored on the unified materials ledger. Selling price is deliberately
 * kept separate from cost: the two numbers have different accounting roles.
 */
class ProductCostingService
{
    public function __construct(
        private readonly UnitConversionService $units,
        private readonly ConsumptionRuleService $consumptionRules,
    ) {
    }

    public function calculateVariant(ProductVariant $variant): array
    {
        $variant->loadMissing([
            'product',
            'recipes' => fn ($q) => $q->where('is_active', true)->latest('version')->with('items.material'),
        ]);

        /** @var Recipe|null $recipe */
        $recipe = $variant->recipes->first();

        if (!$recipe) {
            return [
                'size_label' => $variant->size_label,
                'volume_ml' => $variant->volume_ml !== null ? (float) $variant->volume_ml : null,
                'selling_price' => $variant->selling_price_default !== null ? (float) $variant->selling_price_default : null,
                'estimated_cost' => 0.0,
                'gross_profit' => null,
                'margin_percentage' => null,
                'has_recipe' => false,
                'has_missing_costs' => false,
                'missing_materials' => [],
                'breakdown' => [],
            ];
        }

        $volumeMl = $variant->volume_ml !== null ? (float) $variant->volume_ml : null;
        $oilGrams = $this->estimateOilGrams($recipe, $volumeMl);

        $total = 0.0;
        $missing = [];
        $breakdown = [];

        foreach ($recipe->items as $item) {
            if (!$item->material || $item->is_optional) {
                continue;
            }

            $material = $item->material;
            $quantity = $this->quantityPerBottle($item, $volumeMl, $oilGrams);
            if ($quantity <= 0) {
                continue;
            }

            $convertedQty = $this->convertToBaseUnit($quantity, (string) $item->unit, (string) $material->base_unit);
            if ($convertedQty === null) {
                $missing[] = $material->name;
                continue;
            }

            $unitCost = $this->unitCostInSyp($material);
            $lineCost = round($convertedQty * $unitCost, 4);
            $total += $lineCost;

            if ($unitCost <= 0) {
                $missing[] = $material->name;
            }

            $breakdown[] = [
                'material_id' => (int) $material->id,
                'material_name' => $material->name,
                'material_name_ar' => $material->name_ar,
                'category' => $material->material_category,
                'quantity' => round($convertedQty, 4),
                'unit' => $material->base_unit,
                'unit_cost' => round($unitCost, 4),
                'total_cost' => $lineCost,
            ];
        }

        $sellingPrice = $variant->selling_price_default !== null
            ? (float) $variant->selling_price_default
            : null;
        $grossProfit = $sellingPrice !== null ? round($sellingPrice - $total, 4) : null;
        $margin = ($sellingPrice !== null && $sellingPrice > 0)
            ? round(($grossProfit / $sellingPrice) * 100, 2)
            : null;

        return [
            'size_label' => $variant->size_label,
            'volume_ml' => $volumeMl,
            'selling_price' => $sellingPrice,
            'estimated_cost' => round($total, 4),
            'gross_profit' => $grossProfit,
            'margin_percentage' => $margin,
            'has_recipe' => true,
            'has_missing_costs' => count(array_unique($missing)) > 0,
            'missing_materials' => array_values(array_unique($missing)),
            'recipe_id' => $recipe->id,
            'recipe_version' => (int) $recipe->version,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Return the standard cost for every active variant of a product.
     */
    public function calculateProduct($product): array
    {
        $product->loadMissing('variants');

        return $product->variants
            ->filter(fn (ProductVariant $variant) => (bool) $variant->is_active)
            ->sortBy(function (ProductVariant $variant) {
                return $variant->volume_ml !== null ? (float) $variant->volume_ml : PHP_INT_MAX;
            })
            ->map(fn (ProductVariant $variant) => $this->calculateVariant($variant))
            ->values()
            ->all();
    }

    private function estimateOilGrams(Recipe $recipe, ?float $volumeMl): float
    {
        $grams = 0.0;

        foreach ($recipe->items as $item) {
            $material = $item->material;
            if (!$material || $item->is_optional) {
                continue;
            }

            if (($material->material_category ?? '') !== 'perfume_oil') {
                continue;
            }

            $qty = $this->quantityPerBottle($item, $volumeMl, null);
            $converted = $this->convertToBaseUnit($qty, (string) $item->unit, (string) $material->base_unit);
            if ($converted !== null && $this->normaliseUnit($material->base_unit) === 'g') {
                $grams += $converted;
            }
        }

        return $grams;
    }

    private function quantityPerBottle(RecipeItem $item, ?float $volumeMl, ?float $oilGrams): float
    {
        $qty = (float) $item->expected_qty;
        $rule = (string) ($item->consumption_rule_type ?? 'fixed');
        $config = is_array($item->rule_config) ? $item->rule_config : [];

        if ($rule === 'percentage' && $volumeMl !== null) {
            return max(0.0, ($qty / 100) * $volumeMl);
        }

        if ($rule === 'per_bottle') {
            if (($config['formula'] ?? null) === 'bottle_minus_oil' && $volumeMl !== null) {
                return $this->consumptionRules->calculateResidualAlcoholMl($volumeMl, (float) ($oilGrams ?? $qty));
            }
            return max(0.0, $qty);
        }

        if ($rule === 'packaging_rule') {
            $perUnit = (float) ($config['per_unit'] ?? 1);
            return $perUnit > 0 ? max(0.0, $qty / $perUnit) : max(0.0, $qty);
        }

        return max(0.0, $qty);
    }

    private function unitCostInSyp(Material $material): float
    {
        // avg_unit_cost is maintained by the inventory ledger in SYP.
        return max(0.0, (float) ($material->avg_unit_cost ?? 0));
    }

    private function convertToBaseUnit(float $quantity, string $from, string $to): ?float
    {
        return $this->units->convert($quantity, $from, $to);
    }

    private function normaliseUnit(string $unit): string
    {
        return $this->units->normalise($unit);
    }
}

