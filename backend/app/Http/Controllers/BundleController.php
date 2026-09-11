<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BundleController extends Controller
{
    public function index(): JsonResponse
    {
        $bundles = DB::table('bundles')
            ->where('active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderByDesc('created_at')
            ->get();

        foreach ($bundles as $bundle) {
            $this->decorateBundle($bundle, true);
        }

        return response()->json($bundles);
    }

    public function show(string $id): JsonResponse
    {
        $id = trim($id);
        if ($id === '') return response()->json(['message' => 'Offer not found'], 404);

        $bundle = DB::table('bundles')->where('id', $id)->first();
        if (!$bundle) return response()->json(['message' => 'Offer not found'], 404);

        $this->decorateBundle($bundle, false);
        return response()->json($bundle);
    }

    private function decorateBundle(object $bundle, bool $publicList): void
    {
        $bundle->allowed_sizes = $this->decodeJsonArray($bundle->allowed_sizes ?? null);
        $bundle->price_by_size = $this->decodeJsonObject($bundle->price_by_size ?? null);
        $bundle->offer_config = $this->decodeJsonObject($bundle->offer_config ?? null);
        $bundle->items = DB::table('bundle_items')->where('bundle_id', $bundle->id)->orderBy('created_at')->get();

        if (!$publicList) {
            $config = $bundle->offer_config;
            $slots = is_array($config['slots'] ?? null) ? $config['slots'] : [];
            $ids = [];
            foreach ($slots as $slot) {
                foreach ((array) ($slot['product_ids'] ?? []) as $productId) {
                    $ids[] = (string) $productId;
                }
            }

            if ($bundle->offer_type === 'mix_match') {
                $bundle->selection_products = $this->productOptions();
            } elseif ($bundle->offer_type === 'specific_product' && $bundle->target_product_id) {
                $bundle->selection_products = $this->productOptions([(string) $bundle->target_product_id]);
            } elseif ($ids) {
                $bundle->selection_products = $this->productOptions(array_values(array_unique($ids)));
            } else {
                $bundle->selection_products = $this->productOptions();
            }
        }
    }

    private function productOptions(?array $ids = null): array
    {
        $query = DB::table('products')->select(['id','name','name_ar','price','image_url','sizes','size_prices','available_sizes']);
        if ($ids !== null) {
            if ($ids === []) return [];
            $query->whereIn('id', $ids);
        }

        $products = $query->orderBy('name')->get();
        $productIds = $products->pluck('id')->map('strval')->all();
        $variants = empty($productIds)
            ? collect()
            : DB::table('product_variants')
                ->whereIn('product_id', $productIds)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->get(['id','product_id','size_label','selling_price_default','image_url'])
                ->groupBy('product_id');

        return $products->map(function ($p) use ($variants) {
            $productVariants = $variants->get($p->id, collect())->values();
            $variantSizes = $productVariants->pluck('size_label')->map(fn ($v) => trim((string) $v))->filter()->values()->all();
            $sizePrices = [];
            foreach ($productVariants as $variant) {
                if ($variant->selling_price_default !== null) {
                    $sizePrices[(string) $variant->size_label] = (float) $variant->selling_price_default;
                }
            }
            if (!$sizePrices) $sizePrices = $this->decodeJsonObject($p->size_prices ?? null);
            $p->sizes = $variantSizes ?: $this->decodeJsonArray($p->available_sizes ?: $p->sizes);
            $p->size_prices = $sizePrices;
            $p->variants = $productVariants->map(fn ($v) => [
                'id' => (string) $v->id,
                'size' => (string) $v->size_label,
                'price' => $v->selling_price_default !== null ? (float) $v->selling_price_default : null,
                'image_url' => $v->image_url,
            ])->all();
            return $p;
        })->all();
    }

    private function decodeJsonArray($value): array
    {
        if (is_array($value)) return array_values($value);
        if (!is_string($value) || trim($value) === '') return [];
        try { $decoded = json_decode($value, true); return is_array($decoded) ? array_values($decoded) : []; } catch (\Throwable $e) { return []; }
    }

    private function decodeJsonObject($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        try { $decoded = json_decode($value, true); return is_array($decoded) ? $decoded : []; } catch (\Throwable $e) { return []; }
    }
}
