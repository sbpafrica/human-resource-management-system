<?php

namespace App\Http\Controllers;

use App\Models\Jaykaypay;
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
use App\Services\Payroll\ColumnMatcher\HybridColumnMatcher;
use App\Services\Payroll\Anomaly\HybridAnomalyDetector;

class JaykayPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'jkay' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Jaykaypay/Index', [
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
            DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
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
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position) LIKE ?', ["%{$search}%"])
              ->orWhere('bd.bank', 'like', "%{$search}%");
        });
    }

    // Sorting - now works correctly on computed columns
    $sort = $request->input('sort', 'staff_id');
    $order = $request->input('order', 'asc');

    $sortMap = [
        'staff_id' => 'tp.staff_id',
        'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
        'shop'   => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position)'),
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

    return Inertia::render('Jaykaypay/Index', [
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
        $tableName = 'jkay' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staff_id', '=', 'bd.employid')
            ->select(
                'tp.staff_id',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
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
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sort  = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'staff_id'      => 'tp.staff_id',
            'name'          => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
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

            // BULLETPROOF: Account & Code as TEXT
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '',    DataType::TYPE_STRING);

            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->take_home_pay ? (float)$r->take_home_pay : '');

            $row++;
        }

        // Same beautiful styling as every other payroll export
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('bbdcfe');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "jaykay_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

    } catch (\Exception $e) {
        Log::error("JaykayPay export failed: " . $e->getMessage());
        return response()->json(['error' => 'Export failed'], 500);
    }
}

/**
     * Upload Excel file
     */
   public function upload(Request $request, HybridColumnMatcher $columnMatcher, HybridAnomalyDetector $anomalyDetector)
 {
 $request->validate([
 'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
 'month' => ['required', 'date_format:Y-m'],
 ]);

 try {
 $selectedMonth = $request->input('month');
 session(['selected_month' => $selectedMonth]);
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'jkay' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 JaykayPay::createTableIfNotExists($tableName);

 $file = $request->file('file');
 $reader = new XlsxReader();
 $spreadsheet = $reader->load($file->getRealPath());
 $sheet = $spreadsheet->getActiveSheet();
 $rows = $sheet->toArray();
 $rawHeaders = array_shift($rows); // Remove header row

 // 3-Tier Dynamic Column Matcher (Grok AI -> PHP Rules -> Legacy Positions)
 $matchResult = $columnMatcher->match($rawHeaders, 'jaykay', array_slice($rows, 0, 3));
 $cols = $matchResult['mapping'];

 $cStaffId     = $cols['staff_id'] ?? 0;
 $cName        = $cols['name'] ?? 1;
 $cPos         = $cols['position'] ?? 2;
 $cJoinDate    = $cols['join_date'] ?? 3;
 $cBasic       = $cols['basic_salary'] ?? 4;
 $cWorkdays    = $cols['legalworkdays'] ?? 5;
 $cDailyRate   = $cols['daily_rate'] ?? 6;
 $cDaysWorked  = $cols['days_worked'] ?? 7;
 $cAbsentDays  = $cols['absentee_days'] ?? 8;
 $cAbsentDed   = $cols['absent_deductions'] ?? 9;
 $cEarnedBasic = $cols['earned_basic_salary'] ?? 10;
 $cSatWorked   = $cols['saturdays_worked'] ?? 11;
 $cSatRate     = $cols['saturday_rate'] ?? 12;
 $cSatAmount   = $cols['saturday_amount'] ?? 13;
 $cTotalDays   = $cols['total_days_amount'] ?? 14;
 $cOtHours     = $cols['overtime_hours'] ?? 15;
 $cOtRate      = $cols['overtime_rate'] ?? 16;
 $cOtAmount    = $cols['overtime_amount'] ?? 17;
 $cNightAllow  = $cols['night_allowance'] ?? 18;
 $cTotalOt     = $cols['totalovertime'] ?? 19;
 $cGross       = $cols['gross_salary'] ?? 20;
 $cMgmtFee     = $cols['management_fee'] ?? 21;
 $cGetfund     = $cols['getfund'] ?? 22;
 $cNhil        = $cols['nhil'] ?? 23;
 $cVat         = $cols['vat'] ?? 24;
 $cSsf         = $cols['ssf'] ?? 25;
 $cCostToJkay  = $cols['cost_to_jkay'] ?? 26;
 $cSsnitEe     = $cols['ssnit_ee'] ?? 27;
 $cPetra       = $cols['petra'] ?? 28;
 $cTaxableInc  = $cols['taxable_income'] ?? 29;
 $cPaye        = $cols['paye'] ?? 30;
 $cOtTax       = $cols['overtime_tax'] ?? 31;
 $cSurcharge   = $cols['surcharge_or_student_loan'] ?? 32;
 $cTotalDed    = $cols['total_deductions'] ?? 33;
 $cTakeHome    = $cols['net_pay'] ?? 34;

 // Check for existing staff IDs
 $existingStaffIds = DB::table($tableName)->pluck('staff_id')->toArray();
 $duplicateStaffIds = [];
 $validRecords = [];
 $errors = [];

 foreach ($rows as $index => $row) {
    // Basic row check: must have at least staff ID or name
    $staff_id = trim($row[$cStaffId] ?? '') ?: null;
    $name     = trim($row[$cName] ?? '') ?: null;

    if (empty($staff_id) && empty($name)) {
        continue;
    }

// === MAPPED SAFELY VIA 3-TIER RESOLVER ===
$position                = trim($row[$cPos] ?? '') ?: null;
$join_date               = trim($row[$cJoinDate] ?? '') ?: null;

$basic_salary            = is_numeric($row[$cBasic] ?? '') ? (float)$row[$cBasic] : null;
$legalworkdays           = is_numeric($row[$cWorkdays] ?? '') ? (int)$row[$cWorkdays] : null;
$daily_rate              = is_numeric($row[$cDailyRate] ?? '') ? (float)$row[$cDailyRate] : null;
$days_worked             = is_numeric($row[$cDaysWorked] ?? '') ? (float)$row[$cDaysWorked] : null;
$absentee_days           = is_numeric($row[$cAbsentDays] ?? '') ? (float)$row[$cAbsentDays] : null;
$absent_deductions       = is_numeric($row[$cAbsentDed] ?? '') ? (float)$row[$cAbsentDed] : null;
$earned_basic_salary     = is_numeric($row[$cEarnedBasic] ?? '') ? (float)$row[$cEarnedBasic] : null;

$saturdays_worked        = is_numeric($row[$cSatWorked] ?? '') ? (float)$row[$cSatWorked] : null;
$saturday_rate           = is_numeric($row[$cSatRate] ?? '') ? (float)$row[$cSatRate] : null;
$saturday_amount         = is_numeric($row[$cSatAmount] ?? '') ? (float)$row[$cSatAmount] : null;
$total_days_amount       = is_numeric($row[$cTotalDays] ?? '') ? (float)$row[$cTotalDays] : null;

$overtime_hours          = is_numeric($row[$cOtHours] ?? '') ? (float)$row[$cOtHours] : null;
$overtime_rate           = is_numeric($row[$cOtRate] ?? '') ? (float)$row[$cOtRate] : null;
$overtime_amount         = is_numeric($row[$cOtAmount] ?? '') ? (float)$row[$cOtAmount] : null;
$night_allowance         = is_numeric($row[$cNightAllow] ?? '') ? (float)$row[$cNightAllow] : null;
$totalovertime           = is_numeric($row[$cTotalOt] ?? '') ? (float)$row[$cTotalOt] : null;

$gross_salary            = is_numeric($row[$cGross] ?? '') ? (float)$row[$cGross] : null;

$management_fee          = is_numeric($row[$cMgmtFee] ?? '') ? (float)$row[$cMgmtFee] : null;
$getfund                 = is_numeric($row[$cGetfund] ?? '') ? (float)$row[$cGetfund] : null;
$nhil                    = is_numeric($row[$cNhil] ?? '') ? (float)$row[$cNhil] : null;

$vat                     = is_numeric($row[$cVat] ?? '') ? (float)$row[$cVat] : null;
$ssf                     = is_numeric($row[$cSsf] ?? '') ? (float)$row[$cSsf] : null;
$cost_to_jkay            = is_numeric($row[$cCostToJkay] ?? '') ? (float)$row[$cCostToJkay] : null;

$ssnit_ee                = is_numeric($row[$cSsnitEe] ?? '') ? (float)$row[$cSsnitEe] : null;
$petra                   = is_numeric($row[$cPetra] ?? '') ? (float)$row[$cPetra] : null;
$taxable_income          = is_numeric($row[$cTaxableInc] ?? '') ? (float)$row[$cTaxableInc] : null;
$paye                    = is_numeric($row[$cPaye] ?? '') ? (float)$row[$cPaye] : null;
$overtime_tax            = is_numeric($row[$cOtTax] ?? '') ? (float)$row[$cOtTax] : null;
$surcharge_or_student_loan = is_numeric($row[$cSurcharge] ?? '') ? (float)$row[$cSurcharge] : null;
$total_deductions        = is_numeric($row[$cTotalDed] ?? '') ? (float)$row[$cTotalDed] : null;

// Take Home Pay – cleaned like bank transfer
$rawTakeHome             = trim($row[$cTakeHome] ?? '');
$cleanTakeHome           = preg_replace('/[^0-9.-]/', '', $rawTakeHome);
$take_home_pay           = is_numeric($cleanTakeHome) ? (float)$cleanTakeHome : null;

    // === VALIDATION ===
    if (empty($staff_id)) {
        $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
        continue;
    }

    if (empty($name)) {
        $errors[] = "Row " . ($index + 2) . ": Name is required.";
        continue;
    }

    if (is_null($basic_salary) || is_null($gross_salary)) {
        $errors[] = "Row " . ($index + 2) . ": Basic and Gross Pay are required.";
        continue;
    }

    if (is_null($take_home_pay)) {
        $errors[] = "Row " . ($index + 2) . ": Bank Transfer (Net Pay) must be a valid number. Found: '$rawTakeHome'";
        continue;
    }

    // Duplicate check
    if (in_array($staff_id, $existingStaffIds)) {
        $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $staff_id";
        continue;
    }

    // === FINAL DATA ARRAY ===
$data = [
    'staff_id'                  => $staff_id,
    'name'                      => $name,
    'position'                  => $position,
    'join_date'                 => $join_date,
    'basic_salary'              => $basic_salary,
    'legalworkdays'             => $legalworkdays,
    'daily_rate'                => $daily_rate,
    'days_worked'               => $days_worked,
    'absentee_days'             => $absentee_days,
    'absent_deductions'         => $absent_deductions,
    'earned_basic_salary'       => $earned_basic_salary,
    'saturdays_worked'          => $saturdays_worked,
    'saturday_rate'             => $saturday_rate,
    'saturday_amount'           => $saturday_amount,
    'total_days_amount'         => $total_days_amount,
    'overtime_hours'            => $overtime_hours,
    'overtime_rate'             => $overtime_rate,
    'overtime_amount'           => $overtime_amount,
    'night_allowance'           => $night_allowance,
    'totalovertime'             => $totalovertime,
    'gross_salary'              => $gross_salary,
    'management_fee'            => $management_fee,
    'getfund'                   => $getfund,
    'nhil'                      => $nhil,
    
    'vat'                       => $vat,
    'ssf'                       => $ssf,
    'cost_to_jkay'              => $cost_to_jkay,
    'ssnit_ee'                  => $ssnit_ee,
    'petra'                     => $petra,
    'taxable_income'            => $taxable_income,
    'paye'                      => $paye,
    'overtime_tax'              => $overtime_tax,
    'surcharge_or_student_loan' => $surcharge_or_student_loan,
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
 return redirect()->route('jaykaypay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('jaykaypay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 // Run Pre-Flight Anomaly & Fraud Audit
 $auditReport = $anomalyDetector->detect($validRecords, 'jaykay', $selectedMonth);

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 $auditNotice = '';
 if (!empty($auditReport['anomalies'])) {
     $crit = $auditReport['summary']['critical_count'];
     $warn = $auditReport['summary']['warning_count'];
     $auditNotice = " [Audit Score: {$auditReport['audit_score']}/100 — {$crit} critical, {$warn} warnings]";
 }

 $tierLabel = strtoupper($matchResult['tier'] ?? 'LEGACY');
 return redirect()->route('jaykaypay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . " record(s) via {$tierLabel} matcher.{$auditNotice}")
 ->with('audit_report', $auditReport);
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('jaykaypay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'jkay' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('jaykaypay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('jaykaypay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('jaykaypay.index', ['month' => $selectedMonth])
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
    'Staff ID', 'Name', 'Position', 'Join Date',
    'Basic Salary', 'Legal Work Days', 'Daily Rate', 'Days Worked',
    'Absentee Days', 'Absent Deductions', 'Earned Basic Salary',
    'Saturdays Worked', 'Saturday Rate', 'Saturday Amount', 'Total Days Amount',
    'Overtime Hours', 'Overtime Rate', 'Overtime Amount', 'Night Allowance', 'Total Overtime',
    'Gross Salary',
    'Management Fee', 'GETFund', 'NHIL', 'VAT', 'SSF', 'Cost to JKAY',
    'SSNIT EE', 'PETRA', 'Taxable Income', 'PAYE', 'Overtime Tax', 'Surcharge/Student Loan',
    'Total Deductions', 'Take Home Pay'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:Y1')->getFont()->setBold(true);  // Y1 because now only 25 columns (A to Y)

// Example row matching the new structure exactly
$exampleData = [
    'TNS00123',         // 0  Staff ID
    'Kwame Osei',       // 1  Name
    'Sales Executive',  // 2  Position
    '2023-06-15',       // 3  Join Date

    3500.00,           // 4  Basic Salary
    22,                 // 5  Legal Work Days
    159.09,             // 6  Daily Rate (3500 ÷ 22)
    20.5,               // 7  Days Worked
    1.5,                // 8  Absentee Days
    238.64,             // 9  Absent Deductions
    3261.36,           // 10 Earned Basic Salary

    2,                  // 11 Saturdays Worked
    238.64,             // 12 Saturday Rate (1.5 × daily rate)
    477.28,             // 13 Saturday Amount
    3738.64,           // 14 Total Days Amount (Earned Basic + Saturday)

    15.5,              // 15 Overtime Hours
    29.83,              // 16 Overtime Rate
    462.37,             // 17 Overtime Amount
    300.00,             // 18 Night Allowance
    762.37,             // 19 Total Overtime

    4501.01,            // 20 Gross Salary

    800.00,             // 21 Management Fee
    112.53,             // 22 GETFund 2.5%
    112.53,             // 23 NHIL 2.5%
    45.01,              // 24 COVID-19 1%
    0.00,               // 25 VAT
    585.13,             // 26 SSF (ER 13%)
    6653.21,            // 27 Cost to JKAY (total employer cost)

    192.50,             // 28 SSNIT EE (5.5%)
    4308.51,            // 29 Taxable Income
    582.70,             // 30 PAYE
    115.20,             // 31 Overtime Tax
    0.00,               // 32 Surcharge/Student Loan

    890.40,             // 33 Total Deductions
    3610.61             // 34 Take Home Pay
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
