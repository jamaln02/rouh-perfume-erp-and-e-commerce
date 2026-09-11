<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'public_tracking_token',
        'user_id',
        'customer_name',
        'customer_phone',
        'customer_address',
        'city',
        'total',
        'shipping_cost',
        'payment_method',
        'notes',
        'status',
        'coupon_code',
        'discount_amount',
        'source',
        'customer_id',
        'order_status',
        'payment_status',
        'consumption_status',
        'subtotal',
        'shipping_fee',
        'shipping_cost_internal',
        'payment_fees',
        'paid_amount',
        'remaining_amount',
        'refund_amount',
        'delivery_type',
        'delivery_status',
        'accepted_at',
        'prepared_at',
        'completed_at',
        'cancelled_at',
        'returned_at',
        'created_by_admin_id',
        'oil_grams',
        'bottle_size_ml',
        'loyalty_awarded_at',
        'loyalty_points_earned',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'shipping_cost_internal' => 'decimal:2',
        'payment_fees' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'oil_grams' => 'decimal:2',
        'bottle_size_ml' => 'decimal:2',
        'accepted_at' => 'datetime',
        'prepared_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function consumption()
    {
        return $this->hasOne(OrderConsumption::class, 'order_id');
    }
}
