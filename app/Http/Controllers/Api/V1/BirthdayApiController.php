<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\BirthdayLog;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class BirthdayApiController extends BaseApiController
{
    /**
     * Get upcoming or today's birthdays.
     */
    public function upcoming(Request $request): JsonResponse
    {
        $todayMonth = now()->format('m');
        $todayDay   = now()->format('d');

        // Employees with birthdays today
        $todayBirthdays = Employee::query()
            ->whereNotNull('dateofbirth')
            ->whereRaw("SUBSTR(dateofbirth, 6, 5) = ?", ["{$todayMonth}-{$todayDay}"])
            ->get(['staffid', 'employeename', 'company', 'shop', 'phone1', 'dateofbirth']);

        // Employees with birthdays this month
        $monthBirthdays = Employee::query()
            ->whereNotNull('dateofbirth')
            ->whereRaw("SUBSTR(dateofbirth, 6, 2) = ?", [$todayMonth])
            ->orderByRaw("SUBSTR(dateofbirth, 6, 5) asc")
            ->limit(30)
            ->get(['staffid', 'employeename', 'company', 'shop', 'phone1', 'dateofbirth']);

        return $this->sendResponse([
            'today'      => $todayBirthdays,
            'this_month' => $monthBirthdays,
        ], 'Upcoming birthdays retrieved.');
    }

    /**
     * List automated birthday greeting logs.
     */
    public function logs(Request $request): JsonResponse
    {
        $query = BirthdayLog::query()->latest('id');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $logs = $query->paginate(25);

        return $this->sendResponse([
            'items'        => $logs->items(),
            'current_page' => $logs->currentPage(),
            'total_pages'  => $logs->lastPage(),
            'total_items'  => $logs->total(),
        ], 'Birthday logs retrieved.');
    }

    /**
     * Send instant birthday greeting SMS via mNotify.
     */
    public function sendSms(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => ['required', 'string'],
            'message'  => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $employee = Employee::find($request->staff_id);
        if (!$employee) {
            return $this->sendError('Employee not found.', null, 404);
        }

        $phone = $employee->phone1 ?? $employee->phone2;
        if (!$phone) {
            return $this->sendError('Employee has no phone number on record.', null, 422);
        }

        $apiKey   = (string) config('services.mnotify.api_key', config('services.mnotify.key'));
        $senderId = (string) config('services.mnotify.sender_id', 'SBPAFRICA');

        $defaultMessage = "Dear {$employee->employeename}, SBP Africa wishes you a very Happy Birthday! May your day be filled with joy, peace, and prosperity.";
        $messageText = $request->message ?: $defaultMessage;

        // Clean and format Ghana phone number
        $clean = preg_replace('/\D/', '', $phone);
        if (str_starts_with($clean, '0') && strlen($clean) === 10) {
            $formattedPhone = '+233' . substr($clean, 1);
        } elseif (str_starts_with($clean, '233') && strlen($clean) === 12) {
            $formattedPhone = '+' . $clean;
        } else {
            $formattedPhone = $phone;
        }

        if (empty($apiKey)) {
            // Mock entry if no key
            $log = BirthdayLog::create([
                'staffid'       => $employee->staffid,
                'name'          => $employee->employeename,
                'company'       => $employee->company,
                'shop'          => $employee->shop,
                'phone'         => $formattedPhone,
                'message'       => $messageText,
                'status'        => 'sent',
                'sent_date'     => now()->toDateString(),
            ]);

            return $this->sendResponse($log, 'Birthday SMS logged (mocked - no API key).');
        }

        try {
            $response = Http::withoutVerifying()->timeout(15)->withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post("https://api.mnotify.com/api/sms/quick?key=" . urlencode($apiKey), [
                'recipient'     => [$formattedPhone],
                'sender'        => $senderId,
                'message'       => $messageText,
                'is_schedule'   => false,
            ]);

            $isSuccess = $response->successful();

            $log = BirthdayLog::create([
                'staffid'        => $employee->staffid,
                'name'           => $employee->employeename,
                'company'        => $employee->company,
                'shop'           => $employee->shop,
                'phone'          => $formattedPhone,
                'message'        => $messageText,
                'status'         => $isSuccess ? 'sent' : 'failed',
                'api_response'   => $response->body(),
                'sent_date'      => now()->toDateString(),
            ]);

            return $this->sendResponse($log, 'Birthday SMS dispatched.');
        } catch (\Throwable $e) {
            return $this->sendError('Failed to send SMS: ' . $e->getMessage(), null, 500);
        }
    }
}
