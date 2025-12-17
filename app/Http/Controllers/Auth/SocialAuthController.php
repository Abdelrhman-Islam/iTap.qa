<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\UserSocialAccount; 

class SocialAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        // 1. Verify with Google
        $googleUser = $this->verifyGoogleToken($request->id_token);
        if (!$googleUser) {
            return response()->json(['message' => 'Invalid Google Token'], 401);
        }

        $socialAccount = UserSocialAccount::where('provider', 'google')
                                          ->where('provider_id', $googleUser['sub'])
                                          ->first();

        $user = $socialAccount ? $socialAccount->user : null;

        if (!$user) {
            $user = User::where('email', $googleUser['email'])->first();

            if ($user) {
                UserSocialAccount::create([
                    'user_id' => $user->id,
                    'provider' => 'google',
                    'provider_id' => $googleUser['sub'],
                    'avatar_url' => $googleUser['picture'] ?? null
                ]);
            }
        }

        if ($user) {
            $token = $user->createToken('api-token')->plainTextToken;
            return response()->json([
                'status' => 'login_success',
                'message' => 'Login success',
                'token' => $token,
                'user' => $user
            ]);
        } else {
            $names = explode(' ', $googleUser['name'], 2);
            return response()->json([
                'status' => 'register_required',
                'message' => 'User not found, please complete registration',
                'google_data' => [
                    'email' => $googleUser['email'],
                    'fName' => $names[0],
                    'lName' => $names[1] ?? '',
                    'avatar' => $googleUser['picture'] ?? null,
                    'google_id' => $googleUser['sub'] 
                ]
            ], 202); 
        }
    }
    public function store(Request $request): JsonResponse
    {
        // 1. Validate Form Data + Token again (Security)
        $request->validate([
            'id_token' => 'required|string',
            'phone_num' => 'required',
            'password' => 'required|min:6',
            'fName' => 'required',
            'lName' => 'required',
        ]);

        // 2. Verify Token Again
        $googleUser = $this->verifyGoogleToken($request->id_token);
        if (!$googleUser) return response()->json(['message' => 'Invalid Token'], 401);

        // 3. Double Check Existence
        if (User::where('email', $googleUser['email'])->exists()) {
            return response()->json(['message' => 'Email already taken'], 422);
        }

        $formattedFName = ucfirst(strtolower(trim($request->fName)));
        $formattedMName = $request->mName ? ucfirst(strtolower(trim($request->mName))) : null;
        $formattedLName = ucfirst(strtolower(trim($request->lName)));
        
        $cleanPhone = preg_replace('/\D/', '', $request->phone_num);
        $lastFiveDigits = strlen($cleanPhone) >= 5 ? substr($cleanPhone, -5) : rand(10000, 99999);
        $customSlug = $formattedFName . $formattedMName . $formattedLName . '-' . $lastFiveDigits;
        
        if (User::where('profile_url_slug', $customSlug)->exists()) {
            $customSlug .= '-' . rand(1, 99);
        }

        $user = User::create([
            'type' => "individual", // Small letter enum
            'email' => $googleUser['email'],
            'email_verified_at' => now(),
            'password' => Hash::make($request->password),
            'fName' => $formattedFName,
            'mName' => $formattedMName,
            'lName' => $formattedLName,
            'phone_num' => $request->phone_num,
            'profile_url_slug' => $customSlug,
            'sex' => $request->sex,
            'age' => $request->age,
            'profile_image' => $googleUser['picture'] ?? null,
        ]);

        // 6. Create Social Link 
        UserSocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_id' => $googleUser['sub'],
            'avatar_url' => $googleUser['picture'] ?? null
        ]);

        // 7. Login & Return Token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'status' => 'login_success',
            'message' => 'Profile Created successfully',
            'token' => $token,
            'user' => $user
        ], 201);
    }

    // -------------------------------------------------------------------------
    // Helper Function
    // -------------------------------------------------------------------------
    private function verifyGoogleToken($token)
    {
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $token]);
        
        if ($response->failed()) return null;
        
        $data = $response->json();
        
        // Audience Check
        if (($data['aud'] ?? null) != config('services.google.client_id')) {
            return null;
        }

        return $data;
    }
}