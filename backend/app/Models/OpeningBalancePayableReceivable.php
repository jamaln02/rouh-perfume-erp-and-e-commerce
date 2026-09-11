<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalancePayableReceivable extends Model
{
    use HasFactory;

    protected $table = 'opening_balance_payables_receivables';

    protected $fillable = [
        'opening_balance_id',
        'party_name',
        'party_name_ar',
        'type',
        'contact_person',
        'phone',
        'email',
        'amount',
        'currency',
        'exchange_rate',
        'amount_syp',
        'due_date',
        'description',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:2',
        'amount_syp' => 'decimal:2',
        'due_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

static::saving(function ($model) {
            // Only convert to SYP for USD amounts; SYP amounts use the amount as-is.
            if (($model->currency ?? 'SYP') === 'USD' && $model->exchange_rate) {
                $model->amount_syp = $model->amount * $model->exchange_rate;
            } else {
                $model->amount_syp = $model->amount;
            }
        });
    }

    public function openingBalance(): BelongsTo
    {
        return $this->belongsTo(OpeningBalance::class);
    }
}