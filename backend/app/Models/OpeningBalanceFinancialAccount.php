<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Models\FinancialAccount;

class OpeningBalanceFinancialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'opening_balance_id',
        'financial_account_id',
        'account_name',
        'account_name_ar',
        'account_type',
        'currency',
        'balance',
        'exchange_rate',
        'balance_syp',
        'bank_name',
        'account_number',
        'notes',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'exchange_rate' => 'decimal:2',
        'balance_syp' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

static::saving(function ($model) {
            // Only convert to SYP for USD accounts; SYP accounts use the balance as-is.
            if (($model->currency ?? 'SYP') === 'USD' && $model->exchange_rate) {
                $model->balance_syp = $model->balance * $model->exchange_rate;
            } else {
                $model->balance_syp = $model->balance;
            }
        });
    }

    public function openingBalance(): BelongsTo
    {
        return $this->belongsTo(OpeningBalance::class);
    }

    public function financialAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }
}