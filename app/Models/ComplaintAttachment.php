<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplaintAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'complaint_id', // إذا كنت تخزن المرفق مرتبط بشكوى
        'path',         // مسار الملف
        'type',         // نوع المرفق إذا موجود
        'mime',
    ];
}
