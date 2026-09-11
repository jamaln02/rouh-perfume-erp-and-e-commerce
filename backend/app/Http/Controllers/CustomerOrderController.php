<?php

namespace App\Http\Controllers;

use App\Models\ProductVariant;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer-facing order management.
 *
 * Orders are mutable only before the admin accepts them. Once preparation has
 * started (or an admin has accepted the order), the customer can only view it.
 * Ownership is always checked server-side from the authenticated token.
 */
class CustomerOrderController extends Controller
{
    private const EDITABLE_STATUSES = ['pending'];

    private function isEditable(?string $status): bool
    {
        return in_array(strtolower((string) $status), self::EDITABLE_STATUSES, true);
    }

    public function index(Request $request): JsonResponse
    {
        $userId = (string) $request->attributes->get('authUser')->id;
        $orders = DB::table('orders')
            ->select(['id', 'customer_name', 'customer_phone', 'city', 'status', 'order_status', 'total', 'shipping_cost', 'discount_amount', 'payment_method', 'created_at', 'updated_at'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['ok' => true, 'orders' => $orders]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $userId = (string) $request->attributes->get('authUser')->id;
        $order = $this->ownedOrder($id, $userId);
        if ($order instanceof JsonResponse) return $order;

        $items = DB::table('order_items')
            ->select(['id', 'product_id', 'product_variant_id', 'product_name', 'size', 'quantity', 'price', 'line_total'])
            ->where('order_id', $id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'ok' => true,
            'order' => $order,
            'items' => $items,
            'editable' => $this->isEditable($order->status ?? $order->order_status ?? null),
        ]);
    }

    /**
     * Update customer information and/or replace the order's product lines.
     * Product prices, names and variants are always resolved from the database.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $userId = (string) $request->attributes->get('authUser')->id;
        $order = $this->ownedOrder($id, $userId);
        if ($order instanceof JsonResponse) return $order;

        if (!$this->isEditable($order->status ?? $order->order_status ?? null)) {
            return response()->json([
                'ok' => false,
                'message' => 'This order can no longer be edited because it has already been accepted or processing has started.',
            ], 409);
        }

        $validated = $request->validate([
            'customer_name' => ['sometimes', 'string', 'max:200'],
            'customer_phone' => ['sometimes', 'string', 'regex:/^\+9639\d{8}$/', 'max:20'],
            'customer_address' => ['sometimes', 'string', 'max:2000'],
            'city' => ['sometimes', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'string', 'max:64'],
            'items.*.product_variant_id' => ['nullable', 'string', 'max:64'],
            'items.*.size' => ['nullable', 'string', 'max:80'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:100'],
        ]);

        $hasItems = array_key_exists('items', $validated);
        $updates = [];
        foreach (['customer_name', 'customer_phone', 'customer_address', 'city', 'notes'] as $field) {
            if ($request->has($field)) $updates[$field] = $validated[$field] ?? null;
        }

        try {
            DB::transaction(function () use ($id, $order, $validated, $hasItems, &$updates): void {
                $lockedOrder = DB::table('orders')->where('id', $id)->lockForUpdate()->first();
                if (!$lockedOrder) throw new \RuntimeException('Order not found.');
                if (!$this->isEditable($lockedOrder->status ?? $lockedOrder->order_status ?? null)) {
                    throw new \RuntimeException('The order is no longer editable.');
                }

                $existingItems = DB::table('order_items')->where('order_id', $id)->orderBy('id')->get();
                $itemsNeedRepricing = $hasItems
                    || array_key_exists('city', $updates)
                    || array_key_exists('customer_phone', $updates);

                $items = $existingItems->map(static fn ($item): array => [
                    'order_id' => $id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'recipe_id' => $item->recipe_id,
                    'product_name' => $item->product_name,
                    'size' => $item->size,
                    'quantity' => (int) $item->quantity,
                    'price' => (float) $item->price,
                    'line_total' => (float) $item->line_total,
                    'line_discount_amount' => (float) ($item->line_discount_amount ?? 0),
                    'created_at' => $item->created_at,
                    'updated_at' => now(),
                ])->all();

                if ($hasItems) {
                    $items = $this->buildItems($id, $validated['items']);
                    DB::table('order_items')->where('order_id', $id)->delete();
                    DB::table('order_items')->insert($items);
                }

                if ($itemsNeedRepricing) {
                    $newSubtotal = round(array_sum(array_column($items, 'line_total')), 2);
                    $newCity = (string) ($updates['city'] ?? $lockedOrder->city);
                    $shipping = app(\App\Services\ShippingRateService::class)->calculateForOrder($newCity, $newSubtotal);
                    $newPhone = (string) ($updates['customer_phone'] ?? $lockedOrder->customer_phone);
                    $couponPreview = app(\App\Services\CouponService::class)->preview(
                        $lockedOrder->coupon_code ?? null,
                        (string) $lockedOrder->user_id,
                        $newPhone,
                        $newSubtotal,
                        (string) $id
                    );
                    $couponDiscount = round((float) ($couponPreview['discount_amount'] ?? 0), 2);
                    $loyaltyPoints = max(0, (int) ($lockedOrder->loyalty_points_redeemed ?? 0));
                    $loyaltyDiscount = $loyaltyPoints > 0
                        ? app(\App\Services\LoyaltyService::class)->calculatePointsDiscount($loyaltyPoints)
                        : 0.0;
                    if ($loyaltyDiscount > ($newSubtotal + $shipping - $couponDiscount + 0.005)) {
                        throw new \RuntimeException('The retained loyalty redemption is too large for the updated order total. Reissue the order under an approved workflow.');
                    }

                    $newTotal = round(max(0.0, $newSubtotal + $shipping - $couponDiscount - $loyaltyDiscount), 2);
                    $paid = (float) ($lockedOrder->paid_amount ?? 0);
                    if ($paid > $newTotal + 0.005) {
                        throw new \RuntimeException('The updated order total cannot be lower than the amount already paid.');
                    }

                    if ($lockedOrder->coupon_code) {
                        app(\App\Services\CouponService::class)->repriceForOrder($id, $lockedOrder->coupon_code, $couponDiscount);
                    }

                    $updates['subtotal'] = $newSubtotal;
                    $updates['discount_amount'] = ($couponDiscount + $loyaltyDiscount) > 0 ? round($couponDiscount + $loyaltyDiscount, 2) : null;
                    $updates['loyalty_discount_amount'] = $loyaltyDiscount;
                    $updates['total'] = $newTotal;
                    $updates['remaining_amount'] = round(max(0, $newTotal - $paid), 2);
                    $updates['shipping_fee'] = $shipping;
                    $updates['shipping_cost'] = $shipping;
                }

                if (empty($updates)) throw new \RuntimeException('No fields to update.');
                $updates['updated_at'] = now();
                DB::table('orders')->where('id', $id)->update($updates);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => app()->environment('local') ? $e->getMessage() : 'Unable to update the order safely.'], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'Unable to update the order right now.'], 500);
        }

        $fresh = DB::table('orders')->where('id', $id)->first();
        $freshItems = DB::table('order_items')->where('order_id', $id)->orderBy('id')->get();
        return response()->json([
            'ok' => true,
            'order' => $fresh,
            'items' => $freshItems,
            'editable' => $this->isEditable($fresh->status ?? $fresh->order_status ?? null),
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $userId = (string) $request->attributes->get('authUser')->id;
        $order = $this->ownedOrder($id, $userId);
        if ($order instanceof JsonResponse) return $order;

        if (!$this->isEditable($order->status ?? $order->order_status ?? null)) {
            return response()->json([
                'ok' => false,
                'message' => 'This order cannot be cancelled because it has already been accepted or processing has started.',
            ], 409);
        }

        try {
            $fresh = app(\App\Services\OrderLifecycleService::class)->cancel($id, 'Customer cancelled order');
            return response()->json(['ok' => true, 'order' => $fresh, 'editable' => false]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => app()->environment('local') ? $e->getMessage() : 'Unable to cancel the order safely.'], 409);
        }
    }

    private function ownedOrder(string $id, string $userId): \stdClass|JsonResponse
    {
        $order = DB::table('orders')->where('id', $id)->first();
        if (!$order) return response()->json(['ok' => false, 'message' => 'Order not found'], 404);
        if ((string) $order->user_id !== $userId) return response()->json(['ok' => false, 'message' => 'Forbidden'], 403);
        return $order;
    }

    private function buildItems(string $orderId, array $input): array
    {
        $productIds = array_values(array_unique(array_map(fn ($item) => (string) $item['product_id'], $input)));
        $products = DB::table('products')->whereIn('id', $productIds)->get()->keyBy('id');
        $result = [];

        foreach ($input as $item) {
            $productId = (string) $item['product_id'];
            $product = $products->get($productId);
            if (!$product) throw new \RuntimeException('One of the selected products is no longer available.');

            $size = isset($item['size']) ? trim((string) $item['size']) : null;
            $variantId = !empty($item['product_variant_id']) ? (string) $item['product_variant_id'] : null;
            if (!$variantId && $size !== null && $size !== '') {
                $variantId = ProductVariant::where('product_id', $productId)
                    ->where('size_label', $size)
                    ->where('is_active', true)
                    ->value('id');
            }

            $variant = $variantId ? ProductVariant::where('id', $variantId)->where('product_id', $productId)->where('is_active', true)->first() : null;
            if ($size && !$variant && $variantId) throw new \RuntimeException('The selected size is no longer available.');
            if ($size && !$variantId) throw new \RuntimeException('The selected size is no longer available.');

            $unitPrice = null;
            if ($variant && $variant->selling_price_default !== null) $unitPrice = (float) $variant->selling_price_default;
            if ($unitPrice === null && !empty($size) && !empty($product->size_prices)) {
                $sizePrices = is_string($product->size_prices) ? json_decode($product->size_prices, true) : (array) $product->size_prices;
                foreach ((array) $sizePrices as $key => $value) {
                    if (strtolower((string) $key) === strtolower($size) && is_numeric($value)) { $unitPrice = (float) $value; break; }
                }
            }
            if ($unitPrice === null) $unitPrice = (float) $product->price;
            if ($unitPrice < 0) throw new \RuntimeException('Invalid product price.');

            $quantity = (int) $item['quantity'];
            $recipeId = $variantId
                ? Recipe::where('product_variant_id', $variantId)->where('is_active', true)->orderByDesc('version')->value('id')
                : null;

            $result[] = [
                'order_id' => $orderId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'recipe_id' => $recipeId,
                'product_name' => $product->name,
                'size' => $size,
                'quantity' => $quantity,
                'price' => $unitPrice,
                'line_total' => round($unitPrice * $quantity, 2),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $result;
    }

    private function resolveCouponDiscount(?string $couponCode, float $subtotal): float
    {
        if (!$couponCode || $subtotal <= 0) return 0.0;
        $coupon = DB::table('coupons')->where('code', strtoupper(trim($couponCode)))->first();
        if (!$coupon || !(bool) $coupon->active || ($coupon->expires_at && now()->greaterThan($coupon->expires_at))) return 0.0;
        return round($subtotal * ((float) $coupon->discount_percent / 100), 2);
    }
}
