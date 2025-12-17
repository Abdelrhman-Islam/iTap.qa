<?php

// namespace App\Http\Controllers\Auth;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use App\Models\User;
// use App\Models\Company; 

// class ResendOtpController extends Controller
// {
    // public function __invoke(Request $request)
    // {
    //     $account = null;

    //     // الطريقة الأولى: لو باعت إيميل في الـ Body، ندور عليه في الداتابيز
    //     if ($request->has('email')) {
    //         $account = User::where('email', $request->email)->first();
            
    //         // لو ملقاش يوزر، وكنت بتستخدم موديل Company كمان، ممكن تدور هنا:
    //         // if (!$account) {
    //         //     $account = \App\Models\Company::where('email', $request->email)->first();
    //         // }
    //     }

    //     // الطريقة الثانية: لو مبعتش إيميل (أو الإيميل غلط)، نشوف هل باعت Token؟
    //     if (!$account) {
    //         $account = $request->user('sanctum');
    //     }

    //     // في الحالتين، لو المتغير لسه فاضي، نرجع إيرور
    //     if (!$account) {
    //         return response()->json(['message' => 'User not found or unauthenticated.'], 404);
    //     }

    //     // --- من هنا نفس الكود القديم ---

    //     // هل هو مفعل أصلاً؟
    //     if ($account->hasVerifiedEmail()) {
    //         return response()->json(['message' => 'Email already verified.'], 200);
    //     }

    //     // إرسال الكود الجديد
    //     $account->sendEmailVerificationNotification();

    //     return response()->json(['message' => 'Verification code resent successfully.']);
    // }
////////////////////////////////////////////
//     public function __invoke(Request $request)
// {
//     $account = null;

//     // 1. لو معاه توكين (Logged In)
//     if ($request->user('sanctum')) {
//         $account = $request->user('sanctum');
//     } 
//     // 2. لو ممعوش (Guest)، ندور بالإيميل
//     else {
//         $request->validate(['email' => 'required|email']);
        
//         // ندور في اليوزرز
//         $account = User::where('email', $request->email)->first();
        
//         // لو ملقيناهوش، ندور في الشركات
//         if (!$account) {
//             $account = Company::where('email', $request->email)->first();
//         }
//     }

//     if (!$account) {
//         return response()->json(['message' => 'User not found.'], 404);
//     }

//     // نكمل عادي...
//     if ($account->hasVerifiedEmail()) { // دلوقتي مستحيل تضرب Null
//         return response()->json(['message' => 'Email already verified.'], 200);
//     }

//     $account->sendEmailVerificationNotification();

//     return response()->json(['message' => 'Verification code resent successfully.']);
// }


// }



namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;

class ResendOtpController extends Controller
{
    public function __invoke(Request $request)
    {
        $account = null;

        // الحالة 1: لو هو مسجل دخول (معاه Token)
        if ($request->user('sanctum')) {
            $account = $request->user('sanctum');
        } 
        // الحالة 2: لو هو ضيف (Guest) - زي نسيان الباسورد
        else {
            $request->validate(['email' => 'required|email']);
            
            // ندور في الأفراد الأول
            $account = User::where('email', $request->email)->first();
            
            // لو مش موجود، ندور في الشركات
            if (!$account) {
                $account = Company::where('email', $request->email)->first();
            }
        }

        // لو ملقناش حد بالإيميل ده خالص
        if (!$account) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // لو الحساب مفعل أصلاً
        if ($account->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 200);
        }

        // إرسال الكود
        $account->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification code resent successfully.']);
    }
}