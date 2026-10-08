<?php

namespace App\Http\Controllers;

use App\Models\Hondapay;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;           // ← CRITICAL
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;     // ← Also needed

class HondaPayController extends Controller
{
 public function index(Request $request)
 {
         $request->validate([
         'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
         ]);

         $month = $request->input('month', now()->format('Y-m'));
         Log::info("Index: Received month: {$month}");

         $date = Carbon::createFromFormat('Y-m', $month);
         $tableName = 'honda' . strtolower($date->format('F')) . $date->format('y');
         Log::info("Index: Generated table name: {$tableName}");

         if (!DB::getSchemaBuilder()->hasTable($tableName)) {
         Log::warning("Index: Table {$tableName} does not exist");
         return Inertia::render('Hondapay/Index', [
             'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
             'selectedMonth' => $month,
             'formattedMonth' => $date->format('F Y'),
             'totalRecords' => 0,
             'searchCount' => null,
             'flash' => [
             'error' => "No data available for {$date->format('F Y')}. Please upload data.",
             ],
         ]);
         }

         $query = DB::table($tableName . ' as tp')->leftJoin('bankdetails as bd', 'tp.STAFFID', '=', 'bd.employid')
             ->select(
             'tp.STAFFID',
             'tp.NAME',
             'bd.shop',
             'bd.bank',
             'bd.account',
             'bd.code',
             'bd.branch',
             'tp.TAKEHOMEPAY_GHC'
             );

 // Apply search
 if ($search = $request->input('search')) {
 $query->where(function ($q) use ($search, $tableName) {
 $q->where("tp.STAFFID", 'like', "%{$search}%")
 ->orWhere("tp.NAME", 'like', "%{$search}%")
 ->orWhere('bd.bank', 'like', "%{$search}%");
 });
 }

 // Apply sorting
 $sort = $request->input('sort', 'STAFFID');
 $order = $request->input('order', 'asc');
 Log::info("Index: Sorting by {$sort} {$order}");
 $sortColumn = in_array($sort, ['shop', 'department']) ? 'bd.shop' :
 (in_array($sort, ['bank', 'bankname']) ? 'bd.bank' :
 (in_array($sort, ['account', 'alcnum']) ? 'bd.account' :
 (in_array($sort, ['code']) ? 'bd.code' :
 (in_array($sort, ['branch']) ? 'bd.branch' : "tp.{$sort}"))));
 $query->orderBy($sortColumn, $order);

 // Paginate results
 $perPage = 10;
 $records = $query->paginate($perPage)->appends([
 'month' => $month,
 'search' => $search,
 'sort' => $sort,
 'order' => $order,
 ]);

 // Get total records and search count
 $totalRecords = DB::table($tableName)->count();
 $searchCount = $search ? $query->count() : null;

 Log::info("Index: Retrieved records", ['count' => $records->total(), 'sample' => $records->items()[0] ?? null]);

 return Inertia::render('Hondapay/Index', [
 'records' => $records,
 'selectedMonth' => $month,
 'formattedMonth' => $date->format('F Y'),
 'totalRecords' => $totalRecords,
 'searchCount' => $searchCount,
 'flash' => [
 'success' => session('success'),
 'error' => session('error'),
 ],
 ]);
 }



public function export(Request $request)
{
    try {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $month     = $request->input('month');
        $date      = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'honda' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.STAFFID', '=', 'bd.employid')
            ->select('tp.STAFFID', 'tp.NAME', 'bd.shop', 'bd.bank', 'bd.account', 'bd.code', 'bd.branch', 'tp.TAKEHOMEPAY_GHC');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.STAFFID', 'like', "%{$search}%")
                  ->orWhere('tp.NAME', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort  = $request->input('sort', 'STAFFID');
        $order = $request->input('order', 'asc');

        $sortColumn = match ($sort) {
            'shop'    => 'bd.shop',
            'bank'    => 'bd.bank',
            'account' => 'bd.account',
            'code'    => 'bd.code',
            'branch'  => 'bd.branch',
            default   => "tp.{$sort}",
        };

        $records = $query->orderBy($sortColumn, $order)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = ['Staff ID', 'Name', 'Shop', 'Bank', 'Account No.', 'Code', 'Branch', 'Take Home Pay (GHC)'];
        $sheet->fromArray($headers, null, 'A1');

        // Write data row by row — this is the magic
        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->STAFFID);
            $sheet->setCellValue("B{$row}", $r->NAME);
            $sheet->setCellValue("C{$row}", $r->shop ?? '');
            $sheet->setCellValue("D{$row}", $r->bank ?? '');

            // FORCE Account No. and Code as TEXT — no apostrophe, no scientific
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '',    DataType::TYPE_STRING);

            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->TAKEHOMEPAY_GHC ? (float)$r->TAKEHOMEPAY_GHC : '');

            $row++;
        }

        // Styling — exactly like DriversPetraPay
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('bbdcfe');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "honda_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

    } catch (\Exception $e) {
        Log::error("Honda export failed: " . $e->getMessage());
        return response()->json(['error' => 'Export failed'], 500);
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
 $tableName = 'honda' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 HondaPay::createTableIfNotExists($tableName);

 $file = $request->file('file');
 $reader = new XlsxReader();
 $spreadsheet = $reader->load($file->getRealPath());
 $sheet = $spreadsheet->getActiveSheet();
 $rows = $sheet->toArray();
 array_shift($rows); // Remove header row

 // Check for existing staff IDs
 $existingStaffIds = DB::table($tableName)->pluck('STAFFID')->toArray();
 $duplicateStaffIds = [];
 $validRecords = [];
 $errors = [];

 foreach ($rows as $index => $row) {
 if (count($row) < 35) {
 $errors[] = "Row " . ($index + 2) . ": Expected 35 columns, found " . count($row);
 continue;
 }

// === 35-COLUMN MAPPING FOR HONDA (0 → 34) ===
$STAFFID                   = trim($row[0] ?? '') ?: null;
$NAME                      = trim($row[1] ?? '') ?: null;
$DATEOFEMPLOYMENT          = trim($row[2] ?? '') ?: null;
$POSITION                  = trim($row[3] ?? '') ?: null;

$BASICSALARY_GHC           = is_numeric($row[4] ?? '') ? (float)$row[4] : null;
$LEGALWORKDAYS             = is_numeric($row[5] ?? '') ? (int)$row[5] : null;
$NUMBEROFDAYSWORKED        = is_numeric($row[6] ?? '') ? (float)$row[6] : null;
$LEAVEDAYS                 = is_numeric($row[7] ?? '') ? (float)$row[7] : null;
$LEAVEAMOUNT               = is_numeric($row[8] ?? '') ? (float)$row[8] : null;
$BASICSALARYEARNED_GHC     = is_numeric($row[9] ?? '') ? (float)$row[9] : null;

$PERFORMANCEBONUSSTANDARD  = is_numeric($row[10] ?? '') ? (float)$row[10] : null;
$COEFFICIENT               = is_numeric($row[11] ?? '') ? (float)$row[11] : null;
$PERFORMANCEBONUS          = is_numeric($row[12] ?? '') ? (float)$row[12] : null;
$TRANSPORTALLOWANCE        = is_numeric($row[13] ?? '') ? (float)$row[13] : null;
$foodallowance             = is_numeric($row[14] ?? '') ? (float)$row[14] : null;
$holiovertime             = is_numeric($row[15] ?? '') ? (float)$row[15] : null;
$holiovertimeamt            = is_numeric($row[16] ?? '') ? (float)$row[16] : null;
$medicalinsurance          = is_numeric($row[17] ?? '') ? (float)$row[17] : null;

$GROSSSALARY_GHC           = is_numeric($row[18] ?? '') ? (float)$row[18] : null;

$MANAGEMENTFEE_8           = is_numeric($row[19] ?? '') ? (float)$row[19] : null;
$GETFUND_2_5               = is_numeric($row[20] ?? '') ? (float)$row[20] : null;
$NHIL_2_5                  = is_numeric($row[21] ?? '') ? (float)$row[21] : null;

$VAT_15                    = is_numeric($row[22] ?? '') ? (float)$row[22] : null;
$SSF_13                    = is_numeric($row[23] ?? '') ? (float)$row[23] : null;
$COSTTOHONDA_GHC           = is_numeric($row[24] ?? '') ? (float)$row[24] : null;

$SSNITEE_0_5               = is_numeric($row[25] ?? '') ? (float)$row[25] : null;
$PETRA_5                   = is_numeric($row[26] ?? '') ? (float)$row[26] : null;
$TAXABLEINCOME_GHC         = is_numeric($row[27] ?? '') ? (float)$row[27] : null;
$PAYE_GHC                  = is_numeric($row[28] ?? '') ? (float)$row[28] : null;
$TOTALDEDUCTIONS_GHC       = is_numeric($row[29] ?? '') ? (float)$row[29] : null;

// Take Home Pay – cleaned (last numeric column before bank details)
$rawTakehome               = trim($row[30] ?? '');
$cleanTakehome             = preg_replace('/[^0-9.-]/', '', $rawTakehome);
$TAKEHOMEPAY_GHC           = is_numeric($cleanTakehome) ? (float)$cleanTakehome : null;

$BANK                      = trim($row[31] ?? '') ?: null;
$ACCOUNTNUMBER             = trim($row[32] ?? '') ?: null;
$BRANCH                    = trim($row[33] ?? '') ?: null;
$SSNITNUMBER               = trim($row[34] ?? '') ?: null;
$GHCARDNUMBER              = trim($row[35] ?? '') ?: null;


 // Validation
 if (empty($STAFFID)) {
 $errors[] = "Row " . ($index + 2) . ": Staff ID cannot be empty.";
 continue;
 }
 if (empty($NAME)) {
 $errors[] = "Row " . ($index + 2) . ": Name cannot be empty.";
 continue;
 }
 if (is_null($TAKEHOMEPAY_GHC )) {
 $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number (found: '$rawNetPay').";
 continue;
 }

 if (in_array($STAFFID, $existingStaffIds) || in_array($STAFFID, $duplicateStaffIds)) {
 $duplicateStaffIds[] = $STAFFID;
 continue;
 }

 $data = [
    'STAFFID'                  => $STAFFID,
    'NAME'                     => $NAME,
    'DATEOFEMPLOYMENT'         => $DATEOFEMPLOYMENT,
    'POSITION'                 => $POSITION,
    'BASICSALARY_GHC'          => $BASICSALARY_GHC,
    'LEGALWORKDAYS'            => $LEGALWORKDAYS,
    'NUMBEROFDAYSWORKED'       => $NUMBEROFDAYSWORKED,
    'LEAVEDAYS'                => $LEAVEDAYS,
    'LEAVEAMOUNT'              => $LEAVEAMOUNT,
    'BASICSALARYEARNED_GHC'    => $BASICSALARYEARNED_GHC,
    'PERFORMANCEBONUSSTANDARD' => $PERFORMANCEBONUSSTANDARD,
    'COEFFICIENT'              => $COEFFICIENT,
    'PERFORMANCEBONUS'         => $PERFORMANCEBONUS,
    'TRANSPORTALLOWANCE'       => $TRANSPORTALLOWANCE,
    'foodallowance'            => $foodallowance,
    'holiovertime'            => $holiovertime,
    'holiovertimeamt'            => $holiovertimeamt,
    'medicalinsurance'         => $medicalinsurance,
    'GROSSSALARY_GHC'          => $GROSSSALARY_GHC,
    'MANAGEMENTFEE_8'          => $MANAGEMENTFEE_8,
    'GETFUND_2_5'              => $GETFUND_2_5,
    'NHIL_2_5'                 => $NHIL_2_5,
    'VAT_15'                   => $VAT_15,
    'SSF_13'                   => $SSF_13,
    'COSTTOHONDA_GHC'          => $COSTTOHONDA_GHC,
    'SSNITEE_0_5'              => $SSNITEE_0_5,
    'PETRA_5'                  => $PETRA_5,
    'TAXABLEINCOME_GHC'        => $TAXABLEINCOME_GHC,
    'PAYE_GHC'                 => $PAYE_GHC,
    'TOTALDEDUCTIONS_GHC'      => $TOTALDEDUCTIONS_GHC,
    'TAKEHOMEPAY_GHC'          => $TAKEHOMEPAY_GHC,
    'BANK'                     => $BANK,
    'ACCOUNTNUMBER'            => $ACCOUNTNUMBER,
    'BRANCH'                   => $BRANCH,
    'SSNITNUMBER'              => $SSNITNUMBER,
    'GHCARDNUMBER'             => $GHCARDNUMBER,
];

 $validRecords[] = $data;
 $existingStaffIds[] = $STAFFID;
 }

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('hondapay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('hondapay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('hondapay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('hondapay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'honda' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('hondapay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('hondapay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('hondapay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 public function downloadTemplate()
 {
 $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

 $headers = [
    'STAFF ID','NAME','DATE OF EMPLOYMENT','POSITION',
    'BASIC SALARY (GHC)','LEGAL WORK DAYS','DAYS WORKED','LEAVE DAYS','LEAVE AMOUNT',
    'BASIC SALARY EARNED (GHC)','PERFORMANCE BONUS STANDARD','COEFFICIENT','PERFORMANCE BONUS',
    'TRANSPORT ALLOWANCE','FOOD ALLOWANCE','HOLIDAY OVERTIME','HOLIDAY OVERTIMEAMT','MEDICAL INSURANCE',
    'GROSS SALARY (GHC)',
    'MANAGEMENT FEE 8%','GETFUND 2.5%','NHIL 2.5%','VAT 15%','SSF 13%','COST TO HONDA (GHC)',
    'SSNIT EE 0.5%','PETRA 5%','TAXABLE INCOME (GHC)','PAYE (GHC)','TOTAL DEDUCTIONS (GHC)',
    'TAKE HOME PAY (GHC)',
    'BANK','ACCOUNT NUMBER','BRANCH','SSNIT NUMBER','GHANA CARD NUMBER'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:AI1')->getFont()->setBold(true);




 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'honda_template.xlsx';
 $tempFile = tempnam(sys_get_temp_dir(), $fileName);
 $writer->save($tempFile);

 return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
 }
}
