<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FinancialPayment;
use App\Models\FinancialAccount;
use App\Models\Material;
use App\Models\OrderConsumption;
use App\Models\Order;
use App\Models\OrderCostSnapshot;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\Sale;
use App\Models\RecipeItem;
use App\Services\UnitConversionService;
use App\Services\OrderConsumptionService;
use App\Services\FinancePostingService;
use App\Services\CustomerStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class BusinessOrdersController extends Controller
{
    public function index(): JsonResponse
    {
        $orders = DB::table('orders as o')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->select(
                'o.*',
                'c.name as customer_name',
                'c.phone as customer_phone',
                'c.address as customer_address',
                'c.city as customer_city'
            )
            ->orderByDesc('o.created_at')
            ->get();

        if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
            $orders->transform(function ($order) {
                $data = (array) $order;
                foreach (['cost', 'profit', 'profit_syp', 'cost_syp', 'total_price_syp'] as $field) unset($data[$field]);
                return (object) $data;
            });
        }

        return response()->json(['orders' => $orders]);
    }

    private function findOrCreateCustomer(array $data): Customer
    {
        $phone = trim((string) ($data['customer_phone'] ?? ''));
        $name = trim((string) ($data['customer_name'] ?? ''));

        $customer = $phone !== ''
            ? Customer::where('phone', $phone)->first()
            : Customer::where('name', $name)
            ->where('address', $data['customer_address'] ?? null)
            ->first();

        if ($customer) {
            $customer->fill([
                'name' => $name ?: $customer->name,
                'phone' => $phone ?: $customer->phone,
                'address' => $data['customer_address'] ?? $customer->address,
                'city' => $data['city'] ?? $customer->city,
            ]);
            $customer->save();

            return $customer;
        }

        return Customer::create([
            'name' => $name,
            'phone' => $phone ?: null,
            'address' => $data['customer_address'] ?? null,
            'city' => $data['city'] ?? null,
            'status' => 'active',
            'total_orders' => 0,
            'total_spent' => 0,
            'loyalty_points' => 0,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if ($idempotencyKey === '') {
            return response()->json(['message' => 'Idempotency-Key header is required when creating an order.'], 422);
        }
        if (strlen($idempotencyKey) > 100) {
            return response()->json(['message' => 'Idempotency-Key is too long.'], 422);
        }

        $existingOrder = Order::where('idempotency_key', $idempotencyKey)->first();
        if ($existingOrder) {
            return response()->json(['ok' => true, 'order_id' => $existingOrder->id, 'replayed' => true]);
        }

        $validated = $request->validate([
            'customer_name' => ['required', 'string', 'max:200'],
            'customer_phone' => ['required', 'string', 'max:20'],
            'customer_address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:120'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:80'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'in:cash,cash_on_delivery,sham_cash,bank_transfer'],
            'payment_status' => ['nullable', 'string', 'max:30'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_type' => ['nullable', 'string', 'max:30'],
            'order_notes' => ['nullable', 'string', 'max:2000'],
            'product_variant_id' => ['nullable', 'string'],
            'source' => ['nullable', 'string', 'max:30'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:80'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'items.*.product_variant_id' => ['required_with:items', 'uuid', 'exists:product_variants,id'],
            'items.*.oil_mix' => ['nullable', 'array', 'max:3'],
            'items.*.oil_mix.*.oil_id' => ['required', 'integer', 'exists:materials,id'],
            'items.*.oil_mix.*.grams' => ['required', 'numeric', 'gt:0'],
            'items.*.oil_mix.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        // Support both the legacy single-item payload and the new multi-item array.
        $items = $validated['items'] ?? null;
        if (empty($items)) {
            $items = [[
                'product_name' => $validated['product_name'],
                'size' => $validated['size'] ?? null,
                'quantity' => (int) ($validated['quantity'] ?? 1),
                'price' => (float) ($validated['selling_price'] ?? 0),
                'discount' => (float) ($validated['discount'] ?? 0),
                'product_variant_id' => $validated['product_variant_id'] ?? null,
            ]];
        }

        // Prices for admin-created orders must be resolved from the active
        // product variant on the server. Client-supplied prices are never
        // authoritative for financial/accounting purposes.
        $items = $this->normalizeManualOrderItems($items);

        $orderId = (string) Str::uuid();

        $subtotal = 0.0;
        foreach ($items as $item) {
            $lineSubtotal = (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1);
            $subtotal += max(0, $lineSubtotal - (float) ($item['discount'] ?? 0));
        }
        $shippingFee = (float) ($validated['shipping_fee'] ?? 0);
        $total = $subtotal + $shippingFee;
        $paidAmount = (float) ($validated['paid_amount'] ?? 0);
        if ($paidAmount > $total + 0.005) {
            return response()->json(['message' => 'Paid amount cannot exceed order total.'], 422);
        }
        if ($paidAmount > 0 && empty($validated['payment_method'])) {
            return response()->json(['message' => 'Payment method is required when an order is created with an advance payment.'], 422);
        }
        $remainingAmount = max(0, $total - $paidAmount);
        $paymentStatus = $paidAmount >= $total && $total > 0 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');

        try {
            DB::transaction(function () use ($orderId, $idempotencyKey, $validated, $items, $subtotal, $shippingFee, $total, $paidAmount, $remainingAmount, $paymentStatus): void {
                $customer = $this->findOrCreateCustomer($validated);

                DB::table('orders')->insert([
                    'id' => $orderId,
                    'idempotency_key' => $idempotencyKey,
                    'user_id' => null,
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'customer_phone' => $customer->phone,
                    'customer_address' => $customer->address ?? '',
                    'city' => $customer->city ?? '',
                    'total' => $total,
                    'subtotal' => $subtotal,
                    'shipping_fee' => $shippingFee,
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'payment_status' => $paymentStatus,
                    'paid_amount' => $paidAmount,
                    'remaining_amount' => $remainingAmount,
                    'notes' => $validated['order_notes'] ?? null,
                    'status' => 'pending',
                    'order_status' => 'pending',
                    'consumption_status' => 'not_consumed',
                    'source' => $validated['source'] ?? 'offline',
                    'delivery_type' => $validated['delivery_type'] ?? 'delivery',
                    'delivery_status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($paidAmount > 0) {
                    $accountCode = match ($validated['payment_method']) {
                        'cash', 'cash_on_delivery' => '1000',
                        'sham_cash' => '1020',
                        'bank_transfer' => '1010',
                    };
                    $account = FinancialAccount::where('code', $accountCode)->firstOrFail();
                    $payment = FinancialPayment::create([
                        'payment_number' => 'REC-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                        'direction' => 'receipt',
                        'amount' => $paidAmount,
                        'currency' => 'SYP',
                        'exchange_rate' => 1,
                        'amount_base' => $paidAmount,
                        'financial_account_id' => $account->id,
                        'order_id' => $orderId,
                        'payment_method' => $validated['payment_method'],
                        'reference' => 'ORDER-' . $orderId,
                        'payment_date' => now()->toDateString(),
                        'status' => 'posted',
                        'created_by' => request()->attributes->get('authUser')?->id,
                    ]);
                    app(FinancePostingService::class)->postOrderDeposit($payment);
                }

                foreach ($items as $item) {
                    $lineSubtotal = max(0, (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 1) - (float) ($item['discount'] ?? 0));
                    $variant = ProductVariant::with('product')->find($item['product_variant_id']);
                    if (!$variant || !$variant->is_active) {
                        throw new \InvalidArgumentException('Invalid or inactive product variant.');
                    }

                    if (!empty($item['product_id']) && (string) $item['product_id'] !== (string) $variant->product_id) {
                        throw new \InvalidArgumentException('Product and variant do not belong together.');
                    }

                    if (!empty($item['size']) && trim((string) $item['size']) !== trim((string) $variant->size_label)) {
                        throw new \InvalidArgumentException('Selected size does not match the product variant.');
                    }

                    $recipeId = Recipe::where('product_variant_id', $variant->id)
                        ->where('is_active', true)
                        ->orderByDesc('version')
                        ->value('id');
                    DB::table('order_items')->insert([
                        'order_id' => $orderId,
                        'product_id' => $item['product_id'] ?? $variant?->product_id,
                        'product_variant_id' => $variant?->id ?? ($item['product_variant_id'] ?? null),
                        'recipe_id' => $recipeId,
                        'bottle_type' => !empty($item['oil_mix']) ? 'custom' : 'predefined',
                        'bottle_size_ml' => $variant->volume_ml !== null ? (string)$variant->volume_ml . 'ml' : ($item['size'] ?? null),
                        'oil_mix' => !empty($item['oil_mix']) ? json_encode($item['oil_mix'], JSON_UNESCAPED_UNICODE) : null,
                        'product_name' => $item['product_name'],
                        'size' => $item['size'] ?? null,
                        'quantity' => (int) ($item['quantity'] ?? 1),
                        'price' => (float) ($item['price'] ?? 0),
                        'line_discount_amount' => (float) ($item['discount'] ?? 0),
                        'line_total' => $lineSubtotal,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            $existingOrder = Order::where('idempotency_key', $idempotencyKey)->firstOrFail();
            return response()->json(['ok' => true, 'order_id' => $existingOrder->id, 'replayed' => true]);
        }

        return response()->json(['ok' => true, 'order_id' => $orderId]);
    }

    /**
     * Resolve and validate admin-created order lines against the active variant.
     * The client may suggest a price for display, but the server persists only
     * the authoritative variant selling price.
     */
    private function normalizeManualOrderItems(array $items): array
    {
        return array_map(function (array $item): array {
            $variantId = $item['product_variant_id'] ?? null;
            if (!$variantId) {
                throw new \InvalidArgumentException('A valid product variant is required for every order item.');
            }

            $variant = ProductVariant::with('product')->whereKey($variantId)->first();
            if (!$variant || !$variant->is_active) {
                throw new \InvalidArgumentException('Invalid or inactive product variant.');
            }

            if (!empty($item['product_id']) && (string) $item['product_id'] !== (string) $variant->product_id) {
                throw new \InvalidArgumentException('Product and variant do not belong together.');
            }

            if (!empty($item['size']) && trim((string) $item['size']) !== trim((string) $variant->size_label)) {
                throw new \InvalidArgumentException('Selected size does not match the product variant.');
            }

            $item['product_id'] = (string) $variant->product_id;
            $item['product_variant_id'] = (string) $variant->id;
            $item['product_name'] = $item['product_name'] ?? ($variant->product?->name ?? '');
            $item['size'] = $item['size'] ?? $variant->size_label;
            $item['price'] = round((float) ($variant->selling_price_default ?? 0), 2);
            $item['oil_mix'] = $this->normaliseOilMix($item['oil_mix'] ?? null, $variant);

            return $item;
        }, $items);
    }

    private function normaliseOilMix(?array $mix, ProductVariant $variant): array
    {
        if (!$mix) return [];
        $clean = [];
        $totalOilGrams = 0.0;
        foreach ($mix as $row) {
            $oilId = (int)($row['oil_id'] ?? 0);
            $grams = round((float)($row['grams'] ?? 0), 4);
            if ($oilId <= 0 || $grams <= 0) continue;
            $material = Material::query()->whereKey($oilId)->where('material_category','perfume_oil')->where('is_active',true)->firstOrFail();
            $totalOilGrams += $grams;
            $clean[] = ['oil_id'=>$material->id,'grams'=>$grams,'percentage'=>0];
        }
        if (count($clean) < 2) throw new \InvalidArgumentException('A custom blend must contain at least two fragrance oils.');
        $volumeMl = (float)($variant->volume_ml ?? 0);
        if ($volumeMl <= 0 || $totalOilGrams > $volumeMl + 0.0001) throw new \InvalidArgumentException('The total custom oil quantity cannot exceed the bottle volume.');
        foreach ($clean as &$row) { $row['percentage'] = round($row['grams'] / $totalOilGrams * 100, 2); }
        unset($row);
        return $clean;
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => ['nullable', 'string', 'max:200'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'customer_address' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:120'],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'payment_status' => ['nullable', 'string', 'max:30'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'delivery_type' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'items' => ['nullable', 'array'],
            'items.*.product_name' => ['required_with:items', 'string', 'max:255'],
            'items.*.size' => ['nullable', 'string', 'max:80'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.price' => ['required_with:items', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.product_id' => ['nullable', 'uuid', 'exists:products,id'],
            'items.*.product_variant_id' => ['required_with:items', 'uuid', 'exists:product_variants,id'],
            'items.*.oil_mix' => ['nullable', 'array', 'max:3'],
            'items.*.oil_mix.*.oil_id' => ['required', 'integer', 'exists:materials,id'],
            'items.*.oil_mix.*.grams' => ['required', 'numeric', 'gt:0'],
            'items.*.oil_mix.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $order = DB::table('orders')->where('id', $id)->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if (($order->status ?? $order->order_status) === 'cancelled') {
            return response()->json(['ok' => false, 'message' => 'Cancelled orders are immutable. Use a new order for a replacement.'], 409);
        }

        if (array_key_exists('paid_amount', $validated) && abs((float)$validated['paid_amount'] - (float)($order->paid_amount ?? 0)) > 0.005) {
            return response()->json(['message' => 'Payments cannot be edited from the order form. Use the payment registration action so every receipt is posted to the ledger.'], 409);
        }

        $hasItemsPayload = array_key_exists('items', $validated);
        $hasConsumption = DB::table('order_consumptions')->where('order_id', $id)->whereNotIn('status', ['cancelled'])->exists();
        $existingItemsForComparison = DB::table('order_items')->where('order_id', $id)->orderBy('id')->get();

        $reason = trim((string) ($validated['reason'] ?? ''));

        $items = $validated['items'] ?? null;
        if ($items === null) {
            $items = DB::table('order_items')->where('order_id', $id)->get()->map(fn($item) => [
                'product_name' => $item->product_name,
                'size' => $item->size,
                'quantity' => (int)$item->quantity,
                'price' => (float)$item->price,
                'discount' => (float)($item->line_discount_amount ?? 0),
            ])->all();
        } else {
            // Normalize only an explicit item-edit payload; existing persisted
            // items are already authoritative and may belong to legacy orders.
            $items = $this->normalizeManualOrderItems($items);
        }
        $itemsChanged = false;
        if ($hasItemsPayload) {
            $incomingForComparison = collect($items)->map(fn($item) => [
                'product_id' => $item['product_id'] ?? null,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'product_name' => (string) ($item['product_name'] ?? ''),
                'size' => $item['size'] ?? null,
                'quantity' => (int) ($item['quantity'] ?? 1),
                'price' => (float) ($item['price'] ?? 0),
                'discount' => (float) ($item['discount'] ?? 0),
                'oil_mix' => array_values($item['oil_mix'] ?? []),
            ])->sortBy(fn($row) => implode('|', [
                $row['product_id'] ?? '',
                $row['product_variant_id'] ?? '',
                $row['product_name'],
                $row['size'] ?? '',
                $row['quantity'],
                $row['price'],
                $row['discount']
            ]))->values()->all();
            $existingForComparison = collect($existingItemsForComparison)->map(fn($item) => [
                'product_id' => $item->product_id ?? null,
                'product_variant_id' => $item->product_variant_id ?? null,
                'product_name' => (string) ($item->product_name ?? ''),
                'size' => $item->size ?? null,
                'quantity' => (int) ($item->quantity ?? 1),
                'price' => (float) ($item->price ?? 0),
                'discount' => (float) ($item->line_discount_amount ?? 0),
                'oil_mix' => is_string($item->oil_mix ?? null) ? (json_decode($item->oil_mix, true) ?: []) : (array)($item->oil_mix ?? []),
            ])->sortBy(fn($row) => implode('|', [
                $row['product_id'] ?? '',
                $row['product_variant_id'] ?? '',
                $row['product_name'],
                $row['size'] ?? '',
                $row['quantity'],
                $row['price'],
                $row['discount']
            ]))->values()->all();
            $itemsChanged = $incomingForComparison !== $existingForComparison;
        }

        $subtotal = 0.0;
        foreach ($items as $item) {
            $lineSubtotal = ((float) ($item['price'] ?? 0)) * (int) ($item['quantity'] ?? 1);
            $subtotal += max(0, $lineSubtotal - (float) ($item['discount'] ?? 0));
        }
        $shippingFee = (float) ($validated['shipping_fee'] ?? $order->shipping_fee ?? 0);
        $total = $subtotal + $shippingFee;
        $paidAmount = (float) ($order->paid_amount ?? 0);
        if ($paidAmount > $total) return response()->json(['ok' => false, 'message' => 'Paid amount cannot exceed order total.'], 422);
        $remainingAmount = max(0, $total - $paidAmount);
        $paymentStatus = $validated['payment_status'] ?? $order->payment_status ?? 'unpaid';
        if ($paidAmount >= $total && $total > 0) $paymentStatus = 'paid';
        elseif ($paidAmount > 0) $paymentStatus = 'partial';
        else $paymentStatus = 'unpaid';

        $customerId = null;
        DB::transaction(function () use (&$customerId, $id, $validated, $subtotal, $shippingFee, $total, $paidAmount, $remainingAmount, $paymentStatus, $items, $order, $reason, $hasItemsPayload, $hasConsumption, $itemsChanged, $request): void {
            $lockedOrder = DB::table('orders')->where('id', $id)->lockForUpdate()->first();
            if (!$lockedOrder) {
                throw new \RuntimeException('Order not found.');
            }

            // A posted MTO invoice is immutable. Operational item edits use the
            // controlled reopen/reprepare path below; financial edits without a
            // reissue are rejected so revenue/AR cannot drift from the GL.
            $linkedPostedSale = Sale::where('order_id', $id)
                ->where(function ($q) {
                    $q->whereNull('sale_status')->orWhere('sale_status', 'active');
                })
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('journal_entries as je')
                        ->whereColumn('je.source_id', 'sales.id')
                        ->where('je.source_type', 'sale')
                        ->where('je.entry_kind', 'sale_invoice')
                        ->whereIn('je.status', ['posted', 'reversed']);
                })
                ->latest('id')
                ->first();
            if ($linkedPostedSale && $hasItemsPayload && $itemsChanged) {
                if (!$hasConsumption) {
                    throw new \RuntimeException('This order has a posted invoice but no reversible preparation generation. Item changes are blocked; create a replacement order instead.');
                }

                $newPaymentMethod = $validated['payment_method'] ?? $order->payment_method;
                if ($newPaymentMethod !== $order->payment_method || abs($paidAmount - (float)($order->paid_amount ?? 0)) > 0.005) {
                    throw new \RuntimeException('Item reissue cannot change payment terms. Use the payment receipt workflow separately, then edit the order items.');
                }
            }

            if ($linkedPostedSale && (!$hasItemsPayload || !$itemsChanged)) {
                $oldTotal = round((float)($order->total ?? 0), 2);
                $newPaymentMethod = $validated['payment_method'] ?? $order->payment_method;
                $financialChange = abs($oldTotal - round($total, 2)) > 0.005
                    || $newPaymentMethod !== $order->payment_method
                    || abs($paidAmount - (float)($order->paid_amount ?? 0)) > 0.005;
                if ($financialChange) {
                    throw new \RuntimeException('This prepared order has a posted invoice. Use the payment receipt workflow for payments, or reopen/reissue the order to change its financial terms.');
                }
            }

            // Resolve the customer only after all immutable/financial guards pass.
            // This keeps rejected edits from mutating the customer master record.
            $customer = $this->findOrCreateCustomer([
                'customer_name' => $validated['customer_name'] ?? $order->customer_name,
                'customer_phone' => $validated['customer_phone'] ?? $order->customer_phone,
                'customer_address' => $validated['customer_address'] ?? $order->customer_address,
                'city' => $validated['city'] ?? $order->city,
            ]);
            $customerId = (int) $customer->id;

            // If a prepared order's items are changed by an authorized manager/owner,
            // reverse the prior operational/financial effects and reopen preparation.
            if ($hasConsumption && $hasItemsPayload && $itemsChanged) {
                $consumptionIds = DB::table('order_consumptions')->where('order_id', $id)->whereNotIn('status', ['cancelled'])->pluck('id');
                $this->reverseOrderConsumptionMovements($consumptionIds, 'order_reopen', $id, 'Prepared order reopened for item edit');

                $linkedSales = Sale::where('order_id', $id)
                    ->where(function ($q) {
                        $q->whereNull('sale_status')->orWhere('sale_status', 'active');
                    })
                    ->get();
                $finance = new FinancePostingService();
                app(\App\Services\LoyaltyService::class)->reverseEarnForReissue($order, 'Order reissued after item edit');
                foreach ($consumptionIds as $consumptionId) {
                    $finance->reverseMaterialConsumptionPosting((string)$consumptionId);
                }
                foreach ($linkedSales as $linkedSale) {
                    if ($linkedSale->order_id) {
                        $finance->reclassifySalePaymentsToCustomerDeposits($linkedSale);
                    }
                    $finance->reverseExistingSalePostings((int) $linkedSale->id);
                    $linkedSale->sale_status = 'voided';
                    $linkedSale->notes = trim(($linkedSale->notes ?? '') . "\nReopened for order edit on " . now()->toDateTimeString());
                    $linkedSale->save();
                }

                DB::table('orders')->where('id', $id)->update([
                    'consumption_status' => 'not_consumed',
                    'prepared_at' => null,
                    'completed_at' => null,
                ]);
                // Preserve historical consumption and costing records. Reopening is a controlled
                // reversal, not a destructive delete. The next preparation creates a new generation.
                foreach (DB::table('order_consumptions')->whereIn('id', $consumptionIds)->lockForUpdate()->get() as $consumption) {
                    DB::table('order_consumptions')->where('id', $consumption->id)->update([
                        'status' => 'cancelled',
                        'notes' => trim(($consumption->notes ?? '') . "\nCancelled/reversed for order item edit"),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('order_cost_snapshots')->where('order_id', $id)->update(['is_final' => false, 'updated_at' => now()]);
            }

            DB::table('orders')->where('id', $id)->update([
                'customer_id' => $customer->id,
                'customer_name' => $validated['customer_name'] ?? $customer->name,
                'customer_phone' => $validated['customer_phone'] ?? $customer->phone,
                'customer_address' => $validated['customer_address'] ?? $customer->address,
                'city' => $validated['city'] ?? $customer->city,
                'subtotal' => $subtotal,
                'total' => $total,
                'shipping_fee' => $shippingFee,
                'payment_method' => $validated['payment_method'] ?? $order->payment_method ?? 'cash',
                'payment_status' => $paymentStatus,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $remainingAmount,
                'notes' => $validated['notes'] ?? $order->notes,
                'delivery_type' => $validated['delivery_type'] ?? $order->delivery_type ?? 'delivery',
                'updated_at' => now(),
            ]);

            DB::table('order_items')->where('order_id', $id)->delete();

            if (!empty($items)) {
                foreach ($items as $item) {
                    $variant = ProductVariant::with('product')->find($item['product_variant_id']);
                    if (!$variant || !$variant->is_active) {
                        throw new \InvalidArgumentException('Invalid or inactive product variant.');
                    }

                    if (!empty($item['product_id']) && (string) $item['product_id'] !== (string) $variant->product_id) {
                        throw new \InvalidArgumentException('Product and variant do not belong together.');
                    }

                    if (!empty($item['size']) && trim((string) $item['size']) !== trim((string) $variant->size_label)) {
                        throw new \InvalidArgumentException('Selected size does not match the product variant.');
                    }

                    $recipeId = Recipe::where('product_variant_id', $variant->id)
                        ->where('is_active', true)
                        ->orderByDesc('version')
                        ->value('id');
                    DB::table('order_items')->insert([
                        'order_id' => $id,
                        'product_id' => $item['product_id'] ?? $variant?->product_id,
                        'product_variant_id' => $variant?->id ?? ($item['product_variant_id'] ?? null),
                        'recipe_id' => $recipeId,
                        'bottle_type' => !empty($item['oil_mix']) ? 'custom' : 'predefined',
                        'bottle_size_ml' => $variant->volume_ml !== null ? (string)$variant->volume_ml . 'ml' : ($item['size'] ?? null),
                        'oil_mix' => !empty($item['oil_mix']) ? json_encode($item['oil_mix'], JSON_UNESCAPED_UNICODE) : null,
                        'product_name' => $item['product_name'],
                        'size' => $item['size'] ?? null,
                        'quantity' => (int) ($item['quantity'] ?? 1),
                        'price' => (float) ($item['price'] ?? 0),
                        'line_discount_amount' => (float) ($item['discount'] ?? 0),
                        'line_total' => max(0, ((float) ($item['price'] ?? 0)) * (int) ($item['quantity'] ?? 1) - (float) ($item['discount'] ?? 0)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Keep an existing sale/invoice synchronized when a prepared order's
            // customer/payment fields are edited without changing its items.
            if (!$hasItemsPayload || !$itemsChanged) {
                $linkedSale = Sale::where('order_id', $id)->where('sale_status', 'active')->first();
                if ($linkedSale) {
                    $linkedSale->customer_id = $customer->id;
                    $linkedSale->customer_name = $validated['customer_name'] ?? $customer->name;
                    $linkedSale->customer_phone = $validated['customer_phone'] ?? $customer->phone;
                    $linkedSale->payment_method = $validated['payment_method'] ?? $linkedSale->payment_method;
                    $linkedSale->paid_amount = $paidAmount;
                    $linkedSale->remaining_amount = $remainingAmount;
                    $linkedSale->payment_status = $paymentStatus;
                    $linkedSale->save();
                    if (($linkedSale->revenue_status ?? 'recognized') === 'recognized') {
                        (new FinancePostingService())->postSale($linkedSale);
                    }
                    (new CustomerStatsService())->recalculate($linkedSale->customer_id ? (int) $linkedSale->customer_id : null);
                }
            }
        });

        (new CustomerStatsService())->recalculate($customerId);
        $request->attributes->set('audit.reason', $reason ?: 'Order details updated');

        return response()->json(['ok' => true, 'order_id' => $id]);
    }

    public function show(string $id): JsonResponse
    {
        $order = DB::table('orders as o')
            ->leftJoin('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('o.id', $id)
            ->select(
                'o.*',
                'c.name as customer_name',
                'c.phone as customer_phone',
                'c.address as customer_address',
                'c.city as customer_city'
            )
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $items = DB::table('order_items')->where('order_id', $id)->get();
        $consumption = OrderConsumption::where('order_id', $id)->with('items.material')->first();
        $costSnapshot = OrderCostSnapshot::where('order_id', $id)->orderByDesc('created_at')->first();
        $linkedSale = Sale::where('order_id', $id)
            ->where(function ($query) {
                $query->whereNull('sale_status')->orWhere('sale_status', 'active');
            })
            ->latest('id')
            ->first();

        if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
            $orderData = (array) $order;
            foreach (['cost', 'cost_syp', 'profit', 'profit_syp', 'total_price', 'total_price_syp'] as $field) unset($orderData[$field]);
            $order = (object) $orderData;
            $items = $items->map(function ($item) {
                $data = (array) $item;
                foreach (['cost', 'cost_syp', 'profit', 'profit_syp', 'line_cost', 'unit_cost'] as $field) unset($data[$field]);
                return (object) $data;
            });
            $costSnapshot = null;
            if ($consumption) {
                $consumption->setAttribute('total_material_cost', null);
                $consumption->setAttribute('total_production_cost', null);
                $consumption->setAttribute('labor_cost', null);
                $consumption->setAttribute('electricity_cost', null);
                $consumption->setAttribute('overhead_cost', null);
            }
        }

        return response()->json([
            'order' => $order,
            'items' => $items,
            'consumption' => $consumption,
            'cost_snapshot' => $costSnapshot,
            'invoice' => $linkedSale ? [
                'invoice_number' => $linkedSale->invoice_number,
                'payment_status' => $linkedSale->payment_status,
                'payment_method' => $linkedSale->payment_method,
                'paid_amount' => (float) $linkedSale->paid_amount,
                'remaining_amount' => (float) $linkedSale->remaining_amount,
                'total_price' => (float) $linkedSale->total_price,
                'sale_date' => $linkedSale->sale_date,
            ] : null,
        ]);
    }

    public function listMaterials(): JsonResponse
    {
        $materials = Material::where('is_active', true)
            ->orderBy('name')
            ->get();
        if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
            $materials->transform(function ($material) {
                $data = $material->toArray();
                foreach (['avg_unit_cost', 'currency', 'exchange_rate', 'supplier_name', 'notes'] as $field) unset($data[$field]);
                return $data;
            });
        }
        return response()->json(['materials' => $materials]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $order = app(\App\Services\OrderLifecycleService::class)->cancel($id, 'Order cancelled through admin action');
            return response()->json([
                'ok' => true,
                'message' => 'Order cancelled successfully. Historical financial, inventory, loyalty and audit records were preserved through reversals.',
                'order_id' => $order->id,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Order not found'], 404);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => app()->environment('local') ? $e->getMessage() : 'Failed to cancel order safely.'], 422);
        }
    }

    public function accept(string $id): JsonResponse
    {
        try {
            $order = app(\App\Services\OrderLifecycleService::class)->transition($id, 'accepted');
            return response()->json(['ok' => true, 'order' => $order]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['message' => 'Order not found'], 404);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => app()->environment('local') ? $e->getMessage() : 'Unable to update the order safely.'], 409);
        }
    }

    public function previewConsumption(string $id): JsonResponse
    {
        $order = DB::table('orders')->where('id', $id)->first();
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $item = DB::table('order_items')->where('order_id', $id)->first();
        if (!$item) {
            return response()->json(['message' => 'Order has no items'], 404);
        }

        $recipePayload = $this->buildRecipePayload($item);
        $service = new OrderConsumptionService();
        $result = $service->buildExpectedConsumption([
            'order_item' => [
                'quantity' => (int) ($item->quantity ?? 1),
                'variant' => ['id' => $item->product_variant_id],
            ],
            'recipe' => $recipePayload,
        ]);

        return response()->json([
            'ok' => true,
            'order_id' => $id,
            'expected_items' => $result['items'],
        ]);
    }

    public function confirmConsumption(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'integer', 'exists:materials,id'],
            'items.*.actual_qty' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
            'production.source' => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $actual = [];
            foreach ($validated['items'] as $row) {
                $mid = (string) $row['material_id'];
                if (array_key_exists($mid, $actual)) {
                    throw new \DomainException('The same material cannot be submitted more than once in a preparation request.');
                }
                $actual[$mid] = (float) $row['actual_qty'];
            }

            // Delegate the mutation to the single server-side preparation path.
            // Expected quantities, units, substitutions, variance limits, stock
            // locks, COGS, invoice and loyalty are all derived/posted there.
            $request->merge([
                'order_id' => $id,
                'actual_quantities' => $actual,
                'material_overrides' => [],
                'notes' => trim((string) ($validated['production']['source'] ?? 'Admin order preparation confirmation')),
            ]);

            return app(\App\Http\Controllers\OrderPreparationController::class)->prepareOrderUnified($request);
        } catch (\DomainException|\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => 'Unable to prepare the order safely.'], 500);
        }
    }

    private function buildRecipePayload(object $orderItem): array
    {
        $recipe = Recipe::with('items.material')
            ->where('id', $orderItem->recipe_id)
            ->where('is_active', true)
            ->first();

        if (!$recipe) {
            return ['items' => []];
        }

        return [
            'items' => $recipe->items->map(static function (RecipeItem $item): array {
                return [
                    'material_id' => $item->material_id,
                    'material_name' => $item->material?->name,
                    'expected_qty' => (float) $item->expected_qty,
                    'unit' => $item->unit,
                    'consumption_rule_type' => $item->consumption_rule_type,
                    'rule_config' => $item->rule_config,
                    'is_packaging' => $item->material?->material_category === 'packaging',
                    'is_optional' => (bool) $item->is_optional,
                    'notes' => $item->notes,
                ];
            })->all(),
        ];
    }

    private function reverseOrderConsumptionMovements(iterable $consumptionIds, string $referenceType, string $orderId, string $reason): void
    {
        $movementService = app(\App\Services\InventoryMovementService::class);
        foreach ($consumptionIds as $consumptionId) {
            $movementService->reverseCompletedConsumption((string) $consumptionId, $reason);
        }
    }

    private function packagingCostForConsumption(OrderConsumption $consumption): float
    {
        $units = new UnitConversionService();
        $total = 0.0;

        foreach ($consumption->items()->with('material')->get() as $item) {
            $material = $item->material;
            if (!$material || (!(bool) $item->is_packaging && $material->material_category !== 'packaging')) {
                continue;
            }

            $baseQuantity = $units->convert((float) $item->actual_qty, (string) $item->unit, (string) $material->base_unit);
            if ($baseQuantity !== null) {
                $total += $baseQuantity * (float) $material->avg_unit_cost;
            }
        }

        return round($total, 4);
    }
}
