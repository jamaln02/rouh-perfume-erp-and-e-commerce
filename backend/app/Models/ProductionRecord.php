<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'production_date',
        'status',
        'oils_used',
        'alcohol_used_ml',
        'packaging_used',
        'bottles_produced',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'alcohol_used_ml' => 'decimal:2',
        'bottles_produced' => 'integer',
        'oils_used' => 'array',
        'packaging_used' => 'array',
        'production_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

public function order()
    {
        return $this->belongsTo(CustomerOrder::class, 'order_id');
    }

    public function scopePlanned($query)
    {
        return $query->where('status', 'planned');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function markAsCompleted(): void
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
    }

    public function calculateTotalOilUsed(): float
    {
        $total = 0;
        if ($this->oils_used) {
            foreach ($this->oils_used as $oil) {
                $total += $oil['grams_used'] ?? 0;
            }
        }
        return $total;
    }
}