<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class SocialAuthController extends Controller
{

    public function login(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string'
        ]);

        // ✅ Verify token with Google
        $googleResponse = Http::get(
            'https://oauth2.googleapis.com/tokeninfo',
            ['id_token' => $request->id_token]
        );
        $googleUser = $googleResponse->json();
        
        if (($googleUser['aud'] ?? null) != config('services.google.client_id')) {
            return response()->json([
                'message' => 'Invalid token audience'
            ], 401);
        }


        if ($googleResponse->failed()) {
            return response()->json([
                'message' => 'Invalid Google token'
            ], 401);
        }


        /*
         $googleUser contains:
         - sub (google_id)
         - email
         - email_verified
         - name
         - picture
        */

        // ✅ Optional extra checks
        if (!($googleUser['email_verified'] ?? false)) {
            return response()->json([
                'message' => 'Email not verified'
            ], 403);
        }

        // ✅ Find user
        $user = User::where('email', $googleUser['email'], 'provider', 'google')->first();

        if (!$user) {
            // ✅ Register new user
            // $user = User::create([
            //     'type'              => "Individual",
            //     'fName'             => $googleUser['name'] ?? 'Google User',
            //     'email'             => $googleUser['email'],
            //     'google_id'         => $googleUser['sub'],
            //     'email_verified_at' => now(),
            //     'password'          => bcrypt(Str::random(32)), // dummy password
            //     'avatar'            => $googleUser['picture'] ?? null,
            //     'status'            => 'active',
            //     'provider'          => 'google',
            //     'taps_count'        => 0,
            //     'leads_count'       => 0,
            //     'contacts_count'    => 0,
            //     ]);

            return response()->json(['User Not Found'], 404);
        }

        // ✅ Create API token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Login success',
            'token'   => $token,
            'user'    => $user
        ]);
        
        return response()->json([
            'aud' => $googleUser['aud'],
            'expected' => config('services.google.client_id')
        ]);

    }

    public function update(Request $request): JsonResponse
{
    // ✅ المستخدم جاي من التوكين
    $user = $request->user();

    // (اختياري) Validation
    // $request->validate([
    //     'fName'     => 'required|string|max:255',
    //     'lName'     => 'nullable|string|max:255',
    //     'phone_num' => 'required|string|max:20',
    // ]);

    // 1. Prepare Data
    $fName = $request->fName;
    $mName = $request->mName;
    $lName = $request->lName;
    $phone_num = $request->phone_num;

    // 2. Format Name
    $formattedFName = ucfirst(strtolower(trim($fName)));
    $formattedMName = $mName ? ucfirst(strtolower(trim($mName))) : null;
    $formattedLName = ucfirst(strtolower(trim($lName)));

    // 3. Slug
    $cleanPhone = preg_replace('/\D/', '', $phone_num);
    $lastFiveDigits = strlen($cleanPhone) >= 5
        ? substr($cleanPhone, -5)
        : rand(10000, 99999);

    $customSlug = $formattedFName . $formattedMName . $formattedLName . '-' . $lastFiveDigits;

    if (User::where('profile_url_slug', $customSlug)->where('id', '!=', $user->id)->exists()) {
        $customSlug .= '-' . rand(1, 99);
    }

    // ✅ Update المستخدم نفسه
    $user->update([
        'fName'            => $formattedFName,
        'mName'            => $formattedMName,
        'lName'            => $formattedLName,
        'status'           => $request->status ?? 'active',
        'phone_num'        => $phone_num,
        'profile_url_slug' => $customSlug,
        'sex'              => $request->sex,
        'age'              => $request->age,
        'password'         => Hash::make($request->password),
    ]);

    // ✅ رجوع الـ ID جاي من التوكين
    return response()->json([
        'message' => 'Profile updated successfully',
        'user_id' => $user->id,
        'user'    => $user
    ], 200);
}


    // "fName"
    // "mName"
    // "lName"
    // "status"
    // "password"
    // "phone_num"
    // "sex"
    // "age"
}