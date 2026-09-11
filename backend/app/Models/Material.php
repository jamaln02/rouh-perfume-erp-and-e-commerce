<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_ar',
        'material_category',
        'subcategory',
        'base_unit',
        'track_fractional',
        'current_stock',
        'min_stock',
        'avg_unit_cost',
        'currency',
        'exchange_rate',
        'supplier_name',
        'is_active',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'track_fractional' => 'boolean',
        'current_stock' => 'decimal:4',
        'min_stock' => 'decimal:4',
        'avg_unit_cost' => 'decimal:4',
        'exchange_rate' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function recipeItems(): HasMany
    {
        return $this->hasMany(RecipeItem::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}
