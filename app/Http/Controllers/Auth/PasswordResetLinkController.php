<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;
use App\Models\OtpCode;
use App\Notifications\ResetPasswordWithOtp;

class PasswordResetLinkController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $account = null;

        // 1. تحديد المستخدم (Token or Email)
        if ($request->bearerToken()) {
            $account = $request->user('sanctum');
        }

        if (!$account) {
            $request->validate(['email' => ['required', 'email']]);
            $account = User::where('email', $request->email)->first();
            if (!$account) {
                $account = Company::where('email', $request->email)->first();
            }
        }

        if (!$account) {
            return response()->json([
                'message' => 'We can\'t find a user with that email address.',
                'errors' => ['email' => ['User not found']]
            ], 404);
        }

        // 2. 🔥 توليد الكود وحفظه في الداتا بيز
        $code = rand(100000, 999999);

        // تحديد هوية المستخدم لحفظها في الجدول
        $userId = ($account instanceof User) ? $account->id : null;
        $companyId = ($account instanceof Company) ? $account->id : null;

        OtpCode::updateOrCreate(
            [
                'user_id' => $userId,
                'company_id' => $companyId,
                'type' => 'reset_password' // 👈 نوع مختلف عن التفعيل
            ],
            [
                'otp_code' => $code,
                'expires_at' => now()->addMinutes(15)
            ]
        );

        // 3. إرسال الإيميل (تمرير الكود للنوتيفيكيشن)
        $account->notify(new ResetPasswordWithOtp($code));

        return response()->json([
            'status' => 'OTP sent successfully.',
            'target_email' => $account->email
        ]);
    }
}