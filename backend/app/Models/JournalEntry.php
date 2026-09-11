<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = [
        'journal_number', 'entry_date', 'description', 'source_type', 'source_id', 'entry_kind',
        'status', 'accounting_period_id', 'posted_by', 'posted_at', 'reversal_of_id', 'created_by', 'metadata'
    ];

    protected $casts = ['entry_date' => 'date', 'posted_at' => 'datetime', 'metadata' => 'array'];

    public function lines(): HasMany { return $this->hasMany(JournalLine::class); }
    public function period(): BelongsTo { return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id'); }
    public function reversalOf(): BelongsTo { return $this->belongsTo(self::class, 'reversal_of_id'); }
    public function reversedBy(): HasMany { return $this->hasMany(self::class, 'reversal_of_id'); }
}
