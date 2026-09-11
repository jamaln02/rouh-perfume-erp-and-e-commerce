<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\ProductVariant;
use App\Models\Product;
use App\Services\ProductCostingService;
use App\Services\StandardRecipeService;
use App\Support\PermissionService;

class ProductCrudController extends Controller
{
    /**
     * Keep product-level size data in sync with active variants. Variant data is
     * the source of truth; the product JSON fields remain a compact catalog cache.
     */
    private function syncProductSizeFields(Product $product): void
    {
        $activeVariants = $product->variants()
            ->where('is_active', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['size_label', 'selling_price_default']);

        $sizes = [];
        $prices = [];
        foreach ($activeVariants as $variant) {
            $label = trim((string) $variant->size_label);
            if ($label === '') {
                continue;
            }
            $sizes[] = $label;
            $prices[$label] = (float) ($variant->selling_price_default ?? 0);
        }

        $product->sizes = $sizes;
        $product->size_prices = $prices;
        if (!empty($activeVariants)) {
            $reference = $activeVariants->firstWhere('size_label', '50ml') ?? $activeVariants->first();
            $product->price = (float) ($reference->selling_price_default ?? $product->price ?? 0);
        }
        $product->save();
    }

    private function normalizeSizePrices(array $sizes, float $basePrice, ?array $input): string
    {
        $prices = [];
        foreach ($sizes as $size) {
            $key = (string) $size;
            $candidate = $input[$key] ?? null;
            $prices[$key] = is_numeric($candidate) ? (float) $candidate : $basePrice;
        }

        return json_encode($prices);
    }

    public function index(): JsonResponse
    {
        $costing = app(ProductCostingService::class);
        $products = Product::with([
            'variants' => function ($query) {
                $query->select('id', 'product_id', 'sku', 'name', 'size_label', 'volume_ml', 'bottle_shape', 'selling_price_default', 'is_active', 'notes')
                    ->with(['recipes' => function ($recipeQuery) {
                        $recipeQuery->where('is_active', true)->latest('version')->with('items.material');
                    }]);
            },
        ])->orderByDesc('created_at')->get();

        if (PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
            $products->each(function (Product $product) use ($costing): void {
                $product->setAttribute('estimated_costs', $costing->calculateProduct($product));
            });
        } else {
            $products->each(fn (Product $product) => $product->setAttribute('estimated_costs', []));
        }

        return response()->json(['products' => $products]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'name_ar' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'string', 'max:64'],
            'fragrance' => ['nullable', 'string', 'max:50'],
            'top_notes' => ['nullable', 'array'],
            'top_notes.*' => ['string', 'max:100'],
            'heart_notes' => ['nullable', 'array'],
            'heart_notes.*' => ['string', 'max:100'],
            'base_notes' => ['nullable', 'array'],
            'base_notes.*' => ['string', 'max:100'],
            'fragrance_family' => ['nullable', 'string', 'max:100'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['string', 'max:100'],
            'size_prices' => ['nullable', 'array'],
            'size_prices.*' => ['numeric', 'min:0'],
            'featured' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
            'best_seller' => ['nullable', 'boolean'],
        ]);

        $sizes = array_values(array_unique(array_map('strval', $validated['sizes'] ?? [])));

        // Selling price is a commercial value entered by the manager.
        // Product cost is calculated independently from the active recipe and
        // the material ledger; it must never be used to overwrite selling price.
        $basePrice = (float) ($validated['price'] ?? 0);
        if ($basePrice <= 0 && !empty($validated['size_prices'])) {
            $firstPrice = reset($validated['size_prices']);
            $basePrice = is_numeric($firstPrice) ? (float) $firstPrice : 0.0;
        }

        $id = (string) Str::uuid();

        DB::transaction(function () use ($validated, $sizes, $basePrice, $id): void {
            DB::table('products')->insert([
                'id' => $id,
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'],
                'description' => $validated['description'] ?? null,
                'description_ar' => $validated['description_ar'] ?? null,
                'price' => $basePrice,
                'image_url' => $validated['image_url'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'fragrance' => $validated['fragrance'] ?? 'oriental',
                'top_notes' => isset($validated['top_notes']) ? json_encode(array_values($validated['top_notes'])) : null,
                'heart_notes' => isset($validated['heart_notes']) ? json_encode(array_values($validated['heart_notes'])) : null,
                'base_notes' => isset($validated['base_notes']) ? json_encode(array_values($validated['base_notes'])) : null,
                'fragrance_family' => $validated['fragrance_family'] ?? null,
                'sizes' => json_encode($sizes),
                'size_prices' => $this->normalizeSizePrices($sizes, $basePrice, $validated['size_prices'] ?? null),
                'featured' => $validated['featured'] ?? false,
                'is_new' => $validated['is_new'] ?? false,
                'best_seller' => $validated['best_seller'] ?? false,
                'stock' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!empty($sizes)) {
                $this->syncVariants($id, $validated['size_prices'] ?? [], $basePrice, $sizes);
            }
        });

        return response()->json(['ok' => true, 'id' => $id]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'name_ar' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', 'string', 'max:64'],
            'fragrance' => ['nullable', 'string', 'max:50'],
            'top_notes' => ['nullable', 'array'],
            'top_notes.*' => ['string', 'max:100'],
            'heart_notes' => ['nullable', 'array'],
            'heart_notes.*' => ['string', 'max:100'],
            'base_notes' => ['nullable', 'array'],
            'base_notes.*' => ['string', 'max:100'],
            'fragrance_family' => ['nullable', 'string', 'max:100'],
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['string', 'max:100'],
            'size_prices' => ['nullable', 'array'],
            'size_prices.*' => ['numeric', 'min:0'],
            'featured' => ['nullable', 'boolean'],
            'is_new' => ['nullable', 'boolean'],
            'best_seller' => ['nullable', 'boolean'],
        ]);

        $sizesProvided = array_key_exists('sizes', $validated);
        $sizes = $sizesProvided
            ? array_values(array_unique(array_map('strval', $validated['sizes'] ?? [])))
            : null;

        // Selling price remains separate from manufacturing cost.
        $existingProduct = Product::find($id);
        if (!$existingProduct) {
            return response()->json(['ok' => false, 'message' => 'Product not found'], 404);
        }

        $basePrice = array_key_exists('price', $validated) && $validated['price'] !== null
            ? (float) $validated['price']
            : (float) $existingProduct->price;
        if ($basePrice <= 0 && !empty($validated['size_prices'])) {
            $firstPrice = reset($validated['size_prices']);
            $basePrice = is_numeric($firstPrice) ? (float) $firstPrice : 0.0;
        }

        DB::transaction(function () use ($validated, $sizes, $sizesProvided, $basePrice, $existingProduct): void {
            DB::table('products')->where('id', $existingProduct->id)->update([
                'name' => $validated['name'],
                'name_ar' => $validated['name_ar'],
                'description' => $validated['description'] ?? null,
                'description_ar' => $validated['description_ar'] ?? null,
                'price' => $basePrice,
                'image_url' => $validated['image_url'] ?? null,
                'category_id' => $validated['category_id'] ?? null,
                'fragrance' => $validated['fragrance'] ?? 'oriental',
                'top_notes' => isset($validated['top_notes']) ? json_encode(array_values($validated['top_notes'])) : null,
                'heart_notes' => isset($validated['heart_notes']) ? json_encode(array_values($validated['heart_notes'])) : null,
                'base_notes' => isset($validated['base_notes']) ? json_encode(array_values($validated['base_notes'])) : null,
                'fragrance_family' => $validated['fragrance_family'] ?? null,
                'sizes' => $sizesProvided
                    ? json_encode($sizes)
                    : DB::table('products')->where('id', $existingProduct->id)->value('sizes'),
                'size_prices' => $sizesProvided
                    ? $this->normalizeSizePrices($sizes, $basePrice, $validated['size_prices'] ?? null)
                    : DB::table('products')->where('id', $existingProduct->id)->value('size_prices'),
                'featured' => $validated['featured'] ?? false,
                'is_new' => $validated['is_new'] ?? false,
                'best_seller' => $validated['best_seller'] ?? false,
                // Stock is owned by Inventory; never reset it from product editing.
                'stock' => (int) $existingProduct->stock,
                'updated_at' => now(),
            ]);

            if ($sizesProvided) {
                $this->syncVariants($existingProduct->id, $validated['size_prices'] ?? [], $basePrice, $sizes);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        $product = DB::table('products')->where('id', $id)->first();
        if (!$product) {
            return response()->json(['ok' => false, 'message' => 'Product not found'], 404);
        }

        $hasHistory = DB::table('order_items')->where('product_id', $id)->exists()
            || DB::table('sales')->where('product_id', $id)->exists();
        if ($hasHistory) {
            return response()->json([
                'ok' => false,
                'message' => 'This product has historical orders or sales and cannot be deleted. Deactivate its variants instead.',
            ], 409);
        }

        $deleted = DB::table('products')->where('id', $id)->delete();
        return response()->json(['ok' => (bool) $deleted]);
    }
    private function syncVariants(string $productId, array $sizePrices, float $basePrice, array $sizes): void
    {
        $product = Product::findOrFail($productId);
        $existing = ProductVariant::where('product_id', $productId)->get()->keyBy('size_label');
        $recipeService = app(StandardRecipeService::class);

        foreach ($sizes as $size) {
            $size = trim((string) $size);
            if ($size === '') {
                continue;
            }

            $price = isset($sizePrices[$size]) && is_numeric($sizePrices[$size])
                ? (float) $sizePrices[$size]
                : (float) ($product->size_prices[$size] ?? $basePrice);

            $variant = $existing->get($size);
            if ($variant) {
                $variant->update([
                    'volume_ml' => $this->extractVolume($size),
                    'bottle_shape' => $variant->bottle_shape ?: $this->shapeForSize($size),
                    'selling_price_default' => $price,
                    'is_active' => true,
                ]);
            } else {
                $variant = ProductVariant::create([
                    'id' => (string) Str::uuid(),
                    'product_id' => $productId,
                    'size_label' => $size,
                    'volume_ml' => $this->extractVolume($size),
                    'bottle_shape' => $this->shapeForSize($size),
                    'selling_price_default' => $price,
                    'is_active' => true,
                ]);
            }

            $recipeService->ensureForVariant($variant);
        }

        $variantQuery = ProductVariant::where('product_id', $productId);
        if (empty($sizes)) {
            $variantQuery->update(['is_active' => false]);
        } else {
            $variantQuery->whereNotIn('size_label', $sizes)->update(['is_active' => false]);
        }

        $this->syncProductSizeFields($product->fresh());
    }

    public function addGlobalVariant(Request $request): JsonResponse
    {
        $data = $request->validate([
            'size_label' => ['required', 'string', 'max:100'],
            'volume_ml' => ['nullable', 'numeric', 'gt:0'],
            'bottle_shape' => ['nullable', 'string', 'max:50'],
            'selling_price_default' => ['nullable', 'numeric', 'min:0'],
            'default_material_id' => ['nullable', 'integer', 'exists:materials,id'],
            'reactivate_existing' => ['nullable', 'boolean'],
        ]);

        $sizeLabel = trim($data['size_label']);
        $sizeKey = mb_strtolower(preg_replace('/\s+/', '', $sizeLabel));
        if ($sizeKey === '') {
            return response()->json(['ok' => false, 'message' => 'Size label is required.'], 422);
        }

        $defaultMaterial = null;
        if (!empty($data['default_material_id'])) {
            $defaultMaterial = \App\Models\Material::query()
                ->whereKey($data['default_material_id'])
                ->where('is_active', true)
                ->where('material_category', 'packaging')
                ->first();
            if (!$defaultMaterial) {
                return response()->json(['ok' => false, 'message' => 'Selected default material must be an active packaging material.'], 422);
            }
        }

        $volume = array_key_exists('volume_ml', $data) && $data['volume_ml'] !== null
            ? (float) $data['volume_ml']
            : $this->extractVolume($sizeLabel);
        $price = array_key_exists('selling_price_default', $data) && $data['selling_price_default'] !== null
            ? (float) $data['selling_price_default']
            : null;
        $reactivate = (bool) ($data['reactivate_existing'] ?? false);
        $shape = $data['bottle_shape'] ?? $this->shapeForSize($sizeLabel);

        $created = 0;
        $reactivated = 0;
        $skipped = 0;
        $recipesUpdated = 0;
        $recipeService = app(StandardRecipeService::class);

        DB::transaction(function () use (&$created, &$reactivated, &$skipped, &$recipesUpdated, $sizeLabel, $sizeKey, $volume, $price, $reactivate, $defaultMaterial, $recipeService, $shape): void {
            Product::query()->orderBy('created_at')->orderBy('id')->chunkById(100, function ($products) use (&$created, &$reactivated, &$skipped, &$recipesUpdated, $sizeLabel, $sizeKey, $volume, $price, $reactivate, $defaultMaterial, $recipeService, $shape): void {
                foreach ($products as $product) {
                    $variant = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->get()
                        ->first(fn (ProductVariant $candidate) => mb_strtolower(preg_replace('/\s+/', '', trim((string) $candidate->size_label))) === $sizeKey);

                    if ($variant) {
                        if (!$variant->is_active && $reactivate) {
                            $variant->update([
                                'is_active' => true,
                                'volume_ml' => $variant->volume_ml ?: $volume,
                                'bottle_shape' => $variant->bottle_shape ?: $shape,
                            ]);
                            $reactivated++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        $variant = ProductVariant::create([
                            'id' => (string) Str::uuid(),
                            'product_id' => $product->id,
                            'size_label' => $sizeLabel,
                            'volume_ml' => $volume,
                            'bottle_shape' => $shape,
                            'selling_price_default' => $price ?? (float) ($product->price ?? 0),
                            'is_active' => true,
                        ]);
                        $created++;
                    }

                    $recipe = $recipeService->ensureForVariant($variant);
                    if ($defaultMaterial) {
                        $exists = $recipe->items()->where('material_id', $defaultMaterial->id)->exists();
                        if (!$exists) {
                            $recipe->items()->create([
                                'material_id' => $defaultMaterial->id,
                                'expected_qty' => 1,
                                'unit' => $defaultMaterial->base_unit,
                            ]);
                            $recipesUpdated++;
                        }
                    }

                    $this->syncProductSizeFields($product->fresh());
                }
            });
        });

        return response()->json([
            'ok' => true,
            'size_label' => $sizeLabel,
            'created' => $created,
            'reactivated' => $reactivated,
            'skipped' => $skipped,
            'recipes_updated' => $recipesUpdated,
        ]);
    }

    private function extractVolume(string $size): ?float
    {
        return preg_match('/(\d+(?:\.\d+)?)\s*ml/i', $size, $m) ? (float) $m[1] : null;
    }

    private function shapeForSize(string $size): ?string
    {
        $value = mb_strtolower($size);
        return match (true) {
            str_contains($value, 'crystal') => 'crystal',
            str_contains($value, 'pen') => 'pen',
            str_contains($value, 'laser') => 'laser',
            str_contains($value, 'zara') => 'zara',
            str_contains($value, 'gold') => 'gold_crystal',
            str_contains($value, 'tester') => 'tester',
            str_contains($value, 'yum yum') => 'yum_yum',
            default => null,
        };
    }

}
