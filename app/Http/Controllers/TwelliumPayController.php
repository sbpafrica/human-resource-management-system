<?php

namespace App\Http\Controllers;

use App\Models\Twelliumpay;
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

class TwelliumPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'twellium' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Twelliumpay/Index', [
            'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
            'selectedMonth' => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords' => 0,
            'searchCount' => null,
            'flash' => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
        ]);
    }

    $query = DB::table($tableName . ' as tp')
        ->leftJoin('bankdetails as bd', 'tp.employ', '=', 'bd.employid')
        ->select(
            'tp.employ',
            // Use payroll name/shop if bankdetails missing or null
            DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
            DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) as shop'),
            'bd.bank',
            'bd.account',
            'bd.code',
            'bd.branch',
            'tp.netpay'
        );

    // Search across both sources
    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search, $tableName) {
            $q->where('tp.employ', 'like', "%{$search}%")
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) LIKE ?', ["%{$search}%"])
              ->orWhere('bd.bank', 'like', "%{$search}%");
        });
    }

    // Sorting - now works correctly on computed columns
    $sort = $request->input('sort', 'employ');
    $order = $request->input('order', 'asc');

    $sortMap = [
        'employ' => 'tp.employ',
        'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
        'shop'   => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
        'bank'   => 'bd.bank',
        'account'=> 'bd.account',
        'code'   => 'bd.code',
        'branch' => 'bd.branch',
        'netpay' => 'tp.netpay',
    ];

    $sortColumn = $sortMap[$sort] ?? 'tp.employ';
    $query->orderBy($sortColumn, $order);

    $perPage = 10;
    $records = $query->paginate($perPage)->appends($request->query());

    $totalRecords = DB::table($tableName)->count();
    $searchCount = $search ? $query->count() : null;

    return Inertia::render('Twelliumpay/Index', [
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
        $request->validate([
            'month' => ['required', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month');
        $date  = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'twellium' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.employ', '=', 'bd.employid')
            ->select(
                'tp.employ as staff_id',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as employee_name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) as shop_department'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay as net_pay'
            );

        // Search (works on merged name/shop)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.employ', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(TRIM(bd.shop), ""), tp.department) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting – supports sorting on computed columns
        $sort = $request->input('sort', 'employ');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'employ'     => 'tp.employ',
            'staff_id'   => 'tp.employ',
            'name'       => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
            'employee_name' => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
            'shop'       => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
            'shop_department' => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
            'bank'       => 'bd.bank',
            'account'    => 'bd.account',
            'code'       => 'bd.code',
            'branch'     => 'bd.branch',
            'netpay'     => 'tp.netpay',
            'net_pay'    => 'tp.netpay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.employ', $order);

        $records = $query->get();

        // Generate Real Excel File
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID',
            'Employee Name',
            'Department/Shop',
            'Bank Name',
            'Account Number',
            'Branch Code',
            'Branch',
            'Net Pay (GHS)'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staff_id);
            $sheet->setCellValue("B{$row}", $r->employee_name);
            $sheet->setCellValue("C{$row}", $r->shop_department);
            $sheet->setCellValue("D{$row}", $r->bank ?? '');
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->net_pay ? number_format((float)$r->net_pay, 2, '.', '') : '');
            $row++;
        }

        // Professional Styling – matches all your other exports
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headerStyle = $sheet->getStyle('A1:H1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDEFE'); // Light blue header

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "twellium_payroll_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);

    } catch (\Exception $e) {
        Log::error("TwelliumPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Export failed. Please try again.'], 500);
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
 $tableName = 'twellium' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 TwelliumPay::createTableIfNotExists($tableName);

 $file = $request->file('file');
 $reader = new XlsxReader();
 $spreadsheet = $reader->load($file->getRealPath());
 $sheet = $spreadsheet->getActiveSheet();
 $rows = $sheet->toArray();
 array_shift($rows); // Remove header row

 // Check for existing staff IDs
 $existingStaffIds = DB::table($tableName)->pluck('employ')->toArray();
 $duplicateStaffIds = [];
 $validRecords = [];
 $errors = [];

 foreach ($rows as $index => $row) {
    if (count($row) < 45) {
        $errors[] = "Row " . ($index + 2) . ": Expected 45 columns, found " . count($row);
        continue;
    }

   // === MAPPED TO 24-COLUMN DATABASE STRUCTURE ===
// === MAPPED TO 45-COLUMN DATABASE STRUCTURE ===
$employ                          = trim($row[0] ?? '') ?: null;
$name                            = trim($row[1] ?? '') ?: null;
$department                      = trim($row[2] ?? '') ?: null;
$dateemploy                      = trim($row[3] ?? '') ?: null;

$expected_work_days              = is_numeric($row[4] ?? '') ? (float)$row[4] : null;
$total_hours_worked              = is_numeric($row[5] ?? '') ? (float)$row[5] : null;

$basic                           = is_numeric($row[6] ?? '') ? (float)$row[6] : null;
$basic_earned                    = is_numeric($row[7] ?? '') ? (float)$row[7] : null;
$food_allowance                  = is_numeric($row[8] ?? '') ? (float)$row[8] : null;
$night_allowance                 = is_numeric($row[9] ?? '') ? (float)$row[9] : null;
$refund                          = is_numeric($row[10] ?? '') ? (float)$row[10] : null;
$allowance                       = is_numeric($row[11] ?? '') ? (float)$row[11] : null;
$deduction                       = is_numeric($row[12] ?? '') ? (float)$row[12] : null;

$ssnit_ee                        = is_numeric($row[13] ?? '') ? (float)$row[13] : null;
$petra                        = is_numeric($row[14] ?? '') ? (float)$row[14] : null;
$taxable_income                  = is_numeric($row[15] ?? '') ? (float)$row[15] : null;
$paye                            = is_numeric($row[16] ?? '') ? (float)$row[16] : null;
$ssf                             = is_numeric($row[17] ?? '') ? (float)$row[17] : null;
$welfare                         = is_numeric($row[18] ?? '') ? (float)$row[18] : null;
$medical_insurance               = is_numeric($row[19] ?? '') ? (float)$row[19] : null;
$workmens_compensation           = is_numeric($row[20] ?? '') ? (float)$row[20] : null;
$risk_allowance                  = is_numeric($row[21] ?? '') ? (float)$row[21] : null;
$end_of_year                     = is_numeric($row[22] ?? '') ? (float)$row[22] : null;
$ppe                             = is_numeric($row[23] ?? '') ? (float)$row[23] : null;
$staff_cost                      = is_numeric($row[24] ?? '') ? (float)$row[24] : null;
$mgt_fee                         = is_numeric($row[25] ?? '') ? (float)$row[25] : null;
$gfl                             = is_numeric($row[26] ?? '') ? (float)$row[26] : null;
$nhil                            = is_numeric($row[27] ?? '') ? (float)$row[27] : null;
$covid                           = is_numeric($row[28] ?? '') ? (float)$row[28] : null;
$vat                             = is_numeric($row[29] ?? '') ? (float)$row[29] : null;
$c2c                             = is_numeric($row[30] ?? '') ? (float)$row[30] : null;

$weekday_overtime_hours          = is_numeric($row[31] ?? '') ? (float)$row[31] : null;
$weekday_overtime_rate           = is_numeric($row[32] ?? '') ? (float)$row[32] : null;
$weekday_overtime_amount         = is_numeric($row[33] ?? '') ? (float)$row[33] : null;
$offday_holiday_overtime_hours   = is_numeric($row[34] ?? '') ? (float)$row[34] : null;
$offday_holiday_overtime_rate    = is_numeric($row[35] ?? '') ? (float)$row[35] : null;
$offday_holiday_overtime_total   = is_numeric($row[36] ?? '') ? (float)$row[36] : null;
$special_day_overtime_hours      = is_numeric($row[37] ?? '') ? (float)$row[37] : null;
$special_day_overtime_rate       = is_numeric($row[38] ?? '') ? (float)$row[38] : null;
$special_day_overtime_amount     = is_numeric($row[39] ?? '') ? (float)$row[39] : null;
$total_overtime_amount           = is_numeric($row[40] ?? '') ? (float)$row[40] : null;
$overtime_tax                    = is_numeric($row[41] ?? '') ? (float)$row[41] : null;

$total_taxation                  = is_numeric($row[42] ?? '') ? (float)$row[42] : null;
$total_deductions                = is_numeric($row[43] ?? '') ? (float)$row[43] : null;

// Net Pay – cleaned (last column)
$rawNetpay                       = trim($row[44] ?? '');
$cleanNetpay                     = preg_replace('/[^0-9.-]/', '', $rawNetpay);
$netpay                          = is_numeric($cleanNetpay) ? (float)$cleanNetpay : null;

    // === VALIDATION ===
    if (empty($employ)) {
        $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
        continue;
    }

    if (empty($name)) {
        $errors[] = "Row " . ($index + 2) . ": Name is required.";
        continue;
    }

    if (is_null($basic)) {
        $errors[] = "Row " . ($index + 2) . ": Basic is required.";
        continue;
    }

    if (is_null($netpay)) {
        $errors[] = "Row " . ($index + 2) . ": Bank Transfer (Net Pay) must be a valid number. Found: '$rawBankTransfer'";
        continue;
    }

    // Duplicate check
    if (in_array($employ, $existingStaffIds)) {
        $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $employ";
        continue;
    }

    // === FINAL DATA ARRAY - MATCHING THE SECOND FORMAT ===
$data = [
    'employ'                          => $employ,
    'name'                            => $name,
    'department'                      => $department,
    'dateemploy'                      => $dateemploy,
    'expected_work_days'              => $expected_work_days,
    'total_hours_worked'              => $total_hours_worked,
    'basic'                           => $basic,
    'basic_earned'                    => $basic_earned,
    'food_allowance'                  => $food_allowance,
    'night_allowance'                 => $night_allowance,
    'refund'                          => $refund,
    'allowance'                       => $allowance,
    'deduction'                       => $deduction,
    'ssnit_ee'                        => $ssnit_ee,
    'petra'                        => $petra,
    'taxable_income'                  => $taxable_income,
    'paye'                            => $paye,
    'ssf'                             => $ssf,
    'welfare'                         => $welfare,
    'medical_insurance'               => $medical_insurance,
    'workmens_compensation'           => $workmens_compensation,
    'risk_allowance'                  => $risk_allowance,
    'end_of_year'                     => $end_of_year,
    'ppe'                             => $ppe,
    'staff_cost'                      => $staff_cost,
    'mgt_fee'                         => $mgt_fee,
    'gfl'                             => $gfl,
    'nhil'                            => $nhil,
    'covid'                           => $covid,
    'vat'                             => $vat,
    'c2c'                             => $c2c,
    'weekday_overtime_hours'          => $weekday_overtime_hours,
    'weekday_overtime_rate'           => $weekday_overtime_rate,
    'weekday_overtime_amount'         => $weekday_overtime_amount,
    'offday_holiday_overtime_hours'   => $offday_holiday_overtime_hours,
    'offday_holiday_overtime_rate'    => $offday_holiday_overtime_rate,
    'offday_holiday_overtime_total'   => $offday_holiday_overtime_total,
    'special_day_overtime_hours'      => $special_day_overtime_hours,
    'special_day_overtime_rate'       => $special_day_overtime_rate,
    'special_day_overtime_amount'     => $special_day_overtime_amount,
    'total_overtime_amount'           => $total_overtime_amount,
    'overtime_tax'                    => $overtime_tax,
    'total_taxation'                  => $total_taxation,
    'total_deductions'                => $total_deductions,
    'netpay'                          => $netpay,
];

    $validRecords[] = $data;
    $existingStaffIds[] = $employ;
}

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'twellium' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
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
    'Staff ID', 'Name', 'Department', 'Date Employed',
    'Expected Work Days', 'Total Hours Worked',
    'Basic', 'Basic Earned', 'Food Allowance', 'Night Allowance',
    'Refund', 'Allowance', 'Deduction',
    'SSNIT EE', 'PETRA', 'Taxable Income', 'PAYE', 'SSF', 'Welfare',
    'Medical Insurance', 'Workmens Compensation', 'Risk Allowance',
    'End of Year', 'PPE', 'Staff Cost', 'Mgt Fee', 'GFL', 'NHIL',
    'COVID', 'VAT', 'C2C',
    'Weekday OT Hours', 'Weekday OT Rate', 'Weekday OT Amount',
    'Offday/Holiday OT Hours', 'Offday/Holiday OT Rate', 'Offday/Holiday OT Total',
    'Special Day OT Hours', 'Special Day OT Rate', 'Special Day OT Amount',
    'Total Overtime Amount', 'Overtime Tax',
    'Total Taxation', 'Total Deductions', 'Net Pay'
];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:AU1')->getFont()->setBold(true);

        // Example row
        $exampleData = [
    'EMP00123', 'Ama Serwaa', 'Operations', '2022-03-15',
    22, 176.5,
    4800.00, 4800.00, 400.00, 600.00,
    150.00, 1150.00, 200.00,
    264.00, 5700.00, 850.00, 624.00, 100.00,
    180.00, 120.00, 300.00,
    0.00, 80.00,80.00, 7434.00, 1200.00, 135.00, 135.00,
    57.00, 150.00, 8916.00,
    12.5, 45.00, 562.50,
    8.0, 90.00, 720.00,
    0.0, 0.00, 0.00,
    1282.50, 192.38,
    1042.38, 1656.38, 5897.62
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
