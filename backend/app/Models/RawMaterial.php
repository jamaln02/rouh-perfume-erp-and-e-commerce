<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'type',
        'unit',
        'current_stock',
        'min_stock',
        'cost_per_unit',
        'currency',
        'exchange_rate',
        'cost_per_unit_syp',
        'supplier',
        'notes',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'min_stock' => 'decimal:2',
        'cost_per_unit' => 'decimal:2',
        'exchange_rate' => 'decimal:2',
        'cost_per_unit_syp' => 'decimal:2',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function isLowStock()
    {
        return $this->current_stock <= $this->min_stock;
    }
}
