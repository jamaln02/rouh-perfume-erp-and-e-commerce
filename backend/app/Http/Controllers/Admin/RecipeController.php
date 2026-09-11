<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\ProductVariant;
use App\Models\Recipe;
use App\Models\RecipeItem;
use App\Services\ConsumptionRuleService;
use App\Services\StandardRecipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * RecipeController
 *
 * Manages perfume recipes — one recipe per product variant, versioned and
 * optionally time-bounded. Each recipe now carries an explicit oil_percentage
 * (default 32%) which drives the automatic oil/alcohol split calculation.
 */
class RecipeController extends Controller
{
    /**
     * Return a single recipe with its variant, product, and line items.
     */
    public function show(string $id): JsonResponse
    {
        $recipe = Recipe::with(['variant.product', 'items.material'])->findOrFail($id);

        // Enrich the response with derived oil/alcohol ml values for convenience.
        $enriched = $this->enrichWithOilAlcohol($recipe);

        return response()->json(['recipe' => $enriched]);
    }

    /**
     * Return active materials that can be attached to a recipe.
     */
    public function materials(): JsonResponse
    {
        $materials = Material::query()
            ->where('is_active', true)
            ->orderBy('material_category')
            ->orderBy('name_ar')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'name_ar', 'material_category', 'base_unit']);

        return response()->json(['materials' => $materials]);
    }

    /**
     * Create a new recipe (optionally activating it and deactivating prior versions).
     */
    public function store(Request $request): JsonResponse
    {
        $v = $request->validate([
            'product_variant_id' => 'required|string|exists:product_variants,id',
            'version'            => 'nullable|integer|min:1',
            'is_active'          => 'nullable|boolean',
            'oil_percentage'     => 'nullable|numeric|min:0|max:100',
            'effective_from'     => 'nullable|date',
            'effective_to'       => 'nullable|date',
            'notes'              => 'nullable|string',
        ]);

        $recipe = DB::transaction(function () use ($v) {
            $variant = ProductVariant::findOrFail($v['product_variant_id']);
            $active  = $v['is_active'] ?? true;

            // Deactivate other recipes for the same variant when this one is active.
            if ($active) {
                $variant->recipes()->update(['is_active' => false]);
            }

            // Oil percentage: default 32%. Alcohol = 100 − oil.
            $oilPct     = (float) ($v['oil_percentage'] ?? 32);
            $alcoholPct = round(100 - $oilPct, 2);

            $recipe = Recipe::create([
                'id'                 => (string) Str::uuid(),
                'product_variant_id' => $variant->id,
                'version'            => $v['version'] ?? ((int) $variant->recipes()->max('version') + 1),
                'is_active'          => $active,
                'oil_percentage'     => $oilPct,
                'alcohol_percentage' => $alcoholPct,
                'effective_from'     => $v['effective_from'] ?? now()->toDateString(),
                'effective_to'       => $v['effective_to'] ?? null,
                'notes'              => $v['notes'] ?? 'قالب الوصفة القياسي: الكميات تُحدد عند تجهيز الطلب.',
                'created_by'         => auth()->id(),
            ]);

            $materialIds = app(StandardRecipeService::class)->materialIdsForVariant($variant);
            foreach ($materialIds as $sort => $materialId) {
                $material = Material::findOrFail($materialId);
                $recipe->items()->create([
                    'material_id' => $material->id,
                    'expected_qty' => 0,
                    'unit' => $material->base_unit,
                    'consumption_rule_type' => 'fixed',
                    'rule_config' => [],
                    'is_optional' => false,
                    'allow_manual_override' => true,
                    'sort_order' => $sort + 1,
                    'notes' => null,
                ]);
            }

            return $recipe;
        });

        return response()->json([
            'ok'     => true,
            'recipe' => $recipe->load(['variant.product', 'items.material']),
        ], 201);
    }

    /**
     * Update recipe metadata (version, activation, dates, notes, oil_percentage).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $recipe = Recipe::findOrFail($id);

        $v = $request->validate([
            'version'        => 'nullable|integer|min:1',
            'is_active'      => 'nullable|boolean',
            'oil_percentage' => 'nullable|numeric|min:0|max:100',
            'effective_from' => 'nullable|date',
            'effective_to'   => 'nullable|date',
            'notes'          => 'nullable|string',
        ]);

        DB::transaction(function () use ($recipe, $v) {
            // Deactivate sibling recipes when activating this one.
            if (($v['is_active'] ?? $recipe->is_active) === true) {
                Recipe::where('product_variant_id', $recipe->product_variant_id)
                    ->where('id', '!=', $recipe->id)
                    ->update(['is_active' => false]);
            }

            // Keep alcohol_percentage in sync with oil_percentage.
            if (array_key_exists('oil_percentage', $v)) {
                $v['alcohol_percentage'] = round(100 - (float) $v['oil_percentage'], 2);
            }

            $recipe->update($v);
        });

        return response()->json([
            'ok'     => true,
            'recipe' => $recipe->fresh(['variant.product', 'items.material']),
        ]);
    }

    /**
     * Add a single recipe item (a material line in the recipe).
     */
    public function addItem(Request $request, string $recipeId): JsonResponse
    {
        $recipe = Recipe::findOrFail($recipeId);
        $v      = $this->validateItem($request);
        $this->assertUnit($v['material_id'], $v['unit']);

        $alreadyExists = $recipe->items()
            ->where('material_id', $v['material_id'])
            ->exists();
        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'material_id' => ['This material is already present in the recipe. Edit the existing line instead.'],
            ]);
        }

        $item = $recipe->items()->create($v);

        return response()->json(['ok' => true, 'item' => $item->load('material')], 201);
    }

    /**
     * Update a single recipe item.
     */
    public function updateItem(Request $request, string $recipeId, int $itemId): JsonResponse
    {
        $recipe = Recipe::findOrFail($recipeId);
        $item   = $recipe->items()->whereKey($itemId)->firstOrFail();
        $v      = $this->validateItem($request);
        $this->assertUnit($v['material_id'], $v['unit']);
        $item->update($v);

        return response()->json(['ok' => true, 'item' => $item->fresh('material')]);
    }

    /**
     * Delete a single recipe item.
     */
    public function deleteItem(string $recipeId, int $itemId): JsonResponse
    {
        $recipe = Recipe::findOrFail($recipeId);
        $recipe->items()->whereKey($itemId)->firstOrFail()->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Validate a recipe item payload and normalise it for storage.
     */
    private function validateItem(Request $r): array
    {
        $v = $r->validate([
            'material_id'             => 'required|integer|exists:materials,id',
            'expected_qty'            => 'required|numeric|min:0',
            'unit'                    => 'required|string|max:20',
            'consumption_rule_type'   => 'nullable|string|in:fixed,per_bottle,percentage,ratio,packaging_rule',
            'rule_config'             => 'nullable|array',
            'is_optional'             => 'nullable|boolean',
            'allow_manual_override'   => 'nullable|boolean',
            'sort_order'              => 'nullable|integer|min:0',
            'notes'                   => 'nullable|string',
        ]);

        return [
            'material_id'           => (int) $v['material_id'],
            'expected_qty'          => (float) $v['expected_qty'],
            'unit'                  => $v['unit'],
            'consumption_rule_type' => $v['consumption_rule_type'] ?? 'fixed',
            'rule_config'           => $v['rule_config'] ?? [],
            'is_optional'           => (bool) ($v['is_optional'] ?? false),
            'allow_manual_override' => (bool) ($v['allow_manual_override'] ?? true),
            'sort_order'            => (int) ($v['sort_order'] ?? 0),
            'notes'                 => $v['notes'] ?? null,
        ];
    }

    /**
     * Ensure the recipe item unit is compatible with the material's base unit.
     */
    private function assertUnit(int $id, string $unit): void
    {
        $m    = Material::findOrFail($id);
        $fake = new RecipeItem(['unit' => $unit]);

        if (!(new ConsumptionRuleService())->validateUnitCompatibility($fake, (string) $m->base_unit)) {
            throw new \InvalidArgumentException("Unit {$unit} is incompatible with {$m->base_unit}.");
        }
    }

    /**
     * Append derived oil_ml and alcohol_ml to the recipe object for display.
     * These are computed from the variant volume_ml × percentage.
     */
    private function enrichWithOilAlcohol(Recipe $recipe): Recipe
    {
        $volumeMl  = $recipe->variant ? (float) $recipe->variant->volume_ml : null;
        $oilPct    = (float) ($recipe->oil_percentage ?? 32);
        $alcoholPct = (float) ($recipe->alcohol_percentage ?? (100 - $oilPct));

        $recipe->oil_ml     = $volumeMl !== null ? round($volumeMl * $oilPct / 100, 2) : null;
        $recipe->alcohol_ml = $volumeMl !== null ? round($volumeMl * $alcoholPct / 100, 2) : null;

        return $recipe;
    }
}
