<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use App\Models\LeaveReviewToken;
use App\Traits\SendsMNotifySMS;
use App\Traits\CalculatesWorkingDays;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EmployeeLeaveController extends Controller
{
    use SendsMNotifySMS, CalculatesWorkingDays;

    public function index(Request $request)
    {
        $query = LeaveApplication::query();
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('ida', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $allowedSorts = ['ida', 'name', 'type', 'date_start', 'start_date', 'status', 'created_at'];
        $sort = $request->input('sort', 'date_start');
        if (!in_array($sort, $allowedSorts) || !\Illuminate\Support\Facades\Schema::hasColumn('leaveapplications', $sort)) {
            $sort = \Illuminate\Support\Facades\Schema::hasColumn('leaveapplications', 'date_start') ? 'date_start' : 'id';
        }
        $order = strtolower($request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $order);
        $leaveApplications = $query->paginate(10)->withQueryString();

        // Calculate leave statistics
        $totalEntitlement = 15;
        $usedLeaveDays = 0;
        $pendingLeaveDays = 0;

        if ($employeeId = $request->input('ida_filter')) {
            $approvedLeaves = LeaveApplication::where('ida', $employeeId)
                ->where('status', 1)
                ->whereYear('date_start', Carbon::now()->year)
                ->get();

            foreach ($approvedLeaves as $leave) {
                $start = Carbon::parse($leave->date_start);
                $end = Carbon::parse($leave->date_end);
                $usedLeaveDays += $this->calculateWorkingDays($start, $end);
            }

            $pendingLeaves = LeaveApplication::where('ida', $employeeId)
                ->where('status', 0)
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
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
                'ida_filter' => $request->input('ida_filter'),
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

        $rules = [
            'ida' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
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
        $employeeId = $request->input('ida');
        $approvedLeaves = LeaveApplication::where('ida', $employeeId)
            ->where('status', 1)
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
            'ida' => $request->ida,
            'name' => $request->name,
            'type' => $request->type,
            'date_start' => $request->date_start,
            'date_end' => $request->date_end,
            'reason' => $request->reason,
            'managername' => $request->managername,
            'phone' => $request->phone,
            'status' => 0,
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
        $currentYear = Carbon::now()->year;

        if ($leave->status !== 0) {
            return redirect()->route('employee-leaves.index')->with('error', 'Cannot edit a leave application that is not pending.');
        }

        $rules = [
            'ida' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
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
        $employeeId = $request->input('ida');
        $approvedLeaves = LeaveApplication::where('ida', $employeeId)
            ->where('status', 1)
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

        $leave->update([
            'ida' => $request->ida,
            'name' => $request->name,
            'type' => $request->type,
            'date_start' => $request->date_start,
            'date_end' => $request->date_end,
            'reason' => $request->reason,
            'managername' => $request->managername,
            'phone' => $request->phone,
        ]);

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
    Log::warning('Failed to send SMS to manager for leave application', ['leave_id' => $leave->id, 'phone' => $phone]);
} else {
    Log::info('SMS sent successfully to manager for leave application', ['leave_id' => $leave->id, 'phone' => $phone, 'response' => $smsResponse]);
}

        return redirect()->route('employee-leaves.index')->with('success', 'Leave application updated successfully.');
    }

    public function destroy($id)
    {
        $leave = LeaveApplication::findOrFail($id);

        if ($leave->status !== 0) {
            return redirect()->route('employee-leaves.index')->with('error', 'Cannot delete a leave application that is not pending.');
        }

        $leave->delete();
        return redirect()->route('employee-leaves.index')->with('success', 'Leave application deleted successfully.');
    }
}
