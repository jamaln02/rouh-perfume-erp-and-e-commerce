<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCount extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if ($model->status === null) $model->status = 'draft';
        });
    }

    protected $fillable = [
        'count_code',
        'count_date',
        'status',
        'notes',
        'created_by',
        'confirmed_by',
        'confirmed_at',
        'journal_entry_id',
    ];

    protected $casts = [
        'count_date' => 'date',
        'confirmed_at' => 'datetime',
    ];

    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }
}
