<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InformationRequest extends Model
{
    protected $fillable = ['complaint_id', 'requested_by', 'request_message', 'user_response', 'status', 'answered_at'];

    protected $casts = [
        'requested_at' => 'datetime',
        'answered_at' => 'datetime',
    ];

    public function complaint()
    {
        return $this->belongsTo(Complaint::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
