<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BundleCrudController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['bundles' => $this->withItems()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);
        $id = (string) Str::uuid();
        DB::transaction(fn () => $this->saveBundle($id, $validated, true));
        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if (!DB::table('bundles')->where('id', $id)->exists()) {
            return response()->json(['ok' => false, 'message' => 'Offer not found'], 404);
        }

        $validated = $this->validatePayload($request);
        DB::transaction(fn () => $this->saveBundle($id, $validated, false));
        return response()->json(['ok' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = DB::table('bundles')->where('id', $id)->delete();
        return $deleted
            ? response()->json(['ok' => true])
            : response()->json(['ok' => false, 'message' => 'Offer not found'], 404);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'offer_type' => ['required', 'in:custom_bundle,product_discount,mix_match,specific_product,fixed_bundle'],
            'name' => ['required', 'string', 'max:200'],
            'name_ar' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'description_ar' => ['nullable', 'string', 'max:5000'],
            'image_url' => ['nullable', 'string', 'max:1000'],
            'bundle_price' => ['nullable', 'numeric', 'min:0'],
            'original_price' => ['nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'active' => ['required', 'boolean'],
            'stock' => ['required', 'integer', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'offer_config' => ['nullable', 'array'],
            'offer_config.pricing_mode' => ['nullable', 'in:slot_rules,fixed_total'],
            'offer_config.fixed_total' => ['nullable', 'numeric', 'min:0'],
            'offer_config.slots' => ['nullable', 'array', 'min:1', 'max:100'],
            'offer_config.slots.*.id' => ['required', 'string', 'max:100'],
            'offer_config.slots.*.label_ar' => ['nullable', 'string', 'max:200'],
            'offer_config.slots.*.label_en' => ['nullable', 'string', 'max:200'],
            'offer_config.slots.*.product_mode' => ['required', 'in:any,selected'],
            'offer_config.slots.*.product_ids' => ['nullable', 'array', 'max:200'],
            'offer_config.slots.*.product_ids.*' => ['string', 'max:64'],
            'offer_config.slots.*.size_mode' => ['required', 'in:any,selected'],
            'offer_config.slots.*.sizes' => ['nullable', 'array', 'max:100'],
            'offer_config.slots.*.sizes.*' => ['string', 'max:100'],
            'offer_config.slots.*.price_mode' => ['required', 'in:normal,free,percent_discount,fixed_discount,fixed_price,fixed_price_by_size'],
            'offer_config.slots.*.value' => ['nullable', 'numeric', 'min:0'],
            'offer_config.slots.*.values_by_size' => ['nullable', 'array'],
            'offer_config.slots.*.values_by_size.*' => ['numeric', 'min:0'],
        ]);

        $type = $validated['offer_type'];
        $config = is_array($validated['offer_config'] ?? null) ? $validated['offer_config'] : null;

        if (in_array($type, ['custom_bundle', 'product_discount'], true) && !$config) {
            throw ValidationException::withMessages(['offer_config' => 'Offer rules are required.']);
        }

        if ($config) {
            $slots = array_values($config['slots'] ?? []);
            if (!$slots) throw ValidationException::withMessages(['offer_config.slots' => 'At least one offer item is required.']);

            if ($type === 'product_discount' && count($slots) !== 1) {
                throw ValidationException::withMessages(['offer_config.slots' => 'A product discount offer must contain exactly one item.']);
            }

            $productIds = [];
            foreach ($slots as $index => $slot) {
                $productMode = $slot['product_mode'] ?? 'any';
                $ids = array_values(array_unique(array_map('strval', $slot['product_ids'] ?? [])));
                if ($productMode === 'selected' && !$ids) {
                    throw ValidationException::withMessages(["offer_config.slots.$index.product_ids" => 'Select at least one perfume for this item.']);
                }
                $productIds = array_merge($productIds, $ids);

                $sizeMode = $slot['size_mode'] ?? 'any';
                $sizes = array_values(array_unique(array_map('strval', $slot['sizes'] ?? [])));
                if ($sizeMode === 'selected' && !$sizes) {
                    throw ValidationException::withMessages(["offer_config.slots.$index.sizes" => 'Select at least one size for this item.']);
                }

                $priceMode = $slot['price_mode'] ?? 'normal';
                if (in_array($priceMode, ['percent_discount', 'fixed_discount', 'fixed_price'], true) && !is_numeric($slot['value'] ?? null)) {
                    throw ValidationException::withMessages(["offer_config.slots.$index.value" => 'Enter the price/discount value for this item.']);
                }
                if ($priceMode === 'percent_discount' && (float) $slot['value'] > 100) {
                    throw ValidationException::withMessages(["offer_config.slots.$index.value" => 'Percentage discount cannot exceed 100.']);
                }
                if ($priceMode === 'fixed_price_by_size' && empty($slot['values_by_size'])) {
                    throw ValidationException::withMessages(["offer_config.slots.$index.values_by_size" => 'Enter a price for at least one size.']);
                }
            }

            $existingProducts = empty($productIds)
                ? 0
                : DB::table('products')->whereIn('id', array_values(array_unique($productIds)))->count();
            if ($existingProducts !== count(array_values(array_unique($productIds)))) {
                throw ValidationException::withMessages(['offer_config' => 'One or more selected perfumes are invalid.']);
            }

            if (($config['pricing_mode'] ?? 'slot_rules') === 'fixed_total' && (float) ($config['fixed_total'] ?? 0) <= 0) {
                throw ValidationException::withMessages(['offer_config.fixed_total' => 'Enter a positive fixed total for the offer.']);
            }
        }

        $normalizedConfig = $config ? $this->normalizeConfig($config) : null;
        $validated['offer_config'] = $normalizedConfig;

        if (in_array($type, ['custom_bundle', 'product_discount'], true)) {
            $validated['bundle_price'] = (float) (($normalizedConfig['pricing_mode'] ?? 'slot_rules') === 'fixed_total' ? $normalizedConfig['fixed_total'] : ($validated['bundle_price'] ?? 0));
            $validated['original_price'] = (float) ($validated['original_price'] ?? 0);
        }

        return $validated;
    }

    private function normalizeConfig(array $config): array
    {
        $out = [
            'version' => 2,
            'pricing_mode' => $config['pricing_mode'] ?? 'slot_rules',
            'fixed_total' => (float) ($config['fixed_total'] ?? 0),
            'slots' => [],
        ];

        foreach (array_values($config['slots'] ?? []) as $index => $slot) {
            $out['slots'][] = [
                'id' => (string) ($slot['id'] ?? 'slot-' . ($index + 1)),
                'label_ar' => (string) ($slot['label_ar'] ?? 'القطعة ' . ($index + 1)),
                'label_en' => (string) ($slot['label_en'] ?? 'Item ' . ($index + 1)),
                'product_mode' => $slot['product_mode'] ?? 'any',
                'product_ids' => array_values(array_unique(array_map('strval', $slot['product_ids'] ?? []))),
                'size_mode' => $slot['size_mode'] ?? 'any',
                'sizes' => array_values(array_unique(array_map('strval', $slot['sizes'] ?? []))),
                'price_mode' => $slot['price_mode'] ?? 'normal',
                'value' => (float) ($slot['value'] ?? 0),
                'values_by_size' => is_array($slot['values_by_size'] ?? null) ? $slot['values_by_size'] : [],
            ];
        }

        return $out;
    }

    private function saveBundle(string $id, array $data, bool $insert): void
    {
        $now = now();
        $config = $data['offer_config'] ?? null;
        $slots = $config['slots'] ?? [];
        $firstSlot = $slots[0] ?? null;
        $legacyType = $data['offer_type'];

        $payload = [
            'offer_type' => $legacyType,
            'name' => $data['name'],
            'name_ar' => $data['name_ar'],
            'description' => $data['description'] ?? null,
            'description_ar' => $data['description_ar'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'original_price' => (float) ($data['original_price'] ?? 0),
            'bundle_price' => (float) ($data['bundle_price'] ?? 0),
            'discount_percentage' => $this->displayDiscount($data, $config),
            'active' => (bool) $data['active'],
            'stock' => (int) ($data['stock'] ?? 0),
            'usage_limit' => isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'paid_quantity' => $legacyType === 'custom_bundle' ? count(array_filter($slots, fn ($s) => ($s['price_mode'] ?? 'normal') !== 'free')) : 0,
            'free_quantity' => $legacyType === 'custom_bundle' ? count(array_filter($slots, fn ($s) => ($s['price_mode'] ?? 'normal') === 'free')) : 0,
            'target_product_id' => ($legacyType === 'product_discount' && $firstSlot && !empty($firstSlot['product_ids'][0])) ? $firstSlot['product_ids'][0] : null,
            'allowed_sizes' => json_encode($this->legacyAllowedSizes($config), JSON_UNESCAPED_UNICODE),
            'price_by_size' => json_encode(($firstSlot['values_by_size'] ?? []), JSON_UNESCAPED_UNICODE),
            'offer_config' => $config ? json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'updated_at' => $now,
        ];

        if ($insert) {
            $payload['id'] = $id;
            $payload['created_at'] = $now;
            DB::table('bundles')->insert($payload);
        } else {
            DB::table('bundles')->where('id', $id)->update($payload);
        }

        DB::table('bundle_items')->where('bundle_id', $id)->delete();
    }

    private function displayDiscount(array $data, ?array $config): float
    {
        if (array_key_exists('discount_percentage', $data) && $data['discount_percentage'] !== null) {
            return (float) $data['discount_percentage'];
        }
        $original = (float) ($data['original_price'] ?? 0);
        $price = (float) ($data['bundle_price'] ?? 0);
        return $original > 0 && $price >= 0
            ? round(max(0, 1 - ($price / $original)) * 100, 2)
            : 0;
    }

    private function legacyAllowedSizes(?array $config): array
    {
        if (!$config || empty($config['slots'])) return [];
        $sets = [];
        foreach ($config['slots'] as $slot) {
            if (($slot['size_mode'] ?? 'any') !== 'selected') return [];
            $sizes = array_values(array_unique(array_map('strval', $slot['sizes'] ?? [])));
            if ($sizes) $sets[] = $sizes;
        }
        if (!$sets) return [];
        $common = $sets[0];
        foreach (array_slice($sets, 1) as $set) {
            $common = array_values(array_intersect($common, $set));
        }
        return $common;
    }

    private function withItems(): array
    {
        $bundles = DB::table('bundles')->orderByDesc('created_at')->get();
        foreach ($bundles as $bundle) {
            $bundle->allowed_sizes = $this->decodeJsonArray($bundle->allowed_sizes);
            $bundle->price_by_size = $this->decodeJsonObject($bundle->price_by_size);
            $bundle->offer_config = $this->decodeJsonObject($bundle->offer_config ?? null);
            $bundle->items = DB::table('bundle_items')->where('bundle_id', $bundle->id)->orderBy('created_at')->get();
        }
        return $bundles->all();
    }

    private function decodeJsonArray($value): array
    {
        if (is_array($value)) return array_values($value);
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function decodeJsonObject($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
