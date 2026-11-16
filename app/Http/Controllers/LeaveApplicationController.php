<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use App\Models\Employee;
use App\Models\Client;
use App\Traits\CalculatesWorkingDays;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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

    /**
     * Get accessible client names and models for the given user.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array<string>}
     */
    private function getAccessibleClients($user): array
    {
        $clientsQuery = ($user->is_super_admin || !$user->clients()->exists())
            ? Client::where('client.is_active', 1)
            : $user->clients()->where('client.is_active', 1);

        $accessibleClients = $clientsQuery->orderBy('client.client_name')
            ->select(['client.id', 'client.client_name', 'client.short_name', 'client.client_dbname'])
            ->get();

        $clientNames = $accessibleClients->pluck('client_name')->toArray();
        $shortNames  = $accessibleClients->pluck('short_name')->toArray();
        $allowedClientNames = array_values(array_unique(array_filter(array_merge($clientNames, $shortNames))));

        return [$accessibleClients, $allowedClientNames];
    }

    /**
     * Resolve aliases for a given client name (e.g. Melcom vs MELCOM LTD.).
     */
    private function resolveClientAliases(string $clientInput): array
    {
        $matchedClient = Client::where('client_name', $clientInput)
            ->orWhere('short_name', $clientInput)
            ->orWhere('client_dbname', $clientInput)
            ->first();

        $possible = [$clientInput];
        if ($matchedClient) {
            if ($matchedClient->client_name) $possible[] = $matchedClient->client_name;
            if ($matchedClient->short_name)  $possible[] = $matchedClient->short_name;
        }
        if (stripos($clientInput, 'melcom') !== false) {
            $possible = array_merge($possible, ['Melcom', 'MELCOM', 'MELCOM LTD.', 'MELCOM LTD DRIVERS']);
        }
        return array_values(array_unique(array_filter($possible)));
    }

    /**
     * Display a listing of leave applications scoped by client permissions.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        [$accessibleClients, $allowedClientNames] = $this->getAccessibleClients($user);

        $query = LeaveApplication::query()
            ->leftJoin('newemployeedata', function ($join) {
                $join->on(DB::raw('TRIM(leaveapplications.ida)'), '=', DB::raw('TRIM(newemployeedata.staffid)'));
            })
            ->leftJoin('employeecontact', function ($join) {
                $join->on(DB::raw('TRIM(leaveapplications.ida)'), '=', DB::raw('TRIM(employeecontact.staffid)'));
            })
            ->select([
                'leaveapplications.*',
                DB::raw('COALESCE(NULLIF(leaveapplications.company, ""), newemployeedata.company, employeecontact.shop, "N/A") as company'),
                DB::raw('COALESCE(NULLIF(leaveapplications.name, ""), newemployeedata.employeename, employeecontact.name, "N/A") as name'),
            ]);

        // Client scoping for non-superadmin users
        if (!$user->is_super_admin && $user->clients()->exists()) {
            $query->where(function ($q) use ($allowedClientNames) {
                $q->whereIn('leaveapplications.company', $allowedClientNames)
                  ->orWhereIn('newemployeedata.company', $allowedClientNames)
                  ->orWhereIn('employeecontact.shop', $allowedClientNames);
            });
        }

        // Client filter dropdown
        $selectedClient = $request->input('client');
        if (!empty($selectedClient) && $selectedClient !== 'all') {
            $aliases = $this->resolveClientAliases($selectedClient);
            $query->where(function ($q) use ($aliases) {
                $q->whereIn('leaveapplications.company', $aliases)
                  ->orWhereIn('newemployeedata.company', $aliases)
                  ->orWhereIn('employeecontact.shop', $aliases);
            });
        }

        // Base scoped query for calculating totals
        $baseScopedQuery = clone $query;
        $totalRecords = (clone $baseScopedQuery)->count();
        $pendingCount = (clone $baseScopedQuery)->whereIn('leaveapplications.status', ['pending', 0, '0'])->count();
        $approvedCount = (clone $baseScopedQuery)->whereIn('leaveapplications.status', ['approved', 1, '1'])->count();
        $deniedCount = (clone $baseScopedQuery)->whereIn('leaveapplications.status', ['denied', 2, '2'])->count();

        // Search functionality
        $searchCount = null;
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('leaveapplications.ida', 'like', "%{$search}%")
                  ->orWhere('leaveapplications.name', 'like', "%{$search}%")
                  ->orWhere('newemployeedata.employeename', 'like', "%{$search}%")
                  ->orWhere('leaveapplications.type', 'like', "%{$search}%")
                  ->orWhere('leaveapplications.company', 'like', "%{$search}%")
                  ->orWhere('newemployeedata.company', 'like', "%{$search}%")
                  ->orWhere('employeecontact.shop', 'like', "%{$search}%");
            });
            $searchCount = (clone $query)->count();
        }

        // Sorting
        $allowedSorts = ['ida', 'name', 'company', 'type', 'date_start', 'start_date', 'status', 'created_at'];
        $sort = $request->input('sort', 'date_start');
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'date_start';
        }
        $order = strtolower($request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sort === 'company') {
            $query->orderBy('company', $order);
        } elseif ($sort === 'name') {
            $query->orderBy('name', $order);
        } else {
            $query->orderBy("leaveapplications.{$sort}", $order);
        }

        // Pagination
        $leaveApplications = $query->paginate(10)->withQueryString();

        return Inertia::render('LeaveTracker/Index', [
            'leaveApplications' => $leaveApplications,
            'clients' => $accessibleClients,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
                'client' => $selectedClient ?? 'all',
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
            $user = $request->user();

            // Client scoping check
            if (!$user->is_super_admin && $user->clients()->exists()) {
                [$accessibleClients, $allowedClientNames] = $this->getAccessibleClients($user);
                $appCompany = $leaveApplication->company;
                if (!$appCompany) {
                    $emp = DB::table('newemployeedata')->whereRaw('TRIM(staffid) = ?', [trim($leaveApplication->ida)])->first();
                    $appCompany = $emp?->company;
                }
                if (!$appCompany || !in_array($appCompany, $allowedClientNames, true)) {
                    return redirect()->route('leave-applications.index')->with('error', 'Unauthorized: You can only manage leave applications for your assigned clients.');
                }
            }

            // Only update if the leave is pending
            if (!$leaveApplication->isPending()) {
                return redirect()->route('leave-applications.index')->with('error', 'Cannot update a leave application that is not pending.');
            }

            $status = $request->action === 'approve' ? 'approved' : 'denied';
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
            $user = $request->user();
            $status = $request->action === 'approve' ? 'approved' : 'denied';

            $query = LeaveApplication::whereIn('id', $request->ids)
                ->whereIn('status', ['pending', 0, '0']);

            // Client scoping for non-superadmin
            if (!$user->is_super_admin && $user->clients()->exists()) {
                [$accessibleClients, $allowedClientNames] = $this->getAccessibleClients($user);
                $query->where(function ($q) use ($allowedClientNames) {
                    $q->whereIn('company', $allowedClientNames);
                });
            }

            $updatedCount = $query->update(['status' => $status]);

            if ($updatedCount > 0) {
                return redirect()->route('leave-applications.index')->with('success', "Successfully updated $updatedCount leave application(s).");
            }

            return redirect()->route('leave-applications.index')->with('error', 'No pending leave applications for your assigned clients were updated.');
        } catch (\Exception $e) {
            Log::error('Bulk update failed', ['error' => $e->getMessage(), 'ids' => $request->ids]);
            return redirect()->route('leave-applications.index')->with('error', 'Failed to update leave applications: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $user = $request->user();
        [$accessibleClients, $allowedClientNames] = $this->getAccessibleClients($user);

        $query = LeaveApplication::query()
            ->leftJoin('newemployeedata', function ($join) {
                $join->on(DB::raw('TRIM(leaveapplications.ida)'), '=', DB::raw('TRIM(newemployeedata.staffid)'));
            })
            ->leftJoin('employeecontact', function ($join) {
                $join->on(DB::raw('TRIM(leaveapplications.ida)'), '=', DB::raw('TRIM(employeecontact.staffid)'));
            })
            ->select([
                'leaveapplications.*',
                DB::raw('COALESCE(NULLIF(leaveapplications.company, ""), newemployeedata.company, employeecontact.shop, "N/A") as company'),
                DB::raw('COALESCE(NULLIF(leaveapplications.name, ""), newemployeedata.employeename, employeecontact.name, "N/A") as name'),
            ]);

        // Client scoping
        if (!$user->is_super_admin && $user->clients()->exists()) {
            $query->where(function ($q) use ($allowedClientNames) {
                $q->whereIn('leaveapplications.company', $allowedClientNames)
                  ->orWhereIn('newemployeedata.company', $allowedClientNames)
                  ->orWhereIn('employeecontact.shop', $allowedClientNames);
            });
        }

        // Client filter dropdown
        if ($selectedClient = $request->input('client')) {
            if ($selectedClient !== 'all') {
                $aliases = $this->resolveClientAliases($selectedClient);
                $query->where(function ($q) use ($aliases) {
                    $q->whereIn('leaveapplications.company', $aliases)
                      ->orWhereIn('newemployeedata.company', $aliases)
                      ->orWhereIn('employeecontact.shop', $aliases);
                });
            }
        }

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('leaveapplications.ida', 'like', "%{$search}%")
                  ->orWhere('leaveapplications.name', 'like', "%{$search}%")
                  ->orWhere('newemployeedata.employeename', 'like', "%{$search}%")
                  ->orWhere('leaveapplications.type', 'like', "%{$search}%")
                  ->orWhere('leaveapplications.company', 'like', "%{$search}%")
                  ->orWhere('newemployeedata.company', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'date_start');
        $order = strtolower($request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        if ($sort === 'company') {
            $query->orderBy('company', $order);
        } elseif ($sort === 'name') {
            $query->orderBy('name', $order);
        } else {
            $query->orderBy("leaveapplications.{$sort}", $order);
        }

        $leaveApplications = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Employee ID');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Company / Client');
        $sheet->setCellValue('D1', 'Leave Type');
        $sheet->setCellValue('E1', 'Start Date');
        $sheet->setCellValue('F1', 'End Date');
        $sheet->setCellValue('G1', 'Reason');
        $sheet->setCellValue('H1', 'Status');
        $sheet->setCellValue('I1', 'Manager Name');
        $sheet->setCellValue('J1', 'Phone');

        $row = 2;
        foreach ($leaveApplications as $application) {
            $sheet->setCellValue('A' . $row, $application->ida);
            $sheet->setCellValue('B' . $row, $application->name ?? 'N/A');
            $sheet->setCellValue('C' . $row, $application->company ?? 'N/A');
            $sheet->setCellValue('D' . $row, $application->type ?? 'N/A');
            $sheet->setCellValue('E' . $row, $application->date_start ?? 'N/A');
            $sheet->setCellValue('F' . $row, $application->date_end ?? 'N/A');
            $sheet->setCellValue('G' . $row, $application->reason ?? 'N/A');
            $sheet->setCellValue('H' . $row, $this->getStatusText($application->status));
            $sheet->setCellValue('I' . $row, $application->managername ?? 'N/A');
            $sheet->setCellValue('J' . $row, $application->phone ?? 'N/A');
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
        $user = $request->user();

        // Client scoping check
        if (!$user->is_super_admin && $user->clients()->exists()) {
            [$accessibleClients, $allowedClientNames] = $this->getAccessibleClients($user);
            $appCompany = $leave->company;
            if (!$appCompany) {
                $emp = DB::table('newemployeedata')->whereRaw('TRIM(staffid) = ?', [trim($leave->ida)])->first();
                $appCompany = $emp?->company;
            }
            if (!$appCompany || !in_array($appCompany, $allowedClientNames, true)) {
                return redirect()->route('leave-applications.index')->with('error', 'Unauthorized: You can only download leave applications for your assigned clients.');
            }
        }

        if (!$leave->isApproved()) {
            Log::warning("Download Leave PDF: Leave ID {$id} is not approved");
            return redirect()->route('leave-applications.index')->with('error', 'Only approved leave applications can be downloaded.');
        }

        try {
            $employee = Employee::where('staffid', $leave->ida)->first();
            if (!$employee) {
                $empData = DB::table('newemployeedata')->whereRaw('TRIM(staffid) = ?', [trim($leave->ida)])->first();
                $employee = (object) [
                    'employeename' => $empData?->employeename ?? ($leave->name ?? 'N/A'),
                    'company' => $empData?->company ?? ($leave->company ?? 'N/A'),
                ];
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
        $user = $request->user();

        $query = LeaveApplication::whereIn('id', $leaveIds)
            ->whereIn('status', ['approved', 1, '1']);

        // Client scoping
        if (!$user->is_super_admin && $user->clients()->exists()) {
            [$accessibleClients, $allowedClientNames] = $this->getAccessibleClients($user);
            $query->where(function ($q) use ($allowedClientNames) {
                $q->whereIn('company', $allowedClientNames);
            });
        }

        $leaveApplications = $query->get();

        if ($leaveApplications->isEmpty()) {
            Log::warning('Download Multiple Leave PDFs: No approved leave applications found for IDs ' . implode(', ', $leaveIds));
            return response()->json(['error' => 'No approved leave applications found for your assigned clients for the selected IDs'], 404);
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
                    $empData = DB::table('newemployeedata')->whereRaw('TRIM(staffid) = ?', [trim($leave->ida)])->first();
                    $employee = (object) [
                        'employeename' => $empData?->employeename ?? ($leave->name ?? 'N/A'),
                        'company' => $empData?->company ?? ($leave->company ?? 'N/A'),
                    ];
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
        $s = strtolower((string) $status);
        if ($s === '0' || $s === 'pending') {
            return 'Pending';
        }
        if ($s === '1' || $s === 'approved') {
            return 'Approved';
        }
        if ($s === '2' || $s === 'denied') {
            return 'Denied';
        }
        if ($s === '3' || $s === 'cancelled') {
            return 'Cancelled';
        }
        return 'Unknown';
    }
}
