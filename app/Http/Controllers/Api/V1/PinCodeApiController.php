<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Employee;
use App\Models\EmployeePin;
use App\Models\TwelliumPin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PinCodeApiController extends BaseApiController
{
    /**
     * List employee PIN codes.
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type', 'general'); // general or twellium
        $search = $request->input('search');
        $perPage = min((int) $request->input('per_page', 25), 100);

        if ($type === 'twellium') {
            $query = TwelliumPin::query();
            if ($search) {
                $query->where('id', 'like', "%{$search}%")
                      ->orWhere('staff_id', 'like', "%{$search}%");
            }
            $records = $query->paginate($perPage);
        } else {
            $query = EmployeePin::query();
            if ($search) {
                $query->where('id', 'like', "%{$search}%")
                      ->orWhere('staff_id', 'like', "%{$search}%")
                      ->orWhere('client', 'like', "%{$search}%");
            }
            if ($client = $request->input('client')) {
                $query->where('client', $client);
            }
            $records = $query->paginate($perPage);
        }

        return $this->sendResponse([
            'type'         => $type,
            'items'        => $records->items(),
            'current_page' => $records->currentPage(),
            'total_pages'  => $records->lastPage(),
            'total_items'  => $records->total(),
        ], 'PIN codes retrieved.');
    }

    /**
     * Generate or assign 4-digit PIN code.
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => ['required', 'string'],
            'client'   => ['nullable', 'string'],
            'type'     => ['nullable', 'string', 'in:general,twellium'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $staffId = $request->staff_id;
        $type = $request->input('type', 'general');

        if ($type === 'twellium') {
            $pin = TwelliumPin::generateUniquePincode();
            $record = TwelliumPin::updateOrCreate(
                ['id' => $staffId],
                [
                    'staff_id' => $staffId,
                    'company'  => $request->client ?? 'Twellium',
                    'password' => $pin,
                ]
            );
        } else {
            $pin = EmployeePin::generateUniquePincode();
            $record = EmployeePin::updateOrCreate(
                ['id' => $staffId],
                [
                    'staff_id' => $staffId,
                    'client'   => $request->client,
                    'pincode'  => (string) $pin,
                ]
            );
        }

        return $this->sendResponse([
            'staff_id' => $staffId,
            'pincode'  => (string) $pin,
            'type'     => $type,
        ], 'PIN code generated successfully.', 201);
    }

    /**
     * Send PIN code via SMS to employee phone using mNotify.
     */
    public function sendSms(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => ['required', 'string'],
            'phone'    => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $staffId = $request->staff_id;
        $pinRecord = EmployeePin::where('id', $staffId)->orWhere('staff_id', $staffId)->first()
            ?? TwelliumPin::where('id', $staffId)->orWhere('staff_id', $staffId)->first();

        if (!$pinRecord) {
            return $this->sendError("No PIN code found for staff ID '{$staffId}'.", null, 404);
        }

        $pincode = $pinRecord->pincode ?? $pinRecord->password;

        // Auto-resolve phone number if not supplied in request
        $phone = $request->phone;
        if (!$phone) {
            $emp = Employee::where('staffid', $staffId)->first();
            $phone = $emp?->phone1 ?? $emp?->phone2;
        }

        if (!$phone) {
            return $this->sendError('Phone number not found for this employee.', null, 422);
        }

        $apiKey   = (string) config('services.mnotify.api_key', config('services.mnotify.key'));
        $senderId = (string) config('services.mnotify.sender_id', 'SBPAFRICA');

        if (empty($apiKey)) {
            return $this->sendResponse([
                'staff_id' => $staffId,
                'phone'    => $phone,
                'pincode'  => $pincode,
                'status'   => 'mock_sent',
                'note'     => 'mNotify API key not configured in .env',
            ], 'SMS dispatch mocked (no API key set).');
        }

        // Format Ghana phone number
        $clean = preg_replace('/\D/', '', $phone);
        if (str_starts_with($clean, '0') && strlen($clean) === 10) {
            $formattedPhone = '+233' . substr($clean, 1);
        } elseif (str_starts_with($clean, '233') && strlen($clean) === 12) {
            $formattedPhone = '+' . $clean;
        } else {
            $formattedPhone = $phone;
        }

        $msgText = "Click on the link below to check your Pay slip\nStaffID: {$staffId}\nPinCode: {$pincode}\nDetails: https://employee.sbpafrica.group";

        try {
            $response = Http::withoutVerifying()->timeout(15)->withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post("https://api.mnotify.com/api/sms/quick?key=" . urlencode($apiKey), [
                'recipient'     => [$formattedPhone],
                'sender'        => $senderId,
                'message'       => $msgText,
                'is_schedule'   => false,
                'schedule_date' => '',
            ]);

            return $this->sendResponse([
                'staff_id' => $staffId,
                'phone'    => $formattedPhone,
                'sent'     => $response->successful(),
            ], 'SMS dispatched successfully.');
        } catch (\Throwable $e) {
            return $this->sendError('Error sending SMS: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Delete PIN record.
     */
    public function destroy(string $staffid): JsonResponse
    {
        $deleted = EmployeePin::where('id', $staffid)->orWhere('staff_id', $staffid)->delete()
            + TwelliumPin::where('id', $staffid)->orWhere('staff_id', $staffid)->delete();

        if ($deleted === 0) {
            return $this->sendError('PIN code not found.', null, 404);
        }

        return $this->sendResponse(null, 'PIN code deleted.');
    }
}
