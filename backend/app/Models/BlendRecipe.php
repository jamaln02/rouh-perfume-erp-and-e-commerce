<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlendRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'blend_id',
        'essential_oil_id',
        'percentage',
        'absolute_grams',
        'alcohol_ml',
        'notes',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'absolute_grams' => 'decimal:2',
        'alcohol_ml' => 'decimal:2',
    ];

    public function blend()
    {
        return $this->belongsTo(CustomBlend::class);
    }

    public function essentialOil()
    {
        return $this->belongsTo(EssentialOil::class);
    }

    public function calculateCost(): float
    {
        $oilCost = ($this->essentialOil->price_per_gram ?? 0) * ($this->absolute_grams ?? 0);
        $alcoholMaterial = \App\Models\Material::query()
            ->where('material_category', 'alcohol')
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN code = 'ALC-ETHANOL-1L' THEN 0 WHEN name_ar = 'كحول ايثانول' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->first();
        $alcoholCost = 0.0;
        if ($alcoholMaterial) {
            $quantityInBaseUnit = app(\App\Services\UnitConversionService::class)
                ->convert((float) ($this->alcohol_ml ?? 0), 'mL', (string) $alcoholMaterial->base_unit);
            if ($quantityInBaseUnit !== null) {
                $alcoholCost = $quantityInBaseUnit * (float) ($alcoholMaterial->avg_unit_cost ?? 0);
            }
        }
        return $oilCost + $alcoholCost;
    }
}