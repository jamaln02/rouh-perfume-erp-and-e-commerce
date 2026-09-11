<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinishedProductInventory extends Model
{
    use HasFactory;

    protected $table = 'finished_products_inventory';

    protected $fillable = [
        'product_id',
        'size_type',
        'current_stock',
        'min_stock',
        'cost_per_unit',
        'selling_price',
        'notes',
    ];

    protected $casts = [
        'cost_per_unit' => 'decimal:2',
        'selling_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function isLowStock()
    {
        return $this->current_stock <= $this->min_stock;
    }

    public function getProfitMargin()
    {
        if ($this->selling_price > 0) {
            return (($this->selling_price - $this->cost_per_unit) / $this->selling_price) * 100;
        }
        return 0;
    }
}
