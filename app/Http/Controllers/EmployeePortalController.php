<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\BankDetail;
use App\Models\Petra;
use App\Models\Ssnit;
use App\Models\ArchivedEmployee;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;

class EmployeePortalController extends Controller
{
    public function index(Request $request)
{
    $user = $request->user();

    $clientsQuery = ($user->is_super_admin || !$user->clients()->exists())
        ? Client::where('is_active', 1)
        : $user->clients()->where('is_active', 1);

    $clientNames = $clientsQuery->pluck('client_name')->toArray();
    $shortNames  = $clientsQuery->pluck('short_name')->toArray();
    $allowedClientNames = array_values(array_unique(array_filter(array_merge($clientNames, $shortNames))));

    // Build the query
    $query = Employee::query();

    if (!$user->is_super_admin && $user->clients()->exists()) {
        if (!empty($allowedClientNames)) {
            $query->where(function ($q) use ($allowedClientNames) {
                $q->whereIn('company', $allowedClientNames);
                foreach ($allowedClientNames as $cn) {
                    $q->orWhere('company', 'like', "%{$cn}%");
                }
            });
        }
    }

    $totalRecords = $query->count();
    $searchCount = null;

    // Search filter
    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('staffid', 'like', "%{$search}%")
              ->orWhere('employeename', 'like', "%{$search}%")
              ->orWhere('company', 'like', "%{$search}%")
              ->orWhere('shop', 'like', "%{$search}%")
              ->orWhere('position', 'like', "%{$search}%")
              ->orWhere('phone1', 'like', "%{$search}%")
              ->orWhere('phone2', 'like', "%{$search}%")
              ->orWhere('ssnit', 'like', "%{$search}%")
              ->orWhere('ghcard', 'like', "%{$search}%")
              ->orWhere('email', 'like', "%{$search}%");
        });
        $searchCount = $query->count();
    }

    // Sorting
    $sort = $request->input('sort', 'employeename');
    $order = $request->input('order', 'asc');
    $query->orderBy($sort, $order);

    // Clients for dropdown
    $clients = Client::where('is_active', 1)->orderBy('client_name')->pluck('client_name')->toArray();

    // Paginate
    $employees = $query->paginate(10)->withQueryString();

    return Inertia::render('EmployeePortal/Index', [
        'employees'     => $employees,
        'filters'       => [
            'search' => $search ?? '',
            'sort'   => $sort,
            'order'  => $order,
        ],
        'totalRecords'  => $totalRecords,
        'searchCount'   => $searchCount,
        'clients'       => $clients, // Only allowed clients in dropdown
        'flash'         => [
            'success' => $request->session()->get('success'),
            'error'   => $request->session()->get('error'),
        ],
    ]);
}
    public function show($staffid)
{
    Log::info('Show called', ['staffid' => $staffid]);

    $employee = Employee::with(['bankDetail', 'petra', 'ssnit'])
        ->where('staffid', $staffid)
        ->firstOrFail();

    // Safe toArray conversion
    $logData = [
        'employee' => $employee->toArray(),
        'bankDetail' => $employee->relationLoaded('bankDetail') && $employee->bankDetail instanceof \Illuminate\Database\Eloquent\Model
            ? $employee->bankDetail->toArray()
            : ($employee->bankDetail ?? null),
        'petra' => $employee->relationLoaded('petra') && $employee->petra instanceof \Illuminate\Database\Eloquent\Model
            ? $employee->petra->toArray()
            : ($employee->petra ?? null),
        'ssnit' => $employee->relationLoaded('ssnit') && $employee->ssnit instanceof \Illuminate\Database\Eloquent\Model
            ? $employee->ssnit->toArray()
            : ($employee->ssnit ?? null),
    ];

    Log::info('Employee found', $logData);

    return Inertia::render('EmployeePortal/Profile', [
        'employee' => $employee,
        'bankDetail' => $employee->bankDetail,
        'petra' => $employee->petra,
        'ssnit' => $employee->ssnit,
        'flash' => [
            'success' => session('success'),
            'error' => session('error'),
        ],
    ]);
}

    public function store(Request $request)
    {
        $rules = [
            'staffid' => [
                'required',
                'string',
                'max:20',
                'unique:newemployeedata,staffid',
                'no_french_accents',
            ],
            'employeename' => ['required', 'string', 'max:150', 'no_french_accents'],
            'company' => ['required', 'string', 'max:150', 'no_french_accents'],
            'shop' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'position' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'qualification' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'startdate' => ['nullable', 'date'],
            'dateofbirth' => ['nullable', 'date'],
            'ghcard' => ['nullable', 'string', 'max:255', 'no_french_accents'],
            'phone1' => ['nullable', 'string', 'max:20', 'no_french_accents'],
            'phone2' => ['nullable', 'string', 'max:20', 'no_french_accents'],
            'gender' => ['nullable', 'string', 'max:50', 'no_french_accents'],
            'nationality' => ['nullable', 'string', 'max:100', 'no_french_accents'],
            'email' => ['nullable', 'email', 'max:255', 'no_french_accents'],
            'nextofkin' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'relation' => ['nullable', 'string', 'max:100', 'no_french_accents'],
            'contact' => ['nullable', 'string', 'max:20', 'no_french_accents'],
            'guarantor' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'contactgua' => ['nullable', 'string', 'max:20', 'no_french_accents'],
            'profilepic' => ['nullable', 'string', 'max:255'],
            'ghanafront' => ['nullable', 'string', 'max:255'],
            'ghanaback' => ['nullable', 'string', 'max:255'],
            'gpsaddress' => ['nullable', 'string', 'max:255', 'no_french_accents'],
        ];

        $messages = [
            'staffid.unique' => 'The Staff ID already exists in the database.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            Log::error('Store validation failed', ['errors' => $validator->errors()]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to create employee: ' . implode(' | ', $validator->errors()->all()));
        }

        Employee::create($request->all());
        return redirect()->route('employeeportal.index')->with('success', 'Employee created successfully.');
    }

    public function update(Request $request, $staffid)
    {
        Log::info('Update called', ['staffid' => $staffid]);
        $employee = Employee::where('staffid', $staffid)->firstOrFail();
        Log::info('Record found', ['employee' => $employee->toArray()]);

        $rules = [
            'staffid' => [
                'required',
                'string',
                'max:20',
                Rule::unique('newemployeedata')->ignore($employee->staffid, 'staffid'),
                'no_french_accents',
            ],
            'employeename' => ['required', 'string', 'max:150', 'no_french_accents'],
            'company' => ['required', 'string', 'max:150', 'no_french_accents'],
            'shop' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'position' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'qualification' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'startdate' => ['nullable', 'date'],
            'dateofbirth' => ['nullable', 'date'],
            'ghcard' => ['nullable', 'string', 'max:255', 'no_french_accents'],
            'phone1' => ['nullable', 'string', 'max:20', 'no_french_accents'],
            'phone2' => ['nullable', 'string', 'max:20', 'no_french_accents'],
            'gender' => ['nullable', 'string', 'max:50', 'no_french_accents'],
            'nationality' => ['nullable', 'string', 'max:100', 'no_french_accents'],
            'email' => ['nullable', 'email', 'max:255', 'no_french_accents'],
            'nextofkin' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'relation' => ['nullable', 'string', 'max:100', 'no_french_accents'],
            'contact' => ['nullable', 'string', 'max:20', 'no_french_accents'],
            'guarantor' => ['nullable', 'string', 'max:150', 'no_french_accents'],
            'contactgua' => ['nullable', 'string', 'max:20', 'no_french_accents'],
            'profilepic' => ['nullable', 'string', 'max:255'],
            'ghanafront' => ['nullable', 'string', 'max:255'],
            'ghanaback' => ['nullable', 'string', 'max:255'],
            'gpsaddress' => ['nullable', 'string', 'max:255', 'no_french_accents'],
        ];

        $messages = [
            'staffid.unique' => 'The Staff ID already exists in the database.',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);

        if ($validator->fails()) {
            Log::error('Update validation failed', ['errors' => $validator->errors()]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to update employee: ' . implode(' | ', $validator->errors()->all()));
        }

        $employee->update($request->all());
        return redirect()->route('employeeportal.index')->with('success', 'Employee updated successfully.');
    }

    public function destroy($staffid)
    {
        Log::info('Destroy called', ['staffid' => $staffid]);
        $employee = Employee::where('staffid', $staffid)->firstOrFail();
        $employee->delete();
        return redirect()->route('employeeportal.index')->with('success', 'Employee deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'string', 'exists:newemployeedata,staffid'],
        ]);

        $ids = $request->input('ids');
        Log::info('Bulk destroy called', ['ids' => $ids]);

        $deletedCount = Employee::whereIn('staffid', $ids)->delete();

        if ($deletedCount > 0) {
            return redirect()->route('employeeportal.index')->with('success', "Successfully deleted $deletedCount employee(s).");
        }

        return redirect()->route('employeeportal.index')->with('error', 'No employees were deleted.');
    }

  public function export(Request $request)
    {
        try {
            Log::info('Export method called', [
                'search' => $request->input('search'),
                'sort' => $request->input('sort', 'employeename'),
                'order' => $request->input('order', 'asc'),
            ]);

            // Build query with search and sorting
            $search = $request->input('search');
            $sort = $request->input('sort', 'employeename');
            $order = $request->input('order', 'asc');

            $query = Employee::query();
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('staffid', 'like', "%{$search}%")
                      ->orWhere('employeename', 'like', "%{$search}%")
                      ->orWhere('company', 'like', "%{$search}%");
                });
            }
            $query->orderBy($sort, $order);
            $employees = $query->get();

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'Staff ID');
            $sheet->setCellValue('B1', 'Employee Name');
            $sheet->setCellValue('C1', 'Company');
            $sheet->setCellValue('D1', 'Shop');
            $sheet->setCellValue('E1', 'Position');
            $sheet->setCellValue('F1', 'Qualification');
            $sheet->setCellValue('G1', 'Start Date');
            $sheet->setCellValue('H1', 'Date of Birth');
            $sheet->setCellValue('I1', 'Ghana Card');
            $sheet->setCellValue('J1', 'Phone 1');
            $sheet->setCellValue('K1', 'Phone 2');
            $sheet->setCellValue('L1', 'Gender');
            $sheet->setCellValue('M1', 'Nationality');
            $sheet->setCellValue('N1', 'Email');
            $sheet->setCellValue('O1', 'Next of Kin');
            $sheet->setCellValue('P1', 'Relation');
            $sheet->setCellValue('Q1', 'Contact');
            $sheet->setCellValue('R1', 'Guarantor');
            $sheet->setCellValue('S1', 'Guarantor Contact');
            $sheet->setCellValue('T1', 'GPS Address');

            // Populate data
            $row = 2;
            foreach ($employees as $employee) {
                $sheet->setCellValue('A' . $row, $employee->staffid ?? 'N/A');
                $sheet->setCellValue('B' . $row, $employee->employeename ?? 'N/A');
                $sheet->setCellValue('C' . $row, $employee->company ?? 'N/A');
                $sheet->setCellValue('D' . $row, $employee->shop ?? 'N/A');
                $sheet->setCellValue('E' . $row, $employee->position ?? 'N/A');
                $sheet->setCellValue('F' . $row, $employee->qualification ?? 'N/A');
                $sheet->setCellValue('G' . $row, $employee->startdate ?? 'N/A');
                $sheet->setCellValue('H' . $row, $employee->dateofbirth ?? 'N/A');
                $sheet->setCellValue('I' . $row, $employee->ghcard ?? 'N/A');
                $sheet->setCellValue('J' . $row, $employee->phone1 ?? 'N/A');
                $sheet->setCellValue('K' . $row, $employee->phone2 ?? 'N/A');
                $sheet->setCellValue('L' . $row, $employee->gender ?? 'N/A');
                $sheet->setCellValue('M' . $row, $employee->nationality ?? 'N/A');
                $sheet->setCellValue('N' . $row, $employee->email ?? 'N/A');
                $sheet->setCellValue('O' . $row, $employee->nextofkin ?? 'N/A');
                $sheet->setCellValue('P' . $row, $employee->relation ?? 'N/A');
                $sheet->setCellValue('Q' . $row, $employee->contact ?? 'N/A');
                $sheet->setCellValue('R' . $row, $employee->guarantor ?? 'N/A');
                $sheet->setCellValue('S' . $row, $employee->contactgua ?? 'N/A');
                $sheet->setCellValue('T' . $row, $employee->gpsaddress ?? 'N/A');
                $row++;
            }

            // Save spreadsheet to temporary file
            $writer = new Xlsx($spreadsheet);
            $fileName = 'employees.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            if ($tempFile === false) {
                Log::error('Failed to create temporary file for export');
                return response()->json(['error' => 'Failed to create temporary file'], 500);
            }
            $writer->save($tempFile);

            // Verify file exists and is readable
            if (!file_exists($tempFile) || !is_readable($tempFile)) {
                Log::error('Temporary file not found or not readable', ['file' => $tempFile]);
                return response()->json(['error' => 'Temporary file not found or not readable'], 500);
            }

            // Set response headers
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Content-Length' => filesize($tempFile),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ];

            Log::info('Export file prepared', ['file' => $tempFile, 'size' => filesize($tempFile)]);

            // Return download response
            return response()->download($tempFile, $fileName, $headers)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Export failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to generate export file: ' . $e->getMessage()], 500);
        }
    }

public function archive($staffid)
{
    Log::info('Archive called', ['staffid' => $staffid]);

    $employee = Employee::where('staffid', $staffid)->firstOrFail();

    // Guard: only insert into archive if not already there
    $alreadyArchived = ArchivedEmployee::where('staffid', $staffid)->exists();

    if (!$alreadyArchived) {
        ArchivedEmployee::create([
            'staffid'      => $employee->staffid,
            'employeename' => $employee->employeename,
            'company'      => $employee->company,
            'shop'         => $employee->shop,
            'position'     => $employee->position,
            'qualification'=> $employee->qualification,
            'startdate'    => $employee->startdate,
            'dateofbirth'  => $employee->dateofbirth,
            'ghcard'       => $employee->ghcard,
            'phone1'       => $employee->phone1,
            'phone2'       => $employee->phone2,
            'gender'       => $employee->gender,
            'nationality'  => $employee->nationality,
            'email'        => $employee->email,
            'nextofkin'    => $employee->nextofkin,
            'relation'     => $employee->relation,
            'contact'      => $employee->contact,
            'guarantor'    => $employee->guarantor,
            'contactgua'   => $employee->contactgua,
            'profilepic'   => $employee->profilepic,
            'ghanafront'   => $employee->ghanafront,
            'ghanaback'    => $employee->ghanaback,
            'gpsaddress'   => $employee->gpsaddress,
            'archived_at'  => now(),
        ]);
    }

    // Always remove from active table
    $employee->delete();

    Log::info('Employee archived successfully', ['staffid' => $staffid]);

    return redirect()->route('employeeportal.index')
        ->with('success', 'Employee archived successfully.');
}

public function bulkArchive(Request $request)
{
    $request->validate([
        'ids' => ['required', 'array', 'min:1'],
        'ids.*' => ['required', 'string', 'exists:newemployeedata,staffid'],
    ]);

    $ids = $request->input('ids');
    Log::info('Bulk archive called', ['ids' => $ids]);

    $employees = Employee::whereIn('staffid', $ids)->get();
    $archivedCount = 0;

    foreach ($employees as $employee) {
        $alreadyArchived = ArchivedEmployee::where('staffid', $employee->staffid)->exists();
        if (!$alreadyArchived) {
            ArchivedEmployee::create([
                'staffid'      => $employee->staffid,
                'employeename' => $employee->employeename,
                'company'      => $employee->company,
                'shop'         => $employee->shop,
                'position'     => $employee->position,
                'qualification'=> $employee->qualification,
                'startdate'    => $employee->startdate,
                'dateofbirth'  => $employee->dateofbirth,
                'ghcard'       => $employee->ghcard,
                'phone1'       => $employee->phone1,
                'phone2'       => $employee->phone2,
                'gender'       => $employee->gender,
                'nationality'  => $employee->nationality,
                'email'        => $employee->email,
                'nextofkin'    => $employee->nextofkin,
                'relation'     => $employee->relation,
                'contact'      => $employee->contact,
                'guarantor'    => $employee->guarantor,
                'contactgua'   => $employee->contactgua,
                'profilepic'   => $employee->profilepic,
                'ghanafront'   => $employee->ghanafront,
                'ghanaback'    => $employee->ghanaback,
                'gpsaddress'   => $employee->gpsaddress,
                'archived_at'  => now(),
            ]);
        }
        $employee->delete();
        $archivedCount++;
    }

    if ($archivedCount > 0) {
        return redirect()->route('employeeportal.index')->with('success', "Successfully archived $archivedCount employee(s).");
    }

    return redirect()->route('employeeportal.index')->with('error', 'No employees were archived.');
}


 public function downloadTemplate()
    {
        try {
            Log::info('DownloadTemplate method called');

            // Create template spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setCellValue('A1', 'Staff ID');
            $sheet->setCellValue('B1', 'Employee Name');
            $sheet->setCellValue('C1', 'Company');
            $sheet->setCellValue('D1', 'Shop');
            $sheet->setCellValue('E1', 'Position');
            $sheet->setCellValue('F1', 'Qualification');
            $sheet->setCellValue('G1', 'Start Date');
            $sheet->setCellValue('H1', 'Date of Birth');
            $sheet->setCellValue('I1', 'Ghana Card');
            $sheet->setCellValue('J1', 'Phone 1');
            $sheet->setCellValue('K1', 'Phone 2');
            $sheet->setCellValue('L1', 'Gender');
            $sheet->setCellValue('M1', 'Nationality');
            $sheet->setCellValue('N1', 'Email');
            $sheet->setCellValue('O1', 'Next of Kin');
            $sheet->setCellValue('P1', 'Relation');
            $sheet->setCellValue('Q1', 'Contact');
            $sheet->setCellValue('R1', 'Guarantor');
            $sheet->setCellValue('S1', 'Guarantor Contact');
            $sheet->setCellValue('T1', 'GPS Address');

            // Add example row
            $sheet->setCellValue('A2', 'STF123');
            $sheet->setCellValue('B2', 'John Doe');
            $sheet->setCellValue('C2', 'Company A');
            $sheet->setCellValue('D2', 'Main Branch');
            $sheet->setCellValue('E2', 'Manager');
            $sheet->setCellValue('F2', 'Bachelor’s Degree');
            $sheet->setCellValue('G2', '2023-01-01');
            $sheet->setCellValue('H2', '1990-05-15');
            $sheet->setCellValue('I2', 'GHA123456');
            $sheet->setCellValue('J2', '1234567890');
            $sheet->setCellValue('K2', '');
            $sheet->setCellValue('L2', 'Male');
            $sheet->setCellValue('M2', 'Ghanaian');
            $sheet->setCellValue('N2', 'john.doe@example.com');
            $sheet->setCellValue('O2', 'Jane Doe');
            $sheet->setCellValue('P2', 'Sister');
            $sheet->setCellValue('Q2', '0987654321');
            $sheet->setCellValue('R2', 'James Smith');
            $sheet->setCellValue('S2', '1122334455');
            $sheet->setCellValue('T2', 'GA-123-4567');

            // Save spreadsheet to temporary file
            $writer = new Xlsx($spreadsheet);
            $fileName = 'employees_template.xlsx';
            $tempFile = tempnam(sys_get_temp_dir(), $fileName);
            if ($tempFile === false) {
                Log::error('Failed to create temporary file for template');
                return response()->json(['error' => 'Failed to create temporary file'], 500);
            }
            $writer->save($tempFile);

            // Verify file exists and is readable
            if (!file_exists($tempFile) || !is_readable($tempFile)) {
                Log::error('Temporary file not found or not readable', ['file' => $tempFile]);
                return response()->json(['error' => 'Temporary file not found or not readable'], 500);
            }

            // Set response headers
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
                'Content-Length' => filesize($tempFile),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ];

            Log::info('Template file prepared', ['file' => $tempFile, 'size' => filesize($tempFile)]);

            // Return download response
            return response()->download($tempFile, $fileName, $headers)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('DownloadTemplate failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Failed to generate template file: ' . $e->getMessage()], 500);
        }
    }


    public function bulkUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        set_time_limit(300);

        try {
            $user = $request->user();
            $allowedClientNames = $user->is_super_admin
                ? Client::where('is_active', 1)->pluck('client_name')->toArray()
                : $user->clients()->where('is_active', 1)->pluck('client_name')->toArray();

            $file = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            array_shift($rows); // Remove header row

            // Pre-load all existing staff IDs from database into a hash map for O(1) lookups
            $existingStaffIds = array_flip(
                Employee::pluck('staffid')
                    ->map(fn($id) => trim((string)$id))
                    ->filter()
                    ->toArray()
            );

            $seenStaffIdsInFile = [];
            $errors = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;

                // Skip completely empty rows
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

                $staffid = trim((string)($row[0] ?? ''));
                $employeename = trim((string)($row[1] ?? ''));
                $company = trim((string)($row[2] ?? ''));
                $shop = !empty(trim((string)($row[3] ?? ''))) ? trim((string)$row[3]) : null;
                $position = !empty(trim((string)($row[4] ?? ''))) ? trim((string)$row[4]) : null;
                $qualification = !empty(trim((string)($row[5] ?? ''))) ? trim((string)$row[5]) : null;
                $startdate = $this->normalizeDate($row[6] ?? null);
                $dateofbirth = $this->normalizeDate($row[7] ?? null);
                $ghcard = !empty(trim((string)($row[8] ?? ''))) ? trim((string)$row[8]) : null;
                $phone1 = !empty(trim((string)($row[9] ?? ''))) ? trim((string)$row[9]) : null;
                $phone2 = !empty(trim((string)($row[10] ?? ''))) ? trim((string)$row[10]) : null;
                $gender = !empty(trim((string)($row[11] ?? ''))) ? trim((string)$row[11]) : null;
                $nationality = !empty(trim((string)($row[12] ?? ''))) ? trim((string)$row[12]) : null;
                $email = !empty(trim((string)($row[13] ?? ''))) ? trim((string)$row[13]) : null;
                $nextofkin = !empty(trim((string)($row[14] ?? ''))) ? trim((string)$row[14]) : null;
                $relation = !empty(trim((string)($row[15] ?? ''))) ? trim((string)$row[15]) : null;
                $contact = !empty(trim((string)($row[16] ?? ''))) ? trim((string)$row[16]) : null;
                $guarantor = !empty(trim((string)($row[17] ?? ''))) ? trim((string)$row[17]) : null;
                $contactgua = !empty(trim((string)($row[18] ?? ''))) ? trim((string)$row[18]) : null;
                $gpsaddress = !empty(trim((string)($row[19] ?? ''))) ? trim((string)$row[19]) : null;

                // 1. Staff ID Required & Duplication Checks (ONLY Staff ID is flagged for duplication)
                if (empty($staffid)) {
                    $errors[] = "Row {$rowNum}: Staff ID is required.";
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

                // 2. Required fields
                if (empty($employeename)) {
                    $errors[] = "Row {$rowNum}: Employee Name is required.";
                    continue;
                }

                if (empty($company)) {
                    $errors[] = "Row {$rowNum}: Company is required.";
                    continue;
                }

                // 3. Client permission check
                if (!$user->is_super_admin && !in_array($company, $allowedClientNames)) {
                    $errors[] = "Row {$rowNum}: You do not have permission to upload employees for client '{$company}'.";
                    continue;
                }

                // 4. French accent check
                $fieldsToCheck = [
                    'staffid' => $staffid,
                    'employeename' => $employeename,
                    'company' => $company,
                    'shop' => $shop,
                    'position' => $position,
                    'qualification' => $qualification,
                    'phone1' => $phone1,
                    'phone2' => $phone2,
                    'gender' => $gender,
                    'nationality' => $nationality,
                    'email' => $email,
                    'nextofkin' => $nextofkin,
                    'relation' => $relation,
                    'contact' => $contact,
                    'guarantor' => $guarantor,
                    'contactgua' => $contactgua,
                    'gpsaddress' => $gpsaddress,
                ];

                $hasAccentError = false;
                foreach ($fieldsToCheck as $fieldName => $fieldVal) {
                    if ($fieldVal && preg_match('/[ÂÊÎÔÛâêîôû]/u', $fieldVal)) {
                        $errors[] = "Row {$rowNum}: French accented characters (Â, Ê, Î, Ô, Û, â, ê, î, ô, û) are not allowed in {$fieldName}.";
                        $hasAccentError = true;
                        break;
                    }
                }
                if ($hasAccentError) {
                    continue;
                }

                // Staff details (Ghana Card, Email, Phone, etc.) are allowed to already exist in the system
                $validRecords[] = [
                    'staffid' => $staffid,
                    'employeename' => $employeename,
                    'company' => $company,
                    'shop' => $shop,
                    'position' => $position,
                    'qualification' => $qualification,
                    'startdate' => $startdate,
                    'dateofbirth' => $dateofbirth,
                    'ghcard' => $ghcard,
                    'phone1' => $phone1,
                    'phone2' => $phone2,
                    'gender' => $gender,
                    'nationality' => $nationality,
                    'email' => $email,
                    'nextofkin' => $nextofkin,
                    'relation' => $relation,
                    'contact' => $contact,
                    'guarantor' => $guarantor,
                    'contactgua' => $contactgua,
                    'gpsaddress' => $gpsaddress,
                ];
            }

            if (!empty($errors)) {
                Log::error('Bulk upload validation failed', ['errors' => $errors]);
                $errorMsg = count($errors) > 10
                    ? implode(' | ', array_slice($errors, 0, 10)) . ' ...and ' . (count($errors) - 10) . ' more error(s).'
                    : implode(' | ', $errors);
                return redirect()->route('employeeportal.index')->with('error', $errorMsg);
            }

            if (empty($validRecords)) {
                return redirect()->route('employeeportal.index')->with('error', 'The uploaded file does not contain any valid employee records.');
            }

            DB::transaction(function () use ($validRecords) {
                foreach (array_chunk($validRecords, 200) as $chunk) {
                    Employee::insert($chunk);
                }
            });

            return redirect()->route('employeeportal.index')->with('success', 'Successfully uploaded ' . count($validRecords) . ' employee(s).');
        } catch (\Exception $e) {
            Log::error('Bulk upload failed', ['error' => $e->getMessage()]);
            return redirect()->route('employeeportal.index')->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

    private function normalizeDate($value)
    {
        if (empty($value)) {
            return null;
        }
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
        $trimmed = trim((string)$value);
        if ($trimmed === '' || strtolower($trimmed) === 'n/a' || strtolower($trimmed) === 'null') {
            return null;
        }
        $timestamp = strtotime($trimmed);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'm/d/Y', 'm-d-Y'];
        foreach ($formats as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $trimmed);
            if ($d && $d->format($fmt) === $trimmed) {
                return $d->format('Y-m-d');
            }
        }
        return $trimmed;
    }
}
