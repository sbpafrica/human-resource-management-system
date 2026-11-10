<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Complaint;
use App\Models\Employee;
use App\Models\Contract;
use App\Models\BankDetail;
use App\Models\Ssnit;
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
     * Instant Staff ID Verification endpoint across all employee records.
     */
    public function verifyStaff(Request $request)
    {
        $rawStaffId = $request->input('staff_id') ?? $request->query('staff_id') ?? '';
        $staffId = trim(preg_replace('/[\x00-\x1F\x7F\xC2\xA0]/u', '', (string)$rawStaffId));

        if (empty($staffId)) {
            return response()->json(['found' => false]);
        }

        $variations = array_unique(array_filter([
            $staffId,
            strtoupper($staffId),
            strtolower($staffId),
            str_replace(['-', ' '], '', $staffId),
            preg_replace('/^SBP/i', '', $staffId),
            'SBP' . preg_replace('/^SBP/i', '', $staffId),
        ]));

        $normalizeClient = function ($rawClient) {
            if (empty($rawClient)) {
                return '';
            }
            $clean = trim($rawClient);
            $client = Client::where('is_active', 1)
                ->where(function ($q) use ($clean) {
                    $q->where('client_name', $clean)
                      ->orWhere('short_name', $clean)
                      ->orWhere('client_name', 'like', "%{$clean}%")
                      ->orWhere('short_name', 'like', "%{$clean}%");
                })
                ->first();

            if ($client) {
                return $client->client_name;
            }

            if (stripos($clean, 'melcom') !== false) {
                return 'MELCOM LTD.';
            }
            if (stripos($clean, 'nyonkopa') !== false) {
                return 'NYONKOPA COCOA BUYING LTD';
            }
            if (stripos($clean, 'twellium') !== false) {
                return 'TWELLIUM INDUSTRIAL COMPANY GHANA';
            }

            return $clean;
        };

        // 1. Check Employee (newemployeedata)
        $employee = Employee::whereIn('staffid', $variations)
            ->orWhere(function ($q) use ($staffId) {
                $q->whereRaw('LOWER(TRIM(staffid)) = ?', [strtolower($staffId)]);
            })
            ->first();

        if ($employee) {
            $phone = trim($employee->phone1 ?: ($employee->phone2 ?: ''));
            $shop  = trim($employee->shop ?? '');

            // Fallback enrichment if shop or phone is missing in master record
            if (empty($phone) || empty($shop)) {
                $contact = DB::table('employeecontact')
                    ->whereIn('staffid', $variations)
                    ->first();
                if ($contact) {
                    if (empty($phone) && !empty($contact->phone)) $phone = trim($contact->phone);
                    if (empty($shop) && !empty($contact->shop))   $shop  = trim($contact->shop);
                }
            }

            return response()->json([
                'found'         => true,
                'staff_id'      => $employee->staffid,
                'employee_name' => trim($employee->employeename),
                'client_name'   => $normalizeClient($employee->company),
                'shop_name'     => $shop,
                'phone'         => $phone,
                'email'         => trim($employee->email ?? ''),
                'source'        => 'master_employee',
            ]);
        }

        // 2. Check Employee Contact table (employeecontact)
        $contact = DB::table('employeecontact')
            ->whereIn('staffid', $variations)
            ->orWhere(function ($q) use ($staffId) {
                $q->whereRaw('LOWER(TRIM(staffid)) = ?', [strtolower($staffId)]);
            })
            ->first();

        if ($contact) {
            return response()->json([
                'found'         => true,
                'staff_id'      => $contact->staffid,
                'employee_name' => trim($contact->name),
                'client_name'   => $normalizeClient($contact->shop),
                'shop_name'     => trim($contact->shop ?? ''),
                'phone'         => trim($contact->phone ?? ''),
                'email'         => '',
                'source'        => 'employee_contact',
            ]);
        }

        // 3. Check Contract (contracts)
        $contract = Contract::whereIn('id', $variations)
            ->orWhere(function ($q) use ($staffId) {
                $q->whereRaw('LOWER(TRIM(id)) = ?', [strtolower($staffId)]);
            })
            ->first();

        if ($contract) {
            return response()->json([
                'found'         => true,
                'staff_id'      => $contract->id,
                'employee_name' => trim($contract->employeename),
                'client_name'   => $normalizeClient($contract->client ?: 'Melcom'),
                'shop_name'     => trim($contract->location ?? ''),
                'phone'         => trim($contract->contact ?? ''),
                'email'         => trim($contract->email ?? ''),
                'source'        => 'contract',
            ]);
        }

        // 3. Check BankDetail (bankdetails)
        $bank = BankDetail::whereIn('employid', $variations)
            ->orWhere(function ($q) use ($staffId) {
                $q->whereRaw('LOWER(TRIM(employid)) = ?', [strtolower($staffId)]);
            })
            ->first();

        if ($bank) {
            return response()->json([
                'found'         => true,
                'staff_id'      => $bank->employid,
                'employee_name' => trim($bank->name),
                'client_name'   => $normalizeClient($bank->shop),
                'shop_name'     => trim($bank->shop ?? ''),
                'phone'         => trim($bank->momonum ?? ''),
                'email'         => '',
                'source'        => 'bank_detail',
            ]);
        }

        // 4. Check Ssnit (ssnit)
        $ssnit = Ssnit::whereIn('staffid', $variations)
            ->orWhere(function ($q) use ($staffId) {
                $q->whereRaw('LOWER(TRIM(staffid)) = ?', [strtolower($staffId)]);
            })
            ->first();

        if ($ssnit) {
            $name = trim("{$ssnit->firstname} {$ssnit->othername} {$ssnit->lastname}");
            return response()->json([
                'found'         => true,
                'staff_id'      => $ssnit->staffid,
                'employee_name' => $name,
                'client_name'   => $normalizeClient($ssnit->shop),
                'shop_name'     => trim($ssnit->shop ?? ''),
                'phone'         => '',
                'email'         => '',
                'source'        => 'ssnit',
            ]);
        }

        // 5. Check historical complaints table
        $pastComplaint = Complaint::whereIn('staff_id', $variations)->latest()->first();
        if ($pastComplaint) {
            return response()->json([
                'found'         => true,
                'staff_id'      => $pastComplaint->staff_id,
                'employee_name' => trim($pastComplaint->employee_name),
                'client_name'   => $normalizeClient($pastComplaint->client_name),
                'shop_name'     => trim($pastComplaint->shop_name ?? ''),
                'phone'         => trim($pastComplaint->phone ?? ''),
                'email'         => trim($pastComplaint->email ?? ''),
                'source'        => 'historical_complaint',
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
