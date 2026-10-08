<?php

namespace App\Http\Controllers;

use App\Models\Melcompay;
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

class MelcomPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'melcom' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Melcompay/Index', [
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
            DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename) as name'),
            DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname) as shop'),
            'bd.bank',
            'bd.account',
            'bd.code',
            'bd.branch',
            'tp.banktransfer'
        );

    // Search across both sources
    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search, $tableName) {
            $q->where('tp.employ', 'like', "%{$search}%")
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename) LIKE ?', ["%{$search}%"])
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname) LIKE ?', ["%{$search}%"])
              ->orWhere('bd.bank', 'like', "%{$search}%");
        });
    }

    // Sorting - now works correctly on computed columns
    $sort = $request->input('sort', 'employ');
    $order = $request->input('order', 'asc');

    $sortMap = [
        'employ' => 'tp.employ',
        'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename)'),
        'shop'   => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname)'),
        'bank'   => 'bd.bank',
        'account'=> 'bd.account',
        'code'   => 'bd.code',
        'branch' => 'bd.branch',
        'banktransfer' => 'tp.banktransfer',
    ];

    $sortColumn = $sortMap[$sort] ?? 'tp.employ';
    $query->orderBy($sortColumn, $order);

    $perPage = 10;
    $records = $query->paginate($perPage)->appends($request->query());

    $totalRecords = DB::table($tableName)->count();
    $searchCount = $search ? $query->count() : null;

    return Inertia::render('Melcompay/Index', [
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
        $tableName = 'melcom' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.employ', '=', 'bd.employid')
            ->select(
                'tp.employ as staff_id',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname) as shop'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.banktransfer as take_home_pay'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.employ', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting – works perfectly on computed columns
        $sort = $request->input('sort', 'employ');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'employ'        => 'tp.employ',
            'staff_id'      => 'tp.employ',
            'name'          => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename)'),
            'shop'          => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname)'),
            'bank'          => 'bd.bank',
            'account'       => 'bd.account',
            'code'          => 'bd.code',
            'branch'        => 'bd.branch',
            'banktransfer'  => 'tp.banktransfer',
            'take_home_pay' => 'tp.banktransfer',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.employ', $order);

        $records = $query->get();

        // Generate Real Excel with PhpSpreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Name', 'Shop/Branch', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Net Pay (Bank Transfer)'];
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
            ->getStartColor()->setARGB('BBDEFE'); // Light blue header

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "melcom_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("MelcomPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Export failed. Please try again.'], 500);
    }
}

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
 $tableName = 'melcom' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 MelcomPay::createTableIfNotExists($tableName);

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
    if (count($row) < 39) {
        $errors[] = "Row " . ($index + 2) . ": Expected 39 columns, found " . count($row);
        continue;
    }

    // === DIRECT MAPPING FROM EXCEL (39 COLUMNS) ===
    $employ             = trim($row[0] ?? '')  ?: null;   // Staff ID
    $empoyeename        = trim($row[1] ?? '')  ?: null;   // Name
    $outsourcename      = trim($row[2] ?? '')  ?: null;   // Outsourcing Company
    $designation        = trim($row[3] ?? '')  ?: null;   // Position
    $joindate           = trim($row[4] ?? '')  ?: null;   // Join Date
    $shopname           = trim($row[5] ?? '')  ?: null;   // Shop/Branch
    $category           = trim($row[6] ?? '')  ?: null;   // Staff Category
    $saturdayot         = $row[7] ?? 0;
    $actualdays         = is_numeric($row[8]  ?? '') ? (float)$row[8]  : null;
    $night              = is_numeric($row[9]  ?? '') ? (float)$row[9]  : 0;
    $halfdays           = is_numeric($row[10] ?? '') ? (float)$row[10] : 0;
    $satoffdays         = is_numeric($row[11] ?? '') ? (float)$row[11] : 0;
    $sunoffdays         = is_numeric($row[12] ?? '') ? (float)$row[12] : 0;
    $leavededuct        = is_numeric($row[13] ?? '') ? (float)$row[13] : 0;
    $absentdays         = is_numeric($row[14] ?? '') ? (float)$row[14] : 0;
    $absenthrs          = is_numeric($row[15] ?? '') ? (float)$row[15] : 0;
    $holiday            = is_numeric($row[16] ?? '') ? (float)$row[16] : 0;
    $weekdaysOT         = is_numeric($row[17] ?? '') ? (float)$row[17] : 0;
    $weekendOT          = is_numeric($row[18] ?? '') ? (float)$row[18] : 0;
    $holidayot          = is_numeric($row[19] ?? '') ? (float)$row[19] : 0;
    $basic              = is_numeric($row[20] ?? '') ? (float)$row[20] : null;
    $absentwithoutpay   = is_numeric($row[21] ?? '') ? (float)$row[21] : 0;
    $basicentitlement   = is_numeric($row[22] ?? '') ? (float)$row[22] : null;
    $transport          = is_numeric($row[23] ?? '') ? (float)$row[23] : 0;
    // Excel column 25 (index 24) = Normal Overtime.
    $normalovertime     = is_numeric($row[24] ?? '') ? (float)$row[24] : 0;
    $saturdayovertime   = is_numeric($row[25] ?? '') ? (float)$row[25] : 0;
    $holidayotamt       = is_numeric($row[26] ?? '') ? (float)$row[26] : 0;
    $gross              = is_numeric($row[27] ?? '') ? (float)$row[27] : null;
    $ssniter              = is_numeric($row[28] ?? '') ? (float)$row[28] : 0;
     $ssnitee              = is_numeric($row[29] ?? '') ? (float)$row[29] : 0;
      $ssnit              = is_numeric($row[30] ?? '') ? (float)$row[30] : 0;
    $taxable          = is_numeric($row[31] ?? '') ? (float)$row[31] : 0;
    $paye               = is_numeric($row[32] ?? '') ? (float)$row[32] : 0;
    $totalovertime      = is_numeric($row[33] ?? '') ? (float)$row[33] : 0;
    $overtimetax        = is_numeric($row[34] ?? '') ? (float)$row[34] : 0;
    $totaltax           = is_numeric($row[35] ?? '') ? (float)$row[35] : 0;
    $trust              = is_numeric($row[36] ?? '') ? (float)$row[36] : 0;
    $totaldeduction     = is_numeric($row[37] ?? '') ? (float)$row[37] : 0;

    // Excel column 39 (index 38) = Bank Transfer / Net Pay.
    $rawBankTransfer    = $row[38] ?? null;
    $banktransfer       = is_numeric($rawBankTransfer)
        ? (float)$rawBankTransfer
        : null;

    // === VALIDATION ===
    if (empty($employ)) {
        $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
        continue;
    }

    if (empty($empoyeename)) {
        $errors[] = "Row " . ($index + 2) . ": Name is required.";
        continue;
    }

    if (is_null($basic) || is_null($gross)) {
        $errors[] = "Row " . ($index + 2) . ": Basic and Gross Pay are required.";
        continue;
    }

    if (is_null($banktransfer)) {
        $errors[] = "Row " . ($index + 2) . ": Bank Transfer (Net Pay) must be a valid number. Found: '$rawBankTransfer'";
        continue;
    }

    // Duplicate check
    if (in_array($employ, $existingStaffIds)) {
        $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $employ";
        continue;
    }

    // === FINAL DATA ARRAY - 100% MATCH ===
    $data = [
        'employ'            => $employ,
        'empoyeename'       => $empoyeename,
        'outsourcename'     => $outsourcename,
        'designation'       => $designation,
        'joindate'          => $joindate,
        'shopname'          => $shopname,
        'category'          => $category,
        'saturdayot'        => $saturdayot,
        'actualdays'        => $actualdays,
        'night'             => $night,
        'halfdays'          => $halfdays,
        'satoffdays'       => $satoffdays,
        'sunoffdays'       => $sunoffdays,
        'leavededuct'       => $leavededuct,
        'absentdays'        => $absentdays,
        'absenthrs'          => $absenthrs,
        'holiday'           => $holiday,
        'weekdaysOT'        => $weekdaysOT,
        'weekendOT'         => $weekendOT,
        'holidayot'         => $holidayot,
        'basic'             => $basic,
        'absentwithoutpay'  => $absentwithoutpay,
        'basicentitlement'  => $basicentitlement,
        'transport'         => $transport,
        'normalovertime'    => $normalovertime,
        'saturdayovertime'  => $saturdayovertime,
        'holidayotamt'      => $holidayotamt,
        'gross'             => $gross,
        'ssniter'             => $ssniter,
        'ssnitee'             => $ssnitee,
        'ssnit'             => $ssnit,
        'taxable'          => $taxable,
        'paye'              => $paye,
        'totalovertime'     => $totalovertime,
        'overtimetax'       => $overtimetax,
        'totaltax'          => $totaltax,
        'trust'             => $trust,
        'totaldeduction'    => $totaldeduction,
        'banktransfer'      => $banktransfer,
    ];

    $validRecords[] = $data;
    $existingStaffIds[] = $employ;
}

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('melcompay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('melcompay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
    $chunkSize = 500; // Safe for the current payroll row size
    $chunks = array_chunk($validRecords, $chunkSize);

    DB::transaction(function () use ($tableName, $chunks) {
        foreach ($chunks as $i => $chunk) {
            DB::table($tableName)->insert($chunk);
            Log::info("Inserted chunk " . ($i + 1) . "/" . count($chunks));
        }
    });

    Log::info("Upload: Successfully inserted " . count($validRecords) . " records into {$tableName} using " . count($chunks) . " chunks");
}else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('melcompay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('melcompay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'melcom' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('melcompay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('melcompay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('melcompay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 public function downloadTemplate()
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // HEADERS 100% MATCHING YOUR CURRENT $data ARRAY (the simpler one)
    $headers = [
        'Staff ID',           // employ
        'Name',               // empoyeename
        'Outsourcename',      // outsourcename
        'Designation',        // designation (Position)
        'Join Date',          // joindate
        'Shop Name',          // shopname (Workplace)
        'Category',           // category
        'Saturday OT',        // saturdayot
        'Actual Days',        // actualdays
        'Night',              // night
        'Half Days',          // halfdays
        'Sat Off Days',       // satoffdays
        'Sun Off Days',       // sunoffdays
        'Leave Deduct',       // leavededuct
        'Absent Days',        // absentdays
        'Absent Hrs',         // absenthrs
        'Holiday',            // holiday
        'Weekdays OT',        // weekdaysOT
        'Weekend OT',         // weekendOT
        'Holiday OT',         // holidayot
        'Basic',              // basic
        'Absent Without Pay', // absentwithoutpay
        'Basic Entitlement',  // basicentitlement
        'Transport',          // transport
        'Normal Overtime',    // normalovertime
        'Saturday Overtime',  // saturdayovertime
        'Holiday OT Amt',     // holidayotamt
        'Gross',              // gross
        'SSNIT ER',              // ssnit (employee contribution)
         'SSNIT EE',              // ssnit (employee contribution)
          'SSNIT',              // ssnit (employee contribution)
        'Taxable',            // taxable
        'PAYE',               // paye
        'Total Overtime',     // totalovertime
        'Overtime Tax',       // overtimetax
        'Total Tax',          // totaltax
        'Trust',              // trust (welfare)
        'Total Deduction',    // totaldeduction
        'Bank Transfer'       // banktransfer → Net Pay
    ];

    $sheet->fromArray($headers, null, 'A1');

    // Make headers bold
    $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')
          ->getFont()->setBold(true);

    // Example row - realistic Ghana shop staff (Nov 2025)
    $exampleData = [[
        'STF00421',     // Staff ID
        'Akosua Mensah', // Name
        'Carlcare',     // Outsourcename
        'Shop Assistant', // Designation
        '15/03/2023',   // Join Date
        'Kumasi - Adum', // Shop Name
        'Outsourced',   // Category
        1,              // Saturday OT (yes/no or hours)
        26,             // Actual Days
        8,              // Night shifts
        0,              // Half Days
        0,              // Sat Off Days
        1,              // Sun Off Days
        0,              // Leave Deduct
        0,              // Absent Days
        0,              // Absent Hrs
        0,              // Holiday
        6,              // Weekdays OT (hours)
        4,              // Weekend OT (hours)
        0,              // Holiday OT
        1800.00,        // Basic
        0.00,           // Absent Without Pay
        1800.00,        // Basic Entitlement
        300.00,         // Transport
        450.00,         // Normal Overtime
        400.00,         // Saturday Overtime
        0.00,           // Holiday OT Amt
        3950.00,        // Gross
        247.50,         // SSNIT (13.5%)
        247.50,         // SSNIT (13.5%)
        247.50,         // SSNIT (13.5%)
        3702.50,        // Taxable
        428.75,         // PAYE
        850.00,         // Total Overtime
        123.25,         // Overtime Tax
        552.00,         // Total Tax
        50.00,          // Trust/Welfare
        849.75,         // Total Deduction
        3100.25         // Bank Transfer (Net Pay)
    ]];

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