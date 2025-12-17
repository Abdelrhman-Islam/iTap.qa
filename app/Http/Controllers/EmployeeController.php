<?php
  
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Employee; 
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

// Excel Imports/Exports
use App\Imports\EmployeesImport;
use App\Exports\EmployeesExport;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    /**
     * 1. LIST EMPLOYEES
     * Get all employees for the authenticated company.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->id;

        // Retrieve employees from the employees table and join with user and department
        $employees = Employee::where('company_id', $companyId)
                             ->with(['user', 'department']) // Eager Load
                             ->paginate(10);

        return response()->json([
            'message' => 'Employees retrieved successfully',
            'data' => $employees
        ]);
    }

    /**
     * 2. ADD NEW EMPLOYEE
     * Handles creating a User (if not exists) AND linking them as an Employee.
     */
    public function store(Request $request): JsonResponse
    {
        $company = $request->user();

        // Validation
        $request->validate([
            'email' => 'required|email',
            'fName' => 'required|string',
            'lName' => 'required|string',
            'phone_num' => 'required|string',
            'department_id' => [
                'required', 
                Rule::exists('departments', 'id')->where('company_id', $company->id)
            ],
            'position' => 'nullable|string',
            'password' => 'nullable|min:8', 
        ]);

        return DB::transaction(function () use ($request, $company) {
            
            // Check or Create User
            $user = User::where('email', $request->email)->first();
            $isNewUser = false;

            if (!$user) {
                // --- Create New User---
                $isNewUser = true;
                $formattedFName = ucfirst(strtolower(trim($request->fName)));
                $formattedMName = ucfirst(strtolower(trim($request->mName)));
                $formattedLName = ucfirst(strtolower(trim($request->lName)));
                
                // Slug Logic
                $cleanPhone = preg_replace('/\D/', '', $request->phone_num);
                $lastFiveDigits = (strlen($cleanPhone) >= 5) ? substr($cleanPhone, -5) : rand(10000, 99999);
                $customSlug = $formattedFName . $formattedMName .$formattedLName . '-' . $lastFiveDigits;
                if (User::where('profile_url_slug', $customSlug)->exists()) {
                    $customSlug .= '-' . rand(1, 99);
                }

                $user = User::create([
                    'type' => 'employee', // User type generally
                    'fName' => $formattedFName,
                    'lName' => $formattedLName,
                    'mName' => $formattedMName,
                    'sex' => $request->sex,
                    'age' => $request->age,
                    'email' => $request->email,
                    'password' => Hash::make($request->password ?? Str::random(10)),
                    'phone_num' => $request->phone_num,
                    'profile_url_slug' => $customSlug,
                    'email_verified_at' => now(), 
                ]);
            }

            // Check Duplicate Employee
            $exists = Employee::where('company_id', $company->id)
                              ->where('user_id', $user->id)
                              ->exists();

            if ($exists) {
                abort(422, 'This user is already an employee in your company.');
            }

            // D. Create Employee Record (Linking)
            $employee = Employee::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'department_id' => $request->department_id,
                'position' => $request->position,
                'roles' => $request->roles ?? 'employee',
                'status' => $request->status ?? 'active',
                'is_primary' => $request->is_primary ?? false,
            ]);

            // Optional: Send OTP only if it's a completely new user
            if ($isNewUser) {
                // $user->sendEmailVerificationNotification(); 
            }

            return response()->json([
                'message' => 'Employee added successfully',
                'data' => $employee->load('user', 'department')
            ], 201);
        });
    }

    /**
     * 3. REMOVE EMPLOYEE (Convert back to Individual)
     * Detaches the user from the company but keeps their account active.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $companyId = $request->user()->id;

        // 1. Find the Employee record belonging to this company
        // (Here, id is the employees table ID, not the user_id)
        $employeeRecord = Employee::where('id', $id)
                                  ->where('company_id', $companyId)
                                  ->first();

        if (!$employeeRecord) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        // 2. Get the User associated with this employee
        $user = $employeeRecord->user;

        // 3. Delete the Employee link (Remove from company list)
        // This deletes the row from the employees table only
        $employeeRecord->delete();

        // 4. Downgrade the User to 'Individual'
        if ($user) {
            $user->update([
                'type' => 'individual', // 👈 Revert back to individual
                
                // Clean up any residual data (if these columns still exist in the users table)
                'company_id' => null,     
                'department_id' => null,

            ]);
        }

        return response()->json(['message' => 'Employee removed from company and account reverted to Individual']);
    }

    // Helper function to get the Company ID whether the user is an Owner or an Employee
    private function getCompanyIdForUser($user)
    {
        // 1. First Scenario: Is this user the direct owner of the company?
        // (Assuming the companies table has a user_id column for the owner)
        $ownedCompany = \App\Models\Company::where('id', $user->id)->first();
        
        if ($ownedCompany) {
            return $ownedCompany->id; // ✅ Is the Company Owner
        }

        // 2. Second Scenario: Is this user an employee/manager in the company?
        if ($user->employee) {
            return $user->employee->company_id; // ✅ Is an Employee in the Company
        }

        return null; // ❌ Neither Owner nor Employee
    }

    /**
     * 4. UPDATE MEDIA (Profile Image/Video) -> Updates USER Table
     */
    public function updateMedia(Request $request, $id): JsonResponse
    {
        $currentUser = $request->user();

        // 1. Get Company ID using the smart method (Owner OR Employee)
        $companyId = $this->getCompanyIdForUser($currentUser);

        if (!$companyId) {
            return response()->json([
                'message' => 'Unauthorized: You must be a Company Owner or an Employee.'
            ], 403);
        }
        
        // 2. Access the target employee
        // Search for the employee and ensure they belong to the company identified above
        $employeeRecord = Employee::where('id', $id)
                                  ->where('company_id', $companyId)
                                  ->firstOrFail();
                                  
        $targetUser = $employeeRecord->user; 

        // 3. Validation
        $request->validate([
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:4096',
            'profile_video' => 'nullable|mimetypes:video/avi,video/mpeg,video/mp4|max:20480',
        ]);

        $data = [];
        $disk = Storage::disk('public');

        // --- A. Upload Image ---
        if ($request->hasFile('profile_image')) {
            if (!$disk->exists('avatars')) $disk->makeDirectory('avatars');
            
            if ($targetUser->profile_image && $disk->exists($targetUser->profile_image)) {
                $disk->delete($targetUser->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')->store('avatars', 'public');
        }

        // --- B. Upload Video ---
        if ($request->hasFile('profile_video')) {
            if (!$disk->exists('profile_videos')) $disk->makeDirectory('profile_videos');

            if ($targetUser->profile_video && $disk->exists($targetUser->profile_video)) {
                $disk->delete($targetUser->profile_video);
            }
            $data['profile_video'] = $request->file('profile_video')->store('profile_videos', 'public');
        }

        // 4. Save
        if (!empty($data)) {
            $targetUser->update($data);
        }

        return response()->json([
            'message' => 'Media updated successfully',
            'data' => [
                'user_id' => $targetUser->id,
                'profile_image_url' => $targetUser->profile_image ? asset('storage/' . $targetUser->profile_image) : null,
            ]
        ]);
    }

    /**
     * 5. UPDATE STATUS/ROLES -> Updates EMPLOYEE Table
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $companyId = $request->user()->id;
        $employee = Employee::where('id', $id)->where('company_id', $companyId)->firstOrFail();

        $data = $request->validate([
            'status' => 'nullable|in:active,suspended,deactivated',
            'roles' => 'nullable|string',
        ]);

        $employee->update($data); 

        return response()->json(['message' => 'Status updated', 'data' => $employee]);
    }

    /**
     * 6. UPDATE INFO -> Mixed (User & Employee)
     */
    public function updateInfo(Request $request, $id): JsonResponse
    {
        $companyId = $request->user()->id;
        $employee = Employee::where('id', $id)
                            ->where('company_id', $companyId)
                            ->with('user')
                            ->firstOrFail();

        $request->validate([
            'fName'            => ['nullable', 'string', 'max:255'],
            'mName'            => ['nullable', 'string', 'max:255'],
            'lName'            => ['nullable', 'string', 'max:255'],
            'email'            => ['nullable', 'email'],
            'phone_num'        => ['nullable', 'string', 'max:20'],
            'position'         => ['nullable', 'string', 'max:255'],
            'password'         => ['nullable', 'string', 'min:8'],
            "sex"              => ['nullable', 'string', 'max:20'],
            "age"              => ['nullable', 'max:20'],

            'position' => 'nullable|string',      // Employee Table
            'bio' => 'nullable|string',           // User Table
            'profile_language' => 'nullable|string', // User Table

            'department_id' => [
                'required', 
                'integer',
                // Security Rule: Ensure the new department belongs to the same company
                Rule::exists('departments', 'id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                }),
            ],
        ]);
        $formattedFName = ucfirst(strtolower(trim($request->fName)));
        $formattedMName = $request->mName ? ucfirst(strtolower(trim($request->mName))) : null;
        $formattedLName = ucfirst(strtolower(trim($request->lName)));

        // Update User Data
        // $employee->update($request->only(['bio', 'profile_language']));
        
        // Update User Data
        $employee->user->update([
            "fName" => $formattedFName,
            "mName" => $formattedMName,
            "lName" => $formattedLName,
            "email" => $request->email,
            "password" => Hash::make($request->password),
            "phone_num" => $request->phone_num,
            "sex" => $request->sex,
            "age" => $request->age,
            "bio" => $request->bio,
            "profile_language" => $request->profile_language,
        ]);

        // Update Employee Data
        $employee->update([
            'department_id' => $request->department_id,
            'position' => $request->position
        ]);

        return response()->json(['message' => 'Info updated', 'data' => $employee]);
    }

    // --- Excel Methods ---
    /**
     * 8. IMPORT EMPLOYEES
     * Automatically assigns the imported employees to the current user's company.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'mimes:xlsx,xls,csv', 'max:2048'],
        ]);

        // 1. Get the Company ID automatically from the logged-in user
        $companyId = $this->getCompanyIdForUser($request->user());

        if (!$companyId) {
            return response()->json(['message' => 'Unauthorized: Could not determine company identity.'], 403);
        }

        try {
            // 2. Pass the automatic Company ID to the Import Class constructor
            Excel::import(new EmployeesImport($companyId), $request->file('file'));
            
            return response()->json(['message' => 'Employees imported successfully']);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            return response()->json(['message' => 'Validation Error', 'errors' => $e->failures()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error importing file: ' . $e->getMessage()], 500);
        }
    }

    /**
     * 9. EXPORT EMPLOYEES
     * Exports only the employees belonging to the current user's company.
     */
    public function export(Request $request)
    {
        // 1. Get the Company ID automatically
        $companyId = $this->getCompanyIdForUser($request->user());
        
        if (!$companyId) {
            return response()->json(['message' => 'Unauthorized: Could not determine company identity.'], 403);
        }
        
        // 2. Pass the ID to the Export Class to filter data
        return Excel::download(new EmployeesExport($companyId), 'employees.xlsx');
    }


    /**
     * 7. UPDATE DEPARTMENT (Move Employee)
     * PUT /company/employees/{id}/department
     */
    public function updateDepartment(Request $request, $id): JsonResponse
    {
        $companyId = $request->user()->id;
        
        // 1. Find Employee belonging to this company
        $employee = Employee::where('id', $id)
                            ->where('company_id', $companyId)
                            ->firstOrFail();

        // 2. Validate the new Department
        $request->validate([
            'department_id' => [
                'required', 
                'integer',
                // Security Rule: Ensure the new department belongs to the same company
                Rule::exists('departments', 'id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                }),
            ],
        ]);

        // 3. Update
        $employee->update([
            'department_id' => $request->department_id
        ]);

        return response()->json([
            'message' => 'Employee moved to new department successfully',
            'data' => $employee->load('department') // Return new department data
        ]);
    }
}