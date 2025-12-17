<?php

namespace App\Models;

use App\Models\OtpCode;
use App\Models\Employee;
use App\Notifications\VerifyEmailWithOtp;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens; 

class User extends Authenticatable implements MustVerifyEmail{
// class User extends Model{
    use HasApiTokens, HasFactory, Notifiable; 

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
        'fName',
        'mName',
        'lName',
        'email',
        'email_verified_at',
        'password',
        'sex',
        'age',
        'phone_num',
        'profile_url_slug',
        'profile_image',
        'profile_video',
        'bio',
        'profile_language',
    ];
    // This relationship is very important for getting employee links
    public function links()
    {
        return $this->hasMany(ProfileLink::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    public function sendEmailVerificationNotification()
        {
            $code = rand(100000, 999999);

            OtpCode::where('user_id', $this->id)
                ->where('type', 'email_verification')
                ->delete();

            OtpCode::create([
                'user_id' => $this->id,
                'company_id' => null, 
                'otp_code' => $code,
                'expires_at' => now()->addMinutes(10), 
                'type' => 'email_verification'
            ]);

            $this->notify(new VerifyEmailWithOtp($code));
        }

        public function statistics()
        {
            // بنقول إن اليوزر عنده سجل إحصائيات واحد
            return $this->hasOne(\App\Models\UserStatistic::class);
        }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
