<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EssentialOil extends Model
{
    use HasFactory;

    protected $table = 'essential_oils';

    protected $fillable = [
        'name',
        'name_ar',
        'price_per_gram',
        'container_type',
        'current_stock_grams',
        'min_stock_grams',
        'supplier',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'price_per_gram' => 'decimal:2',
        'current_stock_grams' => 'decimal:2',
        'min_stock_grams' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function isLowStock(): bool
    {
        return $this->current_stock_grams <= $this->min_stock_grams;
    }
}