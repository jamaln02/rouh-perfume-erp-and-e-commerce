<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MaterialSubstitution
 *
 * Audit record for every raw-material substitution performed during order
 * preparation. Links the order consumption, the original material that was
 * expected, and the replacement material that was actually used.
 */
class MaterialSubstitution extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_consumption_id',
        'order_id',
        'original_material_id',
        'replacement_material_id',
        'original_qty',
        'replacement_qty',
        'unit',
        'reason',
        'performed_by',
    ];

    protected $casts = [
        'original_qty' => 'decimal:4',
        'replacement_qty' => 'decimal:4',
    ];

    public function originalMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'original_material_id');
    }

    public function replacementMaterial(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'replacement_material_id');
    }

    public function consumption(): BelongsTo
    {
        return $this->belongsTo(OrderConsumption::class, 'order_consumption_id');
    }
}
