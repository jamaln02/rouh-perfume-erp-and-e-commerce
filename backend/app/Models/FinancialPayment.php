<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialPayment extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;
    protected $fillable = [
        'payment_number','idempotency_key','direction','amount','currency','exchange_rate','amount_base','financial_account_id',
        'sale_id','purchase_id','expense_id','order_id','payment_method','reference','notes','payment_date','status',
        'journal_entry_id','created_by'
    ];
    protected $casts = ['amount'=>'decimal:2','exchange_rate'=>'decimal:6','amount_base'=>'decimal:2','payment_date'=>'date'];
    public function account(): BelongsTo { return $this->belongsTo(FinancialAccount::class, 'financial_account_id'); }
    public function journal(): BelongsTo { return $this->belongsTo(JournalEntry::class, 'journal_entry_id'); }
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function purchase(): BelongsTo { return $this->belongsTo(Purchase::class); }
    public function expense(): BelongsTo { return $this->belongsTo(Expense::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class, 'order_id'); }
}
