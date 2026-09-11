<?php

use App\Models\Product;
use App\Models\Recipe;
use App\Models\ProductVariant;
use App\Services\StandardRecipeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const SIZES = [
        '3ml tester',
        '3ml crystal',
        '5ml tester',
        '5ml pen',
        '10ml tester',
        '15ml laser',
        '30ml zara',
        '50ml',
        '60ml crystal',
        '100ml',
        '100ml yum yum blue',
        '100ml yum yum icecream',
    ];

    private const PRICES = [
        '3ml tester' => 100,
        '3ml crystal' => 500,
        '5ml tester' => 150,
        '5ml pen' => 200,
        '10ml tester' => 350,
        '15ml laser' => 500,
        '30ml zara' => 850,
        '50ml' => 1250,
        '60ml crystal' => 1750,
        '100ml' => 2000,
        '100ml yum yum blue' => 4500,
        '100ml yum yum icecream' => 4500,
    ];

    public function up(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('products') || !DB::getSchemaBuilder()->hasTable('product_variants')) {
            return;
        }

        $service = app(StandardRecipeService::class);

        DB::transaction(function () use ($service): void {
            Product::query()->each(function (Product $product) use ($service): void {
                // Compatibility with the previous label used before the catalog was made flexible.
                ProductVariant::query()
                    ->where('product_id', $product->id)
                    ->where('size_label', '60ml gold crystal')
                    ->whereNotExists(function ($q) use ($product) {
                        $q->select(DB::raw(1))
                            ->from('product_variants as v2')
                            ->whereColumn('v2.product_id', 'product_variants.product_id')
                            ->where('v2.size_label', '60ml crystal');
                    })
                    ->update(['size_label' => '60ml crystal', 'updated_at' => now()]);

                foreach (self::SIZES as $size) {
                    $variant = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->where('size_label', $size)
                        ->first();

                    $payload = [
                        'volume_ml' => $this->volume($size),
                        'bottle_shape' => $this->shape($size),
                        'selling_price_default' => self::PRICES[$size],
                        'is_active' => true,
                    ];

                    if ($variant) {
                        $variant->update($payload);
                    } else {
                        $variant = ProductVariant::create([
                            'id' => (string) Str::uuid(),
                            'product_id' => $product->id,
                            'sku' => Str::slug($product->name) . '-' . Str::slug($size),
                            'name' => $product->name . ' ' . $size,
                            'size_label' => $size,
                            ...$payload,
                            'notes' => null,
                        ]);
                    }

                    $service->ensureForVariant($variant);
                }

                $variants = $product->variants()->where('is_active', true)->get(['size_label', 'selling_price_default', 'created_at', 'id']);
                $prices = [];
                $sizes = [];
                foreach ($variants as $variant) {
                    $label = trim((string) $variant->size_label);
                    if ($label === '') continue;
                    $sizes[] = $label;
                    $prices[$label] = (float) ($variant->selling_price_default ?? 0);
                }

                $product->sizes = $sizes;
                $product->size_prices = $prices;
                $reference = $variants->firstWhere('size_label', '50ml') ?? $variants->first();
                if ($reference) {
                    $product->price = (float) ($reference->selling_price_default ?? $product->price ?? 0);
                }
                $product->save();
            });

            $this->replaceLegacyGeneratedMaterials($service);
        });
    }

    private function replaceLegacyGeneratedMaterials(StandardRecipeService $service): void
    {
        if (!DB::getSchemaBuilder()->hasTable('materials') || !DB::getSchemaBuilder()->hasTable('recipe_items')) {
            return;
        }

        $genericCodes = [
            'PKG-GENERIC-BOTTLE',
            'PKG-ROUH-BOX',
            'PKG-ROUH-BAG',
        ];

        $legacyRows = DB::table('materials')
            ->where(function ($q) use ($genericCodes) {
                $q->whereIn('code', $genericCodes)
                    ->orWhere('code', 'like', 'OIL-PRODUCT-%')
                    ->orWhere('code', 'ALC-ETHANOL-GENERIC');
            })
            ->get(['id', 'code']);
        $genericIds = $legacyRows->pluck('id')->all();
        if (!$genericIds) return;

        foreach (Recipe::query()->where('is_active', true)->with('variant')->get() as $recipe) {
            if (!$recipe->variant) continue;

            $replacementIds = $service->materialIdsForVariant($recipe->variant);
            $replacementByCategory = [];
            foreach ($replacementIds as $materialId) {
                $material = DB::table('materials')->where('id', $materialId)->first(['id', 'material_category', 'subcategory']);
                if (!$material) continue;
                $replacementByCategory[$material->subcategory ?: $material->material_category] = (int) $material->id;
                if ($material->material_category === 'perfume_oil') {
                    $replacementByCategory['perfume_oil'] = (int) $material->id;
                }
                if ($material->material_category === 'alcohol') {
                    $replacementByCategory['alcohol'] = (int) $material->id;
                }
            }

            $items = DB::table('recipe_items')
                ->where('recipe_id', $recipe->id)
                ->whereIn('material_id', $genericIds)
                ->get(['id', 'material_id']);

            foreach ($items as $item) {
                $generic = DB::table('materials')->where('id', $item->material_id)->first(['code', 'subcategory']);
                $target = match (true) {
                    $generic?->code === 'PKG-GENERIC-BOTTLE' => $replacementByCategory['bottle'] ?? null,
                    $generic?->code === 'PKG-ROUH-BOX' => $replacementByCategory['box'] ?? null,
                    $generic?->code === 'PKG-ROUH-BAG' => $replacementByCategory['bag'] ?? null,
                    is_string($generic?->code) && str_starts_with($generic->code, 'OIL-PRODUCT-') => $replacementByCategory['perfume_oil'] ?? null,
                    $generic?->code === 'ALC-ETHANOL-GENERIC' => $replacementByCategory['alcohol'] ?? null,
                    default => null,
                };

                if ($target) {
                    DB::table('recipe_items')->where('id', $item->id)->update([
                        'material_id' => $target,
                        'updated_at' => now(),
                    ]);
                } else {
                    // Never leave a live recipe pointing at a generated placeholder.
                    // The line is intentionally removed so the manager can choose an
                    // actual inventory material later.
                    DB::table('recipe_items')->where('id', $item->id)->delete();
                }
            }
        }

        // Keep legacy rows for historical references, but remove them from all
        // active material pickers so new recipes can only use real inventory.
        DB::table('materials')
            ->whereIn('id', $genericIds)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversed: removing defaults
        // would risk deleting or altering customer-created variants.
    }

    private function volume(string $size): ?float
    {
        return preg_match('/(\d+(?:\.\d+)?)\s*ml/i', $size, $m) ? (float) $m[1] : null;
    }

    private function shape(string $size): ?string
    {
        $key = mb_strtolower($size);
        return match (true) {
            str_contains($key, 'tester') => 'tester',
            str_contains($key, 'crystal') => 'crystal',
            str_contains($key, 'pen') => 'pen',
            str_contains($key, 'laser') => 'laser',
            str_contains($key, 'zara') => 'zara',
            str_contains($key, 'yum yum blue') => 'yum_yum_blue',
            str_contains($key, 'yum yum icecream') => 'yum_yum_ice_cream',
            default => null,
        };
    }
};
