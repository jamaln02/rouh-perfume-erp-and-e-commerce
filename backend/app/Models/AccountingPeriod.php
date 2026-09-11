<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    use HasFactory;

    protected $fillable = ['name','period_start','period_end','status','notes','created_by','closed_by','closed_at','reopened_by','reopened_at'];
    protected $casts = ['period_start'=>'date','period_end'=>'date','closed_at'=>'datetime','reopened_at'=>'datetime'];

    public function closer(): BelongsTo { return $this->belongsTo(User::class, 'closed_by'); }
    public function reopener(): BelongsTo { return $this->belongsTo(User::class, 'reopened_by'); }
    public function transactions(): HasMany { return $this->hasMany(FinancialTransaction::class, 'accounting_period_id'); }
    public function journalEntries(): HasMany { return $this->hasMany(JournalEntry::class, 'accounting_period_id'); }
}
