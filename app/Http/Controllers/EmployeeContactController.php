<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class EmployeeContactController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Allowed clients logic via DB builder
        $clientsQuery = ($user->is_super_admin || !$user->clients()->exists())
            ? DB::table('client')->where('is_active', 1)
            : DB::table('client')
                ->join('user_client', 'client.id', '=', 'user_client.client_id')
                ->where('user_client.user_id', $user->id)
                ->where('client.is_active', 1);

        $allowedClients = $clientsQuery->get();
        $clientNames = $allowedClients->pluck('client_name')->toArray();
        $shortNames  = $allowedClients->pluck('short_name')->toArray();
        $allowedClientNames = array_values(array_unique(array_filter(array_merge($clientNames, $shortNames))));

        $query = DB::table('employeecontact');

        if (!$user->is_super_admin && DB::table('user_client')->where('user_id', $user->id)->exists()) {
            if (!empty($allowedClientNames)) {
                $query->where(function ($q) use ($allowedClientNames) {
                    $q->whereIn('shop', $allowedClientNames);
                    foreach ($allowedClientNames as $cn) {
                        $q->orWhere('shop', 'like', "%{$cn}%");
                    }
                });
            } else {
                // User has client restrictions but no active clients
                $query->whereRaw('1 = 0');
            }
        }

        $totalRecords = $query->count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('staffid', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('shop', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        $sortField = $request->input('sort', 'created_at');
        $sortOrder = $request->input('order', 'desc');
        
        $allowedSorts = ['staffid', 'name', 'shop', 'phone', 'created_at'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'created_at';
        }
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortField, $sortOrder);
        
        // Secondary sort to ensure consistent pagination
        if ($sortField !== 'staffid') {
            $query->orderBy('staffid', 'asc');
        }

        $contacts = $query->paginate(25)->withQueryString();

        return Inertia::render('EmployeeContact/Index', [
            'contacts' => $contacts,
            'filters' => [
                'search' => $request->input('search', ''),
                'sort' => $sortField,
                'order' => $sortOrder,
            ],
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'clients' => $clientNames, // Pass full client names for the dropdown
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staffid' => 'required|string|max:255',
            'name'    => 'required|string|max:255',
            'shop'    => 'required|string|max:255',
            'phone'   => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        $staffidCheck = DB::table('employeecontact')->where('staffid', $request->staffid)->exists();
        if ($staffidCheck) {
            return redirect()->back()->with('error', 'Error: Staff ID already exists.');
        }

        $phoneCheck = DB::table('employeecontact')->where('phone', $request->phone)->exists();
        if ($phoneCheck) {
            return redirect()->back()->with('error', 'Error: Phone Number already exists.');
        }

        try {
            DB::table('employeecontact')->insert([
                'staffid'    => $request->staffid,
                'name'       => $request->name,
                'shop'       => $request->shop,
                'phone'      => $request->phone,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return redirect()->route('employeecontact.index')->with('success', 'New employee contact added successfully!');
        } catch (\Exception $e) {
            Log::error('Error adding employee contact', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Error: Could not add the employee. Please try again.');
        }
    }

    public function update(Request $request, $staffid)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'shop'  => 'required|string|max:255',
            'phone' => 'required|string|max:10',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->errors()->first());
        }

        // Check phone uniqueness ignoring current staffid
        $phoneCheck = DB::table('employeecontact')
            ->where('phone', $request->phone)
            ->where('staffid', '!=', $staffid)
            ->exists();

        if ($phoneCheck) {
            return redirect()->back()->with('error', 'Error: Phone Number already exists for another employee.');
        }

        try {
            $updated = DB::table('employeecontact')
                ->where('staffid', $staffid)
                ->update([
                    'name'       => $request->name,
                    'shop'       => $request->shop,
                    'phone'      => $request->phone,
                    'updated_at' => now(),
                ]);

            if ($updated) {
                return redirect()->route('employeecontact.index')->with('success', 'Employee contact details updated successfully!');
            }
            
            return redirect()->back()->with('error', 'Employee not found.');
        } catch (\Exception $e) {
            Log::error('Error updating employee contact', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Error: Could not update employee details. Please try again.');
        }
    }

    public function destroy($staffid)
    {
        try {
            $deleted = DB::table('employeecontact')->where('staffid', $staffid)->delete();
            if ($deleted) {
                return redirect()->route('employeecontact.index')->with('success', 'Employee contact deleted successfully!');
            }
            return redirect()->back()->with('error', 'Employee not found.');
        } catch (\Exception $e) {
            Log::error('Error deleting employee contact', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Error: Could not delete employee. Please try again.');
        }
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'staffids' => 'required|array',
            'staffids.*' => 'string'
        ]);

        try {
            DB::table('employeecontact')->whereIn('staffid', $request->staffids)->delete();
            return redirect()->route('employeecontact.index')->with('success', 'Selected contacts deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Error bulk deleting contacts', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Error deleting selected contacts.');
        }
    }

    public function export(Request $request)
    {
        $user = $request->user();

        $clientsQuery = ($user->is_super_admin || !$user->clients()->exists())
            ? DB::table('client')->where('is_active', 1)
            : DB::table('client')
                ->join('user_client', 'client.id', '=', 'user_client.client_id')
                ->where('user_client.user_id', $user->id)
                ->where('client.is_active', 1);

        $allowedClients = $clientsQuery->get();
        $clientNames = $allowedClients->pluck('client_name')->toArray();
        $shortNames  = $allowedClients->pluck('short_name')->toArray();
        $allowedClientNames = array_values(array_unique(array_filter(array_merge($clientNames, $shortNames))));

        $query = DB::table('employeecontact');

        if (!$user->is_super_admin && DB::table('user_client')->where('user_id', $user->id)->exists()) {
            if (!empty($allowedClientNames)) {
                $query->where(function ($q) use ($allowedClientNames) {
                    $q->whereIn('shop', $allowedClientNames);
                    foreach ($allowedClientNames as $cn) {
                        $q->orWhere('shop', 'like', "%{$cn}%");
                    }
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('staffid', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('shop', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $sortField = $request->input('sort', 'created_at');
        $sortOrder = $request->input('order', 'desc');
        
        $allowedSorts = ['staffid', 'name', 'shop', 'phone', 'created_at'];
        if (!in_array($sortField, $allowedSorts)) {
            $sortField = 'created_at';
        }
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortField, $sortOrder);
        if ($sortField !== 'staffid') {
            $query->orderBy('staffid', 'asc');
        }

        $contacts = $query->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $sheet->setCellValue('A1', 'Staff ID');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Shop');
        $sheet->setCellValue('D1', 'Phone');

        $row = 2;
        foreach ($contacts as $contact) {
            $sheet->setCellValue('A' . $row, $contact->staffid ?? 'N/A');
            $sheet->setCellValue('B' . $row, $contact->name ?? 'N/A');
            $sheet->setCellValue('C' . $row, $contact->shop ?? 'N/A');
            $sheet->setCellValue('D' . $row, $contact->phone ?? 'N/A');
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'employee_contacts.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ])->deleteFileAfterSend(true);
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Staff ID');
        $sheet->setCellValue('B1', 'Name');
        $sheet->setCellValue('C1', 'Shop');
        $sheet->setCellValue('D1', 'Phone');

        // Example row
        $sheet->setCellValue('A2', 'STF123');
        $sheet->setCellValue('B2', 'John Doe');
        $sheet->setCellValue('C2', 'Company A');
        $sheet->setCellValue('D2', '0241234567');

        $writer = new Xlsx($spreadsheet);
        $fileName = 'employee_contacts_template.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ])->deleteFileAfterSend(true);
    }

    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
        ]);

        set_time_limit(300);

        $user = $request->user();
        
        $clientsQuery = ($user->is_super_admin || !$user->clients()->exists())
            ? DB::table('client')->where('is_active', 1)
            : DB::table('client')
                ->join('user_client', 'client.id', '=', 'user_client.client_id')
                ->where('user_client.user_id', $user->id)
                ->where('client.is_active', 1);

        $allowedClientNames = $clientsQuery->pluck('client_name')->toArray();

        try {
            $file = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            array_shift($rows); // Remove header row

            // Get existing staff IDs via DB query
            $existingStaffIdsList = DB::table('employeecontact')->pluck('staffid')->toArray();
            $existingStaffIds = array_flip(array_map('trim', $existingStaffIdsList));
            
            $seenStaffIdsInFile = [];
            $errors = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;

                $hasContent = false;
                foreach ($row as $cell) {
                    if ($cell !== null && trim((string)$cell) !== '') {
                        $hasContent = true;
                        break;
                    }
                }
                if (!$hasContent) {
                    continue;
                }

                $staffid = trim($row[0] ?? '');
                $name    = trim($row[1] ?? '');
                $shop    = trim($row[2] ?? '');

                $rawPhone = $row[3] ?? '';
                $cleanedPhone = preg_replace('/^\'+/', '', trim((string)$rawPhone));

                if (strlen($cleanedPhone) === 10 && preg_match('/^0\d{9}$/', $cleanedPhone)) {
                    $phone = $cleanedPhone;
                } elseif (strlen($cleanedPhone) === 9 && preg_match('/^[2345]\d{8}$/', $cleanedPhone)) {
                    $phone = '0' . $cleanedPhone;
                } else {
                    $errors[] = "Row {$rowNum}: Phone must be a valid 10-digit Ghana number starting with 0, or 9-digit mobile without leading 0 (original value: '{$rawPhone}').";
                    continue;
                }

                if (!preg_match('/^0\d{9}$/', $phone)) {
                    $errors[] = "Row {$rowNum}: Invalid phone format after normalization (original value: '{$rawPhone}').";
                    continue;
                }

                $data = [
                    'staffid' => $staffid,
                    'name'    => $name,
                    'shop'    => $shop,
                    'phone'   => $phone,
                ];

                if (empty($data['staffid']) || empty($data['name']) || empty($data['shop']) || empty($data['phone'])) {
                    $errors[] = "Row {$rowNum}: All fields (Staff ID, Name, Shop, Phone) are required.";
                    continue;
                }

                foreach (['name', 'shop'] as $field) {
                    if (preg_match('/[ÂÊÎÔÛâêîôû]/u', $data[$field])) {
                        $errors[] = "Row {$rowNum}: French accented characters not allowed in {$field}.";
                        continue 2;
                    }
                }

                if (!$user->is_super_admin && !in_array($data['shop'], $allowedClientNames)) {
                    $errors[] = "Row {$rowNum}: The shop/client does not exist or you do not have access to it.";
                    continue;
                }

                if (isset($existingStaffIds[$staffid])) {
                    $errors[] = "Row {$rowNum}: Staff ID '{$staffid}' already exists in the database.";
                    continue;
                }

                if (isset($seenStaffIdsInFile[$staffid])) {
                    $errors[] = "Row {$rowNum}: Duplicate Staff ID '{$staffid}' found in the uploaded file (first seen on Row {$seenStaffIdsInFile[$staffid]}).";
                    continue;
                }
                $seenStaffIdsInFile[$staffid] = $rowNum;

                $validRecords[] = $data;
            }

            if (!empty($errors)) {
                $errorMsg = count($errors) > 10
                    ? implode(' | ', array_slice($errors, 0, 10)) . ' ...and ' . (count($errors) - 10) . ' more error(s).'
                    : implode(' | ', $errors);
                return redirect()->route('employeecontact.index')
                                 ->with('error', $errorMsg);
            }

            if (!empty($validRecords)) {
                $now = now();
                collect($validRecords)->chunk(500)->each(function ($chunk) use ($now) {
                    $recordsWithTimestamps = $chunk->map(function ($record) use ($now) {
                        return array_merge($record, [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    })->toArray();

                    DB::table('employeecontact')->insert($recordsWithTimestamps);
                });
            }

            return redirect()->route('employeecontact.index')
                             ->with('success', 'Successfully uploaded ' . count($validRecords) . ' contact(s).');
        } catch (\Exception $e) {
            Log::error('Bulk upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('employeecontact.index')
                             ->with('error', 'Failed to process upload: ' . $e->getMessage());
        }
    }
}
