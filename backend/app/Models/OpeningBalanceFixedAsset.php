<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningBalanceFixedAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'opening_balance_id','fixed_asset_id','name','name_ar','category','quantity','unit_cost',
        'useful_life_months','in_service_date','salvage_value','accumulated_depreciation','notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:6','unit_cost' => 'decimal:6','salvage_value' => 'decimal:2','accumulated_depreciation' => 'decimal:2',
        'in_service_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model): void {
            $model->total_value = round((float) $model->quantity * (float) $model->unit_cost, 2);
        });
    }

    public function openingBalance(): BelongsTo { return $this->belongsTo(OpeningBalance::class); }
    public function fixedAsset(): BelongsTo { return $this->belongsTo(FixedAsset::class); }
}
