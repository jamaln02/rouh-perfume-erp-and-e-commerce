<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderConsumptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_consumption_id',
        'order_item_id',
        'material_id',
        'expected_qty',
        'actual_qty',
        'variance_qty',
        'unit',
        'variance_classification',
        'is_packaging',
        'is_optional',
        'notes',
    ];

    protected $casts = [
        'expected_qty' => 'decimal:4',
        'actual_qty' => 'decimal:4',
        'variance_qty' => 'decimal:4',
        'is_packaging' => 'boolean',
        'is_optional' => 'boolean',
    ];

    public function consumption(): BelongsTo
    {
        return $this->belongsTo(OrderConsumption::class, 'order_consumption_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}
