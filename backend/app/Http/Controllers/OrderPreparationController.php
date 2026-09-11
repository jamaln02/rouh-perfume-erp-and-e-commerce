<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderConsumption;
use App\Models\OrderConsumptionItem;
use App\Models\OrderCostSnapshot;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Models\Material;
use App\Models\InventoryMovement;
use App\Models\MaterialSubstitution;
use App\Services\StockValidationService;
use App\Services\InventoryMovementService;
use App\Services\UnitConversionService;
use App\Services\FinancePostingService;
use App\Services\CustomerStatsService;
use App\Services\ConsumptionRuleService;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderPreparationController extends Controller
{
    public function getInventory(): JsonResponse
    {
        try {
            $materials = Material::where('is_active', true)->orderBy('name')->get();
            $essentialOils = $materials->where('material_category', 'perfume_oil')->values()->map(fn(Material $m) => (object)[
                'id'=>$m->id,'name'=>$m->name,'name_ar'=>$m->name_ar,'price_per_gram'=>$m->avg_unit_cost,
                'current_stock_grams'=>$m->current_stock,'min_stock_grams'=>$m->min_stock,'supplier'=>$m->supplier_name,
                'notes'=>$m->notes,'is_active'=>$m->is_active,
            ]);
            $alcoholMaterial = $materials->where('material_category', 'alcohol')->sortBy(fn (Material $m) => [($m->code === 'ALC-ETHANOL-1L' ? 0 : 1), ($m->name_ar === 'كحول ايثانول' ? 0 : 1), $m->id])->first();
            $alcohol = $alcoholMaterial ? (object)[
                'id'=>$alcoholMaterial->id,'price_per_liter'=>(float)$alcoholMaterial->avg_unit_cost*1000,
                'current_stock_ml'=>$alcoholMaterial->current_stock,'min_stock_ml'=>$alcoholMaterial->min_stock,
                'supplier'=>$alcoholMaterial->supplier_name,'notes'=>$alcoholMaterial->notes,'is_active'=>$alcoholMaterial->is_active,
            ] : null;
            $packaging = $materials->where('material_category','packaging')->values();
            $mapPackaging = fn(Material $m)=>(object)[
                'id'=>$m->id,'type'=>$m->subcategory ?: 'other','name'=>$m->name,'name_ar'=>$m->name_ar,
                'size_ml'=>null,'dimensions'=>null,'price'=>$m->avg_unit_cost,'current_stock'=>$m->current_stock,
                'min_stock'=>$m->min_stock,'supplier'=>$m->supplier_name,'notes'=>$m->notes,'is_active'=>$m->is_active,
            ];
            $bottles=$packaging->where('subcategory','bottle')->values()->map($mapPackaging);
            $boxes=$packaging->where('subcategory','box')->values()->map($mapPackaging);
            $bags=$packaging->where('subcategory','bag')->values()->map($mapPackaging);
            if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
                $strip=static function($row){if($row===null)return null;$d=(array)$row;foreach(['price_per_gram','price_per_liter','price','supplier','notes'] as $f)unset($d[$f]);return(object)$d;};
                $essentialOils=$essentialOils->map($strip);$alcohol=$strip($alcohol);$bottles=$bottles->map($strip);$boxes=$boxes->map($strip);$bags=$bags->map($strip);
            }
            return response()->json(['essential_oils'=>$essentialOils,'alcohol'=>$alcohol,'bottles'=>$bottles,'boxes'=>$boxes,'bags'=>$bags]);
        } catch (\Throwable $e) {
            return response()->json(['error'=>'Unable to load preparation inventory.'],500);
        }
    }

    public function getCustomers(): JsonResponse
    {
        try {
            $customers = Customer::orderBy('name')->get();
            return response()->json($customers);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'An unexpected server error occurred.'], 500);
        }
    }

    public function getOrders(): JsonResponse
    {
        try {
            $orders = Order::with(['items', 'consumption'])
                ->orderByDesc('created_at')
                ->get();

            if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
                $orders->each(function (Order $order): void {
                    foreach (['total', 'subtotal', 'shipping_fee', 'shipping_cost', 'paid_amount', 'remaining_amount', 'cost', 'profit', 'profit_syp'] as $field) {
                        $order->makeHidden($field);
                    }
                });
            }

            return response()->json($orders);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Unable to load preparation orders.'], 500);
        }
    }

    public function getWebsiteOrders(): JsonResponse
    {
        try {
            // This endpoint returns orders from the active `orders` table (System A).
            $orders = DB::table('orders')
                ->where('status', 'pending')
                ->orderBy('created_at', 'desc')
                ->get();
            if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
                $orders->transform(function ($order) {
                    $data = (array) $order;
                    foreach (['total','subtotal','shipping_fee','shipping_cost','discount','paid_amount','remaining_amount','payment_method','payment_status','cost','profit','profit_syp','total_price','total_price_syp'] as $field) unset($data[$field]);
                    return (object) $data;
                });
            }
            return response()->json($orders);
        } catch (\Exception $e) {
            return response()->json([]);
        }
    }

    public function prepareOrder(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'The legacy manual preparation endpoint is disabled. Use the unified recipe-based preparation workflow.',
            'replacement_endpoint' => '/api/admin/order-preparation/prepare-unified',
        ], 410);
    }

    public function deleteOrder(string $id): JsonResponse
    {
        try {
            DB::transaction(function () use ($id): void {
                $order = Order::lockForUpdate()->findOrFail($id);
                if (($order->status ?? null) === 'cancelled') return;

                $activeConsumptions = OrderConsumption::where('order_id', $order->id)
                    ->where('status', '!=', 'cancelled')
                    ->lockForUpdate()
                    ->get();

                $movementService = app(InventoryMovementService::class);
                foreach ($activeConsumptions as $consumption) {
                    if (in_array($consumption->status, ['completed', 'confirmed', 'in_progress'], true)) {
                        $movementService->reverseCompletedConsumption((string) $consumption->id, 'Order cancelled from preparation workflow');
                    } elseif ($consumption->status === 'planned') {
                        $movementService->cancelConsumption((string) $consumption->id, 'Order cancelled before material consumption');
                    }
                }

                $finance = app(FinancePostingService::class);
                $sales = Sale::where('order_id', $order->id)
                    ->where(function ($q) { $q->whereNull('sale_status')->orWhere('sale_status', 'active'); })
                    ->lockForUpdate()
                    ->get();
                foreach ($sales as $sale) {
                    $finance->reclassifySalePaymentsToCustomerDeposits($sale);
                    $finance->reverseExistingSalePostings((int) $sale->id);
                    $sale->sale_status = 'voided';
                    $sale->notes = trim(($sale->notes ?? '') . "\nOrder cancelled from preparation workflow");
                    $sale->save();
                }

                DB::table('order_cost_snapshots')->where('order_id', $order->id)->update([
                    'is_final' => false,
                    'updated_at' => now(),
                ]);

                $order->status = 'cancelled';
                $order->order_status = 'cancelled';
                $order->cancelled_at = now();
                $order->consumption_status = 'not_consumed';
                $order->prepared_at = null;
                $order->completed_at = null;
                $order->notes = trim(($order->notes ?? '') . "\nOrder cancelled without deleting historical records");
                $order->save();

                app(CustomerStatsService::class)->recalculate($order->customer_id ? (int) $order->customer_id : null);
            });

            return response()->json([
                'ok' => true,
                'message' => 'Order cancelled successfully. Historical financial and inventory records were preserved.',
                'order_id' => $id,
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'ok' => false,
                'message' => app()->environment('local') ? $e->getMessage() : 'Failed to cancel order.',
            ], 422);
        }
    }

    public function getOrderRecipe(string $orderId): JsonResponse
    {
        try {
            $order = Order::with(['items.product', 'items.variant'])->findOrFail($orderId);
            $service = new StockValidationService();
            $requirements = $service->aggregateMaterialRequirements($order);
            $perProduct = $service->perProductMaterialRequirements($order);
            $combined = $service->combinedPreparationRequirements($order);
            // Strip cost-related fields for employees
            if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
                $stripCost = function ($r) {
                    if (is_object($r['material'])) {
                        $r['material'] = collect($r['material']->toArray())
                            ->except(['avg_unit_cost','currency','exchange_rate','supplier_name','notes'])->all();
                    }
                    return $r;
                };
                $requirements = array_map($stripCost, $requirements);
                foreach ($perProduct as &$bucket) {
                    $bucket['materials'] = array_map($stripCost, $bucket['materials']);
                }
                unset($bucket);
            }
            return response()->json([
                'order' => $order,
                'material_requirements' => array_values($requirements),
                'per_product' => $perProduct,
                'combined_recipe' => $combined,
            ]);
        } catch (\Throwable $e) { report($e); return response()->json(['error'=>app()->environment('local') ? $e->getMessage() : 'The operation could not be completed safely.'],422); }
    }

    public function validateOrderStock(Request $request, string $orderId): JsonResponse
    {
        try {
            $overrides = (array)$request->input('material_overrides', []);
            $actual = (array)$request->input('actual_quantities', []);
            return response()->json((new StockValidationService())->validateOrderRecipe($orderId,$overrides,$actual));
        } catch (\Throwable $e) { report($e); return response()->json(['error'=>app()->environment('local') ? $e->getMessage() : 'The operation could not be completed safely.'],422); }
    }

    public function prepareOrderUnified(Request $request): JsonResponse
    {
        $v = $request->validate([
            'order_id' => ['required', 'string', 'exists:orders,id'],
            'actual_quantities' => ['required', 'array'],
            'actual_quantities.*' => ['required', 'numeric', 'min:0'],
            'material_overrides' => ['nullable', 'array'],
            'material_overrides.*' => ['required', 'integer', 'exists:materials,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = DB::transaction(function () use ($v, $request): array {
                $order = Order::with('items')->lockForUpdate()->findOrFail($v['order_id']);
                if (($order->status ?? $order->order_status) === 'cancelled') {
                    throw new \RuntimeException('Cancelled orders cannot be prepared.');
                }
                $existingConsumption = OrderConsumption::query()
                    ->where('order_id', $order->id)
                    ->where('status', '!=', 'cancelled')
                    ->lockForUpdate()
                    ->first();
                if ($existingConsumption && !($existingConsumption->status === 'planned' && $existingConsumption->source === 'manual')) {
                    throw new \RuntimeException('Order has already been prepared. Use the controlled reopen/reversal workflow to prepare it again.');
                }

                $actor = $request->attributes->get('authUser');
                $actorId = $actor?->id;
                $actorRole = (string) ($request->attributes->get('authRole') ?? 'employee');
                if (!$actorId) throw new \RuntimeException('Authenticated staff context is required.');

                $validator = app(StockValidationService::class);
                $overrides = (array) ($v['material_overrides'] ?? []);
                $actual = array_map('floatval', (array) $v['actual_quantities']);
                $check = $validator->validateOrderRecipe($order->id, $overrides, $actual, $actorRole, (string) ($v['notes'] ?? ''));
                if (!$check['can_proceed']) {
                    throw new \RuntimeException(implode(' | ', array_map(fn ($i) => (string) ($i['message'] ?? 'Invalid preparation'), $check['issues'])));
                }

                $requirements = $check['material_requirements'];
                if (!$requirements) throw new \RuntimeException('The order has no complete server-side recipe. Create/assign a recipe before preparation.');

                $consumption = $existingConsumption ?: OrderConsumption::create([
                    'order_id' => $order->id,
                    'status' => 'completed',
                    'source' => 'order_preparation',
                    'prepared_at' => now(),
                    'prepared_by' => $actorId,
                    'confirmed_at' => now(),
                    'confirmed_by' => $actorId,
                    'notes' => trim((string) ($v['notes'] ?? '')),
                ]);

                if ($existingConsumption) {
                    OrderConsumptionItem::where('order_consumption_id', $consumption->id)->delete();
                    $consumption->update([
                        'status' => 'completed',
                        'source' => 'order_preparation',
                        'prepared_at' => now(),
                        'prepared_by' => $actorId,
                        'confirmed_at' => now(),
                        'confirmed_by' => $actorId,
                        'notes' => trim((string) ($v['notes'] ?? '')),
                    ]);
                }

                foreach ($requirements as $r) {
                    $mid = (int) $r['material_id'];
                    $expected = round((float) $r['expected_qty'], 6);
                    $actualQty = array_key_exists((string) $mid, $actual) ? (float) $actual[(string) $mid] : $expected;
                    $material = Material::whereKey($mid)->where('is_active', true)->lockForUpdate()->firstOrFail();
                    $variance = round($actualQty - $expected, 6);
                    $notes = '';
                    foreach ((array) $r['items'] as $line) {
                        $originalId = (int) ($line['original_material_id'] ?? 0);
                        if ($originalId && $originalId !== $mid) {
                            $original = Material::find($originalId);
                            $notes = 'Substitution: ' . ($original?->name ?? "material #{$originalId}") . ' → ' . $material->name;
                            MaterialSubstitution::create([
                                'order_consumption_id' => $consumption->id,
                                'order_id' => $order->id,
                                'original_material_id' => $originalId,
                                'replacement_material_id' => $mid,
                                'original_qty' => $line['expected_qty'] ?? null,
                                'replacement_qty' => $actualQty,
                                'unit' => $r['unit'],
                                'reason' => (string) $v['notes'],
                                'performed_by' => $actorId,
                            ]);
                        }
                    }

                    OrderConsumptionItem::create([
                        'order_consumption_id' => $consumption->id,
                        'order_item_id' => count($r['items']) === 1 ? ($r['items'][0]['order_item_id'] ?? null) : null,
                        'material_id' => $mid,
                        'expected_qty' => $expected,
                        'actual_qty' => $actualQty,
                        'variance_qty' => $variance,
                        'unit' => $r['unit'],
                        'variance_classification' => $this->classifyVariance($variance),
                        'is_packaging' => $material->material_category === 'packaging',
                        'is_optional' => false,
                        'notes' => $notes ?: ($v['notes'] ?? null),
                    ]);
                }

                app(InventoryMovementService::class)->createConsumptionMovements((string) $consumption->id);
                $consumption->refresh()->load('items.material');
                $materialCost = round((float) $consumption->total_material_cost, 4);
                $conversionCost = round((float) $consumption->labor_cost + (float) $consumption->electricity_cost + (float) $consumption->overhead_cost, 4);
                $cost = round($materialCost + $conversionCost, 2);
                $revenue = round((float) $order->total, 2);
                $shippingRevenue = max(0.0, round((float) ($order->shipping_fee ?? 0), 2));
                $productRevenue = max(0.0, round($revenue - $shippingRevenue, 2));
                $shippingCost = max(0.0, round((float) ($order->shipping_cost_internal ?? 0), 2));
                $paymentFees = max(0.0, round((float) ($consumption->payment_fees ?? $order->payment_fees ?? 0), 2));
                $otherCosts = max(0.0, round((float) ($consumption->other_costs ?? 0), 2));
                $quantity = max(1, (int) $order->items->sum(fn ($item) => (int) $item->quantity));
                $firstItem = $order->items->first();

                $sale = Sale::query()->where('order_id', $order->id)
                    ->where(function ($q) { $q->whereNull('sale_status')->orWhere('sale_status', 'active'); })
                    ->lockForUpdate()->first();
                if ($sale) {
                    $posted = \App\Models\JournalEntry::query()
                        ->where('source_type', 'sale')
                        ->where('source_id', (string) $sale->id)
                        ->where('entry_kind', 'sale_invoice')
                        ->where('status', 'posted')
                        ->exists();
                    if ($posted) {
                        throw new \RuntimeException('A posted invoice already exists for this order; do not prepare the order twice.');
                    }
                } else {
                    $sale = new Sale();
                }
                $sale->fill([
                    'product_id' => $firstItem?->product_id,
                    'product_name' => $firstItem?->product_name ?? 'ROUH Order',
                    'size_type' => $firstItem?->size,
                    'size_ml' => $firstItem?->bottle_size_ml ? (int) $firstItem->bottle_size_ml : null,
                    'quantity' => $quantity,
                    'unit_price' => $quantity > 0 ? round($revenue / $quantity, 2) : 0,
                    'total_price' => $revenue,
                    'currency' => 'SYP', 'exchange_rate' => 1,
                    'total_price_syp' => $revenue, 'cost' => $cost, 'cost_syp' => $cost,
                    'profit' => round($revenue - $cost, 2), 'profit_syp' => round($revenue - $cost, 2),
                    'sale_source' => $order->source === 'online' ? 'online' : 'offline',
                    'customer_id' => $order->customer_id, 'customer_name' => $order->customer_name, 'customer_phone' => $order->customer_phone,
                    'sale_date' => now()->toDateString(), 'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status ?? 'unpaid', 'paid_amount' => (float) $order->paid_amount,
                    'remaining_amount' => max(0, round($revenue - (float) $order->paid_amount, 2)), 'order_id' => $order->id,
                    'invoice_number' => $sale->invoice_number ?: ('INV-' . now()->format('Y') . '-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', (string) $order->id), 0, 10))),
                    'notes' => 'Auto-created from prepared order ' . $order->id,
                    'revenue_status' => 'pending',
                    'revenue_recognized_at' => null,
                ]);
                $sale->save();
                // Preparation creates the operational invoice record, but does not recognize revenue.
                // Revenue/COGS are posted by the order lifecycle at the configured recognition milestone.

                OrderCostSnapshot::create([
                    'order_id' => $order->id,
                    'order_consumption_id' => $consumption->id,
                    'revenue_subtotal' => $productRevenue,
                    'discount_amount' => max(0, (float) ($order->discount_amount ?? 0)),
                    'shipping_revenue' => $shippingRevenue,
                    'shipping_cost' => $shippingCost,
                    'material_cogs' => max(0, round($materialCost - $this->packagingCostForConsumption($consumption->id), 2)),
                    'packaging_cogs' => $this->packagingCostForConsumption($consumption->id),
                    'total_cogs' => $cost,
                    'gross_profit' => round($productRevenue - $cost, 2),
                    'net_profit' => round($revenue - $cost - $shippingCost - $paymentFees - $otherCosts, 2),
                    'currency' => 'SYP', 'exchange_rate' => 1, 'is_final' => true,
                    'computed_by' => $actorId,
                    'notes' => 'Final order profitability from authoritative inventory movements and recorded order charges.',
                ]);

                $order->update(['status' => 'confirmed', 'order_status' => 'confirmed', 'consumption_status' => 'consumed', 'prepared_at' => now()]);
                $pointsEarned = app(LoyaltyService::class)->awardForOrder($order->fresh());

                return ['consumption_id' => $consumption->id, 'sale_id' => $sale->id, 'total_material_cost' => $materialCost, 'total_production_cost' => $cost, 'total_price' => $revenue, 'profit' => round($productRevenue - $cost, 2), 'points_earned' => $pointsEarned];
            });

            if (!\App\Support\PermissionService::canViewCosts($request->attributes->get('authRole'))) {
                foreach (['total_material_cost', 'profit', 'total_production_cost'] as $field) unset($result[$field]);
            }
            return response()->json(['ok' => true, 'message' => 'Order prepared successfully.', ...$result]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['ok' => false, 'message' => app()->environment('local') ? $e->getMessage() : 'Order preparation failed; no changes were committed.'], 422);
        }
    }

    private function packagingCostForConsumption(int $consumptionId): float
    {
        $cost = (float) InventoryMovement::query()
            ->join('materials', 'materials.id', '=', 'inventory_movements.material_id')
            ->where('inventory_movements.reference_type', 'order_consumption')
            ->where('inventory_movements.reference_id', (string) $consumptionId)
            ->where('inventory_movements.movement_type', 'consumption')
            ->where('materials.material_category', 'packaging')
            ->sum('inventory_movements.total_cost');
        return round($cost, 4);
    }

    private function classifyVariance(float $varianceQty): string { return abs($varianceQty)<0.001?'normal':($varianceQty>0?'overage':'shortage'); }

    /**
     * Return the substitution log for a given order consumption.
     * Used by the preparation UI to show what materials were swapped.
     */
    public function getSubstitutionLog(Request $request, string $orderId): JsonResponse
    {
        try {
            $logs = \App\Models\MaterialSubstitution::with(['originalMaterial', 'replacementMaterial'])
                ->where('order_id', $orderId)
                ->orderByDesc('created_at')
                ->get()
                ->map(function ($log) {
                    return [
                        'id'                  => $log->id,
                        'original_material'   => $log->originalMaterial?->name ?? 'Unknown',
                        'replacement_material'=> $log->replacementMaterial?->name ?? 'Unknown',
                        'original_qty'        => $log->original_qty,
                        'replacement_qty'     => $log->replacement_qty,
                        'unit'                => $log->unit,
                        'reason'              => $log->reason,
                        'performed_at'        => $log->created_at?->toDateTimeString(),
                    ];
                });

            return response()->json(['substitutions' => $logs]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * T9: Create a recipe inline from the order preparation page.
     *
     * Employees (and managers/admins) can create a recipe for a product variant
     * directly while preparing an order, without leaving the page or needing the
     * `manufacturing.manage` permission. This accepts the variant id, an optional
     * oil percentage, and a list of material lines, then creates the recipe and
     * its items in a single transaction.
     */
    public function createRecipeInline(Request $request): JsonResponse
    {
        $v = $request->validate([
            'product_variant_id' => 'required|string|exists:product_variants,id',
            'order_item_id'     => 'nullable|integer|exists:order_items,id',
            'oil_percentage'     => 'nullable|numeric|min:0|max:100',
            'notes'              => 'nullable|string|max:2000',
            'items'              => 'required|array|min:1',
            'items.*.material_id'           => 'required|integer|exists:materials,id',
            'items.*.expected_qty'          => 'required|numeric|min:0',
            'items.*.unit'                  => 'required|string|max:20',
            'items.*.consumption_rule_type' => 'nullable|string|in:fixed,per_bottle,percentage,ratio,packaging_rule',
            'items.*.allow_manual_override' => 'nullable|boolean',
        ]);

        try {
            $recipe = DB::transaction(function () use ($v) {
                $variant = ProductVariant::findOrFail($v['product_variant_id']);

                if (!empty($v['order_item_id'])) {
                    $orderItem = \App\Models\OrderItem::lockForUpdate()->findOrFail((int) $v['order_item_id']);
                    if ((string) $orderItem->product_variant_id !== (string) $variant->id) {
                        throw new \InvalidArgumentException('Recipe variant does not match the selected order item.');
                    }
                }

                // A recipe created from an order line is an order-scoped snapshot.
                // It must never replace the variant's master recipe or affect other
                // order lines that happen to use the same variant/recipe.
                $orderScoped = !empty($v['order_item_id']);
                if (!$orderScoped) {
                    Recipe::where('product_variant_id', $variant->id)->update(['is_active' => false]);
                }

                $oilPct     = (float) ($v['oil_percentage'] ?? 32);
                $alcoholPct = round(100 - $oilPct, 2);

                $recipe = Recipe::create([
                    'id'                 => (string) Str::uuid(),
                    'product_variant_id' => $variant->id,
                    'version'            => (int) Recipe::where('product_variant_id', $variant->id)->max('version') + 1,
                    'is_active'          => !$orderScoped,
                    'oil_percentage'     => $oilPct,
                    'alcohol_percentage' => $alcoholPct,
                    'effective_from'     => now()->toDateString(),
                    'effective_to'       => null,
                    'notes'              => $v['notes'] ?? 'Created inline from order preparation',
                    'created_by'         => (int) (request()->attributes->get('authUser')?->id ?? 1),
                ]);

                $ruleService = new ConsumptionRuleService();
                foreach ($v['items'] as $line) {
                    $material = Material::findOrFail((int) $line['material_id']);
                    $fakeItem = new RecipeItem(['unit' => $line['unit']]);
                    if (!$ruleService->validateUnitCompatibility($fakeItem, (string) $material->base_unit)) {
                        throw new \InvalidArgumentException("Unit {$line['unit']} is incompatible with {$material->base_unit}.");
                    }
                    $recipe->items()->create([
                        'material_id'           => (int) $line['material_id'],
                        'expected_qty'          => (float) $line['expected_qty'],
                        'unit'                  => $line['unit'],
                        'consumption_rule_type' => $line['consumption_rule_type'] ?? 'fixed',
                        'rule_config'           => [],
                        'is_optional'           => false,
                        'allow_manual_override' => (bool) ($line['allow_manual_override'] ?? true),
                        'sort_order'            => 0,
                    ]);
                }

                // Freeze this exact recipe onto the selected order line. From this point
                // onward preparation will use this recipe_id, not whatever recipe later
                // becomes active on the variant.
                if (!empty($v['order_item_id'])) {
                    \App\Models\OrderItem::whereKey((int) $v['order_item_id'])->update([
                        'recipe_id' => $recipe->id,
                        'product_variant_id' => $variant->id,
                        'product_id' => $variant->product_id,
                    ]);
                }

                return $recipe;
            });

            return response()->json([
                'ok'     => true,
                'recipe' => $recipe->load(['variant.product', 'items.material']),
            ], 201);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Clone and replace the recipe for exactly one order item.
     *
     * This is deliberately copy-on-write: editing a preparation recipe can never
     * mutate the recipe referenced by another order line.
     */
    public function updateOrderItemRecipe(Request $request): JsonResponse
    {
        $v = $request->validate([
            'order_id' => 'required|string|exists:orders,id',
            'order_item_id' => 'required|integer|exists:order_items,id',
            'recipe_id' => 'required|string|exists:recipes,id',
            'oil_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.material_id' => 'required|integer|exists:materials,id',
            'items.*.expected_qty' => 'required|numeric|min:0',
            'items.*.unit' => 'required|string|max:20',
            'items.*.consumption_rule_type' => 'nullable|string|in:fixed,per_bottle,percentage,ratio,packaging_rule',
            'items.*.allow_manual_override' => 'nullable|boolean',
        ]);

        try {
            $recipe = DB::transaction(function () use ($v, $request) {
                $order = Order::lockForUpdate()->findOrFail($v['order_id']);
                $item = OrderItem::lockForUpdate()
                    ->where('order_id', $order->id)
                    ->findOrFail((int) $v['order_item_id']);
                $source = Recipe::with('items')
                    ->whereKey($v['recipe_id'])
                    ->where('product_variant_id', $item->product_variant_id)
                    ->firstOrFail();

                $oilPct = (float) ($v['oil_percentage'] ?? $source->oil_percentage ?? 32);
                $clone = Recipe::create([
                    'id' => (string) Str::uuid(),
                    'product_variant_id' => $item->product_variant_id,
                    'version' => (int) Recipe::where('product_variant_id', $item->product_variant_id)->max('version') + 1,
                    'is_active' => false,
                    'oil_percentage' => $oilPct,
                    'alcohol_percentage' => round(100 - $oilPct, 2),
                    'effective_from' => now()->toDateString(),
                    'effective_to' => null,
                    'notes' => $v['notes'] ?? 'Order-specific recipe snapshot',
                    'created_by' => (int) ($request->attributes->get('authUser')?->id ?? 1),
                ]);

                $ruleService = new ConsumptionRuleService();
                foreach ($v['items'] as $index => $line) {
                    $material = Material::findOrFail((int) $line['material_id']);
                    $fakeItem = new RecipeItem(['unit' => $line['unit']]);
                    if (!$ruleService->validateUnitCompatibility($fakeItem, (string) $material->base_unit)) {
                        throw new \InvalidArgumentException("Unit {$line['unit']} is incompatible with {$material->base_unit}.");
                    }
                    $clone->items()->create([
                        'material_id' => (int) $line['material_id'],
                        'expected_qty' => (float) $line['expected_qty'],
                        'unit' => $line['unit'],
                        'consumption_rule_type' => $line['consumption_rule_type'] ?? 'fixed',
                        'rule_config' => [],
                        'is_optional' => false,
                        'allow_manual_override' => (bool) ($line['allow_manual_override'] ?? true),
                        'sort_order' => $index,
                    ]);
                }

                $oldRecipe = $source->load('items');
                $oldQty = $oldRecipe->items->pluck('expected_qty', 'material_id')->map(fn($v)=>(float)$v)->all();
                $newQty = collect($v['items'])->mapWithKeys(fn($row)=>[(int)$row['material_id'] => (float)$row['expected_qty']])->all();
                $quantityChanged = $oldQty != $newQty;
                $actor = $request->attributes->get('authUser');
                $role = (string)$request->attributes->get('authRole');
                $reason = trim((string)($v['notes'] ?? ''));
                if ($quantityChanged && $role !== 'admin' && $reason === '') {
                    throw new \InvalidArgumentException('A reason is required when changing recipe quantities.');
                }
                if ($quantityChanged) {
                    \App\Models\AuditLog::create([
                        'user_id' => $actor?->id, 'user_role' => $role, 'action' => 'recipe_quantity_changed',
                        'method' => 'POST', 'route' => '/api/admin/order-preparation/recipes/update-order-item',
                        'entity_type' => 'recipe', 'entity_id' => (string)$clone->id, 'status' => 'success', 'status_code' => 200,
                        'reason' => $reason ?: 'Admin recipe quantity update',
                        'before_data' => ['recipe_id' => $source->id, 'order_id' => $order->id, 'order_item_id' => $item->id, 'quantities' => $oldQty],
                        'after_data' => ['recipe_id' => $clone->id, 'order_id' => $order->id, 'order_item_id' => $item->id, 'quantities' => $newQty],
                        'changes' => ['quantity_changed' => true],
                        'request_data' => ['reason' => $reason], 'occurred_at' => now(),
                    ]);
                }

                $item->update(['recipe_id' => $clone->id]);
                return $clone;
            });

            return response()->json(['ok' => true, 'recipe' => $recipe->load(['variant.product', 'items.material'])], 200);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Add a material directly to the recipe of one exact product/variant while
     * preparing an order. This is persistent (unlike the old temporary UI rows)
     * and remains isolated to the selected recipe.
     */
    public function addRecipeMaterialInline(Request $request): JsonResponse
    {
        $v = $request->validate([
            'order_id' => 'required|string|exists:orders,id',
            'order_item_id' => 'required|integer|exists:order_items,id',
            'recipe_id' => 'required|string|exists:recipes,id',
            'material_id' => 'required|integer|exists:materials,id',
            'expected_qty' => 'required|numeric|min:0',
            'unit' => 'required|string|max:20',
        ]);

        try {
            $result = DB::transaction(function () use ($v) {
                $order = Order::lockForUpdate()->findOrFail($v['order_id']);
                $item = OrderItem::lockForUpdate()
                    ->where('order_id', $order->id)
                    ->findOrFail((int) $v['order_item_id']);
                $variant = ProductVariant::whereKey($item->product_variant_id)
                    ->where('product_id', $item->product_id)
                    ->firstOrFail();
                $recipe = Recipe::lockForUpdate()
                    ->whereKey($v['recipe_id'])
                    ->where('product_variant_id', $variant->id)
                    ->firstOrFail();

                // Freeze this recipe onto the order item. This is crucial when a
                // preparation page creates/edits a recipe for an existing order.
                if ((string) $item->recipe_id !== (string) $recipe->id) {
                    $item->update(['recipe_id' => $recipe->id]);
                }

                $material = Material::findOrFail((int) $v['material_id']);
                $fake = new RecipeItem(['unit' => $v['unit']]);
                if (!(new ConsumptionRuleService())->validateUnitCompatibility($fake, (string) $material->base_unit)) {
                    throw new \InvalidArgumentException("Unit {$v['unit']} is incompatible with {$material->base_unit}.");
                }

                $duplicate = $recipe->items()
                    ->where('material_id', (int) $v['material_id'])
                    ->where('unit', $v['unit'])
                    ->first();

                if ($duplicate) {
                    $duplicate->update([
                        'expected_qty' => (float) $duplicate->expected_qty + (float) $v['expected_qty'],
                    ]);
                    return $duplicate->fresh('material');
                }

                return $recipe->items()->create([
                    'material_id' => (int) $v['material_id'],
                    'expected_qty' => (float) $v['expected_qty'],
                    'unit' => $v['unit'],
                    'consumption_rule_type' => 'fixed',
                    'rule_config' => [],
                    'is_optional' => false,
                    'allow_manual_override' => true,
                    'sort_order' => ((int) $recipe->items()->max('sort_order')) + 1,
                ])->load('material');
            });

            return response()->json(['ok' => true, 'item' => $result], 201);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get available materials for manual override
     */
    public function getAvailableMaterials(): JsonResponse
    {
        try {
            $materials = Material::where('is_active', true)
                ->orderBy('material_category')
                ->orderBy('name')
                ->get();
            if (!\App\Support\PermissionService::canViewCosts(request()->attributes->get('authRole'))) {
                $materials->transform(function ($material) {
                    $data = $material->toArray();
                    foreach (['avg_unit_cost','currency','exchange_rate','supplier_name','notes'] as $field) unset($data[$field]);
                    return $data;
                });
            }
            return response()->json($materials);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'An unexpected server error occurred.'], 500);
        }
    }
}
