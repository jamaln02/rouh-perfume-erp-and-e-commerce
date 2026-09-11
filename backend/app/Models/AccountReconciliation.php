<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountReconciliation extends Model
{
    use HasFactory;
    protected $fillable = ['financial_account_id','reconciliation_date','book_balance','statement_balance','difference','status','notes','reconciled_by'];
    protected $casts = ['reconciliation_date'=>'date','book_balance'=>'decimal:2','statement_balance'=>'decimal:2','difference'=>'decimal:2'];
    public function account(): BelongsTo { return $this->belongsTo(FinancialAccount::class, 'financial_account_id'); }
    public function reconciler(): BelongsTo { return $this->belongsTo(User::class, 'reconciled_by'); }
}
