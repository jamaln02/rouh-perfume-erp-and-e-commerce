<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'tax_type',
        'rate',
        'is_active',
        'post_to_ledger',
        'tax_inclusive',
        'effective_date',
        'expiry_date',
        'description',
        'calculation_method',
        'fixed_amount',
        'applicable_to',
        'applicable_categories',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'fixed_amount' => 'decimal:2',
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
        'post_to_ledger' => 'boolean',
        'tax_inclusive' => 'boolean',
        'applicable_categories' => 'array',
    ];

    /**
     * Scope for active taxes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('effective_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now());
            });
    }


    public function scopeActiveForDate($query, $date)
    {
        return $query->where('is_active', true)
            ->whereDate('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $date);
            });
    }

    /**
     * Calculate tax amount based on base value
     */
    public function calculateTax(float $baseValue): float
    {
        if (!$this->is_active) {
            return 0;
        }

        if ($this->calculation_method === 'fixed_amount') {
            return $this->fixed_amount;
        }

        return ($baseValue * $this->rate) / 100;
    }

    /**
     * Check if tax is applicable to specific category
     */
    public function isApplicableToCategory(string $category): bool
    {
        if ($this->applicable_to === 'all_revenue') {
            return true;
        }

        if ($this->applicable_to === 'specific_categories') {
            return in_array($category, $this->applicable_categories ?? []);
        }

        return false;
    }
}
