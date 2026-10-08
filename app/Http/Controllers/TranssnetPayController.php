<?php

namespace App\Http\Controllers;

use App\Models\TranssnetPay;
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

class TranssnetPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'transsnet' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Transsnetpay/Index', [
            'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
            'selectedMonth' => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords' => 0,
            'searchCount' => null,
            'flash' => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
        ]);
    }

    $query = DB::table($tableName . ' as tp')
        ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
        ->select(
            'tp.staffid',
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
            $q->where('tp.staffid', 'like', "%{$search}%")
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) LIKE ?', ["%{$search}%"])
              ->orWhere('bd.bank', 'like', "%{$search}%");
        });
    }

    // Sorting - now works correctly on computed columns
    $sort = $request->input('sort', 'staffid');
    $order = $request->input('order', 'asc');

    $sortMap = [
        'staffid' => 'tp.staffid',
        'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
        'shop'   => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
        'bank'   => 'bd.bank',
        'account'=> 'bd.account',
        'code'   => 'bd.code',
        'branch' => 'bd.branch',
        'netpay' => 'tp.netpay',
    ];

    $sortColumn = $sortMap[$sort] ?? 'tp.staffid';
    $query->orderBy($sortColumn, $order);

    $perPage = 10;
    $records = $query->paginate($perPage)->appends($request->query());

    $totalRecords = DB::table($tableName)->count();
    $searchCount = $search ? $query->count() : null;

    return Inertia::render('Transsnetpay/Index', [
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
        $tableName = 'transsnet' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
            ->select(
                'tp.staffid',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) as shop'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffid', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort  = $request->input('sort', 'staffid');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'staffid' => 'tp.staffid',
            'name'    => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
            'shop'    => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
            'bank'    => 'bd.bank',
            'account' => 'bd.account',
            'code'    => 'bd.code',
            'branch'  => 'bd.branch',
            'netpay'  => 'tp.netpay',
        ];

        $sortColumn = $sortMap[$sort] ?? 'tp.staffid';
        $query->orderBy($sortColumn, $order);

        $records = $query->get();

        // ==================== EXCEL GENERATION ====================
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Name', 'Department', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Net Pay'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staffid);
            $sheet->setCellValue("B{$row}", $r->name);
            $sheet->setCellValue("C{$row}", $r->shop ?? '');
            $sheet->setCellValue("D{$row}", $r->bank ?? '');

            // 100% BULLETPROOF: Account & Code as TEXT
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '',    DataType::TYPE_STRING);

            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->netpay ? (float)$r->netpay : '');

            $row++;
        }

        // Professional styling — same as HondaPay, A2soya, Transsion, Tecno
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('bbdcfe');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "transsnet_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

    } catch (\Exception $e) {
        Log::error("TranssnetPay export failed: " . $e->getMessage());
        return response()->json(['error' => 'Export failed'], 500);
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
 $tableName = 'transsnet' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 TranssnetPay::createTableIfNotExists($tableName);

 $file = $request->file('file');
 $reader = new XlsxReader();
 $spreadsheet = $reader->load($file->getRealPath());
 $sheet = $spreadsheet->getActiveSheet();
 $rows = $sheet->toArray();
 array_shift($rows); // Remove header row

 // Check for existing staff IDs
 $existingStaffIds = DB::table($tableName)->pluck('staffid')->toArray();
 $duplicateStaffIds = [];
 $validRecords = [];
 $errors = [];

 foreach ($rows as $index => $row) {
    if (count($row) < 44) {
        $errors[] = "Row " . ($index + 2) . ": Expected 46 columns, found " . count($row);
        continue;
    }

    // === CONVERTED TO MATCH FIRST STYLE ===
$staffid                  = trim($row[0] ?? '')  ?: null;   // assuming column 0 is staffid (not present in array keys, adjust if needed)
$name                     = trim($row[1] ?? '')  ?: null;
$business_line            = trim($row[2] ?? '')  ?: null;
$department               = trim($row[3] ?? '')  ?: null;
$position                 = trim($row[4] ?? '')  ?: null;
$hired_date               = trim($row[5] ?? '')  ?: null;
$last_date                = trim($row[6] ?? '')  ?: null;
$work_place               = trim($row[7] ?? '')  ?: null;
$staff_category           = trim($row[8] ?? '')  ?: null;
$expiration_date          = trim($row[9] ?? '')  ?: null;
$contracting_entity       = trim($row[10] ?? '') ?: null;
$bank_name                = trim($row[11] ?? '') ?: null;
$account_name             = trim($row[12] ?? '') ?: null;
$bank_account_no          = trim($row[13] ?? '') ?: null;
$branch_code              = trim($row[14] ?? '') ?: null;
$basic                    = is_numeric($row[15] ?? '') ? (float)$row[15] : 0;
$performance_basis        = trim($row[16] ?? '') ?: null;
$coefficient              = is_numeric($row[17] ?? '') ? (float)$row[17] : 0;
$performance_final        = is_numeric($row[18] ?? '') ? (float)$row[18] : 0;
$call_allowance           = is_numeric($row[19] ?? '') ? (float)$row[19] : 0;
$house_allowance          = is_numeric($row[20] ?? '') ? (float)$row[20] : 0;
$traffic_allowance        = is_numeric($row[21] ?? '') ? (float)$row[21] : 0;
$commission               = is_numeric($row[22] ?? '') ? (float)$row[22] : 0;
$severance_pay            = is_numeric($row[23] ?? '') ? (float)$row[23] : 0;
$other_income             = is_numeric($row[24] ?? '') ? (float)$row[24] : 0;
$work_days                = is_numeric($row[25] ?? '') ? (int)$row[25]   : 0;
$absent_days              = is_numeric($row[26] ?? '') ? (int)$row[26]   : 0;
$absent_days_deduction    = is_numeric($row[27] ?? '') ? (float)$row[27] : 0;
$other_deduction          = is_numeric($row[28] ?? '') ? (float)$row[28] : 0;
$gross_salary             = is_numeric($row[29] ?? '') ? (float)$row[29] : 0;
$petra_ee_5               = is_numeric($row[30] ?? '') ? (float)$row[30] : 0;
$ssnit_ee_05              = is_numeric($row[31] ?? '') ? (float)$row[31] : 0;
$base_for_paye            = is_numeric($row[32] ?? '') ? (float)$row[32] : 0;
$ee_paye                  = is_numeric($row[33] ?? '') ? (float)$row[33] : 0;
$ssnit_er_13              = is_numeric($row[34] ?? '') ? (float)$row[34] : 0;
$outsourced_service       = is_numeric($row[35] ?? '') ? (float)$row[35] : 0;
$getfund                  = is_numeric($row[36] ?? '') ? (float)$row[36] : 0;
$nhiil                    = is_numeric($row[37] ?? '') ? (float)$row[37] : 0;

$vat_for_music_ghana      = is_numeric($row[38] ?? '') ? (float)$row[38] : 0;
$total_cost_of_outsource_service = is_numeric($row[39] ?? '') ? (float)$row[39] : 0;
$ee_cost                  = is_numeric($row[40] ?? '') ? (float)$row[40] : 0;
$er_cost                  = is_numeric($row[41] ?? '') ? (float)$row[41] : 0;
$after_tax_deductions     = is_numeric($row[42] ?? '') ? (float)$row[42] : 0;

// Net Pay (last column) – clean any non-numeric characters just like banktransfer in the first example
$rawNetpay                = trim($row[43] ?? '');
$cleanNetpay              = preg_replace('/[^0-9.-]/', '', $rawNetpay);
$netpay                   = is_numeric($cleanNetpay) ? (float)$cleanNetpay : null;

$total_hr_cost            = is_numeric($row[44] ?? '') ? (float)$row[45] : 0;

    // === VALIDATION ===
    if (empty($staffid)) {
        $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
        continue;
    }

    if (empty($name)) {
        $errors[] = "Row " . ($index + 2) . ": Name is required.";
        continue;
    }

    if (is_null($basic) || is_null($gross_salary )) {
        $errors[] = "Row " . ($index + 2) . ": Basic and Gross Pay are required.";
        continue;
    }

    if (is_null($netpay)) {
        $errors[] = "Row " . ($index + 2) . ": Bank Transfer (Net Pay) must be a valid number. Found: '$rawBankTransfer'";
        continue;
    }

    // Duplicate check
    if (in_array($staffid, $existingStaffIds)) {
        $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $staffid";
        continue;
    }

    // === FINAL DATA ARRAY - MATCHING THE SECOND FORMAT ===
$data = [
    'staffid'                        => $staffid,
    'name'                           => $name,
    'business_line'                  => $business_line,
    'department'                     => $department,
    'position'                       => $position,
    'hired_date'                     => $hired_date,
    'last_date'                      => $last_date,
    'work_place'                     => $work_place,
    'staff_category'                 => $staff_category,
    'expiration_date'                => $expiration_date,
    'contracting_entity'             => $contracting_entity,
    'bank_name'                      => $bank_name,
    'account_name'                   => $account_name,
    'bank_account_no'                => $bank_account_no,
    'branch_code'                    => $branch_code,
    'basic'                          => $basic,
    'performance_basis'              => $performance_basis,
    'coefficient'                    => $coefficient,
    'performance_final'              => $performance_final,
    'call_allowance'                 => $call_allowance,
    'house_allowance'                => $house_allowance,
    'traffic_allowance'              => $traffic_allowance,
    'commission'                     => $commission,
    'severance_pay'                  => $severance_pay,
    'other_income'                   => $other_income,
    'work_days'                      => $work_days,
    'absent_days'                    => $absent_days,
    'absent_days_deduction'          => $absent_days_deduction,
    'other_deduction'                => $other_deduction,
    'gross_salary'                   => $gross_salary,
    'petra_ee_5'                     => $petra_ee_5,
    'ssnit_ee_05'                    => $ssnit_ee_05,
    'base_for_paye'                  => $base_for_paye,
    'ee_paye'                        => $ee_paye,
    'ssnit_er_13'                    => $ssnit_er_13,
    'outsourced_service'             => $outsourced_service,
    'getfund'                        => $getfund,
    'nhiil'                          => $nhiil,
    
    'vat_for_music_ghana'            => $vat_for_music_ghana,
    'total_cost_of_outsource_service'=> $total_cost_of_outsource_service,
    'ee_cost'                        => $ee_cost,
    'er_cost'                        => $er_cost,
    'after_tax_deductions'           => $after_tax_deductions,
    'netpay'                         => $netpay,       // This IS the Net Pay (cleaned exactly like banktransfer in the first example)
    'total_hr_cost'                  => $total_hr_cost,
];

    $validRecords[] = $data;
    $existingStaffIds[] = $staffid;
}

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'transsnet' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
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
            'Staff ID', 'Name', 'Business Line', 'Department', 'Position', 'Hired Date', 'Last Date', 'Work Place',
            'Staff Category', 'Expiration Date', 'Contracting Entity', 'Bank Name', 'Account Name', 'Bank Account No',
            'Branch Code', 'Basic', 'Performance Basis', 'Coefficient', 'Performance Final', 'Call Allowance',
            'House Allowance', 'Traffic Allowance', 'Commission', 'Severance Pay', 'Other Income', 'Work Days',
            'Absent Days', 'Absent Days Deduction', 'Other Deduction', 'Gross Salary', 'Petra EE 5%', 'SSNIT EE 0.5%',
            'Base for PAYE', 'EE PAYE', 'SSNIT ER 13%', 'Outsourced Service', 'GETFund', 'NHIIL', 
            'VAT for Music Ghana', 'Total Cost of Outsource Service', 'EE Cost', 'ER Cost', 'After Tax Deductions',
            'Net Pay', 'Total HR Cost'
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:AU1')->getFont()->setBold(true);

        // Example row
        $exampleData = [
            'TNS00123', 'Kwame Osei', 'Retail', 'Sales', 'Sales Executive', '2023-06-15', '', 'Accra Mall',
            'Permanent', '2026-06-14', 'Transsnet Ghana Ltd', 'GCB Bank', 'Kwame Osei', '003201234567', '002',
            3500.00, 'Yes', 1.15, 4025.00, 200.00, 800.00, 300.00, 500.00, 0.00, 150.00, 26, 0, 0.00, 100.00,
            6975.00, 0.00, 34.88, 6940.12, 750.25, 455.00, 2000.00, 50.00,  20.00, 0.00, 2500.00,
            4475.25, 4025.00, 100.00, 200.00, 5975.00, 10000.00
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
