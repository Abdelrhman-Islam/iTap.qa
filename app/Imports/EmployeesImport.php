<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class EmployeesImport implements ToModel, WithHeadingRow, SkipsEmptyRows
{
    protected $companyId;

    /**
     * Constructor to receive the Company ID automatically from the Controller.
     */
    public function __construct($companyId)
    {
        $this->companyId = $companyId;
    }

    /**
     * Define how to map each row to the database.
     *
     * @param array $row
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // 1. Basic Validation: Ensure essential fields exist in the Excel row
        // Keys are lowercase because of 'WithHeadingRow'
        if (!isset($row['email']) || !isset($row['fname'])) {
            return null;
        }

        $email = trim($row['email']);

        // 2. Find or Create the User First
        $user = User::where('email', $email)->first();

        if (!$user) {
            // --- Logic to Create New User ---
            $fName = ucfirst(strtolower(trim($row['fname'])));
            $mName = isset($row['mname']) ? ucfirst(strtolower(trim($row['mname']))) : '';
            $lName = isset($row['lname']) ? ucfirst(strtolower(trim($row['lname']))) : '';
            
            // Generate a unique slug
            $slug = Str::slug($fName . ' ' . $lName) . '-' . rand(1000, 9999);
            
            // Generate a random phone if not provided (or handle as null)
            $phone = isset($row['phone']) ? $row['phone'] : '';

            $user = User::create([
                'type'              => 'employee',
                'fName'             => $fName,
                'mName'             => $mName,
                'lName'             => $lName,
                'email'             => $email,
                'phone_num'         => $phone,
                'password'          => Hash::make('12345678'), // Default password
                'profile_url_slug'  => $slug,
                'email_verified_at' => now(), // Auto verify for imported users
            ]);
        }

        // 3. Check if this User is ALREADY an employee in THIS company
        // (Prevent duplicate entries for the same company)
        $alreadyEmployee = Employee::where('user_id', $user->id)
                                   ->where('company_id', $this->companyId)
                                   ->exists();

        if ($alreadyEmployee) {
            return null; // Skip this row
        }

        // 4. Create the Employee Record linked to the Company
        return new Employee([
            'user_id'       => $user->id,
            'company_id'    => $this->companyId, // Automatic Company ID
            'department_id' => isset($row['department_id']) ? $row['department_id'] : null, // Ensure ID is valid in Excel
            'position'      => isset($row['position']) ? $row['position'] : 'Member',
            'status'        => 'active',
            'roles'         => 'employee',
        ]);
    }
}