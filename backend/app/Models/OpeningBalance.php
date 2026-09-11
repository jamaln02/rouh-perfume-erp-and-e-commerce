<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OpeningBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'opening_balance_date',
        'status',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'total_inventory_value',
        'total_fixed_assets_value',
        'total_cash_balance',
        'total_bank_balance',
        'total_payables',
        'total_receivables',
        'notes',
        'journal_entry_id',
    ];

    protected $casts = [
        'opening_balance_date' => 'date',
        'confirmed_at' => 'datetime',
        'total_inventory_value' => 'decimal:2',
        'total_fixed_assets_value' => 'decimal:2',
        'total_cash_balance' => 'decimal:2',
        'total_bank_balance' => 'decimal:2',
        'total_payables' => 'decimal:2',
        'total_receivables' => 'decimal:2',
    ];

    public function inventory(): HasMany
    {
        return $this->hasMany(OpeningBalanceInventory::class);
    }

    public function fixedAssets(): HasMany
    {
        return $this->hasMany(\App\Models\OpeningBalanceFixedAsset::class);
    }

    public function financialAccounts(): HasMany
    {
        return $this->hasMany(OpeningBalanceFinancialAccount::class);
    }

    public function payablesReceivables(): HasMany
    {
        return $this->hasMany(OpeningBalancePayableReceivable::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(OpeningBalanceAdjustment::class);
    }

    public function journal()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer()
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed' || $this->status === 'locked';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function canBeEdited(): bool
    {
        return $this->status === 'draft';
    }
}