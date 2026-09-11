<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'city',
        'total_orders',
        'total_spent',
        'loyalty_points',
        'last_purchase_date',
        'preferred_blends',
        'notes',
        'status',
    ];

    protected $casts = [
        'total_spent' => 'decimal:2',
        'loyalty_points' => 'integer',
        'last_purchase_date' => 'date',
        'preferred_blends' => 'array',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'customer_id');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function addLoyaltyPoints(int $points): void
    {
        $this->loyalty_points += $points;
        $this->save();
    }

    public function incrementOrderCount(int $amount = 1): void
    {
        $this->total_orders += $amount;
        $this->save();
    }

    public function addSpentAmount(float $amount): void
    {
        $this->total_spent += $amount;
        $this->save();
    }
}