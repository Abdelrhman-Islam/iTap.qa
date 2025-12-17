<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    protected $companyId;

    /**
     * Constructor to receive the Company ID.
     * This ensures we only export employees belonging to the requesting user's company.
     */
    public function __construct($companyId)
    {
        $this->companyId = $companyId;
    }

    /**
     * Fetch the data for the export.
     */
    public function collection()
    {
        // Retrieve employees for this specific company only
        // Eager load 'user' and 'department' relationships for performance
        return Employee::with(['user', 'department'])
                       ->where('company_id', $this->companyId)
                       ->get();
    }

    /**
     * Map the data for each row.
     * This defines exactly what is shown in the Excel file and how it is formatted.
     */
    public function map($employee): array
    {
        return [
            // 1. Full Name (Concatenated)
            $employee->user ? ($employee->user->fName . ' ' . $employee->user->lName) : 'Unknown',
            
            // 2. Email Address
            $employee->user ? $employee->user->email : '',
            
            // 3. Phone Number
            $employee->user ? $employee->user->phone_num : '',
            
            // 4. Job Position
            $employee->position ?? 'N/A',
            
            // 5. Department Name (Show name instead of ID for better readability)
            $employee->department ? $employee->department->name : 'General',
            
            // 6. Status (Capitalized)
            ucfirst($employee->status),
            
            // 7. Date Added (Formatted)
            $employee->created_at->format('Y-m-d'),
        ];
    }

    /**
     * Define the Column Headings (Header Row).
     */
    public function headings(): array
    {
        return [
            'Full Name',
            'Email Address',
            'Phone Number',
            'Position',
            'Department',
            'Status',
            'Date Added',
        ];
    }
}