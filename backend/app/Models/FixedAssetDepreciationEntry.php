<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class FixedAssetDepreciationEntry extends Model {
    use HasFactory;
    protected $fillable = ['fixed_asset_id','period_start','period_end','amount',
        'production_units','method','status','created_by','notes'];
    protected $casts = ['amount'=>'decimal:2','production_units'=>'decimal:4','period_start'=>'date','period_end'=>'date'];
    public function asset() { return $this->belongsTo(FixedAsset::class, 'fixed_asset_id'); }
    public function journal() { return $this->belongsTo(JournalEntry::class, 'journal_entry_id'); }
}
