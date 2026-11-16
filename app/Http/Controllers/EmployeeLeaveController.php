<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use App\Models\LeaveReviewToken;
use App\Models\Client;
use App\Traits\SendsMNotifySMS;
use App\Traits\CalculatesWorkingDays;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EmployeeLeaveController extends Controller
{
    use SendsMNotifySMS, CalculatesWorkingDays;

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
     * Resolve aliases for a given client name.
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
     * AJAX endpoint to lookup employee details by Staff ID.
     */
    public function lookupEmployee($staffid)
    {
        $cleanId = trim((string) $staffid);
        if ($cleanId === '') {
            return response()->json(['found' => false]);
        }

        $emp = DB::table('newemployeedata')
            ->whereRaw('TRIM(staffid) = ?', [$cleanId])
            ->first();

        if ($emp) {
            return response()->json([
                'found' => true,
                'staffid' => trim($emp->staffid),
                'name' => $emp->employeename ?? '',
                'company' => $emp->company ?: ($emp->shop ?: ''),
                'department' => $emp->shop ?? '',
                'position' => $emp->position ?? '',
                'phone' => $emp->phone1 ?: ($emp->phone2 ?: ''),
            ]);
        }

        $contact = DB::table('employeecontact')
            ->whereRaw('TRIM(staffid) = ?', [$cleanId])
            ->first();

        if ($contact) {
            return response()->json([
                'found' => true,
                'staffid' => trim($contact->staffid),
                'name' => $contact->name ?? '',
                'company' => $contact->shop ?? '',
                'department' => $contact->shop ?? '',
                'position' => '',
                'phone' => $contact->phone ?? '',
            ]);
        }

        return response()->json(['found' => false]);
    }

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

        if ($search = trim($request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('leaveapplications.type', 'like', "%{$search}%")
                  ->orWhere('leaveapplications.reason', 'like', "%{$search}%")
                  ->orWhere('leaveapplications.ida', 'like', "%{$search}%")
                  ->orWhere('leaveapplications.name', 'like', "%{$search}%")
                  ->orWhere('newemployeedata.employeename', 'like', "%{$search}%")
                  ->orWhere('leaveapplications.company', 'like', "%{$search}%")
                  ->orWhere('newemployeedata.company', 'like', "%{$search}%");
            });
        }

        if ($idaFilter = trim($request->input('ida_filter', ''))) {
            $query->where(function ($q) use ($idaFilter) {
                $q->where('leaveapplications.ida', $idaFilter)
                  ->orWhere('leaveapplications.staffid', $idaFilter);
            });
        }

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

        $leaveApplications = $query->paginate(10)->withQueryString();

        // Calculate leave statistics
        $totalEntitlement = 15;
        $usedLeaveDays = 0;
        $pendingLeaveDays = 0;

        if ($employeeId = $request->input('ida_filter')) {
            $approvedLeaves = LeaveApplication::where(function ($q) use ($employeeId) {
                    $q->where('ida', $employeeId)->orWhere('staffid', $employeeId);
                })
                ->whereIn('status', ['approved', 1, '1'])
                ->whereYear('date_start', Carbon::now()->year)
                ->get();

            foreach ($approvedLeaves as $leave) {
                $start = Carbon::parse($leave->date_start);
                $end = Carbon::parse($leave->date_end);
                $usedLeaveDays += $this->calculateWorkingDays($start, $end);
            }

            $pendingLeaves = LeaveApplication::where(function ($q) use ($employeeId) {
                    $q->where('ida', $employeeId)->orWhere('staffid', $employeeId);
                })
                ->whereIn('status', ['pending', 0, '0'])
                ->whereYear('date_start', Carbon::now()->year)
                ->get();

            foreach ($pendingLeaves as $leave) {
                $start = Carbon::parse($leave->date_start);
                $end = Carbon::parse($leave->date_end);
                $pendingLeaveDays += $this->calculateWorkingDays($start, $end);
            }
        }

        return Inertia::render('EmployeeLeave/Index', [
            'leaveApplications' => $leaveApplications,
            'clients' => $accessibleClients,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
                'ida_filter' => $request->input('ida_filter'),
                'client' => $selectedClient ?? 'all',
            ],
            'leaveStats' => [
                'entitlement' => $totalEntitlement,
                'used' => $usedLeaveDays,
                'remaining' => max(0, $totalEntitlement - $usedLeaveDays),
                'pending' => $pendingLeaveDays,
            ],
            'flash' => [
                'success' => $request->hasSession() ? $request->session()->get('success') : null,
                'error' => $request->hasSession() ? $request->session()->get('error') : null,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $currentYear = Carbon::now()->year;
        $user = $request->user();

        $rules = [
            'ida' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'date_start' => ['required', 'date', 'after_or_equal:today'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'reason' => ['required', 'string', 'max:1000'],
            'managername' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:10', 'regex:/^[0-9]{10}$/'],
        ];

        $validator = Validator::make($request->all(), $rules);

        // Auto-resolve company if not passed
        $company = $request->input('company');
        $staffId = trim($request->input('ida'));

        if (empty($company)) {
            $emp = DB::table('newemployeedata')->whereRaw('TRIM(staffid) = ?', [$staffId])->first();
            $company = $emp?->company ?: ($emp?->shop ?: null);
            if (!$company) {
                $contact = DB::table('employeecontact')->whereRaw('TRIM(staffid) = ?', [$staffId])->first();
                $company = $contact?->shop ?: null;
            }
        }

        // Check if user is restricted to specific clients
        if (!$user->is_super_admin && $user->clients()->exists()) {
            [$accessibleClients, $allowedClientNames] = $this->getAccessibleClients($user);
            if ($company && !in_array($company, $allowedClientNames, true)) {
                $validator->errors()->add('company', 'Unauthorized: You can only apply leave for staff belonging to your assigned clients.');
            }
        }

        // Validate leave entitlement
        $totalEntitlement = 15;
        $approvedLeaves = LeaveApplication::where(function ($q) use ($staffId) {
                $q->where('ida', $staffId)->orWhere('staffid', $staffId);
            })
            ->whereIn('status', ['approved', 1, '1'])
            ->whereYear('date_start', $currentYear)
            ->get();

        $usedLeaveDays = 0;
        foreach ($approvedLeaves as $leave) {
            $start = Carbon::parse($leave->date_start);
            $end = Carbon::parse($leave->date_end);
            $usedLeaveDays += $this->calculateWorkingDays($start, $end);
        }

        $newLeaveDays = $this->calculateWorkingDays(
            Carbon::parse($request->date_start),
            Carbon::parse($request->date_end)
        );

        if ($usedLeaveDays + $newLeaveDays > $totalEntitlement) {
            $validator->errors()->add('date_end', 'Requested leave exceeds the employee\'s remaining entitlement of ' . ($totalEntitlement - $usedLeaveDays) . ' working days.');
        }

        if ($validator->fails()) {
            Log::error('Leave application validation failed', ['errors' => $validator->errors()]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to create leave application: ' . implode(' | ', $validator->errors()->all()));
        }

        $leave = LeaveApplication::create([
            'ida' => $staffId,
            'staffid' => $staffId,
            'name' => $request->name,
            'company' => $company,
            'type' => $request->type,
            'leave_type' => $request->type,
            'date_start' => $request->date_start,
            'start_date' => $request->date_start,
            'date_end' => $request->date_end,
            'end_date' => $request->date_end,
            'reason' => $request->reason,
            'managername' => $request->managername,
            'phone' => $request->phone,
            'status' => 'pending',
        ]);

        // Create review token and send SMS
        $token = LeaveReviewToken::create(['leave_application_id' => $leave->id]);
        $reviewUrl = route('manager-leave.review', ['token' => $token->token]);

        $phone = $request->phone;
        if (substr($phone, 0, 1) === '0') {
            $phone = '+233' . substr($phone, 1);
        }
        $msg = "An Employee {$request->name} has submitted a leave request. Click on the link to review: $reviewUrl";
        $smsResponse = $this->sendSMS($phone, $msg);

        if ($smsResponse === false) {
            Log::warning('Failed to send SMS to manager for leave application', ['leave_id' => $leave->id, 'phone' => $phone]);
        }

        return redirect()->route('employee-leaves.index')->with('success', 'Leave application submitted successfully.');
    }

    public function update(Request $request, $id)
    {
        $leave = LeaveApplication::findOrFail($id);
        $user = $request->user();
        $currentYear = Carbon::now()->year;

        // Check if user has access to this leave's client
        if (!$user->is_super_admin && $user->clients()->exists()) {
            [$accessibleClients, $allowedClientNames] = $this->getAccessibleClients($user);
            $company = $leave->company;
            if (!$company) {
                $emp = DB::table('newemployeedata')->whereRaw('TRIM(staffid) = ?', [trim($leave->ida)])->first();
                $company = $emp?->company;
            }
            if (!$company || !in_array($company, $allowedClientNames, true)) {
                return redirect()->route('employee-leaves.index')->with('error', 'Unauthorized: You can only edit leave applications for your assigned clients.');
            }
        }

        if (!$leave->isPending()) {
            return redirect()->route('employee-leaves.index')->with('error', 'Cannot edit a leave application that is not pending.');
        }

        $rules = [
            'ida' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'date_start' => ['required', 'date', 'after_or_equal:today'],
            'date_end' => ['required', 'date', 'after_or_equal:date_start'],
            'reason' => ['required', 'string', 'max:1000'],
            'managername' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:10', 'regex:/^[0-9]{10}$/'],
        ];

        $validator = Validator::make($request->all(), $rules);

        // Validate leave entitlement
        $totalEntitlement = 15;
        $employeeId = trim($request->input('ida'));
        $approvedLeaves = LeaveApplication::where(function ($q) use ($employeeId) {
                $q->where('ida', $employeeId)->orWhere('staffid', $employeeId);
            })
            ->whereIn('status', ['approved', 1, '1'])
            ->whereYear('date_start', $currentYear)
            ->where('id', '!=', $id)
            ->get();

        $usedLeaveDays = 0;
        foreach ($approvedLeaves as $approvedLeave) {
            $start = Carbon::parse($approvedLeave->date_start);
            $end = Carbon::parse($approvedLeave->date_end);
            $usedLeaveDays += $this->calculateWorkingDays($start, $end);
        }

        $newLeaveDays = $this->calculateWorkingDays(
            Carbon::parse($request->date_start),
            Carbon::parse($request->date_end)
        );

        if ($usedLeaveDays + $newLeaveDays > $totalEntitlement) {
            $validator->errors()->add('date_end', 'Requested leave exceeds the employee\'s remaining entitlement of ' . ($totalEntitlement - $usedLeaveDays) . ' working days.');
        }

        if ($validator->fails()) {
            Log::error('Leave application update failed', ['errors' => $validator->errors()]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to update leave application: ' . implode(' | ', $validator->errors()->all()));
        }

        $updateData = [
            'ida' => $employeeId,
            'staffid' => $employeeId,
            'name' => $request->name,
            'type' => $request->type,
            'leave_type' => $request->type,
            'date_start' => $request->date_start,
            'start_date' => $request->date_start,
            'date_end' => $request->date_end,
            'end_date' => $request->date_end,
            'reason' => $request->reason,
            'managername' => $request->managername,
            'phone' => $request->phone,
        ];

        if ($request->filled('company')) {
            $updateData['company'] = $request->company;
        }

        $leave->update($updateData);

        // Create new review token and send SMS
        LeaveReviewToken::where('leave_application_id', $leave->id)->update(['used' => true]);
        $token = LeaveReviewToken::create(['leave_application_id' => $leave->id]);
        $reviewUrl = route('manager-leave.review', ['token' => $token->token]);

        $phone = $request->phone;
        if (substr($phone, 0, 1) === '0') {
            $phone = '+233' . substr($phone, 1);
        }
        $msg = "An Employee {$request->name} has submitted a leave request. Click on the link to review: $reviewUrl";
        $smsResponse = $this->sendSMS($phone, $msg);

        if ($smsResponse === false) {
            Log::warning('Failed to send SMS to manager for leave application update', ['leave_id' => $leave->id, 'phone' => $phone]);
        }

        return redirect()->route('employee-leaves.index')->with('success', 'Leave application updated successfully.');
    }

    public function destroy(Request $request, $id)
    {
        $leave = LeaveApplication::findOrFail($id);
        $user = $request->user();

        // Check if user has access to this leave's client
        if (!$user->is_super_admin && $user->clients()->exists()) {
            [$accessibleClients, $allowedClientNames] = $this->getAccessibleClients($user);
            $company = $leave->company;
            if (!$company) {
                $emp = DB::table('newemployeedata')->whereRaw('TRIM(staffid) = ?', [trim($leave->ida)])->first();
                $company = $emp?->company;
            }
            if (!$company || !in_array($company, $allowedClientNames, true)) {
                return redirect()->route('employee-leaves.index')->with('error', 'Unauthorized: You can only delete leave applications for your assigned clients.');
            }
        }

        if (!$leave->isPending()) {
            return redirect()->route('employee-leaves.index')->with('error', 'Cannot delete a leave application that is not pending.');
        }

        $leave->delete();
        return redirect()->route('employee-leaves.index')->with('success', 'Leave application deleted successfully.');
    }
}
