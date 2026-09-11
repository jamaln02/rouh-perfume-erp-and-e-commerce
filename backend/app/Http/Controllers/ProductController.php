<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Decode the raw `sizes` JSON column into a clean array of size labels.
     * Handles native arrays, JSON-encoded strings, comma-separated strings,
     * and null values. Returns an empty array when nothing usable is found.
     */
    private function sizeKey(string $label): string
    {
        $normalised = mb_strtolower(trim($label));
        return preg_replace('/\s+/u', '', $normalised) ?? $normalised;
    }

    private function decodeSizes($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('strval', $raw), fn ($v) => trim($v) !== ''));
        }
        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return [];
            }
            try {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return array_values(array_filter(array_map('strval', $decoded), fn ($v) => trim($v) !== ''));
                }
            } catch (\Throwable $e) {
                // fall through to comma split
            }
            return array_values(array_filter(array_map('trim', explode(',', $trimmed)), fn ($v) => $v !== ''));
        }
        return [];
    }

    /**
     * Normalize a single product for the PUBLIC customer-facing API response.
     *
     * Security note: this endpoint is consumed by the storefront. We deliberately
     * do NOT expose internal stock quantities (`size_stock` / `current_stock`) to
     * customers. Stock management stays internal to the dashboard / ERP. If a
     * product has zero stock across all sizes we may still surface it (the
     * storefront decides display), but we never leak exact unit counts.
     *
     * We DO explicitly attach `available_sizes` as a clean string[] array so the
     * frontend never has to guess at the shape of the `sizes` column.
     */
    private function normalizeProduct(object $product, ?Collection $variants = null, ?Collection $sizeMedia = null): object
    {
        if (isset($product->image_url) && is_string($product->image_url) && $product->image_url !== '') {
            if (Str::startsWith($product->image_url, '/')) {
                $product->image_url = url($product->image_url);
            }
        }

        $sizeMedia = $sizeMedia ?? DB::table('variant_size_media')->where('is_active', true)->get(['size_key', 'image_url', 'image_is_reference'])->keyBy('size_key');

        $variants = $variants ?? DB::table('product_variants')
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get([
                'id', 'size_label', 'volume_ml', 'bottle_shape',
                'selling_price_default', 'image_url', 'image_is_reference'
            ]);

        $variants = $variants->map(function (object $variant) use ($sizeMedia): object {
            $sizeKey = $this->sizeKey((string) ($variant->size_label ?? ''));
            $global = $sizeMedia->get($sizeKey);
            if ($global && !empty($global->image_url)) {
                $variant->image_url = $global->image_url;
                $variant->image_is_reference = (bool) $global->image_is_reference;
            }
            if (isset($variant->image_url) && is_string($variant->image_url) && $variant->image_url !== '' && Str::startsWith($variant->image_url, '/')) {
                $variant->image_url = url($variant->image_url);
            }
            return $variant;
        })->values();

        $product->variants = $variants;

        $variantSizes = $variants->pluck('size_label')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->values()
            ->all();
        $product->available_sizes = count($variantSizes) > 0
            ? $variantSizes
            : $this->decodeSizes($product->sizes ?? null);
        $product->sizes = $product->available_sizes;

        if (count($variantSizes) > 0) {
            $variantPrices = [];
            foreach ($variants as $variant) {
                if ($variant->selling_price_default !== null) {
                    $variantPrices[(string) $variant->size_label] = (float) $variant->selling_price_default;
                }
            }
            if ($variantPrices) {
                $product->size_prices = $variantPrices;
                $firstPrice = reset($variantPrices);
                if (($product->price ?? null) === null && $firstPrice !== false) {
                    $product->price = $firstPrice;
                }
            }
        }

        return $product;
    }

    public function index(): JsonResponse
    {
        $products = DB::table('products')
            ->orderByDesc('created_at')
            ->get();

        $sizeMedia = DB::table('variant_size_media')
            ->where('is_active', true)
            ->get(['size_key', 'image_url', 'image_is_reference'])
            ->keyBy('size_key');

        $productIds = $products->pluck('id')->all();
        $variantMap = empty($productIds)
            ? collect()
            : DB::table('product_variants')
                ->whereIn('product_id', $productIds)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get([
                    'id', 'product_id', 'size_label', 'volume_ml', 'bottle_shape',
                    'selling_price_default', 'image_url', 'image_is_reference'
                ])
                ->groupBy('product_id');

        $products = $products
            ->map(fn (object $product) => $this->normalizeProduct($product, $variantMap->get($product->id, collect()), $sizeMedia))
            ->values();

        return response()->json([
            'products' => $products,
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $product = DB::table('products')->where('id', $id)->first();

        if (!$product) {
            return response()->json(['product' => null], 404);
        }

        return response()->json([
            'product' => $this->normalizeProduct(
                $product,
                null,
                DB::table('variant_size_media')->where('is_active', true)->get(['size_key', 'image_url', 'image_is_reference'])->keyBy('size_key')
            ),
        ]);
    }
}
