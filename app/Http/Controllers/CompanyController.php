<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use App\Models\Employee;
use App\Models\User;


class CompanyController extends Controller
{
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
        ]);

        $company = $request->user();

        if ($request->hasFile('logo')) {
            // Delete old logo if it exist
            if ($company->getRawOriginal('logo')) {
                Storage::disk('public')->delete($company->getRawOriginal('logo'));
            }

            // Upload new on company_logos
            $path = $request->file('logo')->store('company_logos', 'public');

            $company->update(['logo' => $path]);

            return response()->json([
                'message' => 'Logo uploaded successfully',
                'logo_url' => asset('storage/' . $path)
            ]);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    public function statistics(Request $request): JsonResponse
    {
        $company = $request->user(); // The authenticated company

        return response()->json([
            'message' => 'Statistics retrieved successfully',
            'data' => [
                // Count of entities
                'departments_count' => $company->departments()->count(),
                'employees_count'   => $company->employees()->count(),
                
                // Sum of interactions (The "High-level overview" widget)
                'total_taps'        => $company->employees()->sum('taps_count'),
                'total_leads'       => $company->employees()->sum('leads_count'),
                'total_contacts'    => $company->employees()->sum('contacts_count'),
            ]
        ]);
    }



    public function updateEmployeeData(Request $request, $targetUserId)
    {
        // 1. تحديد شركة المدير الحالي
        $manager = $request->user();
        if (!$manager->employee) {
            return response()->json(['message' => 'Unauthorized: You are not a company employee.'], 403);
        }
        $companyId = $manager->employee->company_id;

        // 2. الفاليديشن (مهم جداً عشان الإيميل واليونيك)
        $request->validate([
            // --- بيانات جدول Users ---
            'fName' => 'required|string|max:50',
            'lName' => 'nullable|string|max:50',
            'phone_num' => 'nullable|string|max:20',
            // الإيميل لازم يكون unique بس نستثني الموظف الحالي عشان ميضربش إيرور
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($targetUserId)],
            
            // --- بيانات جدول Employees ---
            'position' => 'nullable|string|max:100',
            'department_id' => 'nullable|exists:departments,id',
            'status' => 'in:active,suspended,deactivated', // عشان لو عايز توقفه
            'roles' => 'nullable|string' // لو بتخزن الصلاحيات كـ string
        ]);

        return DB::transaction(function () use ($request, $companyId, $targetUserId) {
            
            // 3. الوصول للموظف المستهدف (لازم يكون في نفس الشركة)
            $employeeRecord = Employee::where('user_id', $targetUserId)
                                      ->where('company_id', $companyId)
                                      ->firstOrFail(); //

            // 4. تحديث جدول Employees (البيانات الوظيفية)
            $employeeRecord->update([
                'position' => $request->position,
                'department_id' => $request->department_id,
                'status' => $request->status ?? $employeeRecord->status, // لو مبعتش حالة خلي القديمة
                'roles' => $request->roles ?? $employeeRecord->roles,
            ]);

            // 5. تحديث جدول Users (البيانات الشخصية)
            // بنوصله عن طريق العلاقة
            $userRecord = $employeeRecord->user;
            
            if ($userRecord) {
                $userRecord->update([
                    'fName' => $request->fName,
                    'lName' => $request->lName,
                    'phone_num' => $request->phone_num,
                    'email' => $request->email, // تعديل الإيميل مسموح
                ]);
            }

            return response()->json([
                'message' => 'Employee general data updated successfully',
                'data' => [
                    'id' => $userRecord->id,
                    'name' => $userRecord->fName . ' ' . $userRecord->lName,
                    'position' => $employeeRecord->position,
                    'status' => $employeeRecord->status,
                    'company_id' => $employeeRecord->company_id
                ]
            ]);
        });
    }








    public function update(Request $request, $id): JsonResponse
{
    // 1. Get the current company ID
    $companyId = $request->user()->id;

    // 2. Search stays EXACTLY the same (employees table)
    $employee = Employee::where('user_id', $id)
        ->where('company_id', $companyId)
        ->first();

    if (!$employee) {
        return response()->json(['message' => 'User not found'], 404);
    }

    // 3. Validate
    $request->validate([
        "fName" => ['nullable', 'string', 'max:255'],
        "mName" => ['nullable', 'string', 'max:255'],
        "lName" => ['nullable', 'string', 'max:255'],
        "phone_num" => ['required', 'string', 'max:20'],
        "position" => ['nullable', 'string', 'max:255'],
        "status" => ['nullable', 'string', 'max:255'],   
        "sex" => ['nullable', 'string', 'max:20'],
        "age" => ['nullable', 'max:20'],
    ]);

    // 4. Update USER table (not employees)
    $user = User::find($employee->user_id);


    $formattedFName = ucfirst(strtolower(trim($request->fName)));
    $formattedMName = $request->mName ? ucfirst(strtolower(trim($request->mName))) : null;
    $formattedLName = ucfirst(strtolower(trim($request->lName)));
    $user->update([
        "fName" => $formattedFName,
        "mName" => $formattedMName,
        "lName" => $formattedLName,
        "password" => Hash::make($request->password),
        "phone_num" => $request->phone_num,
        "sex" => $request->sex,
        "age" => $request->age,
        "position" => "",
        "status" => "",
    ]);

    return response()->json([
        'message' => 'User updated successfully',
        'data' => $user
    ], 200);
}
}
