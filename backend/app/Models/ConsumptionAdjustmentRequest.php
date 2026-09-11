<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsumptionAdjustmentRequest extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id','order_consumption_id','order_consumption_item_id','material_id',
        'old_actual_qty','requested_actual_qty','reason','status',
        'requested_by','reviewed_by','reviewed_at','review_note',
    ];
    protected $casts = [
        'old_actual_qty' => 'decimal:4',
        'requested_actual_qty' => 'decimal:4',
        'reviewed_at' => 'datetime',
    ];
    public function item(){ return $this->belongsTo(OrderConsumptionItem::class, 'order_consumption_item_id'); }
    public function material(){ return $this->belongsTo(Material::class); }
    public function requester(){ return $this->belongsTo(User::class, 'requested_by'); }
    public function reviewer(){ return $this->belongsTo(User::class, 'reviewed_by'); }
}
