<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_material_id',
        'material_id', 'supplier_id', 'status', 'payment_status', 'paid_amount', 'remaining_amount', 'payment_account_id', 'attachment_path', 'created_by', 'confirmed_by', 'confirmed_at',
        'item_name',
        'item_type',
        'quantity',
        'unit',
        'unit_cost',
        'total_cost',
        'currency',
        'exchange_rate',
        'total_cost_syp',
        'purchase_date',
        'supplier',
        'invoice_number',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
        'quantity' => 'decimal:2',
        'exchange_rate' => 'decimal:2',
        'total_cost_syp' => 'decimal:2',
        'purchase_date' => 'date',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }

    public function rawMaterial()
    {
        return $this->belongsTo(RawMaterial::class);
    }

    public function payments()
    {
        return $this->hasMany(FinancialPayment::class);
    }
}
