<?php

namespace App\Http\Controllers;

use App\Models\Pzpay;
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


class PzPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'pz' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Pzpay/Index', [
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
            DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept) as shop'),
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
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept) LIKE ?', ["%{$search}%"])
              ->orWhere('bd.bank', 'like', "%{$search}%");
        });
    }

    // Sorting - now works correctly on computed columns
    $sort = $request->input('sort', 'staffid');
    $order = $request->input('order', 'asc');

    $sortMap = [
        'staffid' => 'tp.staffid',
        'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
        'shop'   => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept)'),
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

    return Inertia::render('Pzpay/Index', [
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
        $tableName = 'pz' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
            ->select(
                'tp.staffid as staff_id',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept) as shop'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay as take_home_pay'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffid', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'staffid');
        $order = $request->input('order', 'asc');
        $sortMap = [
            'staffid' => 'tp.staffid',
            'name'    => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
            'shop'    => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept)'),
            'bank'    => 'bd.bank',
            'account' => 'bd.account',
            'code'    => 'bd.code',
            'branch'  => 'bd.branch',
            'netpay'  => 'tp.netpay',
        ];
        $query->orderBy($sortMap[$sort] ?? 'tp.staffid', $order);

        $records = $query->get();

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
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->take_home_pay ? (float)$r->take_home_pay : '');
            $row++;
        }

        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('bbdcfe');
        foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);
        $filename = "pz_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

    } catch (\Exception $e) {
        Log::error("PzPay export failed: " . $e->getMessage());
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
 $tableName = 'pz' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 PzPay::createTableIfNotExists($tableName);

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
    if (count($row) < 25) {
        $errors[] = "Row " . ($index + 2) . ": Expected 24 columns, found " . count($row);
        continue;
    }

   // === MAPPED TO 24-COLUMN DATABASE STRUCTURE ===
$staffid          = trim($row[0] ?? '') ?: null;
$name             = trim($row[1] ?? '') ?: null;
$dept             = trim($row[2] ?? '') ?: null;

$basic            = is_numeric($row[3] ?? '') ? (float)$row[3] : null;
$overtime         = is_numeric($row[4] ?? '') ? (float)$row[4] : null;
$incentive        = is_numeric($row[5] ?? '') ? (float)$row[5] : null;
$bonus            = is_numeric($row[6] ?? '') ? (float)$row[6] : null;
$other            = is_numeric($row[7] ?? '') ? (float)$row[7] : null;
$month13          = is_numeric($row[8] ?? '') ? (float)$row[8] : null;
$totalearn        = is_numeric($row[9] ?? '') ? (float)$row[9] : null;

$ssnitee          = is_numeric($row[10] ?? '') ? (float)$row[10] : null;
$overtimetaxt     = is_numeric($row[11] ?? '') ? (float)$row[11] : null;
$pf               = is_numeric($row[12] ?? '') ? (float)$row[12] : null;
$taxable          = is_numeric($row[13] ?? '') ? (float)$row[13] : null;
$ssniter          = is_numeric($row[14] ?? '') ? (float)$row[14] : null;
$petra            = is_numeric($row[15] ?? '') ? (float)$row[15] : null;
$tax13            = is_numeric($row[16] ?? '') ? (float)$row[16] : null;
$totaltaxdeduct   = is_numeric($row[17] ?? '') ? (float)$row[17] : null;
$totaldeduct      = is_numeric($row[18] ?? '') ? (float)$row[18] : null;

// Net Pay – cleaned (may contain commas or currency symbols)
$rawNetpay        = trim($row[19] ?? '');
$cleanNetpay      = preg_replace('/[^0-9.-]/', '', $rawNetpay);
$netpay           = is_numeric($cleanNetpay) ? (float)$cleanNetpay : null;

$bank1            = trim($row[20] ?? '') ?: null;
$account1         = trim($row[21] ?? '') ?: null;
$branch1          = trim($row[22] ?? '') ?: null;
$ssnit1           = trim($row[23] ?? '') ?: null;
$ghcard           = trim($row[24] ?? '') ?: null;

    // === VALIDATION ===
    if (empty($staffid)) {
        $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
        continue;
    }

    if (empty($name)) {
        $errors[] = "Row " . ($index + 2) . ": Name is required.";
        continue;
    }

    if (is_null($basic) || is_null($totalearn )) {
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
    'staffid'          => $staffid,
    'name'             => $name,
    'dept'             => $dept,
    'basic'            => $basic,
    'overtime'         => $overtime,
    'incentive'        => $incentive,
    'bonus'            => $bonus,
    'other'            => $other,
    '13month'          => $month13,
    'totalearn'        => $totalearn,
    'ssnitee'          => $ssnitee,
    'overtimetaxt'     => $overtimetaxt,
    'pf'               => $pf,
    'taxable'          => $taxable,
    'ssniter'          => $ssniter,
    'petra'           => $petra,
    '13tax'            => $tax13,
    'totaltaxdeduct'   => $totaltaxdeduct,
    'totaldeduct'      => $totaldeduct,
    'netpay'           => $netpay,
    'bank1'            => $bank1,
    'account1'         => $account1,
    'branch1'          => $branch1,
    'ssnit1'           => $ssnit1,
    'ghcard'           => $ghcard,
];

    $validRecords[] = $data;
    $existingStaffIds[] = $staffid;
}

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('pzpay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('pzpay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('pzpay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('pzpay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'pz' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('pzpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('pzpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('pzpay.index', ['month' => $selectedMonth])
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
    'Staff ID', 'Name', 'Department', 'Basic Salary', 'Overtime', 'Incentive', 'Bonus',
    'Other Earnings', '13th Month', 'Total Earnings', 'SSNIT EE', 'Overtime Tax', 'PF',
    'Taxable Income', 'SSNIT ER', 'PETRA', '13th Month Tax', 'Total Tax Deduction', 'Total Deductions',
    'Net Pay', 'Bank Name', 'Account Number', 'Branch', 'SSNIT Number', 'Ghana Card'
];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:AU1')->getFont()->setBold(true);

        // Example row
        $exampleData = [
    'TNS00123', 'Kwame Osei', 'Sales',
    4200.00, 850.00, 1200.00, 500.00, 300.00, 0.00, 7050.00,
    231.00, 170.00, 0.00, 6819.00, 546.00, 546.00,0.00, 716.00, 947.00,
    6103.00,
    'GCB Bank', '003201234567', 'High Street', 'SSNIT12345678', 'GHA-123456789-1'
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
