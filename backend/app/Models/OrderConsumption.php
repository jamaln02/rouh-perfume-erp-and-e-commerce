<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderConsumption extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'source',
        'prepared_at',
        'prepared_by',
        'confirmed_at',
        'confirmed_by',
        'notes',
        'total_material_cost',
        'labor_cost',
        'electricity_cost',
        'overhead_cost',
        'total_production_cost',
    ];

    protected $casts = [
        'prepared_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderConsumptionItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
