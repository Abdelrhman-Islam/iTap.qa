<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class AppleAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string'
        ]);

        // 1. Get Apple public keys
        $keys = Http::get('https://appleid.apple.com/auth/keys')->json();
        $publicKeys = JWK::parseKeySet($keys);

        // 2. Decode token
        try {
            $payload = JWT::decode($request->id_token, $publicKeys);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invalid Apple token',
            ], 401);
        }

        // 3. Validate claims
        if ($payload->iss !== 'https://appleid.apple.com') {
            return response()->json(['message' => 'Invalid issuer'], 401);
        }

        if ($payload->aud !== config('services.apple.client_id')) {
            return response()->json(['message' => 'Invalid audience'], 401);
        }

        // 4. User data
        $appleId = $payload->sub;
        $email   = $payload->email ?? null;

        // 5. Find or Create user
        $user = User::where('provider', 'apple')
            ->where('provider_id', $appleId)
            ->first();

        if (!$user) {
            $user = User::create([
                'type'              => "Individual",
                'email'        => $email,
                'provider'     => 'apple',
                'provider_id'  => $appleId,
                'status'       => 'active',
                'password'     => null,
                'email_verified_at' => now(),
                'status'            => 'active',
                'taps_count'        => 0,
                'leads_count'       => 0,
                'contacts_count'    => 0,
            ]);
        }

        // 6. Create Sanctum token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Authenticated successfully',
            'token'   => $token,
            'user'    => $user,
        ]);
    }
}
