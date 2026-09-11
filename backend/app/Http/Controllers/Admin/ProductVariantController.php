<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\StandardRecipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductVariantController extends Controller
{
    public function index(string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $globalMedia = DB::table('variant_size_media')
            ->where('is_active', true)
            ->get(['size_key', 'image_url', 'image_is_reference'])
            ->keyBy('size_key');

        $variants = $product->variants()
            ->with(['recipes' => fn ($q) => $q->orderByDesc('version')])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $payload = $variants->map(function (ProductVariant $variant) use ($globalMedia) {
            $active = $variant->recipes->firstWhere('is_active', true);
            $data = $variant->toArray();
            $sizeKey = $this->sizeKey((string) $variant->size_label);
            $global = $globalMedia->get($sizeKey);
            if ($global && !empty($global->image_url)) {
                $data['image_url'] = url($global->image_url);
                $data['image_is_reference'] = (bool) $global->image_is_reference;
            } elseif (!empty($data['image_url']) && str_starts_with((string) $data['image_url'], '/')) {
                $data['image_url'] = url($data['image_url']);
            }
            $data['active_recipe'] = $active ? [
                'id' => $active->id,
                'version' => $active->version,
                'items_count' => $active->items()->count(),
            ] : null;
            return $data;
        });

        return response()->json(['product' => $product, 'variants' => $payload]);
    }

    public function store(Request $request, string $productId): JsonResponse
    {
        $product = Product::findOrFail($productId);
        $v = $request->validate([
            'size_label' => ['required', 'string', 'max:100'],
            'volume_ml' => ['nullable', 'numeric', 'min:0'],
            'bottle_shape' => ['nullable', 'string', 'max:100'],
            'image_url' => ['nullable', 'string', 'max:1000', 'regex:/^(https?:\/\/|\/)[^\s]+$/i'],
            'image_is_reference' => ['sometimes', 'boolean'],
            'selling_price_default' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $label = trim($v['size_label']);
        if ($label === '') {
            return response()->json(['ok' => false, 'message' => 'Size label cannot be empty.'], 422);
        }
        if (!empty($v['image_is_reference']) && empty($v['image_url'])) {
            return response()->json(['ok' => false, 'message' => 'A reference image must have an image URL.'], 422);
        }

        $variant = DB::transaction(function () use ($product, $v, $label): ProductVariant {
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('size_label', $label)
                ->first();

            $payload = [
                'volume_ml' => array_key_exists('volume_ml', $v)
                    ? ($v['volume_ml'] === null ? null : (float) $v['volume_ml'])
                    : $this->extractVolume($label),
                'bottle_shape' => $v['bottle_shape'] ?? $this->shapeForSize($label),
                'image_url' => $v['image_url'] ?? null,
                'image_is_reference' => (bool) ($v['image_is_reference'] ?? false),
                'selling_price_default' => array_key_exists('selling_price_default', $v) && $v['selling_price_default'] !== null
                    ? (float) $v['selling_price_default']
                    : (float) ($product->price ?? 0),
                'notes' => $v['notes'] ?? null,
                'is_active' => true,
            ];

            if ($variant) {
                $variant->update($payload);
            } else {
                $variant = ProductVariant::create([
                    'id' => (string) Str::uuid(),
                    'product_id' => $product->id,
                    'size_label' => $label,
                    ...$payload,
                ]);
            }

            app(StandardRecipeService::class)->ensureForVariant($variant);
            $this->syncProductSizeFields($product->fresh());
            return $variant->fresh();
        });

        return response()->json([
            'ok' => true,
            'variant' => $variant->load(['recipes' => fn ($q) => $q->where('is_active', true)->latest('version')]),
        ], 201);
    }

    /**
     * Upload the customer-facing image for a specific product variant.
     * Images are stored on Laravel's public disk so the same flow works on
     * local environments and shared hosting after `php artisan storage:link`.
     */
    public function uploadImage(Request $request, string $variantId): JsonResponse
    {
        $variant = ProductVariant::findOrFail($variantId);
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'image_is_reference' => ['sometimes', 'boolean'],
        ]);

        $label = trim((string) $variant->size_label);
        $sizeKey = $this->sizeKey($label);
        $media = app(\App\Models\VariantSizeMedia::class)->newQuery()->firstOrNew(['size_key' => $sizeKey]);
        $oldPath = $this->storagePathFromUrl($media->image_url);
        $path = $request->file('image')->store('products/variants-by-size', 'public');
        $url = Storage::disk('public')->url($path);
        $media->fill([
            'size_label' => $label,
            'image_url' => $url,
            'image_is_reference' => array_key_exists('image_is_reference', $validated) ? (bool) $validated['image_is_reference'] : false,
            'is_active' => true,
        ])->save();
        if ($oldPath && $oldPath !== $path) Storage::disk('public')->delete($oldPath);

        return response()->json(['ok' => true, 'variant' => $variant->fresh(), 'image_url' => url($url)]);
    }

    /** Remove the shared image for this variant size. */
    public function deleteImage(string $variantId): JsonResponse
    {
        $variant = ProductVariant::findOrFail($variantId);
        $sizeKey = $this->sizeKey((string) $variant->size_label);
        $media = \App\Models\VariantSizeMedia::where('size_key', $sizeKey)->first();
        if (!$media) {
            return response()->json(['ok' => true]);
        }

        $oldPath = $this->storagePathFromUrl($media->image_url);
        $media->update(['image_url' => null, 'image_is_reference' => false]);
        if ($oldPath) Storage::disk('public')->delete($oldPath);

        return response()->json(['ok' => true, 'variant' => $variant->fresh()]);
    }

    public function update(Request $request, string $variantId): JsonResponse
    {
        $variant = ProductVariant::findOrFail($variantId);
        $v = $request->validate([
            'size_label' => ['sometimes', 'required', 'string', 'max:100'],
            'volume_ml' => ['nullable', 'numeric', 'min:0'],
            'bottle_shape' => ['nullable', 'string', 'max:100'],
            'image_url' => ['nullable', 'string', 'max:1000', 'regex:/^(https?:\/\/|\/)[^\s]+$/i'],
            'image_is_reference' => ['sometimes', 'boolean'],
            'selling_price_default' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $product = Product::findOrFail($variant->product_id);
        if (!empty($v['image_is_reference']) && empty($v['image_url']) && empty($variant->image_url)) {
            return response()->json(['ok' => false, 'message' => 'A reference image must have an image URL.'], 422);
        }
        $newLabel = array_key_exists('size_label', $v) ? trim($v['size_label']) : $variant->size_label;
        if ($newLabel === '') {
            return response()->json(['ok' => false, 'message' => 'Size label cannot be empty.'], 422);
        }

        $updated = DB::transaction(function () use ($variant, $product, $v, $newLabel): ProductVariant {
            if ($newLabel !== $variant->size_label) {
                $duplicate = ProductVariant::where('product_id', $variant->product_id)
                    ->where('size_label', $newLabel)
                    ->where('id', '!=', $variant->id)
                    ->first();
                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'size_label' => ['A size with this label already exists for this product.'],
                    ]);
                }
            }

            $payload = [];
            if (array_key_exists('size_label', $v)) {
                $payload['size_label'] = $newLabel;
            }
            if (array_key_exists('volume_ml', $v)) {
                $payload['volume_ml'] = $v['volume_ml'] === null ? null : (float) $v['volume_ml'];
            } elseif ($newLabel !== $variant->size_label) {
                $payload['volume_ml'] = $this->extractVolume($newLabel);
            }
            if (array_key_exists('bottle_shape', $v)) {
                $payload['bottle_shape'] = $v['bottle_shape'];
            } elseif ($newLabel !== $variant->size_label) {
                $payload['bottle_shape'] = $this->shapeForSize($newLabel);
            }
            if (array_key_exists('image_url', $v)) {
                $payload['image_url'] = $v['image_url'];
            }
            if (array_key_exists('image_is_reference', $v)) {
                $payload['image_is_reference'] = (bool) $v['image_is_reference'];
            }
            if (array_key_exists('selling_price_default', $v)) {
                $payload['selling_price_default'] = $v['selling_price_default'] === null ? null : (float) $v['selling_price_default'];
            }
            if (array_key_exists('is_active', $v)) {
                $payload['is_active'] = (bool) $v['is_active'];
            }
            if (array_key_exists('notes', $v)) {
                $payload['notes'] = $v['notes'];
            }

            $variant->update($payload);
            $this->syncProductSizeFields($product->fresh());
            return $variant->fresh();
        });

        return response()->json(['ok' => true, 'variant' => $updated]);
    }

    public function destroy(string $variantId): JsonResponse
    {
        $variant = ProductVariant::findOrFail($variantId);
        $product = Product::findOrFail($variant->product_id);

        DB::transaction(function () use ($variant, $product): void {
            // Deactivate instead of hard-deleting: recipes and historical orders may reference it.
            $variant->update(['is_active' => false]);
            $this->syncProductSizeFields($product->fresh());
        });

        return response()->json(['ok' => true, 'variant' => $variant->fresh()]);
    }

    private function sizeKey(string $label): string
    {
        $normalised = mb_strtolower(trim($label));
        return preg_replace('/\s+/u', '', $normalised) ?? $normalised;
    }

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
        if ($activeVariants->isNotEmpty()) {
            $reference = $activeVariants->firstWhere('size_label', '50ml') ?? $activeVariants->first();
            $product->price = (float) ($reference->selling_price_default ?? $product->price ?? 0);
        }
        $product->save();
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
