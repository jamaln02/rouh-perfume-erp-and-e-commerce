<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id', 'financial_account_id', 'description', 'debit', 'credit',
        'currency', 'exchange_rate', 'base_debit', 'base_credit', 'counterparty_type', 'counterparty_id'
    ];

    protected $casts = [
        'debit' => 'decimal:2', 'credit' => 'decimal:2', 'exchange_rate' => 'decimal:6',
        'base_debit' => 'decimal:2', 'base_credit' => 'decimal:2'
    ];

    public function entry(): BelongsTo { return $this->belongsTo(JournalEntry::class, 'journal_entry_id'); }
    public function account(): BelongsTo { return $this->belongsTo(FinancialAccount::class, 'financial_account_id'); }
}
