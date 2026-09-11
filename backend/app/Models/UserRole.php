<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserRole extends Model {
    protected $table = 'user_roles';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['user_id','role'];
}
