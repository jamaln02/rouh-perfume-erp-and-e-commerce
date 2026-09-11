<?php

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductMaterialMapping;
use App\Models\ProductVariant;
use App\Models\Recipe;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const STANDARD_SIZES = [
        '50ml',
        '3ml tester',
        '3ml crystal',
        '5ml tester',
        '5ml pen',
        '10ml tester',
        '15ml laser',
        '30ml zara',
        '60ml gold crystal',
        '100ml',
        '100ml yum yum blue',
        '100ml yum yum icecream',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $alcohol = $this->findOrCreateAlcohol();
            $box = $this->findOrCreatePackaging('box', 'علبة روح', 'ROUH Box', 'PKG-ROUH-BOX');
            $bag = $this->findOrCreatePackaging('bag', 'كيس روح', 'ROUH Bag', 'PKG-ROUH-BAG');
            $bottle = $this->findOrCreateGenericBottle();

            Product::query()->orderBy('id')->chunkById(100, function ($products) use ($alcohol, $box, $bag, $bottle): void {
                foreach ($products as $product) {
                    $existingPrices = is_array($product->size_prices)
                        ? $product->size_prices
                        : (json_decode((string) $product->size_prices, true) ?: []);
                    $basePrice = (float) $product->price;
                    if ($basePrice <= 0) {
                        foreach ($existingPrices as $existingPrice) {
                            if (is_numeric($existingPrice) && (float) $existingPrice > 0) {
                                $basePrice = (float) $existingPrice;
                                break;
                            }
                        }
                    }
                    $prices = [];

                    foreach (self::STANDARD_SIZES as $size) {
                        $prices[$size] = isset($existingPrices[$size]) && is_numeric($existingPrices[$size])
                            ? (float) $existingPrices[$size]
                            : $basePrice;
                    }

                    DB::table('products')->where('id', $product->id)->update([
                        'sizes' => json_encode(self::STANDARD_SIZES, JSON_UNESCAPED_UNICODE),
                        'size_prices' => json_encode($prices, JSON_UNESCAPED_UNICODE),
                        // Product stock is no longer edited from the Products screen.
                        'stock' => (int) $product->stock,
                        'updated_at' => now(),
                    ]);

                    $oil = $this->findProductOil($product);
                    $materialIds = [$oil->id, $alcohol->id, $bottle->id, $box->id, $bag->id];
                    $existing = ProductVariant::where('product_id', $product->id)->get()->keyBy('size_label');

                    foreach (self::STANDARD_SIZES as $size) {
                        $data = [
                            'volume_ml' => $this->volume($size),
                            'bottle_shape' => $this->shapeForSize($size),
                            'selling_price_default' => $prices[$size],
                            'is_active' => true,
                        ];

                        $variant = $existing->get($size);
                        if ($variant) {
                            $variant->update($data);
                        } else {
                            $variant = ProductVariant::create([
                                'id' => (string) Str::uuid(),
                                'product_id' => $product->id,
                                'sku' => null,
                                'name' => null,
                                'size_label' => $size,
                                ...$data,
                                'notes' => null,
                            ]);
                        }

                        $this->ensureStandardRecipe($variant, $materialIds);
                    }

                    ProductVariant::where('product_id', $product->id)
                        ->whereNotIn('size_label', self::STANDARD_SIZES)
                        ->update(['is_active' => false]);
                }
            });
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive. Historical variants/recipes must remain intact.
    }

    private function ensureStandardRecipe(ProductVariant $variant, array $materialIds): void
    {
        $active = $variant->recipes()->where('is_active', true)->latest('version')->first();
        $items = $active ? $active->items()->orderBy('sort_order')->get(['material_id', 'expected_qty', 'unit']) : collect();
        $expected = array_map('intval', $materialIds);
        $matches = $items->count() === 5;

        if ($matches) {
            foreach ($items->values() as $index => $item) {
                $material = Material::find((int) $expected[$index]);
                if (!$material || (int) $item->material_id !== (int) $material->id || (float) $item->expected_qty !== 0.0 || (string) $item->unit !== (string) $material->base_unit) {
                    $matches = false;
                    break;
                }
            }
        }

        if ($active && $matches) {
            return;
        }

        if ($active) {
            $active->update(['is_active' => false]);
        }

        $recipe = Recipe::create([
            'id' => (string) Str::uuid(),
            'product_variant_id' => $variant->id,
            'version' => ((int) $variant->recipes()->max('version')) + 1,
            'is_active' => true,
            'oil_percentage' => 32,
            'alcohol_percentage' => 68,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
            'notes' => 'قالب الوصفة القياسي: الكميات تُحدد عند تجهيز الطلب.',
            'created_by' => null,
        ]);

        foreach ($materialIds as $sort => $materialId) {
            $material = Material::findOrFail($materialId);
            $recipe->items()->create([
                'material_id' => $material->id,
                'expected_qty' => 0,
                'unit' => $material->base_unit,
                'consumption_rule_type' => 'fixed',
                'rule_config' => [],
                'is_optional' => false,
                'allow_manual_override' => true,
                'sort_order' => $sort + 1,
            ]);
        }
    }

    private function findProductOil(Product $product): Material
    {
        $mapped = ProductMaterialMapping::query()
            ->where('product_id', $product->id)
            ->where('is_default', true)
            ->whereHas('material', fn ($q) => $q->where('material_category', 'perfume_oil'))
            ->first();

        if ($mapped?->material_id) {
            return Material::findOrFail($mapped->material_id);
        }

        return Material::where('material_category', 'perfume_oil')
            ->where(function ($q) use ($product) {
                $q->where('name', $product->name)->orWhere('name_ar', $product->name_ar);
            })
            ->first()
            ?? Material::create([
                'code' => 'OIL-PRODUCT-' . $product->id,
                'name' => 'Perfume Oil - ' . $product->name,
                'name_ar' => 'زيت عطر - ' . $product->name_ar,
                'material_category' => 'perfume_oil',
                'subcategory' => 'perfume',
                'base_unit' => 'g',
                'track_fractional' => true,
                'current_stock' => 0,
                'min_stock' => 0,
                'avg_unit_cost' => 0,
                'currency' => 'SYP',
                'exchange_rate' => 1,
                'is_active' => true,
                'supplier_name' => null,
                'notes' => 'زيت تلقائي لإنشاء قالب الوصفة القياسي.',
                'created_by' => null,
            ]);
    }

    private function findOrCreateAlcohol(): Material
    {
        return Material::where('material_category', 'alcohol')->where('is_active', true)->orderBy('id')->first()
            ?? Material::create([
                'code' => 'ALC-ETHANOL-GENERIC',
                'name' => 'Ethanol Alcohol',
                'name_ar' => 'كحول ايثانول',
                'material_category' => 'alcohol',
                'subcategory' => 'ethanol',
                'base_unit' => 'L',
                'track_fractional' => true,
                'current_stock' => 0,
                'min_stock' => 0,
                'avg_unit_cost' => 0,
                'currency' => 'SYP',
                'exchange_rate' => 1,
                'is_active' => true,
                'created_by' => null,
            ]);
    }

    private function findOrCreateGenericBottle(): Material
    {
        return Material::firstOrCreate(
            ['code' => 'PKG-GENERIC-BOTTLE'],
            [
                'name' => 'Bottle (selected at preparation)',
                'name_ar' => 'زجاجة',
                'material_category' => 'packaging',
                'subcategory' => 'bottle',
                'base_unit' => 'pcs',
                'track_fractional' => false,
                'current_stock' => 0,
                'min_stock' => 0,
                'avg_unit_cost' => 0,
                'currency' => 'SYP',
                'exchange_rate' => 1,
                'is_active' => true,
                'created_by' => null,
                'notes' => 'زجاجة عامة للوصفة؛ النوع الفعلي يُحدد عند تجهيز الطلب.',
            ]
        );
    }

    private function findOrCreatePackaging(string $subcategory, string $nameAr, string $nameEn, string $code): Material
    {
        return Material::firstOrCreate(
            ['code' => $code],
            [
                'name' => $nameEn,
                'name_ar' => $nameAr,
                'material_category' => 'packaging',
                'subcategory' => $subcategory,
                'base_unit' => 'pcs',
                'track_fractional' => false,
                'current_stock' => 0,
                'min_stock' => 0,
                'avg_unit_cost' => 0,
                'currency' => 'SYP',
                'exchange_rate' => 1,
                'is_active' => true,
                'created_by' => null,
            ]
        );
    }

    private function volume(string $size): ?float
    {
        return preg_match('/(\d+(?:\.\d+)?)\s*ml/i', $size, $m) ? (float) $m[1] : null;
    }

    private function shapeForSize(string $size): ?string
    {
        $key = strtolower(trim($size));
        return match (true) {
            str_contains($key, 'tester') => 'tester',
            str_contains($key, 'crystal') => 'crystal',
            str_contains($key, 'pen') => 'pen',
            str_contains($key, 'laser') => 'laser',
            str_contains($key, 'zara') => 'zara',
            str_contains($key, 'gold crystal') => 'gold_crystal',
            str_contains($key, 'yum yum blue') => 'yum_yum_blue',
            str_contains($key, 'yum yum icecream') => 'yum_yum_ice_cream',
            default => null,
        };
    }
};
