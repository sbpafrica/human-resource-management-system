<?php

namespace App\Http\Controllers;

use App\Models\LeaveApplication;
use App\Models\LeaveReviewToken;
use App\Models\Employee;
use App\Traits\SendsMNotifySMS;
use App\Traits\CalculatesWorkingDays;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ManagerLeaveReviewController extends Controller
{
    use SendsMNotifySMS, CalculatesWorkingDays;

    public function review(Request $request, $token)
    {
        Log::debug('Accessing leave review page with token: ' . $token);

        $reviewToken = LeaveReviewToken::where('token', $token)->first();

        if (!$reviewToken) {
            Log::warning('Invalid review token', ['token' => $token]);
            return Inertia::render('ManagerLeave/Review', [
                'error' => 'Invalid review link.',
                'leaveApplication' => null,
                'token' => null,
                'leaveDays' => null,
            ]);
        }

        if (!$reviewToken->isValid()) {
            Log::warning('Expired review token', ['token' => $token]);
            return Inertia::render('ManagerLeave/Review', [
                'error' => 'This review link is invalid or has expired.',
                'leaveApplication' => null,
                'token' => null,
                'leaveDays' => null,
            ]);
        }

        $leaveApplication = $reviewToken->leaveApplication;

        if (!$leaveApplication || $leaveApplication->status !== 0) {
            Log::warning('Leave application already processed or not found', ['leave_id' => $leaveApplication?->id]);
            return Inertia::render('ManagerLeave/Review', [
                'error' => 'This leave application has already been processed or does not exist.',
                'leaveApplication' => null,
                'token' => null,
                'leaveDays' => null,
            ]);
        }

        // Calculate leave days
        $leaveDays = $this->calculateWorkingDays(
            Carbon::parse($leaveApplication->date_start),
            Carbon::parse($leaveApplication->date_end)
        );

        return Inertia::render('ManagerLeave/Review', [
            'leaveApplication' => $leaveApplication,
            'token' => $token,
            'error' => null,
            'success' => null,
            'leaveDays' => $leaveDays,
        ]);
    }

    public function process(Request $request, $token)
    {
        Log::debug('Processing leave review with token: ' . $token, ['request_data' => $request->all()]);

        $reviewToken = LeaveReviewToken::where('token', $token)->first();

        if (!$reviewToken || !$reviewToken->isValid()) {
            Log::warning('Invalid or expired review token', ['token' => $token]);
            return Inertia::render('ManagerLeave/Review', [
                'error' => 'This review link is invalid or has expired.',
                'leaveApplication' => null,
                'token' => null,
                'leaveDays' => null,
            ])->with('error', 'This review link is invalid or has expired.');
        }

        $leaveApplication = $reviewToken->leaveApplication;

        if (!$leaveApplication || $leaveApplication->status !== 0) {
            Log::warning('Leave application already processed or not found', ['leave_id' => $leaveApplication?->id]);
            return Inertia::render('ManagerLeave/Review', [
                'error' => 'This leave application has already been processed or does not exist.',
                'leaveApplication' => null,
                'token' => null,
                'leaveDays' => null,
            ])->with('error', 'This leave application has already been processed or does not exist.');
        }

        $request->validate([
            'action' => ['required', 'in:approve,deny'],
        ], [
            'action.required' => 'Please select an action (Approve or Deny).',
            'action.in' => 'Invalid action selected.',
        ], ['action' => 'Action']);

        try {
            DB::transaction(function () use ($request, $leaveApplication, $reviewToken) {
                $status = $request->action === 'approve' ? 1 : 2;
                $leaveApplication->update(['status' => $status]);
                $reviewToken->update(['used' => true]);

                // Send SMS to employee
                $employee = Employee::where('staffid', $leaveApplication->ida)->first();
                if ($employee && $employee->phone1) {
                    $employeePhone = $employee->phone1;
                    if (substr($employeePhone, 0, 1) === '0') {
                        $employeePhone = '+233' . substr($employeePhone, 1);
                    }
                    $startDate = Carbon::parse($leaveApplication->date_start)->format('jS F, Y');
                    $endDate = Carbon::parse($leaveApplication->date_end)->format('jS F, Y');
                    $msg = "Your leave request from {$startDate} to {$endDate} has been " . ($status === 1 ? 'approved' : 'denied') . " by {$leaveApplication->managername}.";
                    $smsResponse = $this->sendSMS($employeePhone, $msg);

                    if ($smsResponse === false) {
                        Log::warning('Failed to send SMS to employee for leave status update', [
                            'leave_id' => $leaveApplication->id,
                            'phone' => $employeePhone,
                        ]);
                    } else {
                        Log::info('SMS sent successfully to employee for leave status update', [
                            'leave_id' => $leaveApplication->id,
                            'phone' => $employeePhone,
                        ]);
                    }
                } else {
                    Log::warning('Employee phone number not found for leave status update', [
                        'leave_id' => $leaveApplication->id,
                        'staffid' => $leaveApplication->ida,
                    ]);
                }
            });

            return Inertia::render('ManagerLeave/Review', [
                'leaveApplication' => null,
                'token' => null,
                'success' => 'Leave application has been ' . ($request->action === 'approve' ? 'approved' : 'denied') . ' successfully.',
                'error' => null,
                'leaveDays' => null,
            ])->with('success', 'Leave application has been ' . ($request->action === 'approve' ? 'approved' : 'denied') . ' successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to process leave application', [
                'token' => $token,
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);
            return Inertia::render('ManagerLeave/Review', [
                'leaveApplication' => $leaveApplication,
                'token' => $token,
                'error' => 'Failed to process leave application: ' . $e->getMessage(),
                'success' => null,
                'leaveDays' => $this->calculateWorkingDays(
                    Carbon::parse($leaveApplication->date_start),
                    Carbon::parse($leaveApplication->date_end)
                ),
            ])->with('error', 'Failed to process leave application: ' . $e->getMessage());
        }
    }
}
