<?php

namespace App\Http\Controllers;

use App\Models\Fonpay;
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

class FonPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'fonpackage' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Fonpay/Index', [
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

    return Inertia::render('Fonpay/Index', [
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
        $tableName = 'fonpackage' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
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

        // Sorting – works perfectly on computed columns
        $sort = $request->input('sort', 'staff_id');
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

        $query->orderBy($sortMap[$sort] ?? 'tp.staff_id', $order);

        $records = $query->get();

        // Generate Real Excel File
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

        // Styling – same beautiful look as all your other exports
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headerStyle = $sheet->getStyle('A1:H1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDEFE'); // Light blue

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "fon_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("FonPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
 $tableName = 'fonpackage' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 FonPay::createTableIfNotExists($tableName);

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
    if (count($row) < 41) {
        $errors[] = "Row " . ($index + 2) . ": Expected 38 columns, found " . count($row);
        continue;
    }

// === 41-COLUMN DATABASE MAPPING (0 → 40) ===
$staff_id                        = trim($row[0] ?? '') ?: null;
$name                            = trim($row[1] ?? '') ?: null;
$position                        = trim($row[2] ?? '') ?: null;
$date_of_employment              = trim($row[3] ?? '') ?: null;

$basic_salary                    = is_numeric($row[4] ?? '') ? (float)$row[4] : null;
$legal_work_days                 = is_numeric($row[5] ?? '') ? (int)$row[5] : null;
$number_of_days_worked           = is_numeric($row[6] ?? '') ? (float)$row[6] : null;
$no_of_absent_days               = is_numeric($row[7] ?? '') ? (float)$row[7] : null;
$absent_days_deduction           = is_numeric($row[8] ?? '') ? (float)$row[8] : null;
$basic_salary_earned             = is_numeric($row[9] ?? '') ? (float)$row[9] : null;

$number_of_days_on_night_duty    = is_numeric($row[10] ?? '') ? (float)$row[10] : null;

$normal_weekday_overtime_hours   = is_numeric($row[11] ?? '') ? (float)$row[11] : null;
$normal_weekday_overtime_rate    = is_numeric($row[12] ?? '') ? (float)$row[12] : null;
$weekday_overtime_total          = is_numeric($row[13] ?? '') ? (float)$row[13] : null;
$saturday_overtime_hours         = is_numeric($row[14] ?? '') ? (float)$row[14] : null;
$saturday_overtime_rate          = is_numeric($row[15] ?? '') ? (float)$row[15] : null;
$saturday_overtime_total         = is_numeric($row[16] ?? '') ? (float)$row[16] : null;
$sunday_holiday_overtime_hours   = is_numeric($row[17] ?? '') ? (float)$row[17] : null;
$sunday_holiday_overtime_rate    = is_numeric($row[18] ?? '') ? (float)$row[18] : null;
$sunday_holiday_overtime_total   = is_numeric($row[19] ?? '') ? (float)$row[19] : null;
$total_overtime_amount           = is_numeric($row[20] ?? '') ? (float)$row[20] : null;

$night_duty_allowance            = is_numeric($row[21] ?? '') ? (float)$row[21] : null;

$gross_salary                    = is_numeric($row[22] ?? '') ? (float)$row[22] : null;

$ssf_13                          = is_numeric($row[23] ?? '') ? (float)$row[23] : null;
$medical_insurance               = is_numeric($row[24] ?? '') ? (float)$row[24] : null;
$ppe                             = is_numeric($row[25] ?? '') ? (float)$row[25] : null;
$workmen_compensation            = is_numeric($row[26] ?? '') ? (float)$row[26] : null;
$management_fee_10               = is_numeric($row[27] ?? '') ? (float)$row[27] : null;
$getfund_2_5                     = is_numeric($row[28] ?? '') ? (float)$row[28] : null;
$nhil_2_5                        = is_numeric($row[29] ?? '') ? (float)$row[29] : null;

$vat_15                          = is_numeric($row[30] ?? '') ? (float)$row[30] : null;
$cost_to_fon_packaging           = is_numeric($row[31] ?? '') ? (float)$row[31] : null;

$gross_salary_repeat             = is_numeric($row[32] ?? '') ? (float)$row[32] : null;

$ssnit_ee_5_5                    = is_numeric($row[33] ?? '') ? (float)$row[33] : null;

$petra                    = is_numeric($row[34] ?? '') ? (float)$row[34] : null;

$taxable_income                  = is_numeric($row[35] ?? '') ? (float)$row[35] : null;
$paye                            = is_numeric($row[36] ?? '') ? (float)$row[36] : null;
$overtime_tax                    = is_numeric($row[37] ?? '') ? (float)$row[37] : null;
$total_taxation                  = is_numeric($row[38] ?? '') ? (float)$row[38] : null;
$total_deductions                = is_numeric($row[39] ?? '') ? (float)$row[39] : null;

// Take Home Pay – cleaned (final column)
$rawTakeHome                     = trim($row[40] ?? '');
$cleanTakeHome                   = preg_replace('/[^0-9.-]/', '', $rawTakeHome);
$take_home_pay                   = is_numeric($cleanTakeHome) ? (float)$cleanTakeHome : null;

    // === VALIDATION ===
    if (empty($staff_id)) {
        $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
        continue;
    }

    if (empty($name)) {
        $errors[] = "Row " . ($index + 2) . ": Name is required.";
        continue;
    }

    if (is_null($basic_salary  )) {
        $errors[] = "Row " . ($index + 2) . ": Basic is required.";
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
    'staff_id'                        => $staff_id,
    'name'                            => $name,
    'position'                        => $position,
    'date_of_employment'              => $date_of_employment,
    'basic_salary'                    => $basic_salary,
    'legal_work_days'                 => $legal_work_days,
    'number_of_days_worked'           => $number_of_days_worked,
    'no_of_absent_days'               => $no_of_absent_days,
    'absent_days_deduction'           => $absent_days_deduction,
    'basic_salary_earned'             => $basic_salary_earned,
    'number_of_days_on_night_duty'    => $number_of_days_on_night_duty,
    'normal_weekday_overtime_hours'   => $normal_weekday_overtime_hours,
    'normal_weekday_overtime_rate'    => $normal_weekday_overtime_rate,
    'weekday_overtime_total'          => $weekday_overtime_total,
    'saturday_overtime_hours'         => $saturday_overtime_hours,
    'saturday_overtime_rate'          => $saturday_overtime_rate,
    'saturday_overtime_total'         => $saturday_overtime_total,
    'sunday_holiday_overtime_hours'   => $sunday_holiday_overtime_hours,
    'sunday_holiday_overtime_rate'    => $sunday_holiday_overtime_rate,
    'sunday_holiday_overtime_total'   => $sunday_holiday_overtime_total,
    'total_overtime_amount'           => $total_overtime_amount,
    'night_duty_allowance'            => $night_duty_allowance,
    'gross_salary'                    => $gross_salary,
    'ssf_13'                          => $ssf_13,
    'medical_insurance'               => $medical_insurance,
    'ppe'                             => $ppe,
    'workmen_compensation'            => $workmen_compensation,
    'management_fee_10'               => $management_fee_10,
    'getfund_2_5'                     => $getfund_2_5,
    'nhil_2_5'                        => $nhil_2_5,
    
    'vat_15'                          => $vat_15,
    'cost_to_fon_packaging'           => $cost_to_fon_packaging,
    'gross_salary_repeat'             => $gross_salary_repeat,
    'ssnit_ee_5_5'                    => $ssnit_ee_5_5,
    'petra'                           => $petra,
    'taxable_income'                  => $taxable_income,
    'paye'                            => $paye,
    'overtime_tax'                    => $overtime_tax,
    'total_taxation'                  => $total_taxation,
    'total_deductions'                => $total_deductions,
    'take_home_pay'                   => $take_home_pay,
];

    $validRecords[] = $data;
    $existingStaffIds[] = $staff_id;
}

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('fonpay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('fonpay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('fonpay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('fonpay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'fonpackage' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('fonpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('fonpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('fonpay.index', ['month' => $selectedMonth])
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
    'Staff ID','Name','Position','Date of Employment',
    'Basic Salary','Legal Work Days','Days Worked','Absent Days','Absent Deduction','Basic Earned',
    'Night Duty Days',
    'Weekday OT Hours','Weekday OT Rate','Weekday OT Total',
    'Saturday OT Hours','Saturday OT Rate','Saturday OT Total',
    'Sunday/Holiday OT Hours','Sunday/Holiday OT Rate','Sunday/Holiday OT Total',
    'Total Overtime Amount','Night Duty Allowance',
    'Gross Salary',
    'SSF 13%','Medical Insurance','PPE','Workmen Compensation',
    'Management Fee 10%','GETFund 2.5%','NHIL 2.5%','VAT 15%','Cost to FON Packaging',
    'Gross Salary (Repeat)',
    'SSNIT EE 5.5%','PETRA','Taxable Income','PAYE','Overtime Tax','Total Taxation',
    'Total Deductions','Take Home Pay'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:Y1')->getFont()->setBold(true);  // Y1 because now only 25 columns (A to Y)



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
