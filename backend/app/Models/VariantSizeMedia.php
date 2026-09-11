<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariantSizeMedia extends Model
{
    protected $table = 'variant_size_media';

    protected $fillable = [
        'size_key',
        'size_label',
        'image_url',
        'image_is_reference',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'image_is_reference' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
