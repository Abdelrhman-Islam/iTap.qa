<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\VerifyEmailWithOtp;

class Company extends Authenticatable implements MustVerifyEmail{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'website',
        'password',
        'logo',
        'employees_size',
        'main_reason',
        'booked_date',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
    ];

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function employees()
    {
        return $this->hasMany(User::class)->where('type', 'Employee');
    }
    protected function logo(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value) => $value ? asset('storage/' . $value) : null,
        );
    }


    public function sendEmailVerificationNotification()
    {
        $code = rand(100000, 999999);

        // 2. تسجيله في الداتا بيز
        OtpCode::updateOrCreate(
            ['company_id' => $this->id, 'type' => 'email_verification'],
            [
                'otp_code' => $code,
                'user_id' => null, 
                'expires_at' => now()->addMinutes(10)
            ]
        );
        $this->notify(new VerifyEmailWithOtp($code));
    }
}