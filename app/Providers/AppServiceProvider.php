<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use App\Models\Company;


class AppServiceProvider extends ServiceProvider
{
    // /**
    //  * Register any application services.
    //  */
    // public function register(): void
    // {
    //     //
    // }

    // /**
    //  * Bootstrap any application services.
    //  */
    // public function boot(): void
    // {
    //     //
    // }

    public function boot(): void
    {
        // تخصيص رابط التفعيل
        VerifyEmail::createUrlUsing(function ($notifiable) {
            
            // تحديد النوع (شركة أم فرد)
            $type = ($notifiable instanceof Company) ? 'company' : 'user';
    
            // بناء الرابط مع إضافة parameter الـ type
            return URL::temporarySignedRoute(
                'verification.verify', // اسم الراوت
                Carbon::now()->addMinutes(60), // مدة الصلاحية
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                    'type' => $type // ✅ ده السحر اللي هيحل المشكلة
                ]
            );
        });
        // تخصيص رابط استعادة كلمة المرور
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            
            // هذا الرابط هو الذي سيصل للمستخدم في الإيميل
            // المفروض يوديه على صفحة في الفرونت إند (React/Flutter)
            // سنفترض أن الفرونت موجود على نفس الدومين أو صب دومين
            
            return "https://app.itap.qa/reset-password?token={$token}&email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
