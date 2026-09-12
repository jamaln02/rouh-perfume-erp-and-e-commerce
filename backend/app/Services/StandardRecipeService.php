<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductMaterialMapping;
use App\Models\ProductVariant;
use App\Models\Recipe;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class StandardRecipeService
{
    /**
     * Create a starter recipe for a variant without ever creating inventory materials.
     * Every material must already exist in the Materials/Inventory module.
     */
    public function ensureForVariant(ProductVariant $variant, bool $forceReplace = false): Recipe
    {
        $variant->loadMissing('product');

        $active = $variant->recipes()->where('is_active', true)->latest('version')->first();
        if ($active && !$forceReplace) {
            return $active->load(['variant.product', 'items.material']);
        }

        if ($active && $forceReplace) {
            $active->update(['is_active' => false]);
        }

        $version = ((int) $variant->recipes()->max('version')) + 1;
        $recipe = $variant->recipes()->create([
            'id' => (string) Str::uuid(),
            'version' => max(1, $version),
            'is_active' => true,
            'oil_percentage' => 32,
            'alcohol_percentage' => 68,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'notes' => 'وصفة قابلة للتعديل. المواد يجب أن تكون من المواد الموجودة في المخزون.',
            'created_by' => Auth::id(),
        ]);

        foreach ($this->materialIdsForVariant($variant) as $sort => $materialId) {
            $material = Material::find($materialId);
            if (!$material) {
                continue;
            }

            $recipe->items()->create([
                'material_id' => $material->id,
                'expected_qty' => $this->defaultQuantity($material, $variant),
                'unit' => $material->base_unit,
                'consumption_rule_type' => 'fixed',
                'rule_config' => [],
                'is_optional' => false,
                'allow_manual_override' => true,
                'sort_order' => $sort + 1,
                'notes' => null,
            ]);
        }

        return $recipe->load(['variant.product', 'items.material']);
    }

    private function defaultQuantity(Material $material, ProductVariant $variant): float
    {
        $volumeMl = max(0.0, (float) ($variant->volume_ml ?? 0));

        return match ($material->material_category) {
            'perfume_oil' => round($volumeMl * 0.32, 4),
            'alcohol' => round($volumeMl * 0.68, 4),
            'packaging' => 1.0,
            default => 0.0,
        };
    }

    /**
     * Resolve the starter recipe materials from inventory only.
     * No fallback material is created by this service.
     */
    public function materialIdsForVariant(ProductVariant $variant): array
    {
        $variant->loadMissing('product');

        $ids = [];

        if ($oil = $this->findProductOil($variant->product)) {
            $ids[] = $oil->id;
        }
        if ($alcohol = $this->findAlcohol()) {
            $ids[] = $alcohol->id;
        }
        if ($bottle = $this->findBottleForVariant($variant)) {
            $ids[] = $bottle->id;
        }
        if ($box = $this->findNamedPackaging('علبة روح كرتون', 'ROUH Cardboard Box', 'box')) {
            $ids[] = $box->id;
        }
        if ($bag = $this->findNamedPackaging('كيس روح كرتون', 'ROUH Paper Bag', 'bag')) {
            $ids[] = $bag->id;
        }

        return array_values(array_unique($ids));
    }

    /** Backwards-compatible helper for older callers. */
    public function materialIdsForProduct(Product $product): array
    {
        $variant = $product->variants()->orderBy('created_at')->orderBy('id')->first();
        if (!$variant) {
            return [];
        }

        return $this->materialIdsForVariant($variant);
    }

    private function findProductOil(Product $product): ?Material
    {
        $mapped = ProductMaterialMapping::query()
            ->where('product_id', $product->id)
            ->where('is_default', true)
            ->whereHas('material', fn($q) => $q
                ->where('material_category', 'perfume_oil')
                ->where('is_active', true))
            ->with('material')
            ->first();

        if ($mapped?->material) {
            return $mapped->material;
        }

        return Material::query()
            ->where('material_category', 'perfume_oil')
            ->where('is_active', true)
            ->where(function ($q) use ($product) {
                $q->where('name', $product->name)
                    ->orWhere('name_ar', $product->name_ar);
            })
            ->first();
    }

    private function findAlcohol(): ?Material
    {
        return Material::query()
            ->where('material_category', 'alcohol')
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN code = 'ALC-ETHANOL-1L' THEN 0 WHEN name_ar = 'كحول ايثانول' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->first();
    }

    private function findNamedPackaging(string $nameAr, string $nameEn, string $subcategory): ?Material
    {
        return Material::query()
            ->where('material_category', 'packaging')
            ->where('is_active', true)
            ->where('subcategory', $subcategory)
            ->where(function ($q) use ($nameAr, $nameEn) {
                $q->where('name_ar', $nameAr)
                    ->orWhere('name', $nameEn);
            })
            ->orderBy('id')
            ->first();
    }

private function findBottleForVariant(ProductVariant $variant): ?Material
{
    $label = mb_strtolower(trim((string) $variant->size_label));
    $volume = (float) ($variant->volume_ml ?? 0);

    $query = Material::query()
        ->where('material_category', 'packaging')
        ->where('is_active', true);

    /*
     * Oil vials
     */
    if (str_contains($label, '12ml oil vial')) {
        return $query
            ->where('id', 102)
            ->first();
    }

    if (str_contains($label, '6ml oil vial')) {
        return $query
            ->where('id', 103)
            ->first();
    }

    /*
     * Yum Yum
     */
    if (str_contains($label, '100ml yum yum blue')) {
        return $query
            ->where('id', 111)
            ->first();
    }

    if (str_contains($label, '100ml yum yum flower')) {
        return $query
            ->where('id', 110)
            ->first();
    }

    /*
     * Gold Laser
     */
    if (str_contains($label, '30ml gold laser')) {
        return $query
            ->where('id', 118)
            ->first();
    }

    if (str_contains($label, '50ml gold laser')) {
        return $query
            ->where('id', 117)
            ->first();
    }

    /*
     * 15ml Laser
     */
    if (str_contains($label, '15ml laser')) {
        return $query
            ->where('id', 116)
            ->first();
    }

    /*
     * Zara
     */
    if (str_contains($label, '30ml zara')) {
        return $query
            ->where('id', 113)
            ->first();
    }

    /*
     * Red Pen
     */
    if (str_contains($label, '5ml red pen')) {
        return $query
            ->where('id', 114)
            ->first();
    }

    /*
     * Testers
     */
    if (str_contains($label, '10ml tester')) {
        return $query
            ->where('id', 109)
            ->first();
    }

    if (str_contains($label, '5ml tester')) {
        return $query
            ->where('id', 115)
            ->first();
    }

    /*
     * 3ml Crystal
     */
    if (str_contains($label, '3ml crystal')) {
        return $query
            ->where('id', 112)
            ->first();
    }

    /*
     * Standard bottles
     */
    if ($label === '50ml') {
        return $query
            ->where('id', 120)
            ->first();
    }

    if ($label === '100ml') {
        return $query
            ->where('id', 119)
            ->first();
    }

    return null;
}
    private function materialNameHasVolume(?string $name, float $volume): bool
    {
        if (!$name || $volume <= 0) return false;
        $pattern = '/(?<!\d)' . preg_quote((string) (int) $volume, '/') . '\s*مل(?!\d)/u';
        return (bool) preg_match($pattern, $name);
    }
}
