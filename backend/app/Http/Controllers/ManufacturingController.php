<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Material;
use App\Models\ProductMaterialMapping;
use App\Models\OrderConsumption;
use App\Models\OrderConsumptionItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ManufacturingController extends Controller
{
    /**
     * Get product-material mappings
     */
    public function getProductMappings(): JsonResponse
    {
        try {
            $mappings = ProductMaterialMapping::with(['product', 'material'])
                ->orderBy('product_id')
                ->orderBy('is_default', 'desc')
                ->get();
            
            return response()->json($mappings);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'An unexpected server error occurred.'], 500);
        }
    }

    /**
     * Get default material for a product
     */
    public function getDefaultMaterial($productId): JsonResponse
    {
        try {
            $mapping = ProductMaterialMapping::where('product_id', $productId)
                ->where('is_default', true)
                ->with('material')
                ->first();
            
            if (!$mapping) {
                return response()->json(['message' => 'No default material mapping found'], 404);
            }
            
            return response()->json($mapping);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'An unexpected server error occurred.'], 500);
        }
    }

    /**
     * Create or update product-material mapping
     */
    public function storeProductMapping(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'material_id' => 'required|exists:materials,id',
                'is_default' => 'boolean',
                'notes' => 'nullable|string',
            ]);

            // Check if mapping already exists
            $existing = ProductMaterialMapping::where('product_id', $validated['product_id'])
                ->where('material_id', $validated['material_id'])
                ->first();

            if ($existing) {
                // Update existing
                $existing->update([
                    'is_default' => $validated['is_default'] ?? true,
                    'notes' => $validated['notes'],
                ]);
                $mapping = $existing->load(['product', 'material']);
            } else {
                // Create new
                $mapping = ProductMaterialMapping::create([
                    'product_id' => $validated['product_id'],
                    'material_id' => $validated['material_id'],
                    'is_default' => $validated['is_default'] ?? true,
                    'notes' => $validated['notes'],
                    'created_by' => auth()->id(),
                ]);
                $mapping->load(['product', 'material']);
            }

            // If this is set as default, remove default flag from other mappings for this product
            if ($validated['is_default'] ?? true) {
                ProductMaterialMapping::where('product_id', $validated['product_id'])
                    ->where('id', '!=', $mapping->id)
                    ->update(['is_default' => false]);
            }

            return response()->json($mapping, 201);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'An unexpected server error occurred.'], 500);
        }
    }

    /**
     * Delete product-material mapping
     */
    public function updateProductMapping(Request $request, $id): JsonResponse
    {
        $mapping = ProductMaterialMapping::findOrFail($id);
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'material_id' => 'required|exists:materials,id',
            'is_default' => 'boolean',
            'notes' => 'nullable|string',
        ]);
        DB::transaction(function () use ($mapping, $validated) {
            $mapping->update($validated + ['created_by' => auth()->id()]);
            if (($validated['is_default'] ?? false) === true) {
                ProductMaterialMapping::where('product_id', $validated['product_id'])->where('id','!=',$mapping->id)->update(['is_default'=>false]);
            }
        });
        return response()->json($mapping->fresh(['product','material']));
    }

    public function deleteProductMapping($id): JsonResponse
    {
        try {
            $mapping = ProductMaterialMapping::findOrFail($id);
            $mapping->delete();
            return response()->json(['message' => 'Mapping deleted']);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'An unexpected server error occurred.'], 500);
        }
    }

    /**
     * Create production order for customer order
     */
    public function createProductionOrder(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'order_id' => 'required|exists:orders,id',
                'notes' => 'nullable|string',
            ]);

            $order = Order::findOrFail($validated['order_id']);

            // Check if production order already exists
            $existingConsumption = OrderConsumption::where('order_id', $validated['order_id'])
                ->where('status', '!=', 'completed')
                ->first();

            if ($existingConsumption) {
                return response()->json(['error' => 'Production order already exists for this order'], 400);
            }

            DB::beginTransaction();

            // Create production order
            $consumption = OrderConsumption::create([
                'order_id' => $validated['order_id'],
                'status' => 'planned',
                'source' => 'manual',
                'notes' => $validated['notes'],
                'prepared_by' => auth()->id(),
            ]);

            // Build expected consumption from each order line's own frozen recipe.
            // The legacy product-level material mapping is intentionally not used here
            // because it cannot represent different formulations for different products
            // or variants inside the same customer order.
            $order = Order::with('items')->findOrFail($validated['order_id']);
            $service = new \App\Services\StockValidationService();
            $requirements = $service->perProductMaterialRequirements($order);

            foreach ($requirements as $bucket) {
                if (!$bucket['recipe']) {
                    continue;
                }

                foreach ($bucket['materials'] as $materialLine) {
                    OrderConsumptionItem::create([
                        'order_consumption_id' => $consumption->id,
                        'order_item_id' => $bucket['order_item_id'],
                        'material_id' => $materialLine['material_id'],
                        'expected_qty' => $materialLine['expected_qty'],
                        'actual_qty' => 0,
                        'variance_qty' => 0,
                        'unit' => $materialLine['unit'],
                        'is_packaging' => $materialLine['material']->material_category === 'packaging',
                        'is_optional' => false,
                    ]);
                }
            }

            DB::commit();

            return response()->json($consumption->load(['items.material', 'items.orderItem']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'The operation could not be completed safely.'], 500);
        }
    }

    /**
     * Start production (enter actual materials)
     */
    public function startProduction(Request $request, $consumptionId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'materials' => 'required|array',
                'materials.*.material_id' => 'required|exists:materials,id',
                'materials.*.actual_qty' => 'required|numeric|min:0',
                'materials.*.is_packaging' => 'boolean',
                'materials.*.is_optional' => 'boolean',
                'labor_cost' => 'nullable|numeric|min:0',
                'electricity_cost' => 'nullable|numeric|min:0',
                'overhead_cost' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $consumption = OrderConsumption::findOrFail($consumptionId);
            if (!$consumption->order_id) {
                return response()->json(['error' => 'This production route is reserved for customer orders.'], 422);
            }
            if ($consumption->status !== 'planned') {
                return response()->json(['error' => 'Production order is not in planned status.'], 409);
            }

            foreach (['labor_cost', 'electricity_cost', 'overhead_cost'] as $field) {
                if ((float) ($validated[$field] ?? 0) > 0) {
                    return response()->json(['error' => 'Production overhead must be recorded through the approved cost/expense workflow; the order-preparation route records inventory COGS only.'], 422);
                }
            }

            $actual = [];
            foreach ($validated['materials'] as $row) {
                $mid = (string) $row['material_id'];
                if (array_key_exists($mid, $actual)) {
                    return response()->json(['error' => 'The same material cannot be submitted more than once.'], 422);
                }
                $actual[$mid] = (float) $row['actual_qty'];
            }

            $request->merge([
                'order_id' => (string) $consumption->order_id,
                'actual_quantities' => $actual,
                'material_overrides' => [],
                'notes' => trim((string) ($validated['notes'] ?? 'Legacy manufacturing screen — authoritative order preparation')),
            ]);

            return app(\App\Http\Controllers\OrderPreparationController::class)->prepareOrderUnified($request);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'Production preparation failed safely; no changes were committed.'], 422);
        }
    }

    /**
     * Complete production
     */
    public function completeProduction($consumptionId): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => 'This legacy completion route is disabled. Complete customer-order production through the authoritative order-preparation flow.',
        ], 410);
    }

    /**
     * Get production order details
     */
    public function getProductionOrder($consumptionId): JsonResponse
    {
        try {
            $consumption = OrderConsumption::with([
                'items.material',
                'items.orderItem.product',
                'order'
            ])->findOrFail($consumptionId);

            return response()->json($consumption);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'An unexpected server error occurred.'], 500);
        }
    }

    /**
     * Get all production orders
     */
    public function getProductionOrders(): JsonResponse
    {
        try {
            $consumptions = OrderConsumption::with([
                'items.material',
                'order'
            ])->orderBy('created_at', 'desc')
            ->get();

            return response()->json($consumptions);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'An unexpected server error occurred.'], 500);
        }
    }

    /**
     * Cancel production order (reverses inventory deductions)
     */
    public function cancelProduction($consumptionId): JsonResponse
    {
        try {
            $consumption = OrderConsumption::findOrFail($consumptionId);
            if (!$consumption->order_id) {
                return response()->json(['error' => 'This production record is not linked to a customer order.'], 422);
            }

            $order = Order::findOrFail($consumption->order_id);
            $cancelled = app(\App\Services\OrderLifecycleService::class)->cancel(
                $order,
                'Production order cancelled from manufacturing management'
            );

            return response()->json(['ok' => true, 'message' => 'Order cancelled and related inventory/accounting effects reversed safely.', 'order' => $cancelled]);
        } catch (\DomainException|\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 409);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'The operation could not be completed safely.'], 500);
        }
    }

    /**
     * Get available materials for production
     */
    public function getAvailableMaterials(): JsonResponse
    {
        try {
            $materials = Material::where('is_active', true)
                ->where('current_stock', '>', 0)
                ->orderBy('material_category')
                ->orderBy('name')
                ->get();

            return response()->json($materials);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'An unexpected server error occurred.'], 500);
        }
    }

    /**
     * Get materials by category for production
     */
    public function getMaterialsByCategory($category): JsonResponse
    {
        try {
            $materials = Material::where('material_category', $category)
                ->where('is_active', true)
                ->where('current_stock', '>', 0)
                ->orderBy('name')
                ->get();

            return response()->json($materials);
        } catch (\Exception $e) {
            report($e);
            return response()->json(['error' => app()->environment('local') ? $e->getMessage() : 'An unexpected server error occurred.'], 500);
        }
    }
}