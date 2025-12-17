<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use App\Models\OtpCode; // 👈 لازم نستخدم الموديل الجديد
use Illuminate\Auth\Events\Verified;

class VerifyOtpController extends Controller
{
    public function __invoke(Request $request)
    {
        // 1. تحديد المستخدم (سواء بتوكين أو بإيميل)
        $account = null;
        $columnName = 'user_id'; // الافتراضي

        if ($request->user('sanctum')) {
            $account = $request->user('sanctum');
            // لو هو شركة، نغير اسم العمود
            if ($account instanceof Company) {
                $columnName = 'company_id';
            }
        } else {
            // لو زائر (نسي الباسورد مثلاً)
            $request->validate(['email' => 'required|email']);
            
            $account = User::where('email', $request->email)->first();
            if (!$account) {
                $account = Company::where('email', $request->email)->first();
                $columnName = 'company_id';
            }
        }

        if (!$account) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // 2. التحقق من الكود والـ Purpose
        $request->validate([
            'otp' => 'required|string',
            'purpose' => 'nullable|string|in:email_verification,reset_password'
        ]);

        $purpose = $request->purpose ?? 'email_verification';

        $otpRecord = OtpCode::where($columnName, $account->id)
                            ->where('otp_code', $request->otp)
                            ->where('type', $purpose) 
                            ->first();

        if (!$otpRecord) {
            return response()->json(['message' => 'Invalid verification code.'], 400);
        }

        if ($otpRecord->expires_at < now()) {
            return response()->json(['message' => 'Verification code has expired.'], 400);
        }

        // 4. تنفيذ المطلوب
        if ($purpose === 'reset_password') {
            // حالة استعادة الباسورد: نرجع OK بس (والفرونت يبعت الكود تاني مع الباسورد الجديد)
            return response()->json(['message' => 'OTP is valid.'], 200);
        } 
        else {
            // حالة تفعيل الإيميل: نفعل الحساب فوراً
            if (!$account->hasVerifiedEmail()) {
                $account->markEmailAsVerified();
                event(new Verified($account));            }

            // 🗑️ نمسح الكود عشان ميتعملوش Reuse
            $otpRecord->delete();

            return response()->json(['message' => 'Email verified successfully.'], 200);
        }
    }
}