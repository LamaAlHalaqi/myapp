<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agency extends Model
{
    use HasFactory;

    // الأعمدة القابلة للتعبئة
    protected $fillable = [
        'name',
    ];

    // علاقة الشكاوى مع الجهة
    public function complaints()
    {
        return $this->hasMany(Complaint::class);
    }
}
