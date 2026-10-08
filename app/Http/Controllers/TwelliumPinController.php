<?php

namespace App\Http\Controllers;

use App\Models\TwelliumPin;
use App\Models\EmployeeContact;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TwelliumPinController extends Controller
{
    private string $apiKey;
    private string $senderId;

    public function __construct()
    {
        $this->apiKey   = (string) config('services.mnotify.api_key', config('services.mnotify.key'));
        $this->senderId = (string) config('services.mnotify.sender_id', 'SBPAFRICA');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $isSuperOrFullAccess = $user->is_super_admin || !$user->clients()->exists();

        $query = DB::table('userstwi');

        if (!$isSuperOrFullAccess) {
            $userClients = $user->clients()->where('is_active', 1)->get();
            $allowedNames = [];
            foreach ($userClients as $cl) {
                if ($cl->client_name) $allowedNames[] = $cl->client_name;
                if ($cl->short_name) $allowedNames[] = $cl->short_name;
                if ($cl->client_dbname) $allowedNames[] = $cl->client_dbname;
            }
            $allowedNames = array_values(array_unique(array_filter($allowedNames)));

            if (!empty($allowedNames)) {
                $query->where(function ($q) use ($allowedNames) {
                    $q->whereIn('company', $allowedNames);
                    foreach ($allowedNames as $name) {
                        $q->orWhere('company', 'like', "{$name}%");
                    }
                });
            }
        }

        $search = trim((string) $request->input('search', ''));
        $searchCount = null;

        if ($search !== '') {
            $matchingStaffIds = DB::table('employeecontact')
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('shop', 'like', "%{$search}%")
                ->limit(200)
                ->pluck('staffid')
                ->toArray();

            $query->where(function ($q) use ($search, $matchingStaffIds) {
                $q->where('staff_id', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('password', 'like', "%{$search}%");

                if (!empty($matchingStaffIds)) {
                    $q->orWhereIn('staff_id', $matchingStaffIds)
                      ->orWhereIn('id', $matchingStaffIds);
                }
            });

            $searchCount = (clone $query)->count();
        }

        $totalRecords = $search !== '' ? $searchCount : (clone $query)->count();

        $sort = $request->input('sort', 'staff_id');
        $order = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortMap = [
            'id'       => 'id',
            'staff_id' => 'staff_id',
            'company'  => 'company',
            'password' => 'password',
            'pincode'  => 'password',
        ];

        $sortColumn = $sortMap[$sort] ?? 'staff_id';
        $query->orderBy($sortColumn, $order);

        $pins = $query->paginate(15)->withQueryString();

        // Fast batch-fetch contact details for only the 15 records on this page
        $pageStaffIds = collect($pins->items())
            ->map(fn($row) => $row->staff_id ?? $row->id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $contacts = !empty($pageStaffIds)
            ? DB::table('employeecontact')->whereIn('staffid', $pageStaffIds)->get()->keyBy('staffid')
            : collect();

        $pins->setCollection($pins->getCollection()->map(function ($row) use ($contacts) {
            $staffId = (string) ($row->staff_id ?? $row->id ?? '');
            $contact = $contacts->get($staffId);

            return (object) [
                'id'       => (string) ($row->id ?? $staffId),
                'staff_id' => $staffId,
                'company'  => (string) ($row->company ?? 'N/A'),
                'password' => (string) ($row->password ?? ''),
                'pincode'  => (string) ($row->password ?? ''),
                'name'     => (string) ($contact->name ?? 'N/A'),
                'shop'     => (string) ($contact->shop ?? 'N/A'),
                'phone'    => (string) ($contact->phone ?? 'N/A'),
            ];
        }));

        $validCompanies = ['Twellium', 'Transsion', 'Honda', 'Shefer', 'PZ Cussons', 'Transsnet'];
        $dbClients = Client::where('is_active', 1)->orderBy('short_name')->pluck('short_name')->toArray();
        $companies = array_values(array_unique(array_merge($validCompanies, $dbClients)));

        return Inertia::render('TwelliumPin/Index', [
            'pins'         => $pins,
            'filters'      => [
                'search' => $search,
                'sort'   => $sort,
                'order'  => $order,
            ],
            'totalRecords' => $totalRecords,
            'searchCount'  => $searchCount,
            'companies'    => $companies,
            'flash'        => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => ['required', 'string', 'max:100', 'unique:userstwi,staff_id'],
            'company'  => ['required', 'string', 'max:255'],
        ], [
            'staff_id.unique'   => 'The Staff ID already exists in the Twellium PIN database.',
            'staff_id.required' => 'Staff ID is required.',
            'company.required'  => 'Company is required.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()
                ->with('error', 'Failed to add user: ' . implode(' | ', $validator->errors()->all()));
        }

        $staffId = trim($request->input('staff_id'));
        $company = trim($request->input('company'));
        $pincode = TwelliumPin::generateUniquePincode();

        TwelliumPin::create([
            'id'       => $staffId,
            'staff_id' => $staffId,
            'company'  => $company,
            'password' => $pincode,
        ]);

        return redirect()->back()->with('success', "User '{$staffId}' added successfully with PIN: {$pincode}");
    }

    public function changePin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => ['required', 'string'],
            'send_sms' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', 'Staff ID is required for PIN change.');
        }

        $staffId = trim($request->input('staff_id'));
        $sendSms = (bool) $request->input('send_sms', false);

        $pinRecord = TwelliumPin::where('staff_id', $staffId)->orWhere('id', $staffId)->first();
        if (!$pinRecord) {
            return redirect()->back()->with('error', "Staff ID '{$staffId}' not found in Twellium PIN database.");
        }

        $newPinCode = TwelliumPin::generateUniquePincode();
        $pinRecord->password = $newPinCode;
        $pinRecord->save();

        $message = "PIN code for Staff ID '{$staffId}' changed successfully to {$newPinCode}.";

        if ($sendSms) {
            $contact = EmployeeContact::where('staffid', $staffId)->first();
            $phone = $contact?->phone;

            if ($phone) {
                $smsText = "Click https://employeeportal.sbpafrica.group to access your payslips.\nStaff ID: {$staffId}\nNew PIN: {$newPinCode}";
                $result = $this->sendSingleSMS($phone, $smsText);

                if ($result['success']) {
                    $message .= " SMS sent successfully to {$phone}.";
                } else {
                    $message .= " Failed to send SMS: {$result['error']}.";
                }
            } else {
                $message .= " No phone number found in employee contact records for '{$staffId}'.";
            }
        }

        return redirect()->back()->with('success', $message);
    }

    public function sendSms(Request $request)
    {
        $messageType = $request->input('message_type', 'selected');
        $selectedStaffIds = $request->input('selected_users', []);

        if ($messageType === 'all') {
            $query = DB::table('userstwi as u')
                ->leftJoin('employeecontact as c', 'u.staff_id', '=', 'c.staffid')
                ->whereNotNull('c.phone')
                ->where('c.phone', '!=', '')
                ->select(['u.staff_id', 'u.password', 'c.phone'])
                ->get();
        } else {
            if (empty($selectedStaffIds)) {
                return redirect()->back()->with('error', 'No users selected for SMS.');
            }

            $query = DB::table('userstwi as u')
                ->leftJoin('employeecontact as c', 'u.staff_id', '=', 'c.staffid')
                ->whereIn('u.staff_id', $selectedStaffIds)
                ->whereNotNull('c.phone')
                ->where('c.phone', '!=', '')
                ->select(['u.staff_id', 'u.password', 'c.phone'])
                ->get();
        }

        if ($query->isEmpty()) {
            return redirect()->back()->with('error', 'No valid recipients with phone numbers found in employee contact records.');
        }

        $successCount = 0;
        $failedCount = 0;
        $failedDetails = [];

        foreach ($query as $row) {
            $staffId = $row->staff_id;
            $pinCode = $row->password;
            $phone   = $row->phone;

            $smsText = "Click https://employeeportal.sbpafrica.group to access your payslips.\nStaff ID: {$staffId}\nPIN: {$pinCode}";
            $result = $this->sendSingleSMS($phone, $smsText);

            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
                $failedDetails[] = "{$staffId} ({$phone}): {$result['error']}";
            }
        }

        $msg = "SMS sending completed: {$successCount} sent successfully.";
        if ($failedCount > 0) {
            $msg .= " Failed: {$failedCount} recipient(s).";
            if (!empty($failedDetails)) {
                $msg .= " Errors: " . implode('; ', array_slice($failedDetails, 0, 5));
                if (count($failedDetails) > 5) $msg .= ' ...';
            }
            return redirect()->back()->with('error', $msg);
        }

        return redirect()->back()->with('success', $msg);
    }

    public function destroy($staffid)
    {
        $deleted = TwelliumPin::where('staff_id', $staffid)->orWhere('id', $staffid)->delete();

        if ($deleted) {
            return redirect()->back()->with('success', "User '{$staffid}' deleted successfully from Twellium PIN records.");
        }

        return redirect()->back()->with('error', 'Could not delete record or user not found.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return redirect()->back()->with('error', 'No records selected for deletion.');
        }

        TwelliumPin::whereIn('staff_id', $ids)->orWhereIn('id', $ids)->delete();

        return redirect()->back()->with('success', count($ids) . ' Twellium PIN record(s) deleted successfully.');
    }

    public function bulkUpload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)
                ->with('error', 'File validation failed: ' . implode(' | ', $validator->errors()->all()));
        }

        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray();

            if (empty($data) || count($data) < 2) {
                return redirect()->back()->with('error', 'Uploaded spreadsheet is empty or has no data rows.');
            }

            $headers = array_map(fn($h) => strtolower(trim((string)$h)), $data[0]);

            $staffIdIndex = array_search('staffid', $headers);
            if ($staffIdIndex === false) {
                $staffIdIndex = array_search('staff_id', $headers);
            }
            if ($staffIdIndex === false) {
                $staffIdIndex = array_search('staff id', $headers);
            }

            $companyIndex = array_search('company', $headers);

            if ($staffIdIndex === false || $companyIndex === false) {
                return redirect()->back()->with('error', "Missing required headers: 'staffid' and 'company'.");
            }

            $successCount = 0;
            $smsSuccessCount = 0;
            $smsFailedCount = 0;
            $errors = [];

            $existingIds = array_flip(
                TwelliumPin::pluck('staff_id')
                    ->map(fn($id) => trim((string)$id))
                    ->filter()
                    ->toArray()
            );

            $seenInFile = [];

            for ($i = 1; $i < count($data); $i++) {
                $rowNum  = $i + 1;
                $staffId = trim((string)($data[$i][$staffIdIndex] ?? ''));
                $company = trim((string)($data[$i][$companyIndex] ?? ''));

                if (empty($staffId) && empty($company)) {
                    continue; // Skip empty row
                }

                if (empty($staffId) || empty($company)) {
                    $errors[] = "Row {$rowNum}: Missing staffid or company.";
                    continue;
                }

                if (isset($existingIds[$staffId])) {
                    $errors[] = "Row {$rowNum}: Staff ID '{$staffId}' already exists in database.";
                    continue;
                }

                if (isset($seenInFile[$staffId])) {
                    $errors[] = "Row {$rowNum}: Duplicate Staff ID '{$staffId}' in file.";
                    continue;
                }
                $seenInFile[$staffId] = true;

                $pinCode = TwelliumPin::generateUniquePincode();

                TwelliumPin::create([
                    'id'       => $staffId,
                    'staff_id' => $staffId,
                    'company'  => $company,
                    'password' => $pinCode,
                ]);

                $existingIds[$staffId] = true;
                $successCount++;

                // If phone contact is available, send SMS
                $contact = EmployeeContact::where('staffid', $staffId)->first();
                if ($contact && $contact->phone) {
                    $smsText = "Click https://employeeportal.sbpafrica.group to access your payslips.\nStaff ID: {$staffId}\nPIN: {$pinCode}";
                    $smsResult = $this->sendSingleSMS($contact->phone, $smsText);
                    if ($smsResult['success']) {
                        $smsSuccessCount++;
                    } else {
                        $smsFailedCount++;
                    }
                }
            }

            $msg = "Processed {$successCount} Twellium user(s) successfully. Sent {$smsSuccessCount} SMS successfully.";
            if ($smsFailedCount > 0) {
                $msg .= " Failed to send {$smsFailedCount} SMS.";
            }
            if (!empty($errors)) {
                $msg .= " Errors: " . implode(' | ', array_slice($errors, 0, 5));
                if (count($errors) > 5) $msg .= ' ...';
                return redirect()->back()->with('error', $msg);
            }

            return redirect()->back()->with('success', $msg);
        } catch (\Exception $e) {
            Log::error('Twellium PIN Bulk Upload Exception: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error processing file: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $query = DB::table('userstwi as u')
            ->leftJoin('employeecontact as c', 'u.staff_id', '=', 'c.staffid')
            ->select([
                'u.staff_id as staff_id',
                DB::raw("COALESCE(c.name, 'N/A') as name"),
                'u.company as company',
                DB::raw("COALESCE(c.shop, 'N/A') as shop"),
                DB::raw("COALESCE(c.phone, 'N/A') as phone"),
                'u.password as pincode',
            ]);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('u.staff_id', 'like', "%{$search}%")
                  ->orWhere('u.company', 'like', "%{$search}%")
                  ->orWhere('u.password', 'like', "%{$search}%")
                  ->orWhere('c.name', 'like', "%{$search}%")
                  ->orWhere('c.shop', 'like', "%{$search}%")
                  ->orWhere('c.phone', 'like', "%{$search}%");
            });
        }

        $records = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Twellium PINs');

        $sheet->setCellValue('A1', 'Staff ID');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Company');
        $sheet->setCellValue('D1', 'Shop');
        $sheet->setCellValue('E1', 'Phone');
        $sheet->setCellValue('F1', 'PIN Code');

        $rowNum = 2;
        foreach ($records as $item) {
            $sheet->setCellValue('A' . $rowNum, $item->staff_id);
            $sheet->setCellValue('B' . $rowNum, $item->name);
            $sheet->setCellValue('C' . $rowNum, $item->company);
            $sheet->setCellValue('D' . $rowNum, $item->shop);
            $sheet->setCellValue('E' . $rowNum, $item->phone);
            $sheet->setCellValue('F' . $rowNum, $item->pincode);
            $rowNum++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Twellium_PINs_' . date('Y_m_d_H_i_s') . '.xlsx';

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Twellium PIN Template');

        $sheet->setCellValue('A1', 'staffid');
        $sheet->setCellValue('B1', 'company');

        $sheet->setCellValue('A2', 'SBP/TWI/001');
        $sheet->setCellValue('B2', 'Twellium');

        $sheet->setCellValue('A3', 'SBP/TWI/002');
        $sheet->setCellValue('B3', 'Twellium');

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Twellium_PIN_Upload_Template.xlsx';

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function isValidPhoneNumber(string $phoneNumber): string|false
    {
        $phone = trim($phoneNumber);
        if (preg_match('/^0\d{9}$/', $phone)) {
            return '+233' . substr($phone, 1);
        }
        if (preg_match('/^\+\d{10,15}$/', $phone)) {
            return $phone;
        }
        if (preg_match('/^233\d{9}$/', $phone)) {
            return '+' . $phone;
        }
        return false;
    }

    private function sendSingleSMS(string $phoneNumber, string $message): array
    {
        $validatedPhone = $this->isValidPhoneNumber($phoneNumber);
        if ($validatedPhone === false) {
            return ['success' => false, 'error' => "Invalid phone number format: {$phoneNumber}"];
        }

        try {
            // Primary: mNotify v2 JSON API
            $response = Http::withoutVerifying()->timeout(20)->withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post("https://api.mnotify.com/api/sms/quick?key=" . urlencode($this->apiKey), [
                'recipient'     => [$validatedPhone],
                'sender'        => $this->senderId,
                'message'       => $message,
                'is_schedule'   => false,
                'schedule_date' => '',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $status = strtolower((string)($data['status'] ?? ''));
                $code = (string)($data['code'] ?? ($data['response_code'] ?? ''));
                if ($status === 'success' || $code === '1000' || $code === '200' || $code === '2000') {
                    return ['success' => true, 'error' => null];
                }
            }

            // Fallback: legacy HTTP endpoint
            $fallback = Http::withoutVerifying()->timeout(20)->get('http://apps.mnotify.net/smsapi', [
                'key'       => $this->apiKey,
                'to'        => $validatedPhone,
                'msg'       => $message,
                'sender_id' => $this->senderId,
            ]);

            if ($fallback->successful() || str_contains(strtolower($fallback->body()), '1000') || str_contains(strtolower($fallback->body()), 'success')) {
                return ['success' => true, 'error' => null];
            }

            return ['success' => false, 'error' => 'mNotify response error'];
        } catch (\Exception $e) {
            Log::error('mNotify Exception: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
