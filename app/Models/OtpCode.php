<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'otp_code',
        'expires_at',
        'type'
    ];

    // عشان اللارفيل يتعامل مع التاريخ صح
    protected $casts = [
        'expires_at' => 'datetime',
    ];
}