<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Services\CouponService;
use App\Services\CustomerReferenceService;
use App\Services\LoyaltyService;
use App\Services\ShippingRateService;
use App\Support\ApiTokenAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrderController extends Controller
{
    public function __construct(
        private readonly ApiTokenAuth $auth,
        private readonly CustomerReferenceService $customers,
        private readonly ShippingRateService $shipping,
        private readonly CouponService $coupons,
        private readonly LoyaltyService $loyalty,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:200'],
            'customer_phone' => ['required', 'string', 'regex:/^\+9639\d{8}$/'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['required', 'string', 'max:2000'],
            'city' => ['required', 'string', 'max:100'],
            'payment_method' => ['nullable', 'in:cash_on_delivery'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'coupon_code' => ['nullable', 'string', 'max:32'],
            'loyalty_points_to_redeem' => ['nullable', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'string', 'max:64'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:50'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:' . (int) config('rouh.order.max_quantity_per_line', 100)],
            'items.*.product_variant_id' => ['nullable', 'string', 'max:64'],
            'items.*.offer_id' => ['nullable', 'string', 'max:64'],
            'items.*.offer_selection_id' => ['nullable', 'string', 'max:100'],
            'items.*.offer_slot_id' => ['nullable', 'string', 'max:100'],
        ]);

        $existingKey = trim((string) $request->header('Idempotency-Key', ''));
        if ($existingKey === '' || strlen($existingKey) > 100) {
            return response()->json(['ok' => false, 'message' => 'Idempotency-Key header is required.'], 422);
        }

        $authUser = $this->auth->resolveUser($request);
        if ($request->bearerToken() && !$authUser) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        $userId = $authUser ? (string) $authUser->id : null;

        $existing = DB::table('orders')->where('idempotency_key', $existingKey)->first();
        if ($existing) {
            return response()->json([
                'ok' => true,
                'order_id' => $existing->id,
                'tracking_token' => $existing->public_tracking_token,
                'replayed' => true,
            ]);
        }

        try {
            $result = DB::transaction(function () use ($validated, $existingKey, $userId): array {
                $customer = $this->customers->findOrCreateForPublicOrder($validated);
                $orderId = (string) Str::uuid();
                $trackingToken = hash('sha384', $orderId . '|' . Str::random(32));

                [$items, $offerDiscount] = $this->buildAuthoritativeItems($orderId, $validated['items']);
                $subtotal = round(array_sum(array_column($items, 'line_total')), 2);
                $shippingCost = $this->shipping->calculateForOrder((string) $validated['city'], $subtotal);

                $coupon = $this->coupons->preview(
                    $validated['coupon_code'] ?? null,
                    $userId,
                    (string) $validated['customer_phone'],
                    $subtotal
                );
                $couponDiscount = round((float) ($coupon['discount_amount'] ?? 0), 2);

                $loyaltyPoints = (int) ($validated['loyalty_points_to_redeem'] ?? 0);
                $loyaltyDiscount = 0.0;
                if ($loyaltyPoints > 0) {
                    if (!$userId) throw new \InvalidArgumentException('Loyalty points are available only for signed-in customers.');
                    $loyaltyDiscount = $this->loyalty->calculatePointsDiscount($loyaltyPoints);
                    if ($loyaltyDiscount > ($subtotal + $shippingCost - $couponDiscount + 0.005)) {
                        throw new \InvalidArgumentException('The requested loyalty points exceed the amount payable for this order.');
                    }
                }

                $total = round(max(0.0, $subtotal + $shippingCost - $couponDiscount - $loyaltyDiscount), 2);
                $now = now();

                DB::table('orders')->insert([
                    'id' => $orderId,
                    'idempotency_key' => $existingKey,
                    'public_tracking_token' => $trackingToken,
                    'user_id' => $userId,
                    'customer_id' => $customer->id,
                    'customer_name' => $validated['customer_name'],
                    'customer_phone' => $validated['customer_phone'],
                    'customer_address' => $validated['customer_address'],
                    'city' => $validated['city'],
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'shipping_cost' => $shippingCost,
                    'shipping_fee' => $shippingCost,
                    'payment_method' => $validated['payment_method'] ?? 'cash_on_delivery',
                    'notes' => $validated['notes'] ?? null,
                    'status' => 'pending',
                    'order_status' => 'pending',
                    'consumption_status' => 'not_consumed',
                    'payment_status' => 'unpaid',
                    'paid_amount' => 0,
                    'remaining_amount' => $total,
                    'coupon_code' => $coupon['code'] ?? null,
                    'discount_amount' => ($couponDiscount + $loyaltyDiscount + $offerDiscount) > 0 ? round($couponDiscount + $loyaltyDiscount + $offerDiscount, 2) : null,
                    'loyalty_points_redeemed' => $loyaltyPoints,
                    'loyalty_discount_amount' => $loyaltyDiscount,
                    'loyalty_redeemed_at' => $loyaltyPoints > 0 ? $now : null,
                    'loyalty_points_earned' => 0,
                    'source' => 'online',
                    'delivery_type' => 'delivery',
                    'delivery_status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('order_items')->insert($items);

                if ($coupon['code'] ?? null) {
                    $this->coupons->redeemForOrder(
                        $orderId,
                        $coupon['code'],
                        $userId,
                        (string) $validated['customer_phone'],
                        $subtotal
                    );
                }

                if ($loyaltyPoints > 0) {
                    $order = \App\Models\Order::query()->lockForUpdate()->findOrFail($orderId);
                    $this->loyalty->redeemForOrder($order, $userId, $loyaltyPoints);
                }

                return ['order_id' => $orderId, 'tracking_token' => $trackingToken, 'points_redeemed' => $loyaltyPoints];
            });

            return response()->json(['ok' => true, ...$result]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            $existing = DB::table('orders')->where('idempotency_key', $existingKey)->first();
            if ($existing) {
                return response()->json([
                    'ok' => true,
                    'order_id' => $existing->id,
                    'tracking_token' => $existing->public_tracking_token,
                    'replayed' => true,
                ]);
            }
            report($e);
            return response()->json(['ok' => false, 'message' => 'Order could not be created safely.'], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'Unable to create the order right now.'], 500);
        }
    }

    private function buildAuthoritativeItems(string $orderId, array $input): array
    {
        $result = [];
        foreach ($input as $item) {
            $productId = (string) $item['product_id'];
            $product = DB::table('products')->where('id', $productId)->first();
            if (!$product) throw new \InvalidArgumentException('One of the selected products is no longer available.');

            $size = isset($item['size']) ? trim((string) $item['size']) : null;
            $variantId = !empty($item['product_variant_id']) ? (string) $item['product_variant_id'] : null;
            if (!$variantId && $size !== '') {
                $variantId = ProductVariant::where('product_id', $productId)->where('size_label', $size)->where('is_active', true)->value('id');
            }
            if (!$variantId) throw new \InvalidArgumentException('A valid active product variant is required for every order line.');

            $variant = ProductVariant::whereKey($variantId)->where('product_id', $productId)->where('is_active', true)->first();
            if (!$variant) throw new \InvalidArgumentException('Invalid or inactive product variant.');
            if ($size !== null && $size !== '' && trim((string) $variant->size_label) !== $size) throw new \InvalidArgumentException('Selected size does not match the product variant.');

            $unitPrice = $variant->selling_price_default !== null ? (float) $variant->selling_price_default : null;
            if ($unitPrice === null && !empty($size) && !empty($product->size_prices)) {
                $sizePrices = is_string($product->size_prices) ? json_decode($product->size_prices, true) : (array) $product->size_prices;
                foreach ((array) $sizePrices as $key => $value) {
                    if (strcasecmp((string) $key, $size) === 0 && is_numeric($value)) { $unitPrice = (float) $value; break; }
                }
            }
            if ($unitPrice === null) $unitPrice = (float) ($product->price ?? 0);
            if ($unitPrice < 0) throw new \InvalidArgumentException('Invalid product price.');

            $qty = (int) $item['quantity'];
            $recipeId = Recipe::where('product_variant_id', $variantId)->where('is_active', true)->orderByDesc('version')->value('id');
            $result[] = [
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'recipe_id' => $recipeId,
                'product_name' => $product->name,
                'size' => $variant->size_label,
                'quantity' => $qty,
                'price' => round($unitPrice, 2),
                'line_discount_amount' => 0,
                'line_total' => round($unitPrice * $qty, 2),
                'offer_id' => $item['offer_id'] ?? null,
                'offer_name' => null,
                'offer_selection_id' => $item['offer_selection_id'] ?? null,
                'offer_slot_id' => $item['offer_slot_id'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $offerDiscount = $this->applyOfferPricing($result, $input);
        return [$result, $offerDiscount];
    }

    private function applyOfferPricing(array &$items, array $input): float
    {
        $groups = [];
        foreach ($input as $index => $row) {
            $offerId = trim((string) ($row['offer_id'] ?? ''));
            if ($offerId === '') continue;
            $selectionId = trim((string) ($row['offer_selection_id'] ?? ''));
            $slotId = trim((string) ($row['offer_slot_id'] ?? ''));
            if ($selectionId === '' || $slotId === '') {
                throw new \InvalidArgumentException('The offer selection is incomplete.');
            }
            if ((int) ($row['quantity'] ?? 0) !== 1) {
                throw new \InvalidArgumentException('Offer items cannot be multiplied from the cart.');
            }
            $groups[$offerId][$selectionId][$slotId][] = $index;
        }

        $totalDiscount = 0.0;
        foreach ($groups as $offerId => $selections) {
            $offer = DB::table('bundles')->where('id', $offerId)->lockForUpdate()->first();
            if (!$offer || !$offer->active) throw new \InvalidArgumentException('This offer is no longer available.');
            if ($offer->starts_at && now()->lt($offer->starts_at)) throw new \InvalidArgumentException('This offer has not started yet.');
            if ($offer->ends_at && now()->gt($offer->ends_at)) throw new \InvalidArgumentException('This offer has expired.');

            if ($offer->usage_limit !== null && (int) $offer->usage_limit > 0) {
                $used = DB::table('order_items')->where('offer_id', $offerId)->distinct('order_id')->count('order_id');
                if ($used >= (int) $offer->usage_limit) throw new \InvalidArgumentException('This offer has reached its usage limit.');
            }

            $config = $this->decodeJsonObject($offer->offer_config ?? null);
            $slots = is_array($config['slots'] ?? null) ? array_values($config['slots']) : [];
            if (!$slots) {
                throw new \InvalidArgumentException('This offer is not configured correctly.');
            }
            $slotMap = [];
            foreach ($slots as $slot) {
                $slotMap[(string) ($slot['id'] ?? '')] = $slot;
            }

            foreach ($selections as $selectionId => $slotGroups) {
                $selectedSlotIds = array_keys($slotGroups);
                $configuredSlotIds = array_keys($slotMap);
                sort($selectedSlotIds);
                sort($configuredSlotIds);
                if ($selectedSlotIds !== $configuredSlotIds) {
                    throw new \InvalidArgumentException('The selected items do not match this offer.');
                }

                $chosen = [];
                $regularTotal = 0.0;
                $eligibleFixedTotalBase = 0.0;

                foreach ($slotMap as $slotId => $slot) {
                    $indices = $slotGroups[$slotId] ?? [];
                    if (count($indices) !== 1) throw new \InvalidArgumentException('Each offer item must be selected exactly once.');
                    $i = $indices[0];
                    $row = $input[$i];
                    $size = trim((string) ($items[$i]['size'] ?? ''));
                    $productId = (string) $items[$i]['product_id'];

                    $this->validateOfferSlot($slot, $productId, $size);

                    $base = (float) $items[$i]['line_total'];
                    $regularTotal += $base;
                    $priceMode = (string) ($slot['price_mode'] ?? 'normal');
                    if ($priceMode !== 'free') $eligibleFixedTotalBase += $base;
                    $chosen[] = [$i, $slot, $base];
                }

                $pricingMode = (string) ($config['pricing_mode'] ?? 'slot_rules');
                if ($pricingMode === 'fixed_total') {
                    $targetTotal = (float) ($config['fixed_total'] ?? 0);
                    if ($targetTotal <= 0 || $targetTotal > $regularTotal + 0.005) {
                        throw new \InvalidArgumentException('The configured offer total is invalid for the selected products.');
                    }
                    $remainingTarget = $targetTotal;
                    $remainingDiscount = round($regularTotal - $targetTotal, 2);
                    $paidPositions = [];
                    foreach ($chosen as $position => [$i, $slot, $base]) {
                        if ((string) ($slot['price_mode'] ?? 'normal') !== 'free') $paidPositions[] = $position;
                    }
                    $lastPaidPosition = $paidPositions ? end($paidPositions) : null;
                    foreach ($chosen as $position => [$i, $slot, $base]) {
                        $priceMode = (string) ($slot['price_mode'] ?? 'normal');
                        if ($priceMode === 'free') {
                            $lineDiscount = $base;
                        } else {
                            $allocated = $position === $lastPaidPosition && $eligibleFixedTotalBase > 0
                                ? $remainingTarget
                                : round($targetTotal * ($base / max($eligibleFixedTotalBase, 0.01)), 2);
                            $allocated = min($base, max(0, $allocated));
                            $lineDiscount = round($base - $allocated, 2);
                            $remainingTarget = round($remainingTarget - $allocated, 2);
                        }
                        $lineDiscount = min($base, max(0, $lineDiscount));
                        $items[$i]['line_discount_amount'] = $lineDiscount;
                        $items[$i]['line_total'] = round($base - $lineDiscount, 2);
                        $items[$i]['offer_name'] = $offer->name_ar ?: $offer->name;
                        $remainingDiscount = round($remainingDiscount - $lineDiscount, 2);
                    }
                    $totalDiscount += round($regularTotal - $targetTotal, 2);
                } else {
                    $bundleTotal = 0.0;
                    foreach ($chosen as [$i, $slot, $base]) {
                        $mode = (string) ($slot['price_mode'] ?? 'normal');
                        $value = (float) ($slot['value'] ?? 0);
                        $lineTotal = $this->calculateOfferLineTotal($slot, $items[$i], $base);
                        if ($lineTotal > $base + 0.005) throw new \InvalidArgumentException('An offer price cannot exceed the normal product price.');
                        $items[$i]['line_discount_amount'] = round($base - $lineTotal, 2);
                        $items[$i]['line_total'] = round($lineTotal, 2);
                        $items[$i]['offer_name'] = $offer->name_ar ?: $offer->name;
                        $bundleTotal += $lineTotal;
                    }
                    $totalDiscount += round($regularTotal - $bundleTotal, 2);
                }
            }
        }

        return round($totalDiscount, 2);
    }

    private function validateOfferSlot(array $slot, string $productId, string $size): void
    {
        $productMode = (string) ($slot['product_mode'] ?? 'any');
        $productIds = array_map('strval', (array) ($slot['product_ids'] ?? []));
        if ($productMode === 'selected' && !in_array($productId, $productIds, true)) {
            throw new \InvalidArgumentException('The selected perfume is not allowed in this offer item.');
        }

        $sizeMode = (string) ($slot['size_mode'] ?? 'any');
        $sizes = array_map('strval', (array) ($slot['sizes'] ?? []));
        if ($sizeMode === 'selected' && !$this->containsSize($sizes, $size)) {
            throw new \InvalidArgumentException('The selected size is not allowed in this offer item.');
        }
    }

    private function calculateOfferLineTotal(array $slot, array $item, float $base): float
    {
        return match ((string) ($slot['price_mode'] ?? 'normal')) {
            'free' => 0.0,
            'normal' => $base,
            'percent_discount' => round($base * (1 - min(100, max(0, (float) ($slot['value'] ?? 0))) / 100), 2),
            'fixed_discount' => round(max(0, $base - max(0, (float) ($slot['value'] ?? 0))), 2),
            'fixed_price' => round(max(0, min($base, (float) ($slot['value'] ?? 0))), 2),
            'fixed_price_by_size' => $this->configuredSizePrice($slot, (string) ($item['size'] ?? ''), $base),
            default => throw new \InvalidArgumentException('Unknown offer pricing rule.'),
        };
    }

    private function configuredSizePrice(array $slot, string $size, float $base): float
    {
        $map = is_array($slot['values_by_size'] ?? null) ? $slot['values_by_size'] : [];
        foreach ($map as $key => $value) {
            if ($this->containsSize([(string) $key], $size)) {
                $price = (float) $value;
                if ($price > $base + 0.005) throw new \InvalidArgumentException('The configured offer price cannot exceed the normal product price.');
                return max(0, round($price, 2));
            }
        }
        throw new \InvalidArgumentException('No configured offer price exists for the selected size.');
    }

    private function containsSize(array $sizes, string $value): bool
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', '', trim($value)) ?? trim($value));
        foreach ($sizes as $size) {
            $candidate = mb_strtolower(preg_replace('/\s+/u', '', trim((string) $size)) ?? trim((string) $size));
            if ($candidate === $normalized) return true;
        }
        return false;
    }

    private function sizePrice(array $map, string $size, float $fallback): float
    {
        if (isset($map[$size]) && is_numeric($map[$size])) return (float) $map[$size];
        foreach ($map as $key => $value) if ($this->containsSize([(string) $key], $size) && is_numeric($value)) return (float) $value;
        return $fallback;
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
