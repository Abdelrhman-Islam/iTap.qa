<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Str;

class RegisteredUserController extends Controller
{
    public function storeIn(Request $request): JsonResponse
    {
        // 1. Check if user exists
        $existingUser = User::where('email', $request->email)->first();

        if ($existingUser) {
            // Case A: User exists and is verified -> Reject
            if ($existingUser->hasVerifiedEmail()) {
                return response()->json([
                    'message' => 'The email has already been taken.',
                    'errors' => [
                        'email' => ['The email has already been taken.']
                    ]
                ], 422);
            }

            // Case B: User exists but NOT verified -> Update & Resend OTP
            
            // Format Names
            $formattedFName = ucfirst(strtolower(trim($request->fName)));
            $formattedMName = $request->mName ? ucfirst(strtolower(trim($request->mName))) : null;
            $formattedLName = ucfirst(strtolower(trim($request->lName)));
            
            $existingUser->update([
                'fName' => $formattedFName,
                'mName' => $formattedMName,
                'lName' => $formattedLName,
                'password' => Hash::make($request->password),
                'phone_num' => $request->phone_num,
                'sex' => $request->sex,
                'age' => $request->age,
                // Update other optional fields if provided
                // 'position' => $request->position,
                // 'bio' => $request->bio,
                // 'role' => $request->role,
                // ... add other fields as needed
            ]);

            // Resend OTP
            $existingUser->sendEmailVerificationNotification();

            // Create Token
            $token = $existingUser->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'User already exists but not verified. New verification code sent.',
                'user' => $existingUser,
                'token' => $token
            ], 200);
        }

        // 2. Validation (New User)
        $request->validate([
            'fName' => ['required', 'string', 'max:255'],
            'mName' => ['nullable', 'string', 'max:255'],
            'lName' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'], // Removed 'unique' since we check manually above
            'password' => ['required', Rules\Password::defaults()],
            'phone_num' => ['required', 'string', 'max:20'],
            
            // 'status' => ['nullable', 'string', 'max:50'],
            'sex' => ['nullable', 'string', 'max:20'],
            'age' => ['nullable', 'string', 'max:20'],

        ]);

        // 3. Slug Generation Logic
        $fName = $request->fName;
        $mName = $request->mName;
        $lName = $request->lName;
        $phone_num = $request->phone_num;

        // Format Name
        $formattedFName = ucfirst(strtolower(trim($fName))); 
        $formattedMName = $mName ? ucfirst(strtolower(trim($mName))) : ''; 
        $formattedLName = ucfirst(strtolower(trim($lName))); 

        // Safe phone extraction
        $cleanPhone = preg_replace('/\D/', '', $phone_num);
        $lastFiveDigits = (strlen($cleanPhone) >= 5) ? substr($cleanPhone, -5) : rand(10000, 99999);

        // Create Slug with Middle Name
        $customSlug = $formattedFName . $formattedMName . $formattedLName . '-' . $lastFiveDigits;

        // Unique Check
        if (User::where('profile_url_slug', $customSlug)->exists()) {
            $customSlug .= '-' . rand(1, 99);
        }

        // 4. Create User
        $user = User::create([
            'type' => "individual",
            'fName' => $formattedFName,
            'mName' => $formattedMName,
            'lName' => $formattedLName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone_num' => $phone_num,
            // Generated Slug
            'profile_url_slug' => $customSlug, 
            'sex' => $request->sex,
            'age' => $request->age,
        ]);

        // Send OTP
        $user->sendEmailVerificationNotification();

        // 5. Generate Token
        $token = $user->createToken('auth_token')->plainTextToken;
        $fullUrl = 'https://iTap.qa/' . $customSlug; // Removed 'in.' based on your recent preference

        // 6. Return JSON Response
        return response()->json([
            'message' => 'User registered successfully',
            'user' => $user,
            'profile_url' => $fullUrl,
            'token' => $token
        ], 201);
    }
}