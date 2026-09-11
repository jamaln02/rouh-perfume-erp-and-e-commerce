<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_id',
        'material_id',
        'expected_qty',
        'unit',
        'consumption_rule_type',
        'rule_config',
        'is_optional',
        'allow_manual_override',
        'sort_order',
        'notes',
    ];

    protected $casts = [
        'expected_qty' => 'decimal:4',
        'rule_config' => 'array',
        'is_optional' => 'boolean',
        'allow_manual_override' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
