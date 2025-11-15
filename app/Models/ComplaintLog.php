<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ComplaintLog extends Model {
    protected $fillable = ['complaint_id','user_id','action','old_value','new_value','note'];
    protected $casts = ['old_value'=>'array','new_value'=>'array'];
    public function complaint() { return $this->belongsTo(Complaint::class); }
    public function user() { return $this->belongsTo(User::class); }
}
