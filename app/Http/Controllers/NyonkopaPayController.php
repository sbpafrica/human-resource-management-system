<?php

namespace App\Http\Controllers;

use App\Models\NyonkopaPay;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
 use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class NyonkopaPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'nyonkopa' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Nyonkopapay/Index', [
            'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
            'selectedMonth' => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords' => 0,
            'searchCount' => null,
            'flash' => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
        ]);
    }

    $query = DB::table($tableName . ' as tp')
        ->leftJoin('bankdetails as bd', 'tp.staff_id', '=', 'bd.employid')
        ->select(
            'tp.staff_id',
            // Use payroll name/shop if bankdetails missing or null
            DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.employee_name) as name'),
            DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position) as shop'),
            'bd.bank',
            'bd.account',
            'bd.code',
            'bd.branch',
            'tp.take_home_pay'
        );

    // Search across both sources
    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search, $tableName) {
            $q->where('tp.staff_id', 'like', "%{$search}%")
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) LIKE ?', ["%{$search}%"])
              ->orWhere('bd.bank', 'like', "%{$search}%");
        });
    }

    // Sorting - now works correctly on computed columns
    $sort = $request->input('sort', 'staff_id');
    $order = $request->input('order', 'asc');

    $sortMap = [
        'staff_id' => 'tp.staff_id',
        'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
        'shop'   => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
        'bank'   => 'bd.bank',
        'account'=> 'bd.account',
        'code'   => 'bd.code',
        'branch' => 'bd.branch',
        'take_home_pay' => 'tp.take_home_pay',
    ];

    $sortColumn = $sortMap[$sort] ?? 'tp.staff_id';
    $query->orderBy($sortColumn, $order);

    $perPage = 30;
    $records = $query->paginate($perPage)->appends($request->query());

    $totalRecords = DB::table($tableName)->count();
    $searchCount = $search ? $query->count() : null;

    return Inertia::render('Nyonkopapay/Index', [
        'records' => $records,
        'selectedMonth' => $month,
        'formattedMonth' => $date->format('F Y'),
        'totalRecords' => $totalRecords,
        'searchCount' => $searchCount,
        'flash' => ['success' => session('success'), 'error' => session('error')],
    ]);
}



public function export(Request $request)
{
    try {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $month = $request->input('month');
        $date  = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'nyonkopa' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staff_id', '=', 'bd.employid')
            ->select(
                'tp.staff_id',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.employee_name) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position) as shop'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.take_home_pay'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staff_id', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.employee_name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sort  = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'staff_id'      => 'tp.staff_id',
            'name'          => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.employee_name)'),
            'shop'          => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position)'),
            'bank'          => 'bd.bank',
            'account'       => 'bd.account',
            'code'          => 'bd.code',
            'branch'        => 'bd.branch',
            'take_home_pay' => 'tp.take_home_pay',
        ];

        $sortColumn = $sortMap[$sort] ?? 'tp.staff_id';
        $query->orderBy($sortColumn, $order);

        $records = $query->get();

        // ==================== EXCEL GENERATION ====================
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Name', 'Department', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Net Pay'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staff_id);
            $sheet->setCellValue("B{$row}", $r->name);
            $sheet->setCellValue("C{$row}", $r->shop ?? '');
            $sheet->setCellValue("D{$row}", $r->bank ?? '');

            // 100% GUARANTEED: Account & Code as TEXT
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '',    DataType::TYPE_STRING);

            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->take_home_pay ? (float)$r->take_home_pay : '');

            $row++;
        }

        // Professional styling — identical to all other exports
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('bbdcfe');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "nyonkopa_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

    } catch (\Exception $e) {
        Log::error("NyonkopaPay export failed: " . $e->getMessage());
        return response()->json(['error' => 'Export failed'], 500);
    }
}

/**
     * Upload Excel file
     */
   public function upload(Request $request)
 {
 $request->validate([
 'file' => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:2048'],
 'month' => ['required', 'date_format:Y-m'],
 ]);

 try {
 $selectedMonth = $request->input('month');
 session(['selected_month' => $selectedMonth]);
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'nyonkopa' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 NyonkopaPay::createTableIfNotExists($tableName);

 $file = $request->file('file');
 $reader = new XlsxReader();
 $spreadsheet = $reader->load($file->getRealPath());
 $sheet = $spreadsheet->getActiveSheet();
 $rows = $sheet->toArray();
 array_shift($rows); // Remove header row

 // Check for existing staff IDs
 $existingStaffIds = DB::table($tableName)->pluck('staff_id')->toArray();
 $duplicateStaffIds = [];
 $validRecords = [];
 $errors = [];

 foreach ($rows as $index => $row) {
    if (count($row) < 28) {
        $errors[] = "Row " . ($index + 2) . ": Expected 24 columns, found " . count($row);
        continue;
    }

// === FINAL CLEAN MAPPING – SEQUENTIAL COLUMNS (0 → 1 → 2 → 3...) ===
$staff_id                  = trim($row[0] ?? '') ?: null;
$employee_name             = trim($row[1] ?? '') ?: null;
$start_date                = trim($row[2] ?? '') ?: null;
$position                  = trim($row[3] ?? '') ?: null;
$work_location             = trim($row[4] ?? '') ?: null;

$base_salary               = is_numeric($row[5] ?? '') ? (float)$row[5] : null;
$fuel_and_rent_allowance   = is_numeric($row[6] ?? '') ? (float)$row[6] : null;   // now ONE column only
$gross_salary              = is_numeric($row[7] ?? '') ? (float)$row[7] : null;

$management_fee_8          = is_numeric($row[8] ?? '') ? (float)$row[8] : null;
$getfund_2_5               = is_numeric($row[9] ?? '') ? (float)$row[9] : null;
$nhil_2_5                  = is_numeric($row[10] ?? '') ? (float)$row[10] : null;

$vat_15                    = is_numeric($row[11] ?? '') ? (float)$row[11] : null;
$medical_insurance         = is_numeric($row[12] ?? '') ? (float)$row[12] : null;
$employer_provident_fund_5 = is_numeric($row[13] ?? '') ? (float)$row[13] : null;
$thirteenth_month_bonus    = is_numeric($row[14] ?? '') ? (float)$row[14] : null;

$workman    = is_numeric($row[15] ?? '') ? (float)$row[15] : null;
$ssnit_er_13               = is_numeric($row[16] ?? '') ? (float)$row[16] : null;
$cost_to_nyonkopa          = is_numeric($row[17] ?? '') ? (float)$row[17] : null;

$ssnit_ee_5_5              = is_numeric($row[18] ?? '') ? (float)$row[18] : null;
$petra              = is_numeric($row[19] ?? '') ? (float)$row[19] : null;
$employeepf              = is_numeric($row[20] ?? '') ? (float)$row[20] : null;
$taxable_income            = is_numeric($row[21] ?? '') ? (float)$row[21] : null;
$paye                      = is_numeric($row[22] ?? '') ? (float)$row[22] : null;
$thirteenth_month_tax      = is_numeric($row[23] ?? '') ? (float)$row[23] : null;
$total_taxation            = is_numeric($row[24] ?? '') ? (float)$row[24] : null;
$deductions              = is_numeric($row[25] ?? '') ? (float)$row[25] : null;
$total_deductions          = is_numeric($row[26] ?? '') ? (float)$row[26] : null;

// Take Home Pay – cleaned like bank transfer
$rawTakeHome               = trim($row[27] ?? '');
$cleanTakeHome             = preg_replace('/[^0-9.-]/', '', $rawTakeHome);
$take_home_pay             = is_numeric($cleanTakeHome) ? (float)$cleanTakeHome : null;
    // === VALIDATION ===
    if (empty($staff_id)) {
        $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
        continue;
    }

    if (empty($employee_name)) {
        $errors[] = "Row " . ($index + 2) . ": Name is required.";
        continue;
    }

    if (is_null($base_salary ) || is_null($gross_salary)) {
        $errors[] = "Row " . ($index + 2) . ": Basic and Gross Pay are required.";
        continue;
    }

    if (is_null($take_home_pay)) {
        $errors[] = "Row " . ($index + 2) . ": Bank Transfer (Net Pay) must be a valid number. Found: '$rawBankTransfer'";
        continue;
    }

    // Duplicate check
    if (in_array($staff_id, $existingStaffIds)) {
        $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $staff_id";
        continue;
    }

    // === FINAL DATA ARRAY - MATCHING THE SECOND FORMAT ===
$data = [
    'staff_id'                  => $staff_id,
    'employee_name'             => $employee_name,
    'start_date'                => $start_date,
    'position'                  => $position,
    'work_location'             => $work_location,
    'base_salary'               => $base_salary,
    'fuel_and_rent_allowance'   => $fuel_and_rent_allowance,
    'gross_salary'              => $gross_salary,
    'management_fee_8'          => $management_fee_8,
    'getfund_2_5'               => $getfund_2_5,
    'nhil_2_5'                  => $nhil_2_5,
    
    'vat_15'                    => $vat_15,
    'medical_insurance'         => $medical_insurance,
    'employer_provident_fund_5' => $employer_provident_fund_5,
    'thirteenth_month_bonus'    => $thirteenth_month_bonus,
     'workman'                  => $workman,
    'ssnit_er_13'               => $ssnit_er_13,
    'cost_to_nyonkopa'          => $cost_to_nyonkopa,
    'ssnit_ee_5_5'              => $ssnit_ee_5_5,
    'petra'                     => $petra,
    'employeepf'                => $employeepf,
    'taxable_income'            => $taxable_income,
    'paye'                      => $paye,
    'thirteenth_month_tax'      => $thirteenth_month_tax,
    'total_taxation'            => $total_taxation,
    'deductions'                => $deductions,
    'total_deductions'          => $total_deductions,
    'take_home_pay'             => $take_home_pay,
];

    $validRecords[] = $data;
    $existingStaffIds[] = $staff_id;
}

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'nyonkopa' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 /**
     * Download Excel template
     */
    public function downloadTemplate()
    {
       $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

       $headers = [
    'Staff ID',
    'Employee Name',
    'Start Date',
    'Position',
    'Work Location',
    'Base Salary',
    'Fuel and Rent Allowance',
    'Gross Salary',
    'Management Fee 8%',
    'GETFund 2.5%',
    'NHIL 2.5%',
    'VAT 15%',
    'Medical Insurance',
    'Employer Provident Fund 5%',
    '13th Month Bonus',
    'WORKMAN COMPENSATION',
    'SSNIT ER 13%',
    'Cost to Nyonkopa',
    'SSNIT EE 5.5%',
    'PETRA',
    'EmployeePF 3%',
    'Taxable Income',
    'PAYE',
    '13th Month Tax',
    'Total Taxation',
    'Deductions',
    'Total Deductions',
    'Take Home Pay'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:Y1')->getFont()->setBold(true);  // Y1 because now only 25 columns (A to Y)

// Example row matching the new structure exactly
$exampleData = [
    'TNS00123',         // Staff ID
    'Kwame Osei',       // Employee Name
    '2023-06-15',       // Start Date
    'Sales Executive',  // Position
    'Accra Mall',       // Work Location
    3500.00,            // Base Salary
    1300.00,            // Fuel and Rent Allowance (Call + House + Traffic combined)
    6975.00,            // Gross Salary
    2000.00,            // Management Fee 8%
    50.00,              // GETFund 2.5%
    80.00,              // NHIL 2.5%
    20.00,              // COVID-19 1%
    0.00,               // VAT 15%
    150.00,             // Medical Insurance
    0.00,               // Employer Provident Fund 5%
    0.00,               // 13th Month Bonus
    455.00,             // SSNIT ER 13%
    10000.00,           // Cost to Nyonkopa (Total HR Cost)
    192.50,             // SSNIT EE 5.5%
     192.50,             // SSNIT EE 5.5%
      192.50,             // SSNIT EE 5.5%
       192.50,             // SSNIT EE 5.5%
    6940.12,            // Taxable Income
    750.25,             // PAYE
    0.00,               // 13th Month Tax
    750.25,             // Total Taxation
    300.00,             // Total Deductions (example)
    5975.00             // Take Home Pay
];

   $sheet->fromArray($exampleData, null, 'A2');

    // Instruction note
    $sheet->setCellValue('A' . ($sheet->getHighestRow() + 3),
        'INSTRUCTIONS: Fill only the yellow/required fields. All amounts in GHS. Do NOT add commas, GH¢ or text in number columns. Dates: DD/MM/YYYY or MM/DD/YYYY.'
    );

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $fileName = 'Payroll_Upload_Template_' . date('M-Y') . '.xlsx';
    $tempFile = tempnam(sys_get_temp_dir(), $fileName);
    $writer->save($tempFile);

    return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
}
}
