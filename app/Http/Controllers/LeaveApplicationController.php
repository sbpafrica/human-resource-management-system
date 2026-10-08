<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use App\Models\Employee;
use App\Traits\CalculatesWorkingDays;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class LeaveApplicationController extends Controller
{
    use CalculatesWorkingDays;

    public function index(Request $request)
    {
        $query = LeaveApplication::query();
        $totalRecords = LeaveApplication::count();
        $searchCount = null;

        // Search functionality
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ida', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        // Sorting
        $allowedSorts = ['ida', 'name', 'type', 'date_start', 'start_date', 'status', 'created_at'];
        $sort = $request->input('sort', 'date_start');
        if (!in_array($sort, $allowedSorts) || !Schema::hasColumn('leaveapplications', $sort)) {
            $sort = Schema::hasColumn('leaveapplications', 'date_start') ? 'date_start' : 'id';
        }
        $order = strtolower($request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $order);

        // Pagination
        $leaveApplications = $query->paginate(10)->withQueryString();

        // Summary counts
        $pendingCount = LeaveApplication::where('status', 0)->count();
        $approvedCount = LeaveApplication::where('status', 1)->count();
        $deniedCount = LeaveApplication::where('status', 2)->count();

        return Inertia::render('LeaveTracker/Index', [
            'leaveApplications' => $leaveApplications,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
            ],
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'summary' => [
                'total' => $totalRecords,
                'pending' => $pendingCount,
                'approved' => $approvedCount,
                'denied' => $deniedCount,
            ],
            'flash' => [
                'success' => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => $request->hasSession() ? $request->session()->get('error') : null,
            ],
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'action' => ['required', 'in:approve,deny'],
        ]);

        try {
            $leaveApplication = LeaveApplication::findOrFail($id);

            // Only update if the leave is pending
            if ($leaveApplication->status !== 0) {
                return redirect()->route('leave-applications.index')->with('error', 'Cannot update a leave application that is not pending.');
            }

            $status = $request->action === 'approve' ? 1 : 2; // 1=approved, 2=denied
            $leaveApplication->update(['status' => $status]);

            return redirect()->route('leave-applications.index')->with('success', 'Leave application updated successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to update leave application', ['error' => $e->getMessage(), 'leave_id' => $id]);
            return redirect()->route('leave-applications.index')->with('error', 'Failed to update leave application: ' . $e->getMessage());
        }
    }

    public function bulkUpdate(Request $request)
    {
        if (!Schema::hasTable('leaveapplications')) {
            Log::warning('Bulk Update Leave Applications: Table leaveapplications does not exist');
            return redirect()->route('leave-applications.index')->with('error', 'Leave applications data not available.');
        }

        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'exists:leaveapplications,id'],
            'action' => ['required', 'in:approve,deny'],
        ]);

        try {
            $status = $request->action === 'approve' ? 1 : 2;
            $updatedCount = LeaveApplication::whereIn('id', $request->ids)
                ->where('status', 0) // Only update pending requests
                ->update(['status' => $status]);

            if ($updatedCount > 0) {
                return redirect()->route('leave-applications.index')->with('success', "Successfully updated $updatedCount leave application(s).");
            }

            return redirect()->route('leave-applications.index')->with('error', 'No pending leave applications were updated.');
        } catch (\Exception $e) {
            Log::error('Bulk update failed', ['error' => $e->getMessage(), 'ids' => $request->ids]);
            return redirect()->route('leave-applications.index')->with('error', 'Failed to update leave applications: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $query = LeaveApplication::query();
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ida', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }
        $allowedSorts = ['ida', 'name', 'type', 'date_start', 'start_date', 'status', 'created_at'];
        $sort = $request->input('sort', 'date_start');
        if (!in_array($sort, $allowedSorts) || !Schema::hasColumn('leaveapplications', $sort)) {
            $sort = Schema::hasColumn('leaveapplications', 'date_start') ? 'date_start' : 'id';
        }
        $order = strtolower($request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $order);
        $leaveApplications = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Employee ID');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Leave Type');
        $sheet->setCellValue('D1', 'Start Date');
        $sheet->setCellValue('E1', 'End Date');
        $sheet->setCellValue('F1', 'Reason');
        $sheet->setCellValue('G1', 'Status');
        $sheet->setCellValue('H1', 'Manager Name');
        $sheet->setCellValue('I1', 'Phone');

        $row = 2;
        foreach ($leaveApplications as $application) {
            $sheet->setCellValue('A' . $row, $application->ida);
            $sheet->setCellValue('B' . $row, $application->name ?? 'N/A');
            $sheet->setCellValue('C' . $row, $application->type ?? 'N/A');
            $sheet->setCellValue('D' . $row, $application->date_start ?? 'N/A');
            $sheet->setCellValue('E' . $row, $application->date_end ?? 'N/A');
            $sheet->setCellValue('F' . $row, $application->reason ?? 'N/A');
            $sheet->setCellValue('G' . $row, $this->getStatusText($application->status));
            $sheet->setCellValue('H' . $row, $application->managername ?? 'N/A');
            $sheet->setCellValue('I' . $row, $application->phone ?? 'N/A');
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'leave_applications.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return Response::download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    public function downloadLeavePdf(Request $request, $id)
    {
        Log::debug('Attempting to download PDF for Leave ID: ' . $id);

        $leave = LeaveApplication::findOrFail($id);

        if ($leave->status !== 1) {
            Log::warning("Download Leave PDF: Leave ID {$id} is not approved");
            return redirect()->route('leave-applications.index')->with('error', 'Only approved leave applications can be downloaded.');
        }

        try {
            $employee = Employee::where('staffid', $leave->ida)->first();
            if (!$employee) {
                Log::warning("Download Leave PDF: No employee found for staffid {$leave->ida} for Leave ID {$id}");
            }

            $leaveDays = $this->calculateWorkingDays(
                Carbon::parse($leave->date_start),
                Carbon::parse($leave->date_end)
            );

            $pdf = Pdf::loadView('leave.pdf', [
                'leave' => $leave,
                'employee' => $employee ?? (object) ['employeename' => $leave->name ?? 'N/A'],
                'leaveDays' => $leaveDays,
                'formattedStartDate' => Carbon::parse($leave->date_start)->format('jS F, Y'),
                'formattedEndDate' => Carbon::parse($leave->date_end)->format('jS F, Y'),
            ]);

            Log::info("Download Leave PDF: Successfully generated PDF for Leave ID {$id}");
            return $pdf->download("leave_{$leave->ida}_{$leave->id}.pdf");
        } catch (\Exception $e) {
            Log::error("Download Leave PDF: Failed to generate PDF for Leave ID {$id}: {$e->getMessage()}");
            return redirect()->route('leave-applications.index')->with('error', 'Failed to generate PDF: ' . $e->getMessage());
        }
    }

    public function downloadMultipleLeavePdfs(Request $request)
    {
        Log::info('Download Multiple Leave PDFs: Received request', ['request_data' => $request->all()]);

        if (!Schema::hasTable('leaveapplications')) {
            Log::warning('Download Multiple Leave PDFs: Table leaveapplications does not exist');
            return response()->json(['error' => 'Leave applications data not available'], 404);
        }

        $validator = Validator::make($request->all(), [
            'leave_ids' => 'required|array|min:1',
            'leave_ids.*' => 'required|integer|exists:leaveapplications,id',
        ], [
            'leave_ids.min' => 'Please select at least one approved leave application.',
        ]);

        if ($validator->fails()) {
            Log::warning('Download Multiple Leave PDFs: Validation failed', $validator->errors()->toArray());
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        $leaveIds = $request->input('leave_ids');

        $leaveApplications = LeaveApplication::whereIn('id', $leaveIds)
            ->where('status', 1)
            ->get();

        if ($leaveApplications->isEmpty()) {
            Log::warning('Download Multiple Leave PDFs: No approved leave applications found for IDs ' . implode(', ', $leaveIds));
            return response()->json(['error' => 'No approved leave applications found for the selected IDs'], 404);
        }

        try {
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $zipFileName = 'leaves_' . uniqid() . '.zip';
            $zipPath = storage_path("app/temp/{$zipFileName}");
            $zip = new \ZipArchive();

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                Log::error('Download Multiple Leave PDFs: Failed to create ZIP file', ['zip_file' => $zipPath]);
                return response()->json(['error' => 'Failed to create ZIP file'], 500);
            }

            foreach ($leaveApplications as $leave) {
                $employee = Employee::where('staffid', $leave->ida)->first();
                if (!$employee) {
                    Log::warning('Download Multiple Leave PDFs: Employee not found for leave application', [
                        'leave_id' => $leave->id,
                        'staffid' => $leave->ida,
                    ]);
                    $zip->close();
                    return response()->json(['error' => "Employee not found for leave ID {$leave->id}"], 404);
                }

                $leaveDays = $this->calculateWorkingDays(
                    Carbon::parse($leave->date_start),
                    Carbon::parse($leave->date_end)
                );

                $pdf = Pdf::loadView('leave.pdf', [
                    'leave' => $leave,
                    'employee' => $employee,
                    'leaveDays' => $leaveDays,
                    'formattedStartDate' => Carbon::parse($leave->date_start)->format('jS F, Y'),
                    'formattedEndDate' => Carbon::parse($leave->date_end)->format('jS F, Y'),
                ]);

                $pdfFileName = "leave_{$leave->ida}_{$leave->id}.pdf";
                $pdfPath = storage_path("app/temp/{$pdfFileName}");
                $pdf->save($pdfPath);
                $zip->addFile($pdfPath, $pdfFileName);

                Log::info('Download Multiple Leave PDFs: Added PDF to ZIP', ['file' => $pdfFileName, 'leave_id' => $leave->id]);
            }

            $zip->close();

            $response = response()->download($zipPath, 'leaves.zip', [
                'Content-Type' => 'application/zip',
            ]);

            $response->deleteFileAfterSend($zipPath);
            foreach ($leaveApplications as $leave) {
                $pdfFileName = "leave_{$leave->ida}_{$leave->id}.pdf";
                $pdfPath = storage_path("app/temp/{$pdfFileName}");
                if (file_exists($pdfPath)) {
                    unlink($pdfPath);
                }
            }

            Log::info('Download Multiple Leave PDFs: ZIP file sent successfully', ['zip_file' => $zipFileName, 'leave_ids' => $leaveIds]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Download Multiple Leave PDFs: Failed to generate ZIP', [
                'error' => $e->getMessage(),
                'leave_ids' => $leaveIds,
            ]);
            return response()->json(['error' => 'Failed to generate leave PDFs ZIP'], 500);
        }
    }

    private function getStatusText($status)
    {
        switch ($status) {
            case 0:
                return 'Pending';
            case 1:
                return 'Approved';
            case 2:
                return 'Denied';
            case 3:
                return 'Cancelled';
            default:
                return 'Unknown';
        }
    }
}
