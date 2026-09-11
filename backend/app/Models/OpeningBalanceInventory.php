<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalanceInventory extends Model
{
    use HasFactory;

    protected $table = 'opening_balance_inventory';

    protected $fillable = [
        'opening_balance_id',
        'material_id',
        'material_name',
        'material_name_ar',
        'material_type',
        'unit',
        'quantity',
        'unit_cost',
        'currency',
        'exchange_rate',
        'total_value',
        'supplier',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:6',
        'unit_cost' => 'decimal:6',
        'exchange_rate' => 'decimal:6',
        'total_value' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

static::saving(function ($model) {
            // Only convert to SYP for USD items; SYP items use the cost as-is.
            if (($model->currency ?? 'SYP') === 'USD' && $model->exchange_rate) {
                $model->total_value = $model->quantity * $model->unit_cost * $model->exchange_rate;
            } else {
                $model->total_value = $model->quantity * $model->unit_cost;
            }
        });
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function openingBalance(): BelongsTo
    {
        return $this->belongsTo(OpeningBalance::class);
    }
}