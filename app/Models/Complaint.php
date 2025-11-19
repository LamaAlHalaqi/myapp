<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model {
    protected $fillable = [
        'reference','user_id','agency_id','type','location','description',
        'status','is_locked','locked_by','locked_at'
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function agency() { return $this->belongsTo(Agency::class); }
    public function attachments() { return $this->hasMany(ComplaintAttachment::class); }
    public function logs() { return $this->hasMany(ComplaintLog::class); }
}
