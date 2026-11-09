<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Complaint;
use App\Models\Employee;
use App\Models\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ComplaintSubmissionController extends Controller
{
    /**
     * Show public complaint submission portal.
     */
    public function create(Request $request)
    {
        $clients = Client::where('is_active', 1)
            ->orderBy('client_name')
            ->get(['id', 'client_name', 'short_name']);

        // Generate list of the last 12 months for easy selection
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $months[] = date('F Y', strtotime("-{$i} month"));
        }

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

        return Inertia::render('Complaints/Submit', [
            'clients'       => $clients,
            'months'        => $months,
            'categories'    => $categories,
            'prefillClient' => $request->query('client', ''),
        ]);
    }

    /**
     * Instant Staff ID Verification endpoint.
     */
    public function verifyStaff(Request $request)
    {
        $staffId = trim($request->input('staff_id', ''));
        if (empty($staffId)) {
            return response()->json(['found' => false]);
        }

        // Try exact match first in newemployeedata
        $employee = Employee::where('staffid', $staffId)->first();

        // Try case-insensitive or trimmed match
        if (!$employee) {
            $employee = Employee::whereRaw('LOWER(TRIM(staffid)) = ?', [strtolower($staffId)])->first();
        }

        // Try contracts table fallback
        if (!$employee) {
            $contract = Contract::where('id', $staffId)
                ->orWhereRaw('LOWER(TRIM(id)) = ?', [strtolower($staffId)])
                ->first();

            if ($contract) {
                return response()->json([
                    'found'         => true,
                    'staff_id'      => $contract->id,
                    'employee_name' => $contract->employeename,
                    'client_name'   => $contract->client ?: 'Melcom',
                    'shop_name'     => $contract->location,
                    'phone'         => null,
                    'email'         => $contract->email,
                ]);
            }
        }

        if ($employee) {
            return response()->json([
                'found'         => true,
                'staff_id'      => $employee->staffid,
                'employee_name' => $employee->employeename,
                'client_name'   => $employee->company,
                'shop_name'     => $employee->shop,
                'phone'         => $employee->phone1 ?: $employee->phone2,
                'email'         => $employee->email,
            ]);
        }

        return response()->json(['found' => false]);
    }

    /**
     * Submit a new complaint.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'staff_id'       => ['required', 'string', 'max:50'],
            'employee_name'  => ['required', 'string', 'max:200'],
            'client_name'    => ['required', 'string', 'max:150'],
            'shop_name'      => ['nullable', 'string', 'max:150'],
            'phone'          => ['required', 'string', 'max:50'],
            'email'          => ['nullable', 'email', 'max:150'],
            'month_period'   => ['required', 'string', 'max:50'],
            'category'       => ['required', 'string', 'max:100'],
            'complaint_text' => ['required', 'string', 'min:10', 'max:5000'],
            'attachment'     => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $ticketNumber = Complaint::generateTicketNumber();

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $subDir = 'complaints/attachments/' . date('Y/m');
            $destName = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
            $attachmentPath = $file->storeAs($subDir, $destName);
        }

        $complaint = Complaint::create([
            'ticket_number'     => $ticketNumber,
            'client_name'       => $validated['client_name'],
            'staff_id'          => $validated['staff_id'],
            'employee_name'     => $validated['employee_name'],
            'shop_name'         => $validated['shop_name'] ?? null,
            'phone'             => $validated['phone'],
            'email'             => $validated['email'] ?? null,
            'month_period'      => $validated['month_period'],
            'category'          => $validated['category'],
            'complaint_text'    => $validated['complaint_text'],
            'attachment_path'   => $attachmentPath,
            'status'            => 'pending',
            'priority'          => 'medium',
            'submission_source' => 'portal',
        ]);

        return redirect()->route('complaints.track', ['ticket' => $complaint->ticket_number])
            ->with('success', "Your complaint has been submitted successfully! Your Ticket Number is {$complaint->ticket_number}. Please save it for reference.");
    }

    /**
     * Public self-service tracking page for staff.
     */
    public function track(Request $request)
    {
        $ticketQuery = trim($request->input('ticket', ''));
        $complaint = null;

        if ($ticketQuery) {
            $complaint = Complaint::where('ticket_number', $ticketQuery)
                ->orWhere('staff_id', $ticketQuery)
                ->orderBy('created_at', 'desc')
                ->first();
        }

        return Inertia::render('Complaints/Track', [
            'ticketQuery' => $ticketQuery,
            'complaint'   => $complaint,
        ]);
    }
}
