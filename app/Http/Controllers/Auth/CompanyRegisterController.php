<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Company;

use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse; 
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Str;

class CompanyRegisterController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $existingCompany = Company::where('email', $request->email)->first();

        if ($existingCompany) {
            if ($existingCompany->hasVerifiedEmail()) {
                return response()->json([
                    'message' => 'The email has already been taken.',
                    'errors' => ['email' => ['The email has already been taken.']]
                ], 422);
            }

            $existingCompany->update([
                'name' => ucfirst(strtolower(trim($request->name))),
                'website' => $request->website,
                'password' => Hash::make($request->password),
            ]);

            // إرسال الـ OTP
            $existingCompany->sendEmailVerificationNotification();

            // دخول مباشر
            $token = $existingCompany->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Company already exists but not verified. New OTP sent.',
                'company' => $existingCompany,
                'token' => $token
            ], 200);
        }
 
        // 2. Validation 
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'], 
            'website' => ['nullable', 'string', 'max:255'],
            'password' => ['required', Rules\Password::defaults()],

            'employees_size' => ['nullable', 'string'],
            'main_reason' => ['nullable', 'string'],
            'booked_date' => ['nullable', 'date'],
        ]);
        
        $formattedName = ucfirst(strtolower(trim($request->name))); 
        
        // 3. Create Company
        $company = Company::create([
            'name' => $formattedName,
            'email' => $request->email,
            'website' => $request->website,
            'password' => Hash::make($request->password),
            'employees_size' => $request->employees_size,
            'main_reason'    => $request->main_reason,
            'booked_date'    => $request->booked_date,
        ]);

        $company->sendEmailVerificationNotification();

        // 4. Generate Token
        $token = $company->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Company registered successfully',
            'company' => $company,
            'token' => $token
        ], 201);
    }
}
