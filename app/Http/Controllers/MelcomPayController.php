<?php

namespace App\Http\Controllers;

use App\Models\Melcompay;
use App\Services\Payroll\ColumnMatcher\HybridColumnMatcher;
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

 public function upload(Request $request, HybridColumnMatcher $columnMatcher)
 {
 $request->validate([
     'file' => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:51200'],
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
     $reader->setReadDataOnly(true);
     $spreadsheet = $reader->load($file->getRealPath());
     $sheet = $spreadsheet->getActiveSheet();
     // Extract raw unformatted calculated values
     $allRows = $sheet->toArray(null, true, false, false);

     if (empty($allRows)) {
         return redirect()->route('melcompay.index', ['month' => $selectedMonth])
             ->with('error', 'Uploaded spreadsheet is empty.');
     }

     // Robust number cleaner handling commas, currency symbols, and spaces
     $parseNum = function ($val, $default = 0.0) {
         if ($val === null || $val === '') return $default;
         if (is_numeric($val)) return (float)$val;
         $clean = preg_replace('/[^\d\.\-]/', '', (string)$val);
         return (is_numeric($clean) && $clean !== '' && $clean !== '-' && $clean !== '.') ? (float)$clean : $default;
     };

     // Auto-detect the actual header row (scanning first 10 rows for core payroll headers)
     $headerRowIndex = null;
     $headers = [];

     foreach ($allRows as $rIdx => $r) {
         if ($rIdx > 10) break;
         $matchCount = 0;
         foreach ($r as $cellVal) {
             if (!is_string($cellVal)) continue;
             $norm = strtolower(trim(preg_replace('/[\s\xc2\xa0]+/u', ' ', $cellVal)));
             if (preg_match('/^(emp|staff(\s*id)?|employee|name|basic|gross|take\s*home|net(\s*pay)?|outsourcename|designation|shop|location)/i', $norm)) {
                 $matchCount++;
             }
         }
         if ($matchCount >= 2) {
             $headerRowIndex = $rIdx;
             $headers = array_map(fn($h) => trim(preg_replace('/[\s\xc2\xa0]+/u', ' ', (string)$h)), $r);
             break;
         }
     }

     if ($headerRowIndex === null) {
         $headerRowIndex = 0;
         $headers = array_map(fn($h) => trim(preg_replace('/[\s\xc2\xa0]+/u', ' ', (string)$h)), $allRows[0]);
     }

     $dataRows = array_slice($allRows, $headerRowIndex + 1);

     // Run Hybrid AI Matcher (Grok AI -> Rule-Based -> Legacy Fallback)
     $sampleData = array_slice($dataRows, 0, 5);
     $matchResult = $columnMatcher->match($headers, 'melcom', $sampleData);
     $mapping = $matchResult['mapping'] ?? [];

     Log::info("Melcom upload: AI column matcher matched with confidence {$matchResult['confidence']} via {$matchResult['tier']}", [
         'mapping' => $mapping,
         'headerRow' => $headerRowIndex + 1,
     ]);

     $offset = (count($headers) >= 42) ? 2 : 0;
     $hasNewColsInRow = (count($headers) - $offset >= 41);
     $postGross = $hasNewColsInRow ? 2 : 0;

     $colStaffId          = $mapping['staff_id'] ?? ($offset + 0);
     $colName             = $mapping['name'] ?? ($offset + 1);
     $colOutsource        = $mapping['outsourcename'] ?? ($offset + 2);
     $colPosition         = $mapping['position'] ?? ($offset + 3);
     $colJoinDate         = $mapping['join_date'] ?? ($offset + 4);
     $colShop             = $mapping['shop'] ?? ($offset + 5);
     $colCategory         = $mapping['category'] ?? ($offset + 6);
     $colSaturdayOt       = $mapping['saturday_rate'] ?? ($offset + 7);
     $colActualDays       = $mapping['days_worked'] ?? ($offset + 8);
     $colNight            = $mapping['night_shifts'] ?? ($offset + 9);
     $colHalfDays         = $mapping['half_days'] ?? ($offset + 10);
     $colSatOffDays       = $mapping['sat_off_days'] ?? ($offset + 11);
     $colSunOffDays       = $mapping['sun_off_days'] ?? ($offset + 12);
     $colLeaveDeduct      = $mapping['leave_deduct'] ?? ($offset + 13);
     $colAbsentDays       = $mapping['absentee_days'] ?? ($offset + 14);
     $colAbsentHrs        = $mapping['absent_hrs'] ?? ($offset + 15);
     $colHoliday          = $mapping['holidays'] ?? ($offset + 16);
     $colWeekdaysOt       = $mapping['weekday_hours'] ?? ($offset + 17);
     $colWeekendOt        = $mapping['saturday_hours'] ?? ($offset + 18);
     $colHolidayOt        = $mapping['num_sundays_holidays'] ?? ($offset + 19);
     $colBasic            = $mapping['basic_salary'] ?? ($offset + 20);
     $colAbsentWithoutPay = $mapping['absent_deductions'] ?? ($offset + 21);
     $colBasicEntitlement = $mapping['basic_entitlement'] ?? ($offset + 22);
     $colTransport        = $mapping['transport'] ?? ($offset + 23);
     $colNormalOt         = $mapping['weekday_overtime_amt'] ?? ($offset + 24);
     $colSaturdayOtAmt    = $mapping['saturday_overtime_amt'] ?? ($offset + 25);
     $colHolidayOtAmt     = $mapping['sunday_holiday_overtime_amt'] ?? ($offset + 26);
     $colGross            = $mapping['gross_salary'] ?? ($offset + 27);
     $colTotalCash        = $mapping['total_cash_emolument'] ?? ($hasNewColsInRow ? $offset + 28 : null);
     $colTotalAssessable  = $mapping['total_assessable_income'] ?? ($hasNewColsInRow ? $offset + 29 : null);
     $colSsnitEr          = $mapping['ssnit_er'] ?? ($offset + 28 + $postGross);
     $colSsnitEe          = $mapping['ssnit_ee'] ?? ($offset + 29 + $postGross);
     $colSsnit            = $mapping['ssnit'] ?? ($offset + 30 + $postGross);
     $colTaxable          = $mapping['taxable_income'] ?? ($offset + 31 + $postGross);
     $colPaye             = $mapping['paye'] ?? ($offset + 32 + $postGross);
     $colTotalOt          = $mapping['total_overtime_amt'] ?? ($offset + 33 + $postGross);
     $colOtTax            = $mapping['overtime_tax'] ?? ($offset + 34 + $postGross);
     $colTotalTax         = $mapping['total_tax'] ?? ($offset + 35 + $postGross);
     $colTrust            = $mapping['trust'] ?? ($offset + 36 + $postGross);
     $colTotalDeduction   = $mapping['total_deductions'] ?? ($offset + 37 + $postGross);
     $colNetPay           = $mapping['net_pay'] ?? ($offset + 38 + $postGross);

     // Check for existing staff IDs
     $existingStaffIds = DB::table($tableName)->pluck('employ')->toArray();
     $duplicateStaffIds = [];
     $validRecords = [];
     $errors = [];
     $hasNewColsInTable = DB::getSchemaBuilder()->hasColumn($tableName, 'total_cash_emolument');

     foreach ($dataRows as $index => $row) {
         $excelRowNum = $headerRowIndex + 2 + $index;

         // Skip empty rows
         if (empty(array_filter($row, fn($v) => !is_null($v) && trim((string)$v) !== ''))) {
             continue;
         }

         $employ = trim((string)($row[$colStaffId] ?? ''));
         $empName = trim((string)($row[$colName] ?? ''));

         // Skip blank/summary/footer rows
         if (empty($employ) && (empty($empName) || is_numeric($empName))) {
             continue;
         }
         if (in_array(strtolower($employ), ['total', 'totals', 'grand total', 'subtotal', 'count', 'emp', 'staff id', 'sr. no', 'sr.no'])) {
             continue;
         }
         if (in_array(strtolower($empName), ['total', 'totals', 'grand total', 'subtotal', 'count', 'employee', 'name'])) {
             continue;
         }

         // === VALIDATION ===
         if (empty($employ)) {
             $errors[] = "Row {$excelRowNum}: Staff ID is required.";
             continue;
         }

         if (empty($empName)) {
             $errors[] = "Row {$excelRowNum}: Name is required.";
             continue;
         }

         $basic = $parseNum($row[$colBasic] ?? null, null);
         $gross = $parseNum($row[$colGross] ?? null, null);
         $netPay = $parseNum($row[$colNetPay] ?? null, null);

         if (is_null($basic) || is_null($gross)) {
             $errors[] = "Row {$excelRowNum}: Basic and Gross Pay are required.";
             continue;
         }

         if (is_null($netPay)) {
             $rawVal = $row[$colNetPay] ?? '';
             $errors[] = "Row {$excelRowNum}: Bank Transfer (Net Pay) must be a valid number. Found: '{$rawVal}'";
             continue;
         }

         // Duplicate check
         if (in_array($employ, $existingStaffIds)) {
             $duplicateStaffIds[] = $employ;
             continue;
         }

         $data = [
             'employ'            => $employ,
             'empoyeename'       => $empName,
             'outsourcename'     => trim((string)($row[$colOutsource] ?? '')) ?: null,
             'designation'       => trim((string)($row[$colPosition] ?? '')) ?: null,
             'joindate'          => trim((string)($row[$colJoinDate] ?? '')) ?: null,
             'shopname'          => trim((string)($row[$colShop] ?? '')) ?: null,
             'category'          => trim((string)($row[$colCategory] ?? '')) ?: null,
             'saturdayot'        => $parseNum($row[$colSaturdayOt] ?? 0),
             'actualdays'        => $parseNum($row[$colActualDays] ?? null, null),
             'night'             => $parseNum($row[$colNight] ?? 0),
             'halfdays'          => $parseNum($row[$colHalfDays] ?? 0),
             'satoffdays'        => $parseNum($row[$colSatOffDays] ?? 0),
             'sunoffdays'        => $parseNum($row[$colSunOffDays] ?? 0),
             'leavededuct'       => $parseNum($row[$colLeaveDeduct] ?? 0),
             'absentdays'        => $parseNum($row[$colAbsentDays] ?? 0),
             'absenthrs'         => $parseNum($row[$colAbsentHrs] ?? 0),
             'holiday'           => $parseNum($row[$colHoliday] ?? 0),
             'weekdaysOT'        => $parseNum($row[$colWeekdaysOt] ?? 0),
             'weekendOT'         => $parseNum($row[$colWeekendOt] ?? 0),
             'holidayot'         => $parseNum($row[$colHolidayOt] ?? 0),
             'basic'             => $basic,
             'absentwithoutpay'  => $parseNum($row[$colAbsentWithoutPay] ?? 0),
             'basicentitlement'  => $parseNum($row[$colBasicEntitlement] ?? null, $basic),
             'transport'         => $parseNum($row[$colTransport] ?? 0),
             'normalovertime'    => $parseNum($row[$colNormalOt] ?? 0),
             'saturdayovertime'  => $parseNum($row[$colSaturdayOtAmt] ?? 0),
             'holidayotamt'      => $parseNum($row[$colHolidayOtAmt] ?? 0),
             'gross'             => $gross,
             'ssniter'           => $parseNum($row[$colSsnitEr] ?? 0),
             'ssnitee'           => $parseNum($row[$colSsnitEe] ?? 0),
             'ssnit'             => $parseNum($row[$colSsnit] ?? 0),
             'taxable'           => $parseNum($row[$colTaxable] ?? 0),
             'paye'              => $parseNum($row[$colPaye] ?? 0),
             'totalovertime'     => $parseNum($row[$colTotalOt] ?? 0),
             'overtimetax'       => $parseNum($row[$colOtTax] ?? 0),
             'totaltax'          => $parseNum($row[$colTotalTax] ?? 0),
             'trust'             => $parseNum($row[$colTrust] ?? 0),
             'totaldeduction'    => $parseNum($row[$colTotalDeduction] ?? 0),
             'banktransfer'      => $netPay,
         ];

         if ($hasNewColsInTable) {
             $totalCash = $colTotalCash !== null ? $parseNum($row[$colTotalCash] ?? null, null) : null;
             $totalAssessable = $colTotalAssessable !== null ? $parseNum($row[$colTotalAssessable] ?? null, null) : null;

             $data['total_cash_emolument'] = $totalCash ?? $gross;
             $data['total_assessable_income'] = $totalAssessable ?? $gross;
         }

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
        'Total Cash Emolument',    // total_cash_emolument
        'Total Assessable Income', // total_assessable_income
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
        3950.00,        // Total Cash Emolument
        3950.00,        // Total Assessable Income
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