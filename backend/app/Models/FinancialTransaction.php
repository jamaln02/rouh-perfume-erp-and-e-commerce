<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'txn_code',
        'transaction_type',
        'direction',
        'amount',
        'currency',
        'exchange_rate',
        'amount_base',
        'account_id',
        'accounting_period_id',
        'reference_type',
        'reference_id',
        'counterparty_type',
        'counterparty_id',
        'description',
        'meta',
        'transaction_date',
        'created_by',
        'approved_by',
        'approved_at',
        'reversed_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
        'amount_base' => 'decimal:2',
        'meta' => 'array',
        'transaction_date' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }
}
