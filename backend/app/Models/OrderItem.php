<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'recipe_id',
        'product_name',
        'size',
        'price',
        'blend_id',
        'bottle_type',
        'bottle_size_ml',
        'oil_mix',
        'quantity',
        'unit_cost',
        'unit_price',
        'line_total',
        'line_cost',
        'packaging_items',
        'packaging_cost',
        'notes',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'line_cost' => 'decimal:2',
        'packaging_cost' => 'decimal:2',
        'oil_mix' => 'array',
        'packaging_items' => 'array',
    ];

public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product() { return $this->belongsTo(Product::class, 'product_id'); }

    public function variant() { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }

    public function recipe() { return $this->belongsTo(Recipe::class, 'recipe_id'); }

    public function blend()
    {
        return $this->belongsTo(CustomBlend::class);
    }

    public function calculateLineTotal(): void
    {
        $this->line_total = $this->unit_price * $this->quantity;
        $this->line_cost = $this->unit_cost * $this->quantity;
        $this->save();
    }

    public function calculateUnitCost(): float
    {
        // Calculate material cost based on oil mix
        if ($this->bottle_type === 'custom' && $this->oil_mix) {
            $oilCost = 0;
            foreach ($this->oil_mix as $mix) {
                $oil = Material::query()->whereKey((int)($mix['oil_id'] ?? 0))->where('material_category','perfume_oil')->where('is_active',true)->first();
                if ($oil) {
                    $oilCost += ((float)($oil->avg_unit_cost ?? 0)) * (float)($mix['grams'] ?? 0);
                }
            }
            // Project production rule: alcohol is the residual bottle volume
            // after the recorded oil quantity. The cost comes from the active alcohol
            // material's weighted-average inventory cost, not a hardcoded price.
            $bottleSizeMl = (float) preg_replace('/[^0-9.]+/', '', (string) $this->bottle_size_ml);
            $oilGrams = array_sum(array_map(
                static fn (array $mix): float => (float) ($mix['grams'] ?? 0),
                is_array($this->oil_mix) ? $this->oil_mix : []
            ));
            $alcoholMl = app(\App\Services\ConsumptionRuleService::class)
                ->calculateResidualAlcoholMl($bottleSizeMl, $oilGrams);
            $alcoholMaterial = Material::query()
                ->where('material_category', 'alcohol')
                ->where('is_active', true)
                ->orderByRaw("CASE WHEN code = 'ALC-ETHANOL-1L' THEN 0 WHEN name_ar = 'كحول ايثانول' THEN 1 ELSE 2 END")
                ->orderBy('id')
                ->first();
            $alcoholCost = 0.0;
            if ($alcoholMaterial) {
                $quantityInBaseUnit = app(\App\Services\UnitConversionService::class)
                    ->convert($alcoholMl, 'mL', (string) $alcoholMaterial->base_unit);
                if ($quantityInBaseUnit !== null) {
                    $alcoholCost = $quantityInBaseUnit * (float) ($alcoholMaterial->avg_unit_cost ?? 0);
                }
            }
            return $oilCost + $alcoholCost;
        } elseif ($this->blend_id) {
            return $this->blend->total_cost_per_bottle;
        }
        return 0;
    }

    public function calculatePackagingCost(): float
    {
        $packagingCost = 0;
        if ($this->packaging_items) {
            foreach ($this->packaging_items as $item) {
                $packaging = Material::query()->whereKey((int)($item['id'] ?? 0))->where('material_category','packaging')->where('is_active',true)->first();
                if ($packaging) {
                    $packagingCost += ((float)($packaging->avg_unit_cost ?? 0)) * (float)($item['quantity'] ?? 0);
                }
            }
        }
        return $packagingCost;
    }
}