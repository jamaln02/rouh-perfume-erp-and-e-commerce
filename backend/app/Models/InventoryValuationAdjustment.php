<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryValuationAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'material_id', 'adjustment_date', 'carrying_value_before', 'nrv_value',
        'adjustment_amount', 'type', 'status', 'journal_entry_id', 'created_by', 'reason',
    ];

    protected $casts = [
        'adjustment_date' => 'date',
        'carrying_value_before' => 'decimal:2',
        'nrv_value' => 'decimal:2',
        'adjustment_amount' => 'decimal:2',
    ];

    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
    public function journal(): BelongsTo { return $this->belongsTo(JournalEntry::class, 'journal_entry_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
