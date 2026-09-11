<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'product_id',
        'sku',
        'name',
        'size_label',
        'volume_ml',
        'bottle_shape',
        'image_url',
        'image_is_reference',
        'selling_price_default',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'volume_ml' => 'decimal:2',
        'selling_price_default' => 'decimal:2',
        'is_active' => 'boolean',
        'image_is_reference' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function activeRecipe()
    {
        return $this->recipes()->where('is_active', true)->latest();
    }
}
