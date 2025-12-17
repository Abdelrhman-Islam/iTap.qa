<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage; // Important for file handling
use Illuminate\Support\Str; 

class ProfileController extends Controller
{

public function index(Request $request): JsonResponse
    {
        $user = $request->user()->load(['employee.company', 'employee.department']);
        $userData = $user->toArray();
        if ($user->profile_image) {
            
            if (Str::startsWith($user->profile_image, ['http://', 'https://'])) {
                $userData['profile_image'] = $user->profile_image;
            } else {
                $userData['profile_image'] = asset('storage/' . $user->profile_image);
            }

        } else {
            $userData['profile_image'] = null;
        }

        if ($user->profile_video) {
            if (Str::startsWith($user->profile_video, ['http://', 'https://'])) {
                $userData['profile_video'] = $user->profile_video;
            } else {
                $userData['profile_video'] = asset('storage/' . $user->profile_video);
            }
        }

    // 3. باقي البيانات
    $userData['company_info'] = $user->employee ? $user->employee->company : null;
    $userData['department_info'] = $user->employee ? $user->employee->department : null;

    return response()->json($userData);
    }


    // 1. Show Public Profile (Existing method)
   public function showBySlug($slug)
    {
        // 1. Find User by Slug with relationships
        $user = User::where('profile_url_slug', $slug)
                ->with(['links.type', 'department', 'company']) 
                ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // 2. Increment the Taps Counter
        // This updates the 'taps_count' column in the database by 1 automatically.
        $user->increment('taps_count');

        return response()->json(['user' => $user]);
    }

    // 2. Upload Profile Image (New Method ðŸ†•)
/**
     * 2. UPLOAD PROFILE IMAGE
     * Uploads a new profile image and deletes the old one.
     */
    public function uploadImage(Request $request): JsonResponse
    {
        // A. Validation
        // Localization will happen automatically based on your middleware
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'], // Max 2MB
        ]);

        $user = $request->user();

        // B. Handle File Upload
        if ($request->hasFile('image')) {
            $disk = Storage::disk('public');
            $folder = 'avatars';

            // 🛑 SECURITY FIX: Ensure the directory exists to prevent "No such file" errors
            if (!$disk->exists($folder)) {
                $disk->makeDirectory($folder);
            }

            // 1. Delete old image if exists (Cleanup)
            if ($user->profile_image && $disk->exists($user->profile_image)) {
                $disk->delete($user->profile_image);
            }

            // 2. Store new image
            // This saves it to: storage/app/public/avatars
            $path = $request->file('image')->store($folder, 'public');

            // 3. Update User Record
            $user->update([
                'profile_image' => $path
            ]);

            // 4. Generate Full URL for response
            // This ensures the frontend gets a clickable link immediately
            $fullUrl = asset('storage/' . $path);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'data' => [
                    'image_url' => $fullUrl,
                    'path' => $path
                ]
            ]);
        }

        return response()->json(['message' => 'No image uploaded'], 400);
    }
    
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Validation
        $validated = $request->validate([
            'fName'            => ['nullable', 'string', 'max:255'],
            'mName'            => ['nullable', 'string', 'max:255'],
            'lName'            => ['nullable', 'string', 'max:255'],
            'phone_num'        => ['nullable', 'string', 'max:20'],
            'position'         => ['nullable', 'string', 'max:255'],
            'bio'              => ['nullable', 'string', 'max:1000'],
            'profile_language' => ['nullable', 'string', 'max:10'],
            'password'         => ['nullable', 'string', 'min:8'],
            'sex'              => ['nullable', ]
        ]);

        
        // fName formatting
        if ($request->has('fName') && $validated['fName']) {
            $validated['fName'] = ucfirst(strtolower(trim($validated['fName'])));
        }

        // mName formatting (بيقبل null عادي)
        if (array_key_exists('mName', $validated)) {
             $validated['mName'] = $validated['mName'] ? ucfirst(strtolower(trim($validated['mName']))) : null;
        }

        // lName formatting
        if ($request->has('lName') && $validated['lName']) {
            $validated['lName'] = ucfirst(strtolower(trim($validated['lName'])));
        }

        // 3. تحديث الباسورد (زي ما هو)
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        } else {
            unset($validated['password']);
        }

        // 4. التحديث النهائي
        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user
        ]);
    }
}