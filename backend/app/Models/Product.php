<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Product extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'name_ar',
        'description',
        'description_ar',
        'price',
        'image_url',
        'image_source_url',
        'category_id',
        'fragrance',
        'top_notes',
        'heart_notes',
        'base_notes',
        'fragrance_family',
        'sizes',
        'size_prices',
        'featured',
        'is_new',
        'best_seller',
        'stock',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'sizes' => 'array',
        'size_prices' => 'array',
        'top_notes' => 'array',
        'heart_notes' => 'array',
        'base_notes' => 'array',
        'featured' => 'boolean',
        'is_new' => 'boolean',
        'best_seller' => 'boolean',
        'stock' => 'integer',
    ];

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function materialMappings(): HasMany
    {
        return $this->hasMany(ProductMaterialMapping::class);
    }

    public function recipes(): HasManyThrough
    {
        return $this->hasManyThrough(Recipe::class, ProductVariant::class, 'product_id', 'product_variant_id', 'id', 'id');
    }

    public function getDefaultRecipeForSize(?string $size = null)
    {
        if ($size) {
            $variant = $this->variants()->where('size_label', $size)->first();
            if ($variant) {
                return Recipe::where('product_variant_id', $variant->id)
                    ->where('is_active', true)
                    ->first();
            }
        }
        
        // Do not fall back to an arbitrary variant. A product can have multiple
        // sizes, each with a different recipe; returning the first one can silently
        // apply the wrong formulation.
        return null;
    }
}
