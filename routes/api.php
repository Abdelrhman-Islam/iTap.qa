[<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Controllers Imports
use App\Http\Controllers\Controller;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyCardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LinkTypesController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ProfileLinksController;
use App\Http\Controllers\EmailSignatureController;
use App\Http\Controllers\NfcInventoryController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\AdminCardController;
// Auth Controllers Imports
use App\Http\Controllers\Auth\VerifyOtpController;
use App\Http\Controllers\Auth\ResendOtpController;
use App\Http\Controllers\Auth\AppleAuthController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\CompanyRegisterController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// ==========================
// 1. Public Routes (No Auth)
// ==========================

// --- Registration ---
// Individual Account Creation
Route::post('/in.register', [RegisteredUserController::class, 'storeIn'])->name('registerIn');
// Company Account Creation
Route::post('/co.register', [CompanyRegisterController::class, 'register'])->name('registerCo');

// --- Authentication ---
Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');

// --- Password Reset ---
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
    // ->middleware('guest')
    ->name('password.email');

Route::post('/reset-password', [NewPasswordController::class, 'store'])
    ->middleware('guest')
    ->name('password.store');
// تفعيل بالكود (OTP)
Route::post('/verify-otp', VerifyOtpController::class);

// Social Login (Google & Apple)
Route::post('/auth/google', [SocialAuthController::class, 'login']);

Route::post('auth/google/register', [SocialAuthController::class, 'store']);

Route::post('/auth/apple', [AppleAuthController::class, 'login']);



// --- Email Verification ---
// Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
//             ->middleware(['signed', 'throttle:6,1'])
//             ->name('verification.verify');


// ==========================
// 2. Protected Routes (Need Token)
// ==========================
Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware(['verified'])->group(function () {
        // --- User Info ---

       Route::get('/user', [ProfileController::class, 'index']);

        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

        Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
            ->middleware(['throttle:6,1'])
            ->name('verification.send');

        Route::get('/email-signature', [EmailSignatureController::class, 'generate']);
        

        // --- Company Management (For Company Admin) ---
        // Departments Management
        Route::prefix('company/departments')->group(function() {
            Route::get('/', [DepartmentController::class, 'index']); // List departments
            Route::post('/', [DepartmentController::class, 'store']); // Create department
            Route::put('/{id}', [DepartmentController::class, 'update']); // Create department
            Route::delete('/{id}', [DepartmentController::class, 'destroy']); // Create department
        });
        
        // Employees
        Route::prefix('company/employees')->group(function () {
            // Employees Management
            Route::post('/', [EmployeeController::class, 'store']); // Add New Employee
            Route::get('/', [EmployeeController::class, 'index']);  // List Employees
            Route::delete('/{id}', [EmployeeController::class, 'destroy']); // Delete Employee
            Route::put('/{id}/profile', [CompanyController::class, 'update']); // Update Employee
            
            // Excel Actions
            Route::post('/import', [EmployeeController::class, 'import']);
            Route::get('/export', [EmployeeController::class, 'export']);
            
            // 1. Media (POST because of file upload)
            Route::post('/{id}/media', [EmployeeController::class, 'updateMedia']);

            // 2. Subscription (PUT)
            Route::put('/{id}/subscription', [EmployeeController::class, 'updateSubscription']);

            // 3. Status & Roles (PUT)
            Route::put('/{id}/status', [EmployeeController::class, 'updateStatus']);

            // 4. Info & Bio (PUT)
            Route::put('/{id}/info', [EmployeeController::class, 'updateInfo']);

        });


        // --- Profile Links System ---

        // 2. Manage My Links (CRUD)
        Route::prefix('profile/links')->group(function () {
            Route::get('/', [ProfileLinksController::class, 'index']);   // List my links
            Route::post('/', [ProfileLinksController::class, 'store']);  // Add a link
            Route::delete('/{id}', [ProfileLinksController::class, 'destroy']); // Delete a link
        });

        // Profile Image Upload Route 
        Route::post('/profile/upload-image', [ProfileController::class, 'uploadImage']);

        // Company Logo Upload Route 
        Route::post('/company/upload-logo', [CompanyController::class, 'uploadLogo']);

        // Company Statistics (Count Departments & Employees)
        Route::get('/company/statistics', [CompanyController::class, 'statistics']);


        // --- Granular Employee Updates ---


        // Update Profile INFO
        Route::put('/profile/info', [ProfileController::class, 'update']);





        // --- User Actions (Individual & Employee) ---
        Route::get('/cards/me', [CardController::class, 'myCards']);
        Route::put('/cards/{id}/profile', [CardController::class, 'updateProfile']);
        Route::post('/cards/{id}/upload-image', [CardController::class, 'uploadCardImage']);
        Route::post('/cards/{id}/freeze', [CardController::class, 'freeze']);
        Route::post('/cards/claim', [CardController::class, 'claim']);
        Route::patch('/cards/{id}/primary', [CardController::class, 'setPrimary']);

        // --- Company Actions (Managers) ---
        Route::prefix('company/cards')->group(function () {
            Route::post('/issue', [CompanyCardController::class, 'issue']);
            Route::post('/reassign', [CompanyCardController::class, 'reassign']);
            Route::post('/swap', [CompanyCardController::class, 'swap']);
        });


        
    });

});



Route::middleware(['auth:sanctum', 'super_admin', 'verified'])->prefix('admin')->group(function () {
    
    // 1. Get Link Types (For frontend icons: Facebook, WhatsApp, etc.)
    Route::get('/link-types', [LinkTypesController::class, 'index']);
    Route::post('/link-types', [LinkTypesController::class, 'store']);


    // Inventory Management
    Route::get('/inventory', [NfcInventoryController::class, 'index']);
    Route::post('/inventory', [NfcInventoryController::class, 'store']);
    Route::post('/inventory/generate', [NfcInventoryController::class, 'generateBatch']);
    Route::post('/cards/{id}/action', [AdminCardController::class, 'action']);
    Route::put('/inventory/{tag_id}', [NfcInventoryController::class, 'update']);
    Route::delete('/inventory/{tag_id}', [NfcInventoryController::class, 'destroy']);

});


Route::get('/cards/{id}', [CardController::class, 'show']); // Public Profile

// Resend OTP (Using Token)
Route::post('/resend-otp', ResendOtpController::class)
    ->middleware('throttle:5,1');

// --- Public Profile (The Digital Card) ---
Route::get('/{slug}', [ProfileController::class, 'showBySlug']);