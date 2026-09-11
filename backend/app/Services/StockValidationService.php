<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Recipe;

class StockValidationService
{
    public function validateOrderRecipe(string $orderId, array $overrides = [], array $actual = [], ?string $actorRole = null, string $reason = ''): array
    {
        $order = Order::with(['items'])->findOrFail($orderId);
        $issues = [];
        $warnings = [];
        $normalizedOverrides = $this->normalizeOverrides($overrides);

        foreach ($order->items as $item) {
            $resolved = $this->resolveVariantAndRecipe($item);
            $productName = $item->product_name ?: ($resolved['product']?->name ?? 'Unknown product');
            if (!$resolved['variant']) {
                $issues[] = ['order_item_id' => $item->id, 'message' => "No valid product variant found for {$productName}.", 'severity' => 'block'];
            } elseif (!$resolved['recipe']) {
                $issues[] = ['order_item_id' => $item->id, 'product_id' => $resolved['product']?->id, 'product_variant_id' => $resolved['variant']->id, 'message' => "No recipe is assigned to {$productName}" . ($item->size ? " ({$item->size})" : '') . '.', 'severity' => 'block'];
            }
        }

        try {
            $reqs = $this->aggregateMaterialRequirements($order, $normalizedOverrides);
        } catch (\Throwable $e) {
            $issues[] = ['message' => $e->getMessage(), 'severity' => 'block'];
            return ['can_proceed' => false, 'issues' => $issues, 'warnings' => $warnings, 'material_requirements' => []];
        }

        $allowedIds = array_map('intval', array_keys($reqs));
        foreach ($actual as $id => $qty) {
            if (!is_numeric($qty) || (float) $qty < 0) {
                $issues[] = ['material_id' => $id, 'message' => 'Actual quantity must be a non-negative number.', 'severity' => 'block'];
                continue;
            }
            if (!in_array((int) $id, $allowedIds, true)) {
                $issues[] = ['material_id' => $id, 'message' => 'Material is not part of the server-calculated recipe for this order.', 'severity' => 'block'];
            }
        }

        foreach ($reqs as $r) {
            $m = $r['material'];
            if (!(new ConsumptionRuleService())->unitsCompatible($r['unit'], $m->base_unit)) {
                $issues[] = ['material_id' => $m->id, 'message' => "Unit mismatch: recipe {$r['unit']} / material {$m->base_unit}", 'severity' => 'block'];
                continue;
            }

            $required = array_key_exists((string) $r['material_id'], $actual)
                ? (float) $actual[(string) $r['material_id']]
                : (float) $r['expected_qty'];
            $expected = (float) $r['expected_qty'];
            $variance = $required - $expected;
            if (abs($variance) > 0.0001 && trim($reason) === '') {
                $issues[] = ['material_id' => $m->id, 'message' => 'A reason is required whenever actual consumption differs from the server-calculated recipe.', 'severity' => 'block'];
            }
            if ($expected > 0 && abs($variance) / $expected * 100 > (float) config('rouh.order.max_consumption_variance_percent', 10) && !in_array($actorRole, ['admin', 'manager'], true)) {
                $issues[] = ['material_id' => $m->id, 'message' => 'Consumption variance exceeds the employee approval threshold.', 'severity' => 'block'];
            }

            $available = (float) $m->current_stock;
            $baseRequired = (new UnitConversionService())->convert($required, $r['unit'], $m->base_unit);
            if ($baseRequired === null) {
                $issues[] = ['material_id' => $m->id, 'message' => 'Unable to convert requested consumption to the material base unit.', 'severity' => 'block'];
                continue;
            }
            if ($available + 0.0001 < $baseRequired) {
                $issues[] = ['material_id' => $m->id, 'material_name' => $m->name, 'required' => $baseRequired, 'available' => $available, 'unit' => $m->base_unit, 'severity' => 'block', 'message' => "Insufficient stock. Required: {$baseRequired}, Available: {$available}"];
            } elseif ($available <= (float) $m->min_stock) {
                $warnings[] = ['material_id' => $m->id, 'material_name' => $m->name, 'required' => $baseRequired, 'available' => $available, 'min_stock' => $m->min_stock, 'unit' => $m->base_unit, 'severity' => 'warning', 'message' => "Low stock warning. Available: {$available}, Min: {$m->min_stock}"];
            }
        }

        return ['can_proceed' => empty($issues), 'issues' => $issues, 'warnings' => $warnings, 'material_requirements' => array_values($reqs)];
    }

    public function aggregateMaterialRequirements(Order $order, array $overrides = []): array
    {
        $out = [];
        $overrides = $this->normalizeOverrides($overrides);
        $units = app(UnitConversionService::class);

        foreach ($order->items as $item) {
            $resolved = $this->resolveVariantAndRecipe($item);
            $variant = $resolved['variant'];
            $recipe = $resolved['recipe'];
            if (!$variant || !$recipe) continue;

            $customMix = $this->normaliseCustomOilMix($item);
            $hasCustomMix = count($customMix) > 0;

            // When a customer orders a custom blend, the order-line oil mix is the
            // authoritative fragrance composition for this preparation. Do not also
            // consume the variant recipe's perfume-oil rows; otherwise the oils are
            // double-counted. Packaging/other recipe lines still apply.
            foreach ($recipe->items as $ri) {
                $recipeMaterial = $ri->material;
                if ($hasCustomMix && in_array((string) $recipeMaterial?->material_category, ['perfume_oil', 'alcohol'], true)) {
                    continue;
                }

                $original = (int) $ri->material_id;
                $mid = (int) ($overrides[$original] ?? $original);
                if ($mid !== $original && !$ri->allow_manual_override) {
                    throw new \InvalidArgumentException("Manual override is not allowed for material {$original}.");
                }
                $material = Material::whereKey($mid)->where('is_active', true)->firstOrFail();
                $qtyPerUnit = (new ConsumptionRuleService())->calculateExpectedQty(
                    $ri,
                    $variant->volume_ml !== null ? (float) $variant->volume_ml : null
                );
                $qty = $qtyPerUnit * max(1, (int) $item->quantity);
                $baseQty = $units->convert($qty, (string) $ri->unit, (string) $material->base_unit);
                if ($baseQty === null) {
                    throw new \InvalidArgumentException(
                        "Unit mismatch: recipe {$ri->unit} cannot be converted to material {$material->base_unit}."
                    );
                }

                $key = (string) $mid;
                if (!isset($out[$key])) {
                    $out[$key] = [
                        'material' => $material,
                        'material_id' => $mid,
                        'expected_qty' => 0.0,
                        'unit' => (string) $material->base_unit,
                        'items' => [],
                        'allow_manual_override' => true,
                    ];
                }
                $out[$key]['expected_qty'] += (float) $baseQty;
                $out[$key]['allow_manual_override'] = $out[$key]['allow_manual_override'] && (bool) $ri->allow_manual_override;
                $out[$key]['items'][] = [
                    'order_item_id' => $item->id,
                    'recipe_item_id' => $ri->id,
                    'original_material_id' => $original,
                    'expected_qty' => $baseQty,
                    'quantity' => (int) $item->quantity,
                    'recipe_unit' => (string) $ri->unit,
                    'base_unit' => (string) $material->base_unit,
                ];
            }
        }

        // Custom blend: consume exactly the oil grams entered on the order line,
        // multiplied by the ordered bottle quantity, plus the remaining bottle
        // volume as alcohol. This keeps the recipe reusable while allowing a
        // customer-specific two-oil/three-oil composition.
        foreach ($order->items as $item) {
            $customMix = $this->normaliseCustomOilMix($item);
            if (!$customMix) continue;

            $resolved = $this->resolveVariantAndRecipe($item);
            $variant = $resolved['variant'];
            if (!$variant || $variant->volume_ml === null) {
                throw new \InvalidArgumentException('A custom blend requires a product variant with a bottle volume.');
            }

            $bottleCount = max(1, (int) $item->quantity);
            $oilGramsPerBottle = array_sum(array_map(fn ($mix) => (float) $mix['grams'], $customMix));
            if ($oilGramsPerBottle <= 0 || $oilGramsPerBottle > (float) $variant->volume_ml + 0.0001) {
                throw new \InvalidArgumentException(
                    "Custom oil mix for {$item->product_name} exceeds the bottle volume or has no oil quantity."
                );
            }

            foreach ($customMix as $mix) {
                $material = Material::query()
                    ->whereKey((int) $mix['oil_id'])
                    ->where('material_category', 'perfume_oil')
                    ->where('is_active', true)
                    ->firstOrFail();
                $baseQty = $units->convert((float) $mix['grams'] * $bottleCount, 'g', (string) $material->base_unit);
                if ($baseQty === null) throw new \InvalidArgumentException("Unit mismatch for custom blend oil {$material->name}.");
                $key = (string) $material->id;
                if (!isset($out[$key])) {
                    $out[$key] = [
                        'material' => $material, 'material_id' => (int) $material->id, 'expected_qty' => 0.0,
                        'unit' => (string) $material->base_unit, 'items' => [], 'allow_manual_override' => true,
                    ];
                }
                $out[$key]['expected_qty'] += $baseQty;
                $out[$key]['items'][] = [
                    'order_item_id' => $item->id, 'recipe_item_id' => 0, 'original_material_id' => (int) $material->id,
                    'expected_qty' => $baseQty, 'quantity' => $bottleCount, 'recipe_unit' => 'g', 'base_unit' => (string) $material->base_unit,
                    'custom_blend' => true,
                ];
            }

            $alcohol = Material::query()->where('material_category', 'alcohol')->where('is_active', true)->orderBy('id')->first();
            if (!$alcohol) throw new \InvalidArgumentException('No active alcohol material is configured for custom blends.');
            $alcoholMl = app(ConsumptionRuleService::class)->calculateResidualAlcoholMl((float) $variant->volume_ml, $oilGramsPerBottle) * $bottleCount;
            $baseAlcohol = $units->convert($alcoholMl, 'ml', (string) $alcohol->base_unit);
            if ($baseAlcohol === null) throw new \InvalidArgumentException("Unit mismatch for alcohol material {$alcohol->name}.");
            $key = (string) $alcohol->id;
            if (!isset($out[$key])) {
                $out[$key] = [
                    'material' => $alcohol, 'material_id' => (int) $alcohol->id, 'expected_qty' => 0.0,
                    'unit' => (string) $alcohol->base_unit, 'items' => [], 'allow_manual_override' => true,
                ];
            }
            $out[$key]['expected_qty'] += $baseAlcohol;
            $out[$key]['items'][] = [
                'order_item_id' => $item->id, 'recipe_item_id' => 0, 'original_material_id' => (int) $alcohol->id,
                'expected_qty' => $baseAlcohol, 'quantity' => $bottleCount, 'recipe_unit' => 'ml', 'base_unit' => (string) $alcohol->base_unit,
                'custom_blend' => true,
            ];
        }

        foreach ($out as &$row) {
            $row['expected_qty'] = round((float) $row['expected_qty'], 6);
        }
        unset($row);
        return $out;
    }

    private function normaliseCustomOilMix(OrderItem $item): array
    {
        $raw = $item->oil_mix;
        if (!is_array($raw)) return [];
        $result = [];
        foreach ($raw as $mix) {
            if (!is_array($mix)) continue;
            $oilId = (int) ($mix['oil_id'] ?? 0);
            $grams = (float) ($mix['grams'] ?? 0);
            if ($oilId <= 0 || $grams <= 0) continue;
            $result[] = ['oil_id' => $oilId, 'grams' => $grams, 'percentage' => (float) ($mix['percentage'] ?? 0)];
        }
        return $result;
    }

    private function normalizeOverrides(array $overrides): array
    {
        $normalized = [];
        foreach ($overrides as $original => $replacement) {
            if (!is_numeric($original) || !is_numeric($replacement) || (int) $original <= 0 || (int) $replacement <= 0) throw new \InvalidArgumentException('Material override IDs must be positive integers.');
            $normalized[(int) $original] = (int) $replacement;
        }
        return $normalized;
    }

    private function resolveRecipeForOrderItem(OrderItem $item, ProductVariant $variant): ?Recipe
    {
        if (!empty($item->recipe_id)) {
            return Recipe::with('items.material')->whereKey($item->recipe_id)->where('product_variant_id', $variant->id)->first();
        }
        return $variant->activeRecipe()->with('items.material')->first();
    }

    private function resolveVariantAndRecipe(OrderItem $item): array
    {
        $product = $item->product_id ? Product::find($item->product_id) : null;
        if (!$product) return ['product' => null, 'variant' => null, 'recipe' => null];
        $variant = $item->product_variant_id ? ProductVariant::whereKey($item->product_variant_id)->where('product_id', $product->id)->where('is_active', true)->first() : null;
        if (!$variant && $item->size) $variant = $product->variants()->where('size_label', $item->size)->where('is_active', true)->first();
        return ['product' => $product, 'variant' => $variant, 'recipe' => $variant ? $this->resolveRecipeForOrderItem($item, $variant) : null];
    }

    public function validateActualQuantities(array $actual): array
    {
        $errors = [];
        foreach ($actual as $id => $qty) if (!is_numeric($qty) || (float) $qty < 0) $errors[] = ['material_id' => $id, 'message' => 'Actual quantity must be a non-negative number'];
        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function combinedPreparationRequirements(Order $order, array $overrides = []): array
    {
        $buckets = $this->perProductMaterialRequirements($order, $overrides);
        $groups = [];
        foreach ($buckets as $bucket) foreach ($bucket['materials'] as $material) {
            $key = (int) $material['material_id'] . '|' . (string) $material['unit'];
            if (!isset($groups[$key])) $groups[$key] = ['key'=>$key,'material'=>$material['material'],'material_id'=>(int)$material['material_id'],'unit'=>(string)$material['unit'],'expected_qty'=>0,'allow_manual_override'=>true,'contributions'=>[]];
            $groups[$key]['expected_qty'] += (float) $material['expected_qty'];
            $groups[$key]['allow_manual_override'] = $groups[$key]['allow_manual_override'] && (bool)$material['allow_manual_override'];
            $groups[$key]['contributions'][] = ['order_item_id'=>$bucket['order_item_id'],'product_id'=>$bucket['product_id'],'product_name'=>$bucket['product_name'],'size'=>$bucket['size'],'bottle_shape'=>$bucket['variant']['bottle_shape'] ?? null,'quantity'=>$bucket['quantity'],'expected_qty'=>(float)$material['expected_qty'],'recipe_item_id'=>$material['recipe_item_id'],'original_material_id'=>$material['original_material_id']];
        }
        return ['mode'=>'combined','lines'=>array_values($groups),'product_count'=>count($buckets),'shared_line_count'=>count(array_filter($groups, fn($g)=>count($g['contributions'])>1)),'product_lines'=>$buckets];
    }

    public function perProductMaterialRequirements(Order $order, array $overrides = []): array
    {
        $buckets = [];
        foreach ($order->items as $item) {
            $resolved = $this->resolveVariantAndRecipe($item); $product=$resolved['product']; $variant=$resolved['variant']; $recipe=$resolved['recipe'];
            if (!$product || !$variant || !$recipe) continue;
            $oilPct=(float)($recipe->oil_percentage ?? 32); $alcPct=100-$oilPct; $volMl=$variant->volume_ml!==null?(float)$variant->volume_ml:null;
            $materials=[];
            $customMix = $this->normaliseCustomOilMix($item);
            $hasCustomMix = count($customMix) > 0;
            foreach ($recipe->items as $ri) {
                if ($hasCustomMix && in_array((string) $ri->material?->material_category, ['perfume_oil', 'alcohol'], true)) {
                    continue;
                }
                $original=(int)$ri->material_id; $mid=(int)($overrides[$original]??$original);
                if ($mid!==$original && !$ri->allow_manual_override) throw new \InvalidArgumentException("Manual override is not allowed for material {$original}.");
                $material=Material::whereKey($mid)->where('is_active',true)->firstOrFail();
                $qty=(new ConsumptionRuleService())->calculateExpectedQty($ri,$volMl)*max(1,(int)$item->quantity);
                $materials[]=['material'=>$material,'material_id'=>$mid,'original_material_id'=>$original,'expected_qty'=>$qty,'unit'=>$ri->unit,'allow_manual_override'=>(bool)$ri->allow_manual_override,'recipe_item_id'=>$ri->id];
            }

            if ($hasCustomMix) {
                $units = app(UnitConversionService::class);
                $bottleCount = max(1, (int)$item->quantity);
                $oilGrams = array_sum(array_map(fn($mix)=>(float)$mix['grams'], $customMix));
                foreach ($customMix as $mix) {
                    $material = Material::query()->whereKey((int)$mix['oil_id'])->where('material_category','perfume_oil')->where('is_active',true)->firstOrFail();
                    $baseQty = $units->convert((float)$mix['grams']*$bottleCount,'g',(string)$material->base_unit);
                    if ($baseQty === null) throw new \InvalidArgumentException("Unit mismatch for custom blend oil {$material->name}.");
                    $materials[]=['material'=>$material,'material_id'=>(int)$material->id,'original_material_id'=>(int)$material->id,'expected_qty'=>$baseQty,'unit'=>(string)$material->base_unit,'allow_manual_override'=>true,'recipe_item_id'=>0,'custom_blend'=>true];
                }
                $alcohol = Material::query()->where('material_category','alcohol')->where('is_active',true)->orderByRaw("CASE WHEN code = 'ALC-ETHANOL-1L' THEN 0 WHEN name_ar = 'كحول ايثانول' THEN 1 ELSE 2 END")->orderBy('id')->firstOrFail();
                $alcoholMl = app(ConsumptionRuleService::class)->calculateResidualAlcoholMl((float) $variant->volume_ml, $oilGrams) * $bottleCount;
                $alcoholBase = $units->convert($alcoholMl, 'ml', (string)$alcohol->base_unit);
                if ($alcoholBase === null) throw new \InvalidArgumentException("Unit mismatch for alcohol material {$alcohol->name}.");
                $materials[]=['material'=>$alcohol,'material_id'=>(int)$alcohol->id,'original_material_id'=>(int)$alcohol->id,'expected_qty'=>$alcoholBase,'unit'=>(string)$alcohol->base_unit,'allow_manual_override'=>true,'recipe_item_id'=>0,'custom_blend'=>true];
            }
            $buckets[]=['order_item_id'=>$item->id,'product_id'=>$product->id,'product_name'=>$item->product_name?:($product->name_ar??$product->name),'size'=>$item->size,'quantity'=>(int)$item->quantity,'variant'=>['id'=>$variant->id,'size_label'=>$variant->size_label,'volume_ml'=>$variant->volume_ml,'bottle_shape'=>$variant->bottle_shape],'recipe'=>['id'=>$recipe->id,'version'=>$recipe->version,'is_active'=>(bool)$recipe->is_active,'notes'=>$recipe->notes],'oil_percentage'=>$oilPct,'alcohol_percentage'=>$alcPct,'oil_ml'=>$volMl!==null?$volMl*$oilPct/100:null,'alcohol_ml'=>$volMl!==null?$volMl*$alcPct/100:null,'materials'=>$materials];
        }
        return $buckets;
    }
}
