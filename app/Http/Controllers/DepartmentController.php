<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Department;
use App\Models\Employee;

class DepartmentController extends Controller
{
     public function index(Request $request): JsonResponse
    {
        $departments = $request->user()->departments;
        return response()->json(['data' => $departments]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
        ]);
        $is_exist = Department::where('name', $request->name)->first();

        if($is_exist){
            return response()->json(['This Department Already EXIST'], 400);
        }

        $department = $request->user()->departments()->create([
            'name' => $request->name,
            'status' => $request->status,
        ]);

        return response()->json([
            'message' => 'Department created successfully',
            'data' => $department
        ], 201);

    }
/**
     * Update an existing department.
     * PUT /company/departments/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        // 1. Get the current company ID
        $companyId = $request->user()->id;


        $is_exist = Department::where('name', $request->name)->first();


        if($is_exist){
            return response()->json(['This Department Already EXIST'], 400);
        }


        // 2. Find the department AND ensure it belongs to this company
        // (Security: Prevents editing other companies' departments)
        $department = Department::where('id', $id)
                                            ->where('company_id', $companyId)
                                            ->first();

        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        // 3. Validate
        $validatedData = $request->validate([
            'name' => [
                'sometimes', 
                'string', 
                'max:255',
                // Rule::unique('departments')->where(function ($query) use ($companyId) {
                //     return $query->where('company_id', $companyId);
                // })->ignore($department->id) 
            ],
            'status' => ['sometimes', 'string', 'max:255'],
        ]);

        // 4. Update
        $department->update($validatedData);

        return response()->json([
            'message' => 'Department updated successfully',
            'data' => $department
        ], 200); // Status 200 for OK
    }


    /**
     * Delete a department.
     * DELETE /company/departments/{id}
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $companyId = $request->user()->id;

        // 1. Find the department and ensure ownership
        $department = Department::where('id', $id)
                                            ->where('company_id', $companyId)
                                            ->first();

        if (!$department) {
            return response()->json(['message' => 'Department not found'], 404);
        }

        // 2. Safety Step: Unassign employees from this department before deleting
        // This prevents foreign key constraint errors or accidental data loss.
        // We set department_id to NULL for all employees in this department.
        Employee::where('department_id', $id)
                            ->update(['department_id' => null]);

        // 3. Delete the department
        $department->delete();

        return response()->json(['message' => 'Department deleted successfully']);
    }


   
}
