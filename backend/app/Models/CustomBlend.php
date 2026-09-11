<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomBlend extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'description_ar',
        'is_public',
        'created_by_admin_id',
        'base_cost_per_bottle',
        'packaging_cost_per_bottle',
        'total_cost_per_bottle',
        'selling_price',
        'markup_percentage',
        'bottle_size_ml',
        'is_active',
    ];

    protected $casts = [
        'base_cost_per_bottle' => 'decimal:2',
        'packaging_cost_per_bottle' => 'decimal:2',
        'total_cost_per_bottle' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'markup_percentage' => 'decimal:2',
        'is_public' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function recipes()
    {
        return $this->hasMany(BlendRecipe::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function calculateSellingPrice(): void
    {
        $this->selling_price = $this->total_cost_per_bottle * (1 + ($this->markup_percentage / 100));
        $this->save();
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true)->where('is_active', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}