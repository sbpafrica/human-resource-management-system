<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintController extends Controller
{
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
            ->select(['client.id', 'client.client_name', 'client.short_name'])
            ->get();

        $clientNames = $accessibleClients->pluck('client_name')->toArray();
        $shortNames  = $accessibleClients->pluck('short_name')->toArray();
        $allowedClientNames = array_values(array_unique(array_filter(array_merge($clientNames, $shortNames))));

        return [$accessibleClients, $allowedClientNames];
    }

    /**
     * Display a listing of complaints in the HR management dashboard.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Auto-seed legacy complaints if complaints table is currently empty
        if (Complaint::count() === 0) {
            \App\Console\Commands\MigrateLegacyComplaints::syncLegacy();
        }

        [$accessibleClients, $allowedClientNames] = $this->getAccessibleClients($user);

        $query = Complaint::query()
            ->leftJoin('users', 'complaints.resolved_by', '=', 'users.id')
            ->select('complaints.*', 'users.name as resolver_name');

        // Client scoping for non-superadmin
        if (!$user->is_super_admin && $user->clients()->exists()) {
            $query->whereIn('complaints.client_name', $allowedClientNames);
        }

        // Search filter
        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('complaints.ticket_number', 'like', "%{$search}%")
                  ->orWhere('complaints.staff_id', 'like', "%{$search}%")
                  ->orWhere('complaints.employee_name', 'like', "%{$search}%")
                  ->orWhere('complaints.shop_name', 'like', "%{$search}%")
                  ->orWhere('complaints.complaint_text', 'like', "%{$search}%")
                  ->orWhere('complaints.phone', 'like', "%{$search}%");
            });
        }

        // Helper to resolve all alias variations for a client name (e.g. "MELCOM LTD." <=> "Melcom")
        $resolveClientAliases = function ($clientInput) {
            $matchedClient = Client::where('client_name', $clientInput)
                ->orWhere('short_name', $clientInput)
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
        };

        // Client filter
        if ($client = $request->input('client')) {
            $aliases = $resolveClientAliases($client);
            $query->whereIn('complaints.client_name', $aliases);
        }

        // Month period filter
        if ($month = $request->input('month')) {
            $query->where('complaints.month_period', $month);
        }

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('complaints.category', $category);
        }

        // Status filter
        if ($status = $request->input('status')) {
            $query->where('complaints.status', $status);
        }

        // Priority filter
        if ($priority = $request->input('priority')) {
            $query->where('complaints.priority', $priority);
        }

        // Order: pending and under_review first, then newest
        $query->orderByRaw("CASE WHEN complaints.status = 'pending' THEN 1 WHEN complaints.status = 'under_review' THEN 2 ELSE 3 END")
              ->orderBy('complaints.created_at', 'desc');

        $complaints = $query->paginate(20)->withQueryString();

        // Calculate KPI metrics
        $metricsBase = Complaint::query();
        if (!$user->is_super_admin && $user->clients()->exists()) {
            $metricsBase->whereIn('client_name', $allowedClientNames);
        }
        if ($client = $request->input('client')) {
            $metricsBase->whereIn('client_name', $resolveClientAliases($client));
        }

        $metrics = [
            'total'        => (clone $metricsBase)->count(),
            'pending'      => (clone $metricsBase)->where('status', 'pending')->count(),
            'under_review' => (clone $metricsBase)->where('status', 'under_review')->count(),
            'resolved'     => (clone $metricsBase)->where('status', 'resolved')->count(),
        ];

        // Available months for dropdown (scoped to accessible clients and active client filter)
        $availableMonthsQuery = Complaint::select('month_period')
            ->whereNotNull('month_period')
            ->where('month_period', '!=', '')
            ->distinct();

        if (!$user->is_super_admin && $user->clients()->exists()) {
            $availableMonthsQuery->whereIn('client_name', $allowedClientNames);
        }

        if ($client = $request->input('client')) {
            $availableMonthsQuery->whereIn('client_name', $resolveClientAliases($client));
        }

        $availableMonths = $availableMonthsQuery->pluck('month_period')->toArray();

        // Always include current and recent months (November 2026, October 2026, etc.) in dropdown
        $recentMonths = [];
        for ($i = 0; $i < 6; $i++) {
            $recentMonths[] = date('F Y', strtotime("-{$i} month"));
        }
        $availableMonths = array_values(array_unique(array_merge($recentMonths, $availableMonths)));

        // Sort months chronologically (newest first)
        usort($availableMonths, function ($a, $b) {
            $tA = strtotime("01 " . $a);
            $tB = strtotime("01 " . $b);
            if ($tA && $tB) {
                return $tB <=> $tA;
            }
            return strcmp($b, $a);
        });

        $categories = [
            'Salary Discrepancy / Underpayment',
            'Unmarked / Incorrect Absence',
            'Overtime / Allowance Missing',
            'SSNIT / Tier 1 Deduction Issue',
            'Tier 2 / Petra Pension Issue',
            'Bank Account / Wrong Branch Details',
            'Unapproved / Disputed Leave',
            'General HR / Workplace Query',
        ];

        $canManage = $user->is_super_admin 
            || $user->hasRole('Super Admin')
            || $user->hasRole('HR Manager') 
            || $user->hasRole('HR Officer') 
            || $user->hasRole('HRAudit')
            || $user->hasRole('Payroll Officer');

        return Inertia::render('Complaints/Index', [
            'complaints'      => $complaints,
            'filters'         => $request->only(['search', 'client', 'month', 'category', 'status', 'priority']),
            'clients'         => $accessibleClients,
            'metrics'         => $metrics,
            'availableMonths' => $availableMonths,
            'categories'      => $categories,
            'canManage'       => $canManage,
        ]);
    }

    /**
     * Update complaint status and resolution notes.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $complaint = Complaint::findOrFail($id);

        $canManage = $user->is_super_admin 
            || $user->hasRole('Super Admin')
            || $user->hasRole('HR Manager') 
            || $user->hasRole('HR Officer') 
            || $user->hasRole('HRAudit')
            || $user->hasRole('Payroll Officer');

        if (!$canManage) {
            abort(403, 'Unauthorized: Only HR managers and officers can review and resolve complaints.');
        }

        if (!$user->is_super_admin && $user->clients()->exists()) {
            [, $allowedClientNames] = $this->getAccessibleClients($user);
            if (!in_array($complaint->client_name, $allowedClientNames)) {
                abort(403, 'Unauthorized: You do not have permission to manage complaints for this client.');
            }
        }
            if (!in_array($complaint->client_name, $allowedClientNames)) {
                abort(403, 'Unauthorized: You do not have permission to manage complaints for this client.');
            }
        }

        $validated = $request->validate([
            'status'           => ['required', 'in:pending,under_review,resolved,rejected'],
            'priority'         => ['required', 'in:low,medium,high,urgent'],
            'resolution_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $updateData = [
            'status'           => $validated['status'],
            'priority'         => $validated['priority'],
            'resolution_notes' => $validated['resolution_notes'] ?? null,
        ];

        if ($validated['status'] === 'resolved' || $validated['status'] === 'rejected') {
            $updateData['resolved_by'] = $request->user()->id;
            $updateData['resolved_at'] = now();
        }

        $complaint->update($updateData);

        return redirect()->back()->with('success', "Complaint ticket {$complaint->ticket_number} updated successfully.");
    }

    /**
     * Store manual complaint intake by HR Officer.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user->is_super_admin && $user->clients()->exists()) {
            [, $allowedClientNames] = $this->getAccessibleClients($user);
            if (!in_array($request->input('client_name'), $allowedClientNames)) {
                return redirect()->back()->withErrors(['client_name' => 'You do not have permission to register complaints for this client.']);
            }
        }

        $validated = $request->validate([
            'staff_id'       => ['required', 'string', 'max:50'],
            'employee_name'  => ['required', 'string', 'max:200'],
            'client_name'    => ['required', 'string', 'max:150'],
            'shop_name'      => ['nullable', 'string', 'max:150'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:150'],
            'month_period'   => ['required', 'string', 'max:50'],
            'category'       => ['required', 'string', 'max:100'],
            'priority'       => ['required', 'in:low,medium,high,urgent'],
            'complaint_text' => ['required', 'string', 'min:5', 'max:5000'],
        ]);

        $ticketNumber = Complaint::generateTicketNumber();

        $complaint = Complaint::create([
            'ticket_number'     => $ticketNumber,
            'client_name'       => $validated['client_name'],
            'staff_id'          => $validated['staff_id'],
            'employee_name'     => $validated['employee_name'],
            'shop_name'         => $validated['shop_name'] ?? null,
            'phone'             => $validated['phone'] ?? null,
            'email'             => $validated['email'] ?? null,
            'month_period'      => $validated['month_period'],
            'category'          => $validated['category'],
            'priority'          => $validated['priority'],
            'complaint_text'    => $validated['complaint_text'],
            'status'            => 'pending',
            'submission_source' => 'hr_manual',
        ]);

        return redirect()->back()->with('success', "Complaint ticket {$complaint->ticket_number} registered successfully.");
    }

    /**
     * Delete complaint (Super Admin only).
     */
    public function destroy(Request $request, $id)
    {
        if (!$request->user()->is_super_admin) {
            abort(403, 'Unauthorized action.');
        }

        $complaint = Complaint::findOrFail($id);
        $complaint->delete();

        return redirect()->back()->with('success', "Complaint record deleted.");
    }

    /**
     * Export filtered complaints to Excel spreadsheet.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        [, $allowedClientNames] = $this->getAccessibleClients($user);

        $query = Complaint::query()->with('resolver:id,name');

        if (!$user->is_super_admin && $user->clients()->exists()) {
            $query->whereIn('client_name', $allowedClientNames);
        }

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('staff_id', 'like', "%{$search}%")
                  ->orWhere('employee_name', 'like', "%{$search}%")
                  ->orWhere('complaint_text', 'like', "%{$search}%");
            });
        }
        if ($client = $request->input('client')) {
            $matchedClient = Client::where('client_name', $client)->orWhere('short_name', $client)->first();
            $possible = [$client];
            if ($matchedClient) {
                if ($matchedClient->client_name) $possible[] = $matchedClient->client_name;
                if ($matchedClient->short_name)  $possible[] = $matchedClient->short_name;
            }
            if (stripos($client, 'melcom') !== false) {
                $possible = array_merge($possible, ['Melcom', 'MELCOM', 'MELCOM LTD.', 'MELCOM LTD DRIVERS']);
            }
            $query->whereIn('complaints.client_name', array_values(array_unique(array_filter($possible))));
        }
        if ($month = $request->input('month')) {
            $query->where('month_period', $month);
        }
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $records = $query->orderBy('created_at', 'desc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Complaints');

        $headers = [
            'A1' => 'Ticket #',
            'B1' => 'Client',
            'C1' => 'Staff ID',
            'D1' => 'Employee Name',
            'E1' => 'Shop / Branch',
            'F1' => 'Phone',
            'G1' => 'Email',
            'H1' => 'Month Period',
            'I1' => 'Category',
            'J1' => 'Complaint Details',
            'K1' => 'Status',
            'L1' => 'Priority',
            'M1' => 'Resolved By',
            'N1' => 'Resolution Notes',
            'O1' => 'Date Submitted',
            'P1' => 'Date Resolved',
        ];

        foreach ($headers as $cell => $val) {
            $sheet->setCellValue($cell, $val);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        $row = 2;
        foreach ($records as $item) {
            $sheet->setCellValue("A{$row}", $item->ticket_number);
            $sheet->setCellValue("B{$row}", $item->client_name);
            $sheet->setCellValue("C{$row}", $item->staff_id);
            $sheet->setCellValue("D{$row}", $item->employee_name);
            $sheet->setCellValue("E{$row}", $item->shop_name ?? '');
            $sheet->setCellValue("F{$row}", $item->phone ?? '');
            $sheet->setCellValue("G{$row}", $item->email ?? '');
            $sheet->setCellValue("H{$row}", $item->month_period ?? '');
            $sheet->setCellValue("I{$row}", $item->category);
            $sheet->setCellValue("J{$row}", $item->complaint_text);
            $sheet->setCellValue("K{$row}", ucfirst($item->status));
            $sheet->setCellValue("L{$row}", ucfirst($item->priority));
            $sheet->setCellValue("M{$row}", $item->resolver->name ?? '');
            $sheet->setCellValue("N{$row}", $item->resolution_notes ?? '');
            $sheet->setCellValue("O{$row}", $item->created_at ? $item->created_at->format('Y-m-d H:i') : '');
            $sheet->setCellValue("P{$row}", $item->resolved_at ? $item->resolved_at->format('Y-m-d H:i') : '');
            $row++;
        }

        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = 'Employee_Complaints_' . date('Y_m_d_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
