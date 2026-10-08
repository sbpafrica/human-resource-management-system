<?php

namespace App\Http\Controllers;

use App\Models\Sheferpay;
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

class SheferPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'shefer' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Sheferpay/Index', [
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
            DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name1) as name'),
            DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) as shop'),
            'bd.bank',
            'bd.account',
            'bd.code',
            'bd.branch',
            'tp.net_pay'
        );

    // Search across both sources
    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search, $tableName) {
            $q->where('tp.staff_id', 'like', "%{$search}%")
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name1) LIKE ?', ["%{$search}%"])
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) LIKE ?', ["%{$search}%"])
              ->orWhere('bd.bank', 'like', "%{$search}%");
        });
    }

    // Sorting - now works correctly on computed columns
    $sort = $request->input('sort', 'staff_id');
    $order = $request->input('order', 'asc');

    $sortMap = [
        'staff_id' => 'tp.staff_id',
        'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name1)'),
        'shop'   => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
        'bank'   => 'bd.bank',
        'account'=> 'bd.account',
        'code'   => 'bd.code',
        'branch' => 'bd.branch',
        'net_pay' => 'tp.net_pay',
    ];

    $sortColumn = $sortMap[$sort] ?? 'tp.staff_id';
    $query->orderBy($sortColumn, $order);

    $perPage = 30;
    $records = $query->paginate($perPage)->appends($request->query());

    $totalRecords = DB::table($tableName)->count();
    $searchCount = $search ? $query->count() : null;

    return Inertia::render('Sheferpay/Index', [
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
        $tableName = 'shefer' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staff_id', '=', 'bd.employid')
            ->select(
                'tp.staff_id',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name1) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) as shop'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.net_pay as take_home_pay'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staff_id', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name1) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting – safe for computed columns
        $sort = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'staff_id'      => 'tp.staff_id',
            'name'          => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name1)'),
            'shop'          => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
            'bank'          => 'bd.bank',
            'account'       => 'bd.account',
            'code'          => 'bd.code',
            'branch'        => 'bd.branch',
            'take_home_pay' => 'tp.net_pay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.staff_id', $order);

        $records = $query->get();

        // Generate Excel with PhpSpreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Name', 'Department', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Take Home Pay'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staff_id);
            $sheet->setCellValue("B{$row}", $r->name);
            $sheet->setCellValue("C{$row}", $r->shop ?? '');
            $sheet->setCellValue("D{$row}", $r->bank ?? '');
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->take_home_pay ? number_format((float)$r->take_home_pay, 2, '.', '') : '');
            $row++;
        }

        // Styling – identical to all your other exports
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headerStyle = $sheet->getStyle('A1:H1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDEFE');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "shefer_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);

    } catch (\Exception $e) {
        Log::error("SheferPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Export failed. Please try again.'], 500);
    }
}

/**
     * Upload Excel file
     */
   public function upload(Request $request)
 {
 $request->validate([
 'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
 'month' => ['required', 'date_format:Y-m'],
 ]);

 try {
 $selectedMonth = $request->input('month');
 session(['selected_month' => $selectedMonth]);
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'shefer' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 SheferPay::createTableIfNotExists($tableName);

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
    if (count($row) < 49) {
        $errors[] = "Row " . ($index + 2) . ": Expected 48 columns, found " . count($row);
        continue;
    }

// === 48-COLUMN DATABASE MAPPING (0 to 47) ===
$staff_id             = trim($row[0] ?? '') ?: null;
$name1                = trim($row[1] ?? '') ?: null;
$position             = trim($row[2] ?? '') ?: null;
$department           = trim($row[3] ?? '') ?: null;
$startdate            = trim($row[4] ?? '') ?: null;

$basic                = is_numeric($row[5] ?? '') ? (float)$row[5] : null;
$total_weekdays       = is_numeric($row[6] ?? '') ? (int)$row[6] : null;
$no_of_days_worked    = is_numeric($row[7] ?? '') ? (float)$row[7] : null;
$leavepay             = is_numeric($row[8] ?? '') ? (float)$row[8] : null;
$absent_days          = is_numeric($row[9] ?? '') ? (float)$row[9] : null;
$basic_earned         = is_numeric($row[10] ?? '') ? (float)$row[10] : null;

$nightdays            = is_numeric($row[11] ?? '') ? (float)$row[11] : null;

$weekdayOT_hours      = is_numeric($row[12] ?? '') ? (float)$row[12] : null;
$weekdayOT_rate       = is_numeric($row[13] ?? '') ? (float)$row[13] : null;
$weekdayOT_amount     = is_numeric($row[14] ?? '') ? (float)$row[14] : null;
$weekendOT_hours      = is_numeric($row[15] ?? '') ? (float)$row[15] : null;
$weekendOT_rate       = is_numeric($row[16] ?? '') ? (float)$row[16] : null;
$weekendOT_amount   = is_numeric($row[17] ?? '') ? (float)$row[17] : null;
$sunOT_hours          = is_numeric($row[18] ?? '') ? (float)$row[18] : null;
$sunOT_rate           = is_numeric($row[19] ?? '') ? (float)$row[19] : null;
$sunOT_amount         = is_numeric($row[20] ?? '') ? (float)$row[20] : null;

$fixedotbasic         = is_numeric($row[21] ?? '') ? (float)$row[21] : null;
$fixedot                 = is_numeric($row[22] ?? '') ? (float)$row[22] : null;
$total_overtime       = is_numeric($row[23] ?? '') ? (float)$row[23] : null;

$foodbasic            = is_numeric($row[24] ?? '') ? (float)$row[24] : null;
$food                 = is_numeric($row[25] ?? '') ? (float)$row[25] : null;
$airtimebasic         = is_numeric($row[26] ?? '') ? (float)$row[26] : null;
$airtime              = is_numeric($row[27] ?? '') ? (float)$row[27] : null;
$nightallo            = is_numeric($row[28] ?? '') ? (float)$row[28] : null;
$responsebasic        = is_numeric($row[29] ?? '') ? (float)$row[29] : null;
$responseallo         = is_numeric($row[30] ?? '') ? (float)$row[30] : null;
$transbasic           = is_numeric($row[31] ?? '') ? (float)$row[31] : null;
$transport            = is_numeric($row[32] ?? '') ? (float)$row[32] : null;
$medicalbasic         = is_numeric($row[33] ?? '') ? (float)$row[33] : null;
$medical              = is_numeric($row[34] ?? '') ? (float)$row[34] : null;
$total_allowance      = is_numeric($row[35] ?? '') ? (float)$row[35] : null;

$gross                = is_numeric($row[36] ?? '') ? (float)$row[36] : null;

$emp_ssnit_13_percent = is_numeric($row[37] ?? '') ? (float)$row[37] : null;
$ssnit_5_5_percent    = is_numeric($row[38] ?? '') ? (float)$row[38] : null;
$petra   = is_numeric($row[39] ?? '') ? (float)$row[39] : null;
$taxable_income       = is_numeric($row[40] ?? '') ? (float)$row[40] : null;
$paye                 = is_numeric($row[41] ?? '') ? (float)$row[41] : null;
$ot_tax               = is_numeric($row[42] ?? '') ? (float)$row[42] : null;
$total_tax            = is_numeric($row[43] ?? '') ? (float)$row[43] : null;

$absent_without_pay   = is_numeric($row[44] ?? '') ? (float)$row[44] : null;
$salaryloan           = is_numeric($row[45] ?? '') ? (float)$row[45] : null;
$total_deductions     = is_numeric($row[46] ?? '') ? (float)$row[46] : null;
$refunds              = is_numeric($row[47] ?? '') ? (float)$row[47] : null;

// Net Pay – cleaned (last column)
$rawNet               = trim($row[48] ?? '');
$cleanNet             = preg_replace('/[^0-9.-]/', '', $rawNet);
$net_pay              = is_numeric($cleanNet) ? (float)$cleanNet : null;


// Take Home Pay – cleaned like bank transfer (final column)
$rawTakeHome             = trim($row[48] ?? '');
$cleanTakeHome           = preg_replace('/[^0-9.-]/', '', $rawTakeHome);
$net_pay           = is_numeric($cleanTakeHome) ? (float)$cleanTakeHome : null;
    // === VALIDATION ===
    if (empty($staff_id)) {
        $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
        continue;
    }

    if (empty($name1)) {
        $errors[] = "Row " . ($index + 2) . ": Name is required.";
        continue;
    }

    if (is_null($basic ) || is_null($gross)) {
        $errors[] = "Row " . ($index + 2) . ": Basic and Gross Pay are required.";
        continue;
    }

    if (is_null($net_pay)) {
        $errors[] = "Row " . ($index + 2) . ": Bank Transfer (Net Pay) must be a valid number. Found: '$rawTakeHome'";
        continue;
    }

    // Duplicate check
    if (in_array($staff_id, $existingStaffIds)) {
        $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $staff_id";
        continue;
    }

    // === FINAL DATA ARRAY - MATCHING THE SECOND FORMAT ===
$data = [
    'staff_id'              => $staff_id,
    'name1'                 => $name1,
    'position'              => $position,
    'department'            => $department,
    'startdate'             => $startdate,
    'basic'                 => $basic,
    'total_weekdays'        => $total_weekdays,
    'no_of_days_worked'     => $no_of_days_worked,
    'leavepay'              => $leavepay,
    'absent_days'           => $absent_days,
    'basic_earned'          => $basic_earned,
    'nightdays'             => $nightdays,
    'weekdayOT_hours'       => $weekdayOT_hours,
    'weekdayOT_rate'        => $weekdayOT_rate,
    'weekdayOT_amount'      => $weekdayOT_amount,
    'weekendOT_hours'       => $weekendOT_hours,
    'weekendOT_rate'       => $weekendOT_rate,
    'weekendOT_amount'      => $weekendOT_amount,
    'sunOT_hours'           => $sunOT_hours,
    'sunOT_rate'            => $sunOT_rate,
    'sunOT_amount'          => $sunOT_amount,
    'fixedotbasic'          => $fixedotbasic,
    'fixedot'               => $fixedot,
    'total_overtime'        => $total_overtime,
    'foodbasic'             => $foodbasic,
    'food'                  => $food,
    'airtimebasic'          => $airtimebasic,
    'airtime'               => $airtime,
    'nightallo'             => $nightallo,
    'responsebasic'         => $responsebasic,
    'responseallo'          => $responseallo,
    'transbasic'            => $transbasic,
    'transport'             => $transport,
    'medicalbasic'          => $medicalbasic,
    'medical'               => $medical,
    'total_allowance'       => $total_allowance,
    'gross'                 => $gross,
    'emp_ssnit_13_percent'  => $emp_ssnit_13_percent,
    'ssnit_5_5_percent'     => $ssnit_5_5_percent,
     'petra'                => $petra,
    'taxable_income'        => $taxable_income,
    'paye'                  => $paye,
    'ot_tax'                => $ot_tax,
    'total_tax'             => $total_tax,
    'absent_without_pay'    => $absent_without_pay,
    'salaryloan'            => $salaryloan,
    'total_deductions'      => $total_deductions,
    'refunds'               => $refunds,
    'net_pay'               => $net_pay,
];

    $validRecords[] = $data;
    $existingStaffIds[] = $staff_id;
}

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'shefer' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
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
    'Staff ID','Name','Position','Department','Start Date',
    'Basic Salary','Total Weekdays','Days Worked','Leave Pay','Absent Days','Basic Earned',
    'Night Days',
    'Weekday OT Hours','Weekday OT Rate','Weekday OT Amount',
    'Weekend OT Hours','Weekend OT Rate','Weekend OT Amount',
    'Sunday OT Hours','Sunday OT Rate','Sunday OT Amount',
    'Fixed OT Basic','Fixed OT','Total Overtime',
    'Food Basic','Food Allowance',
    'Airtime Basic','Airtime Allowance',
    'Night Allowance',
    'Response Basic','Response Allowance',
    'Transport Basic','Transport Allowance',
    'Medical Basic','Medical Allowance',
    'Total Allowance',
    'Gross Salary',
    'Employer SSNIT (13%)','Employee SSNIT (5.5%)','PETRA',
    'Taxable Income','PAYE','OT Tax','Total Tax',
    'Absent Without Pay','Salary Loan',
    'Total Deductions','Refunds','Net Pay'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:Y1')->getFont()->setBold(true);  // Y1 because now only 25 columns (A to Y)

// Example row matching the new structure exactly
$exampleData = [
    'ST00123','Akosua Boateng','Team Leader','Operations','2023-01-10',
    5800.00,22,20.5,200.00,1.5,5270.45,
    8,
    12.5,55.00,687.50,
    16.0,110.00,1760.00,
    4.0,165.00,660.00,
    500.00,750.00,3097.50,
    400.00,400.00,
    300.00,300.00,
    600.00,
    200.00,200.00,200.00,
    400.00,400.00,
    500.00,500.00,
    2800.00,
    10867.95,
    1413.00,318.50,
    10449.45,1245.80,309.75,1555.55,
    341.00,500.00,
    2346.55,150.00,
    8270.90
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
