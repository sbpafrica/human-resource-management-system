<?php

namespace App\Http\Controllers;

use App\Models\Ofibwhpay;
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

class OfibwhPayController extends Controller
{
 public function index(Request $request)
 {
 $request->validate([
 'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
 ]);

 $month = $request->input('month', now()->format('Y-m'));
 Log::info("Index: Received month: {$month}");

 $date = Carbon::createFromFormat('Y-m', $month);
 $tableName = 'olambwh' . strtolower($date->format('F')) . $date->format('y');
 Log::info("Index: Generated table name: {$tableName}");

 if (!DB::getSchemaBuilder()->hasTable($tableName)) {
 Log::warning("Index: Table {$tableName} does not exist");
 return Inertia::render('Ofibwhpay/Index', [
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

 $query = DB::table($tableName . ' as tp')
 ->leftJoin('bankdetails as bd', 'tp.id', '=', 'bd.employid')
 ->select(
 'tp.id',
 'tp.name',
 'bd.shop',
 'bd.bank',
 'bd.account',
 'bd.code',
 'bd.branch',
 'tp.netpay'
 );

 // Apply search
 if ($search = $request->input('search')) {
 $query->where(function ($q) use ($search, $tableName) {
 $q->where("tp.id", 'like', "%{$search}%")
 ->orWhere("tp.name", 'like', "%{$search}%")
 ->orWhere('bd.bank', 'like', "%{$search}%");
 });
 }

 // Apply sorting
 $sort = $request->input('sort', 'id');
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

 return Inertia::render('Ofibwhpay/Index', [
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
        $request->validate([
            'month' => ['required', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month');
        $date  = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'olambwh' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.id', '=', 'bd.employid')
            ->select(
                'tp.id as staff_id',
                'tp.name',
                'bd.shop',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay as net_pay'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.id', 'like', "%{$search}%")
                  ->orWhere('tp.name', 'like', "%{$search}%")
                  ->orWhere('bd.shop', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'id'        => 'tp.id',
            'staff_id'  => 'tp.id',
            'name'      => 'tp.name',
            'shop'      => 'bd.shop',
            'bank'      => 'bd.bank',
            'account'   => 'bd.account',
            'code'      => 'bd.code',
            'branch'    => 'bd.branch',
            'netpay'    => 'tp.netpay',
            'net_pay'   => 'tp.netpay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.id', $order);

        $records = $query->get();

        // Generate Real Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Name', 'Shop/Department', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Net Pay'];
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
            $sheet->setCellValue("H{$row}", $r->net_pay ? number_format((float)$r->net_pay, 2, '.', '') : '');
            $row++;
        }

        // Styling – same as all your other exports
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
        $filename = "olam_bwh_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("OfibwhPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
 $tableName = 'olambwh' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 OfibwhPay::createTableIfNotExists($tableName);

 $file = $request->file('file');
 $reader = new XlsxReader();
 $spreadsheet = $reader->load($file->getRealPath());
 $sheet = $spreadsheet->getActiveSheet();
 $rows = $sheet->toArray();
 array_shift($rows); // Remove header row

 // Check for existing staff IDs
 $existingStaffIds = DB::table($tableName)->pluck('id')->toArray();
 $duplicateStaffIds = [];
 $validRecords = [];
 $errors = [];

 foreach ($rows as $index => $row) {
 if (count($row) < 38) {
 $errors[] = "Row " . ($index + 2) . ": Expected 37 columns, found " . count($row);
 continue;
 }

 // === 38-COLUMN MAPPING (0 → 37) ===
$id                  = trim($row[0] ?? '') ?: null;      // Staff ID or Employee ID
$name                = trim($row[1] ?? '') ?: null;
$basic               = is_numeric($row[2] ?? '') ? (float)$row[2] : null;
$workdays            = is_numeric($row[3] ?? '') ? (float)$row[3] : null;

$f_and_t_rate        = is_numeric($row[4] ?? '') ? (float)$row[4] : null;
$weekdayhr           = is_numeric($row[5] ?? '') ? (float)$row[5] : null;
$weekdaytonage       = is_numeric($row[6] ?? '') ? (float)$row[6] : null;
$weekdayrate         = is_numeric($row[7] ?? '') ? (float)$row[7] : null;
$amt                 = is_numeric($row[8] ?? '') ? (float)$row[8] : null;

$weekdayothr         = is_numeric($row[9] ?? '') ? (float)$row[9] : null;
$weekdayottonage     = is_numeric($row[10] ?? '') ? (float)$row[10] : null;
$weekdayotrate       = is_numeric($row[11] ?? '') ? (float)$row[11] : null;
$weekdayotamt1       = is_numeric($row[12] ?? '') ? (float)$row[12] : null;

$weekendhr           = is_numeric($row[13] ?? '') ? (float)$row[13] : null;
$weekendtonage       = is_numeric($row[14] ?? '') ? (float)$row[14] : null;
$weekendrate         = is_numeric($row[15] ?? '') ? (float)$row[15] : null;
$amount               = is_numeric($row[16] ?? '') ? (float)$row[16] : null;

$leavedays           = is_numeric($row[17] ?? '') ? (float)$row[17] : null;
$totalamount         = is_numeric($row[18] ?? '') ? (float)$row[18] : null;
$phonecredit         = is_numeric($row[19] ?? '') ? (float)$row[19] : null;

$ssnit13             = is_numeric($row[20] ?? '') ? (float)$row[20] : null;
$f_and_t_amount      = is_numeric($row[21] ?? '') ? (float)$row[21] : null;
$servicecharge       = is_numeric($row[22] ?? '') ? (float)$row[22] : null;
$getfund             = is_numeric($row[23] ?? '') ? (float)$row[23] : null;
$nhil                = is_numeric($row[24] ?? '') ? (float)$row[24] : null;
$vat                 = is_numeric($row[25] ?? '') ? (float)$row[25] : null;
$costtoolam          = is_numeric($row[26] ?? '') ? (float)$row[26] : null;

$ssnitee             = is_numeric($row[27] ?? '') ? (float)$row[27] : null;
$petra               = is_numeric($row[28] ?? '') ? (float)$row[28] : null;
$taxincome           = is_numeric($row[29] ?? '') ? (float)$row[29] : null;
$totaltax            = is_numeric($row[30] ?? '') ? (float)$row[30] : null;
$totaldeduction      = is_numeric($row[31] ?? '') ? (float)$row[31] : null;

// Net Pay – cleaned
$rawNetpay           = trim($row[32] ?? '');
$cleanNetpay         = preg_replace('/[^0-9.-]/', '', $rawNetpay);
$netpay              = is_numeric($cleanNetpay) ? (float)$cleanNetpay : null;

$bank1               = trim($row[33] ?? '') ?: null;
$account2            = trim($row[34] ?? '') ?: null;
$branch              = trim($row[35] ?? '') ?: null;
$ssnit2              = trim($row[36] ?? '') ?: null;
$ghcard1             = trim($row[37] ?? '') ?: null;



 // Validation
 if (empty($id)) {
 $errors[] = "Row " . ($index + 2) . ": Staff ID cannot be empty.";
 continue;
 }
 if (empty($name)) {
 $errors[] = "Row " . ($index + 2) . ": Name cannot be empty.";
 continue;
 }
 if (is_null($netpay)) {
 $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number (found: '$rawNetPay').";
 continue;
 }

 if (in_array($id, $existingStaffIds) || in_array($id, $duplicateStaffIds)) {
 $duplicateStaffIds[] = $id;
 continue;
 }

 $data = [
    'id'               => $id,
    'name'             => $name,
    'basic'            => $basic,
    'workdays'         => $workdays,
    'f&trate'          => $f_and_t_rate,
    'weekdayhr'        => $weekdayhr,
    'weekdaytonage'    => $weekdaytonage,
    'weekdayrate'      => $weekdayrate,
    'amt'              => $amt,
    'weekdayothr'      => $weekdayothr,
    'weekdayottonage'  => $weekdayottonage,
    'weekdayotrate'    => $weekdayotrate,
    'weekdayotamt1'    => $weekdayotamt1,
    'weekendhr'        => $weekendhr,
    'weekendtonage'    => $weekendtonage,
    'weekendrate'      => $weekendrate,
    'amount'           => $amount,
    'leavedays'        => $leavedays,
    'totalamount'      => $totalamount,
    'phonecredit'      => $phonecredit,
    'ssnit13'          => $ssnit13,
    'f&tamount'        => $f_and_t_amount,
    'servicecharge'    => $servicecharge,
    'getfund'          => $getfund,
    'nhil'             => $nhil,
    'vat'              => $vat,
    'costtoolam'       => $costtoolam,
    'ssnitee'          => $ssnitee,
     'petra'          => $petra,
    'taxincome'        => $taxincome,
    'totaltax'         => $totaltax,
    'totaldeduction'   => $totaldeduction,
    'netpay'           => $netpay,
    'bank1'            => $bank1,
    'account2'         => $account2,
    'branch'           => $branch,
    'ssnit2'           => $ssnit2,
    'ghcard1'          => $ghcard1,
];

 $validRecords[] = $data;
 $existingStaffIds[] = $id;
 }

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('ofibwhpay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('ofibwhpay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('ofibwhpay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('ofibwhpay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'olambwh' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('ofibwhpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('ofibwhpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('ofibwhpay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 public function downloadTemplate()
 {
 $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

 // Set headers
 $headers = [
    'Staff ID','Name','Basic Salary','Work Days',
    'F&T Rate','Weekday Hours','Weekday Tonnage','Weekday Rate','Amount',
    'Weekday OT Hours','Weekday OT Tonnage','Weekday OT Rate','Weekday OT Amount',
    'Weekend Hours','Weekend Tonnage','Weekend Rate','Weekend Amount',
    'Leave Days','Total Amount','Phone Credit',
    'SSNIT 13%','F&T Amount','Service Charge','GETFund','NHIL','VAT','Cost to Olam',
    'SSNIT EE','PETRA','Taxable Income','Total Tax','Total Deduction',
    'Net Pay',
    'Bank','Account Number','Branch','SSNIT No','Ghana Card'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:AL1')->getFont()->setBold(true);





 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'ofibwh_template.xlsx';
 $tempFile = tempnam(sys_get_temp_dir(), $fileName);
 $writer->save($tempFile);

 return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
 }
}
