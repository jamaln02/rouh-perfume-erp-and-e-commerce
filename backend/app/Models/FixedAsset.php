<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'name_ar',
        'asset_number',
        'category',
        'description',
        'quantity',
        'purchase_cost',
        'purchase_date',
        'supplier',
        'invoice_number',
        'payment_method',
        'paid_amount',
        'depreciation_method',
        'useful_life_years',
        'useful_life_months',
        'total_estimated_units',
        'salvage_value',
        'opening_accumulated_depreciation',
        'depreciation_start_date',
        'status',
        'disposal_date',
        'disposal_value',
        'location',
        'serial_number',
        'notes',
    ];

    protected $casts = [
        'purchase_cost' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'disposal_value' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'useful_life_months' => 'integer',
        'total_estimated_units' => 'decimal:4',
        'purchase_date' => 'date',
        'in_service_date' => 'date',
        'opening_accumulated_depreciation' => 'decimal:2',
        'depreciation_start_date' => 'date',
        'disposal_date' => 'date',
    ];

    /**
     * The stored purchase_cost is the TOTAL cost for the quantity recorded.
     * Straight-line depreciation is calculated on (cost - salvage value) over useful life.
     */
    private function lifeMonths(): int
    {
        return max(1, (int)($this->useful_life_months ?: ((int)$this->useful_life_years * 12)));
    }

    public function calculateAnnualDepreciation(): float
    {
        if ($this->status !== 'active' || !$this->depreciation_start_date || $this->lifeMonths() <= 0) return 0.0;
        $cost = (float) $this->purchase_cost;
        $salvage = min($cost, max(0.0, (float) $this->salvage_value));
        if ($this->depreciation_method === 'declining_balance') {
            return max(0.0, $cost * (24 / $this->lifeMonths()));
        }
        if ($this->depreciation_method === 'units_of_production') {
            return 0.0; // Requires period production units; use the period posting API with units.
        }
        return max(0.0, $cost - $salvage) * 12 / $this->lifeMonths();
    }

    public function calculateMonthlyDepreciation(): float
    {
        return $this->calculateAnnualDepreciation() / 12;
    }

    public function calculateDepreciationForPeriod(string $startDate, string $endDate, ?float $productionUnits = null): float
    {
        if ($this->status !== 'active' || !$this->depreciation_start_date || $this->lifeMonths() <= 0) return 0.0;
        $start = \Carbon\Carbon::parse($startDate)->startOfDay();
        $end = \Carbon\Carbon::parse($endDate)->endOfDay();
        $assetStart = \Carbon\Carbon::parse($this->depreciation_start_date)->startOfDay();
        if ($end->lt($assetStart)) return 0.0;
        $effectiveStart = $start->max($assetStart);
        $lifeEnd = $assetStart->copy()->addMonths($this->lifeMonths())->subDay()->endOfDay();
        $effectiveEnd = $end->min($lifeEnd);
        if ($effectiveEnd->lt($effectiveStart)) return 0.0;

        $cost = (float)$this->purchase_cost;
        $salvage = min($cost, max(0.0, (float)$this->salvage_value));
        $days = $effectiveStart->diffInDays($effectiveEnd) + 1;
        $denominator = $effectiveStart->isLeapYear() ? 366 : 365;

        if ($this->depreciation_method === 'units_of_production') {
            if ($productionUnits === null || $productionUnits <= 0) return 0.0;
            $totalUnits = (float) ($this->total_estimated_units ?? 0);
            if ($totalUnits <= 0) return 0.0;
            $prior = (float) $this->opening_accumulated_depreciation + (float) static::query()->whereKey($this->id)->withSum(['depreciationEntries as accumulated_posted_depreciation' => fn($q) => $q->where('status','posted')->whereDate('period_end','<',$effectiveStart->toDateString())], 'amount')->value('accumulated_posted_depreciation');
            $remainingDepreciableBase = max(0.0, $cost - $salvage - $prior);
            return min($remainingDepreciableBase, round(($cost - $salvage) / $totalUnits * $productionUnits, 2));
        }

        if ($this->depreciation_method === 'declining_balance') {
            $annualRate = 24 / $this->lifeMonths();
            $prior = (float) $this->opening_accumulated_depreciation + (float) static::query()->whereKey($this->id)->withSum(['depreciationEntries as accumulated_posted_depreciation' => fn($q) => $q->where('status','posted')->whereDate('period_end','<',$effectiveStart->toDateString())], 'amount')->value('accumulated_posted_depreciation');
            $openingBook = max($salvage, $cost - $prior);
            return min(max(0.0, $openingBook - $salvage), round($openingBook * $annualRate * ($days / $denominator), 2));
        }

        $annual = max(0.0, $cost - $salvage) * 12 / $this->lifeMonths();
        $already = (float) $this->opening_accumulated_depreciation + (float) static::query()->whereKey($this->id)->withSum(['depreciationEntries as accumulated_posted_depreciation' => fn($q) => $q->where('status','posted')->whereDate('period_end','<',$effectiveStart->toDateString())], 'amount')->value('accumulated_posted_depreciation');
        return min(max(0.0, $cost - $salvage - $already), round(($annual / $denominator) * $days, 2));
    }

    public function getBookValueAttribute(): float
    {
        $cost = (float) $this->purchase_cost;
        if ($this->status !== 'active') {
            return 0.0;
        }
        $salvage = min($cost, max(0.0, (float) $this->salvage_value));
        $posted = (float) $this->opening_accumulated_depreciation + (float) $this->depreciationEntries()->where('status', 'posted')->sum('amount');
        return max($salvage, $cost - $posted);
    }

    public function getAccumulatedDepreciationAttribute(): float
    {
        return max(0.0, (float) $this->opening_accumulated_depreciation + (float) $this->depreciationEntries()->where('status', 'posted')->sum('amount'));
    }

    public function depreciationEntries()
    {
        return $this->hasMany(FixedAssetDepreciationEntry::class, 'fixed_asset_id');
    }
}
