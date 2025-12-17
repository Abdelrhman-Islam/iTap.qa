<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;


Route::get('/link-storage', function () {
    $targetFolder = storage_path('app/public');
    $linkFolder = public_path('storage');

    // 1. لو الفولدر الأصلي مش موجود، نعمله (عشان نتفادى الأخطاء)
    if (!File::exists($targetFolder)) {
        File::makeDirectory($targetFolder, 0755, true);
    }

    // 2. لو في رابط قديم (مكسور) نمسحه
    if (File::exists($linkFolder)) {
        // في بعض السيرفرات الرابط بيتشاف كأنه ملف
        unlink($linkFolder); 
    }

    // 3. إنشاء الرابط الجديد (الوصلة السحرية)
    try {
        symlink($targetFolder, $linkFolder);
        return 'Done! Storage Linked Successfully. ✅ <br> Try opening the image now.';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
Route::get('/debug-storage', function () {
    // 1. مسار الرابط (الكوبري)
    $linkPath = public_path('storage');
    
    // 2. مسار التخزين الحقيقي
    $realStoragePath = storage_path('app/public');

    // 3. فحص الرابط
    $linkInfo = [
        'Link Location' => $linkPath,
        'Is Link?' => is_link($linkPath) ? 'YES ✅' : 'NO ❌',
        'Points To' => readlink($linkPath), // ده هيقولنا الكوبري مودّي فين
        'Target Exists?' => File::exists($realStoragePath) ? 'YES ✅' : 'NO ❌ (Serious Error)',
    ];

    // 4. محاولة إصلاح الصلاحيات (Permission Fix)
    // أحياناً الفولدرات بتبقى 700 والويب بيحتاج 755
    try {
        chmod($realStoragePath, 0755);
        chmod(storage_path('app/public/avatars'), 0755);
    } catch (\Exception $e) {
        // مش مشكلة لو فشل
    }

    return response()->json([
        'Debug Info' => $linkInfo,
        'Server Document Root' => $_SERVER['DOCUMENT_ROOT'], // عشان نتأكد احنا فين
        'Message' => 'Permissions updated to 0755. Try opening the image now.'
    ]);
});

Route::get('/fix-symlink-root', function () {
    $targetFolder = storage_path('app/public'); 

    $linkFolder = $_SERVER['DOCUMENT_ROOT'] . '/storage';

    // 3. تنظيف أي روابط قديمة غلط
    if (file_exists($linkFolder)) {
        // لو هو رابط، افصله
        if (is_link($linkFolder)) {
            unlink($linkFolder);
        } 
        // لو هو فولدر حقيقي (بالغلط)، امسحه
        else if (is_dir($linkFolder)) {
            File::deleteDirectory($linkFolder);
        }
    }

    // 4. إنشاء الرابط الصحيح
    try {
        symlink($targetFolder, $linkFolder);
        return response()->json([
            'message' => 'Symlink Created in Root Successfully! ✅',
            'target (Real)' => $targetFolder,
            'link (Public)' => $linkFolder
        ]);
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});


Route::get('/fix-storage', function () {
    $targetFolder = storage_path('app/public');
    $linkFolder = public_path('storage');

    // 1. أهم خطوة: إنشاء الفولدر الأصلي لو مش موجود
    if (!File::exists($targetFolder)) {
        File::makeDirectory($targetFolder, 0755, true); // Create storage/app/public
    }

    // 2. مسح الرابط القديم (التالف) من فولدر public
    if (File::exists($linkFolder)) {
        // نحاول نمسحه كدليل (Directory)
        File::deleteDirectory($linkFolder); 
        
        // لو لسه موجود (ممكن يكون ملف عادي)، نمسحه كملف
        if (File::exists($linkFolder)) {
            unlink($linkFolder);
        }
    }

    // 3. إنشاء الرابط الجديد
    symlink($targetFolder, $linkFolder);

    return 'Storage Fixed & Linked Successfully! ✅ (Folder Created & Linked)';
});
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

Route::get('/fix-system', function() {
    Artisan::call('route:clear');
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('view:clear');
    return "Cache Cleared!";
});

Route::get('/create-super-admin', function () {
    
    // نتأكد إنه مش موجود الأول
    if (User::where('email', 'super@itap.qa')->exists()) {
        return response()->json(['message' => '⚠️ Super Admin already exists!']);
    }

    // إنشاء السوبر أدمن
    $admin = User::create([
        'type'             => 'SuperAdmin',
        'fName'            => 'Itap',
        'lName'            => 'Master',
        'email'            => 'super@itap.qa',
        'password'         => Hash::make('SuperStrongPassword123!'),
        'phone_num'        => '00000000000',
        'profile_url_slug' => 'itap-admin',
        'status'           => 'active',
        'company_id'       => null, 
        'department_id'    => null,
    ]);
    
    

    // إنشاء توكين عشان تستخدمه في Postman فوراً
    $token = $admin->createToken('super_admin_token')->plainTextToken;

    return response()->json([
        'message' => '✅ Super Admin Created Successfully!',
        'email' => 'super@itap.qa',
        'token' => $token
    ]);
});

// 👇 الراوت ده مؤقت عشان نستقبل اللينك ونشوف التوكين
Route::get('/reset-password', function (Request $request) {
    return response()->json([
        'message' => 'Frontend Reset Password Page Placeholder',
        'token' => $request->query('token'),
        'email' => $request->query('email')
    ]);
});

require __DIR__.'/auth.php';
