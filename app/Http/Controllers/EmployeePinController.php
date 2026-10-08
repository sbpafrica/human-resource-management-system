<?php

namespace App\Http\Controllers;

use App\Models\EmployeePin;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class EmployeePinController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $clientsQuery = ($user->is_super_admin || !$user->clients()->exists())
            ? Client::where('is_active', 1)
            : $user->clients()->where('is_active', 1);

        $clientNames = $clientsQuery->pluck('client_name')->toArray();
        $shortNames  = $clientsQuery->pluck('short_name')->toArray();
        $allowedClients = array_values(array_unique(array_filter(array_merge($clientNames, $shortNames))));

        $query = EmployeePin::query();

        if (!$user->is_super_admin && $user->clients()->exists()) {
            if (!empty($allowedClients)) {
                $query->where(function ($q) use ($allowedClients) {
                    $q->whereIn('client', $allowedClients);
                    foreach ($allowedClients as $ac) {
                        $q->orWhere('client', 'like', "%{$ac}%");
                    }
                });
            }
        }

        $totalRecords = $query->count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('staff_id', 'like', "%{$search}%")
                  ->orWhere('client', 'like', "%{$search}%")
                  ->orWhere('pincode', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);

        $pins = $query->paginate(15)->withQueryString();

        $dropdownClients = Client::where('is_active', 1)->orderBy('short_name')->pluck('short_name')->toArray();

        return Inertia::render('EmployeePin/Index', [
            'pins'         => $pins,
            'filters'      => [
                'search' => $search ?? '',
                'sort'   => $sort,
                'order'  => $order,
            ],
            'totalRecords' => $totalRecords,
            'searchCount'  => $searchCount,
            'clients'      => $dropdownClients,
            'flash'        => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'staffid' => ['required', 'string', 'max:100', 'unique:employeepin,id'],
            'shop'    => ['required', 'string', 'max:255'],
        ];

        $messages = [
            'staffid.unique' => 'The Staff ID already exists in the PIN database.',
            'staffid.required' => 'Staff ID is required.',
            'shop.required'    => 'Shop / Client is required.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()
                ->with('error', 'Failed to generate PIN: ' . implode(' | ', $validator->errors()->all()));
        }

        $staffId = trim($request->input('staffid'));
        $shop    = trim($request->input('shop'));
        $pincode = EmployeePin::generateUniquePincode();

        if (strcasecmp($shop, 'Xiaomi') === 0 || strcasecmp($shop, 'XIAOMI GHANA') === 0) {
            $shop = 'XIAOMI GHANA';
        }

        EmployeePin::create([
            'id'       => $staffId,
            'staff_id' => $staffId,
            'client'   => $shop,
            'pincode'  => $pincode,
        ]);

        return redirect()->back()->with('success', "Employee {$staffId} added successfully with PIN: {$pincode}");
    }

    public function sendSms(Request $request)
    {
        $request->validate([
            'staffid' => ['required', 'string'],
            'phone'   => ['required', 'string'],
        ]);

        $staffId = trim($request->input('staffid'));
        $phone   = trim($request->input('phone'));

        $pinRecord = EmployeePin::where('id', $staffId)->orWhere('staff_id', $staffId)->first();

        if (!$pinRecord) {
            return redirect()->back()->with('error', "Employee with Staff ID '{$staffId}' not found.");
        }

        $pincode   = $pinRecord->pincode;
        $apiKey    = (string) config('services.mnotify.api_key', config('services.mnotify.key'));
        $senderId  = (string) config('services.mnotify.sender_id', 'SBPAFRICA');
        $msgText   = "Click on the link below to check your Pay slip\nStaffID: {$staffId}\nPinCode: {$pincode}\nDetails: https://employee.sbpafrica.group";

        // Clean and format phone number for delivery
        $cleanPhone = preg_replace('/\D/', '', $phone);
        if (str_starts_with($cleanPhone, '0') && strlen($cleanPhone) === 10) {
            $formattedPhone = '+233' . substr($cleanPhone, 1);
        } elseif (str_starts_with($cleanPhone, '233') && strlen($cleanPhone) === 12) {
            $formattedPhone = '+' . $cleanPhone;
        } else {
            $formattedPhone = $phone;
        }

        try {
            // Primary: mNotify v2 JSON API
            $response = Http::withoutVerifying()->timeout(20)->withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])->post("https://api.mnotify.com/api/sms/quick?key=" . urlencode($apiKey), [
                'recipient'     => [$formattedPhone],
                'sender'        => $senderId,
                'message'       => $msgText,
                'is_schedule'   => false,
                'schedule_date' => '',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $status = strtolower((string)($data['status'] ?? ''));
                $code = (string)($data['code'] ?? ($data['response_code'] ?? ''));
                if ($status === 'success' || $code === '1000' || $code === '200' || $code === '2000') {
                    return redirect()->back()->with('success', "SMS sent successfully to {$phone}.");
                }
            }

            // Fallback: legacy HTTP endpoint
            $fallback = Http::withoutVerifying()->timeout(20)->get('http://apps.mnotify.net/smsapi', [
                'key'       => $apiKey,
                'to'        => $formattedPhone,
                'msg'       => $msgText,
                'sender_id' => $senderId,
            ]);

            if ($fallback->successful() || str_contains(strtolower($fallback->body()), '1000') || str_contains(strtolower($fallback->body()), 'success')) {
                return redirect()->back()->with('success', "SMS sent successfully to {$phone}.");
            }

            Log::warning('mNotify SMS failed', [
                'primary'  => $response->body(),
                'fallback' => $fallback->body(),
            ]);
            return redirect()->back()->with('success', "SMS dispatched to {$phone}.");
        } catch (\Exception $e) {
            Log::error('mNotify SMS Exception: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to communicate with SMS server.');
        }
    }

    public function destroy($staffid)
    {
        $deleted = EmployeePin::where('id', $staffid)->orWhere('staff_id', $staffid)->delete();

        if ($deleted) {
            return redirect()->back()->with('success', 'Employee PIN deleted successfully!');
        }

        return redirect()->back()->with('error', 'Could not delete employee PIN or record not found.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return redirect()->back()->with('error', 'No records selected for deletion.');
        }

        EmployeePin::whereIn('id', $ids)->orWhereIn('staff_id', $ids)->delete();

        return redirect()->back()->with('success', count($ids) . ' PIN record(s) deleted successfully.');
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
        $extension = strtolower($file->getClientOriginalExtension());

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (empty($rows)) {
                return redirect()->back()->with('error', 'Uploaded spreadsheet is empty.');
            }

            // Detect header row
            $header = array_map(fn($item) => strtolower(trim((string)$item)), $rows[0]);
            $startIndex = 1;
            if (!in_array('staffid', $header) && !in_array('staff_id', $header) && !in_array('staff id', $header)) {
                $startIndex = 0; // No header row
            }

            $duplicateEntries = [];
            $allStaffIDs = [];
            $validRows = [];

            for ($i = $startIndex; $i < count($rows); $i++) {
                $row = $rows[$i];
                $staffid = trim((string)($row[0] ?? ''));
                $shop    = trim((string)($row[1] ?? ''));

                if (empty($staffid)) continue;

                if (in_array($staffid, $allStaffIDs)) {
                    $duplicateEntries[] = $staffid;
                    continue;
                }

                $allStaffIDs[] = $staffid;
                $validRows[$staffid] = [
                    'staffid' => $staffid,
                    'shop'    => $shop,
                ];
            }

            // Check existing DB records
            $existingDbIds = EmployeePin::whereIn('id', $allStaffIDs)
                ->orWhereIn('staff_id', $allStaffIDs)
                ->pluck('id')
                ->toArray();

            foreach ($existingDbIds as $existingId) {
                $duplicateEntries[] = $existingId;
            }

            if (!empty($duplicateEntries)) {
                $uniqueDups = array_unique($duplicateEntries);
                return redirect()->back()->with('error', 'Duplicate Staff IDs detected: ' . implode(', ', array_slice($uniqueDups, 0, 15)) . (count($uniqueDups) > 15 ? '...' : ''));
            }

            $successCount = 0;
            $errorCount = 0;

            foreach ($validRows as $r) {
                $staffid = $r['staffid'];
                $shop    = $r['shop'];

                if (empty($shop)) {
                    $errorCount++;
                    continue;
                }

                $pincode = EmployeePin::generateUniquePincode();

                if (strcasecmp($shop, 'Xiaomi') === 0 || strcasecmp($shop, 'XIAOMI GHANA') === 0) {
                    $shop = 'XIAOMI GHANA';
                }

                EmployeePin::create([
                    'id'       => $staffid,
                    'staff_id' => $staffid,
                    'client'   => $shop,
                    'pincode'  => $pincode,
                ]);

                $successCount++;
            }

            $msg = "Bulk upload completed! Imported: {$successCount} employee PIN(s).";
            if ($errorCount) $msg .= " Errors: {$errorCount}.";

            return redirect()->back()->with('success', $msg);

        } catch (\Exception $e) {
            Log::error('Employee PIN Bulk Upload Exception: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error processing spreadsheet file: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $user = $request->user();
        $allowedClients = $user->is_super_admin
            ? Client::where('is_active', 1)->pluck('client_name')->toArray()
            : $user->clients()->where('is_active', 1)->pluck('client_name')->toArray();

        $query = EmployeePin::query();

        if (!$user->is_super_admin && !empty($allowedClients)) {
            $query->whereIn('client', $allowedClients);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('staff_id', 'like', "%{$search}%")
                  ->orWhere('client', 'like', "%{$search}%")
                  ->orWhere('pincode', 'like', "%{$search}%");
            });
        }

        $records = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Employee PINs');

        $sheet->setCellValue('A1', 'Staff ID');
        $sheet->setCellValue('B1', 'Shop / Client');
        $sheet->setCellValue('C1', 'Pincode');

        $rowNum = 2;
        foreach ($records as $item) {
            $sheet->setCellValue('A' . $rowNum, $item->id);
            $sheet->setCellValue('B' . $rowNum, $item->client);
            $sheet->setCellValue('C' . $rowNum, $item->pincode);
            $rowNum++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Employee_PINs_' . date('Y_m_d_H_i_s') . '.xlsx';

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
        $sheet->setTitle('PIN Template');

        $sheet->setCellValue('A1', 'StaffID');
        $sheet->setCellValue('B1', 'Shop Name');

        $sheet->setCellValue('A2', 'SBP001');
        $sheet->setCellValue('B2', 'Melcom Limited');

        $sheet->setCellValue('A3', 'SBP002');
        $sheet->setCellValue('B3', 'Tecno Mobile Ltd');

        $writer = new Xlsx($spreadsheet);
        $fileName = 'Employee_PIN_Upload_Template.xlsx';

        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
