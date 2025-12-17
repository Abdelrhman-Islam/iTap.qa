<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user && Hash::check($request->password, $user->password)) {

            $token = $user->createToken('user_token')->plainTextToken;
            
            return response()->json([
                'message' => 'Login successful',
                'account_type' => $user->type,
                'data' => $user,
                'token' => $token
            ]);
        }

        $company = Company::where('email', $request->email)->first();

        if ($company && Hash::check($request->password, $company->password)) {
            $token = $company->createToken('company_token')->plainTextToken;

            return response()->json([
                'message' => 'Login successful',
                'account_type' => 'company', 
                'data' => $company,
                'token' => $token
            ]);
        }

        // throw ValidationException::withMessages([
        //     'email' => ['These credentials do not match our records.'],
        // ]);
        return response()->json([
            'message' => 'Incorrect email or password'
        ], 400);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }
}