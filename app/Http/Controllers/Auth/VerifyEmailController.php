<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\JsonResponse; // 💡 هام
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Company;


class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    // public function __invoke(EmailVerificationRequest $request): JsonResponse // 💡 نرجع JSON
    // {
    //     // 1. إذا كان مفعل سابقاً
    //     if ($request->user()->hasVerifiedEmail()) {
    //         return response()->json(['message' => 'Email already verified.'], 200);
    //     }

    //     // 2. تفعيل الإيميل
    //     if ($request->user()->markEmailAsVerified()) {
    //         event(new Verified($request->user()));
    //     }

    //     // 3. رسالة نجاح بدلاً من Redirect
    //     return response()->json(['message' => 'Email verified successfully.'], 200);
    // }

    public function __invoke(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $modelClass = ($type === 'company' ) ? Company::class : User::class;

        $user = $modelClass::find($request->route('id'));

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid hash'], 403);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.'], 200);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json(['message' => 'Email verified successfully.'], 200);
    }
}