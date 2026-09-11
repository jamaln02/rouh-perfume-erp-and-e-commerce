<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_address',
        'city',
        'order_type',
        'status',
        'total_cost',
        'total_price',
        'packaging_cost',
        'profit',
        'markup_percentage',
        'payment_method',
        'payment_status',
        'paid_amount',
        'notes',
        'order_date',
        'ready_date',
    ];

    protected $casts = [
        'total_cost' => 'decimal:2',
        'total_price' => 'decimal:2',
        'packaging_cost' => 'decimal:2',
        'profit' => 'decimal:2',
        'markup_percentage' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'order_date' => 'datetime',
        'ready_date' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function productionRecord()
    {
        return $this->hasOne(ProductionRecord::class, 'order_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeInProduction($query)
    {
        return $query->where('status', 'in_production');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function calculateTotalPrice(): void
    {
        $this->total_price = $this->total_cost * (1 + ($this->markup_percentage / 100));
        $this->profit = $this->total_price - $this->total_cost;
        $this->save();
    }

    public function canBeProduced(): bool
    {
        return in_array($this->status, ['confirmed', 'in_production']);
    }
}