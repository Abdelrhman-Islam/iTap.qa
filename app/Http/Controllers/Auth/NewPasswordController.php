<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use App\Models\User;
use App\Models\Company;
use App\Models\OtpCode; // ✅ استدعاء الموديل الجديد
use Illuminate\Support\Str;

class NewPasswordController extends Controller
{
    /**
     * Handle an incoming new password request.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. التحقق من المدخلات
        $request->validate([
            'email' => ['required', 'email'], // شيلنا exists:users عشان ندعم الشركات كمان
            'otp'   => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // 2. البحث عن الحساب (فرد أو شركة)
        $account = User::where('email', $request->email)->first();
        $columnName = 'user_id'; // الافتراضي

        if (!$account) {
            $account = Company::where('email', $request->email)->first();
            $columnName = 'company_id';
        }

        if (!$account) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // 3. البحث في جدول الأكواد الجديد (OtpCode)
        $otpRecord = OtpCode::where($columnName, $account->id)
                            ->where('otp_code', $request->otp)
                            ->where('type', 'reset_password') // ✅ لازم نتأكد إنه كود تغيير باسورد
                            ->first();

        // 4. فحوصات الأمان للكود
        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid OTP code.'], 400);
        }

        if ($otpRecord->expires_at < now()) {
            return response()->json(['message' => 'OTP code has expired. Please request a new one.'], 400);
        }

        // 5. تغيير الباسورد
        $account->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        // 6. مسح الكود من جدول الـ OTP عشان ميتسخدمش تاني
        $otpRecord->delete();

        return response()->json(['message' => 'Password has been reset successfully.']);
    }
}