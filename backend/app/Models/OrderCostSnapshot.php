<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCostSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'order_consumption_id',
        'revenue_subtotal',
        'discount_amount',
        'shipping_revenue',
        'shipping_cost',
        'payment_fees',
        'other_costs',
        'material_cogs',
        'packaging_cogs',
        'total_cogs',
        'gross_profit',
        'net_profit',
        'currency',
        'exchange_rate',
        'is_final',
        'computed_at',
        'computed_by',
        'notes',
    ];

    protected $casts = [
        'revenue_subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'shipping_revenue' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'payment_fees' => 'decimal:2',
        'other_costs' => 'decimal:2',
        'material_cogs' => 'decimal:2',
        'packaging_cogs' => 'decimal:2',
        'total_cogs' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'is_final' => 'boolean',
        'computed_at' => 'datetime',
    ];

    public function consumption(): BelongsTo
    {
        return $this->belongsTo(OrderConsumption::class, 'order_consumption_id');
    }
}
