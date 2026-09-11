<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalanceAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'opening_balance_id',
        'adjustment_type',
        'reference_id',
        'reference_type',
        'original_values',
        'new_values',
        'reason',
        'notes',
        'adjusted_by',
        'adjusted_at',
        'approved_by',
        'approved_at',
        'journal_entry_id',
        'status',
    ];

    protected $casts = [
        'original_values' => 'array',
        'new_values' => 'array',
        'approved_at' => 'datetime',
        'adjusted_at' => 'datetime',
    ];

    public function openingBalance(): BelongsTo
    {
        return $this->belongsTo(OpeningBalance::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function adjustedBy()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}