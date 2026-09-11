<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'category',
        'description',
        'amount',
        'currency',
        'exchange_rate',
        'amount_syp',
'expense_date',
        'payment_method',
        'rent_duration_days',
        'vendor',
        'notes',
        'status','voided_at','voided_by','void_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:2',
        'amount_syp' => 'decimal:2',
        'expense_date' => 'date',
        'voided_at' => 'datetime',
        'rent_duration_days' => 'integer',
    ];

    public function payments()
    {
        return $this->hasMany(FinancialPayment::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Always SYP. Exchange rate is kept at 1 for legacy compatibility.
            $model->currency = 'SYP';
            $model->exchange_rate = 1;
            $model->amount_syp = $model->amount;
        });
    }
}
