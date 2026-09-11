<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id','user_role','action','method','route','entity_type','entity_id','status','status_code',
        'reason','before_data','after_data','changes','request_data','response_data','ip_address','user_agent','occurred_at'
    ];
    protected $casts = [
        'request_data'=>'array','response_data'=>'array','before_data'=>'array','after_data'=>'array','changes'=>'array','occurred_at'=>'datetime'
    ];
    public function user() { return $this->belongsTo(User::class); }
}
