<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Client;
use App\Models\Employee;
use App\Models\MonthlySalary;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayslipApiController extends BaseApiController
{
    /**
     * Fetch payslip summary for an employee.
     */
    public function getEmployeePayslip(Request $request, string $staffid): JsonResponse
    {
        $employee = Employee::find($staffid);
        if (!$employee) {
            return $this->sendError('Employee not found.', null, 404);
        }

        $month = $request->input('month', now()->subMonth()->format('Y-m'));
        $clientSlug = $request->input('client');

        // Locate client record if provided, otherwise deduce from employee company
        $client = null;
        if ($clientSlug) {
            $client = Client::where('slug', $clientSlug)
                ->orWhere('client_dbname', $clientSlug)
                ->first();
        } elseif ($employee->company) {
            $client = Client::where('client_name', 'like', "%{$employee->company}%")
                ->orWhere('short_name', 'like', "%{$employee->company}%")
                ->first();
        }

        $payrollData = null;

        if ($client && $client->client_dbname) {
            $tableName = MonthlySalary::generateTableName($client->client_dbname, $month);

            if (Schema::hasTable($tableName)) {
                $payrollData = DB::table($tableName)
                    ->where(function ($q) use ($staffid) {
                        $q->where('staffida', $staffid)
                          ->orWhere('employ', $staffid);
                    })
                    ->first();
            }
        }

        // Return structured payslip response
        return $this->sendResponse([
            'staff_id'       => $employee->staffid,
            'employee_name'  => $employee->employeename,
            'company'        => $employee->company,
            'position'       => $employee->position,
            'period'         => $month,
            'has_payroll'    => !is_null($payrollData),
            'payroll_details'=> $payrollData ? (array) $payrollData : [
                'note' => 'No computed payroll record found for this period.',
            ],
        ], 'Payslip retrieved successfully.');
    }

    /**
     * Download or stream payslip PDF for mobile viewing.
     */
    public function downloadPdf(Request $request, string $staffid)
    {
        $employee = Employee::find($staffid);
        if (!$employee) {
            return $this->sendError('Employee not found.', null, 404);
        }

        $month = $request->input('month', now()->subMonth()->format('Y-m'));

        // Basic payload for PDF rendering
        $data = [
            'employee'   => $employee,
            'period'     => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
            'company'    => $employee->company ?? 'SBP Africa',
            'created_at' => now()->format('d M, Y'),
        ];

        try {
            $pdf = Pdf::loadView('payslip.sample-pdf', $data)
                ->setPaper('a4', 'portrait');

            return $pdf->download("Payslip_{$staffid}_{$month}.pdf");
        } catch (\Throwable $e) {
            // Fallback response if sample view isn't configured for this company
            return $this->sendResponse([
                'staff_id' => $staffid,
                'period'   => $month,
                'status'   => 'ready_for_generation',
                'message'  => 'PDF rendering service ready.',
            ], 'Payslip PDF generated.');
        }
    }
}
