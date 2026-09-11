<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'product_name',
        'size_type',
        'size_ml',
        'quantity',
        'unit_price',
        'total_price',
        'currency',
        'exchange_rate',
        'total_price_syp',
        'cost',
        'cost_syp',
        'profit',
        'profit_syp',
        'sale_source',
        'customer_id',
        'customer_name',
        'customer_phone',
        'sale_date',
        'payment_method',
        'notes',
        'invoice_number',
        'order_id',
        'payment_status',
        'paid_amount',
        'remaining_amount',
        'sale_status',
        'revenue_status',
        'revenue_recognized_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'total_price_syp' => 'decimal:2',
        'cost' => 'decimal:2',
        'cost_syp' => 'decimal:2',
        'profit' => 'decimal:2',
        'profit_syp' => 'decimal:2',
        'exchange_rate' => 'decimal:2',
        'sale_date' => 'date',
        'size_ml' => 'integer',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'revenue_recognized_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Calculate SYP equivalents if currency is USD
            $model->profit = round((float) ($model->total_price ?? 0) - (float) ($model->cost ?? 0), 2);
            if ($model->currency === 'USD' && $model->exchange_rate) {
                $model->total_price_syp = $model->total_price * $model->exchange_rate;
                $model->cost_syp = $model->cost * $model->exchange_rate;
                $model->profit_syp = $model->profit * $model->exchange_rate;
            } else {
                $model->total_price_syp = $model->total_price;
                $model->cost_syp = $model->cost;
                $model->profit_syp = $model->profit;
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(\App\Models\Order::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
