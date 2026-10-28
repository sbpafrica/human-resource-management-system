<?php

namespace App\Http\Controllers;

use App\Models\Ofioffloadpay;
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

class OfioffloadPayController extends Controller
{
 public function index(Request $request)
 {
 $request->validate([
 'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
 ]);

 $month = $request->input('month', now()->format('Y-m'));
 Log::info("Index: Received month: {$month}");

 $date = Carbon::createFromFormat('Y-m', $month);
 $tableName = 'offload' . strtolower($date->format('F')) . $date->format('y');
 Log::info("Index: Generated table name: {$tableName}");

 if (!DB::getSchemaBuilder()->hasTable($tableName)) {
 Log::warning("Index: Table {$tableName} does not exist");
 return Inertia::render('Ofioffloadpay/Index', [
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
 ->leftJoin('bankdetails as bd', 'tp.StaffID', '=', 'bd.employid')
 ->select(
 'tp.StaffID',
 'tp.employeename',
 'bd.shop',
 'bd.bank',
 'bd.account',
 'bd.code',
 'bd.branch',
 'tp.takehomepay'
 );

 // Apply search
 if ($search = $request->input('search')) {
 $query->where(function ($q) use ($search, $tableName) {
 $q->where("tp.StaffID", 'like', "%{$search}%")
 ->orWhere("tp.employeename", 'like', "%{$search}%")
 ->orWhere('bd.bank', 'like', "%{$search}%");
 });
 }

 // Apply sorting
 $sort = $request->input('sort', 'StaffID');
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

 return Inertia::render('Ofioffloadpay/Index', [
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
        $tableName = 'offload' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.StaffID', '=', 'bd.employid')
            ->select(
                'tp.StaffID as staff_id',
                'tp.employeename as name',
                'bd.shop',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.takehomepay as net_pay'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.StaffID', 'like', "%{$search}%")
                  ->orWhere('tp.employeename', 'like', "%{$search}%")
                  ->orWhere('bd.shop', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting – works on all columns including shop from bankdetails
        $sort = $request->input('sort', 'StaffID');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'StaffID'     => 'tp.StaffID',
            'staff_id'    => 'tp.StaffID',
            'employeename'=> 'tp.employeename',
            'name'        => 'tp.employeename',
            'shop'        => 'bd.shop',
            'bank'        => 'bd.bank',
            'account'     => 'bd.account',
            'code'        => 'bd.code',
            'branch'      => 'bd.branch',
            'takehomepay' => 'tp.takehomepay',
            'net_pay'     => 'tp.takehomepay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.StaffID', $order);

        $records = $query->get();

        // Generate Real Excel File
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Employee Name', 'Work Area/Shop', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Take Home Pay'];
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

        // Professional Styling – same as all your other exports
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
        $filename = "olam_offload_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("OfioffloadPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Export failed. Please try again.'], 500);
    }
}

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
 $tableName = 'offload' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 OfioffloadPay::createTableIfNotExists($tableName);

 $file = $request->file('file');
 $reader = new XlsxReader();
 $spreadsheet = $reader->load($file->getRealPath());
 $sheet = $spreadsheet->getActiveSheet();
 $rows = $sheet->toArray();
 array_shift($rows); // Remove header row

 // Check for existing staff IDs
 $existingStaffIds = DB::table($tableName)->pluck('StaffID')->toArray();
 $duplicateStaffIds = [];
 $validRecords = [];
 $errors = [];

 foreach ($rows as $index => $row) {
 if (count($row) < 22) {
 $errors[] = "Row " . ($index + 2) . ": Expected 22 columns, found " . count($row);
 continue;
 }

// === 22-COLUMN MAPPING (0 → 21) ===
$StaffID             = trim($row[0] ?? '') ?: null;
$employeename        = trim($row[1] ?? '') ?: null;
$workarea            = trim($row[2] ?? '') ?: null;

$basicsalary         = is_numeric($row[3] ?? '') ? (float)$row[3] : null;
$arrears             = is_numeric($row[4] ?? '') ? (float)$row[4] : null;
$gross               = is_numeric($row[5] ?? '') ? (float)$row[5] : null;

$servicecharge       = is_numeric($row[6] ?? '') ? (float)$row[6] : null;
$getfund             = is_numeric($row[7] ?? '') ? (float)$row[7] : null;
$nhil                = is_numeric($row[8] ?? '') ? (float)$row[8] : null;

$vat                 = is_numeric($row[9] ?? '') ? (float)$row[9] : null;
$ssf                 = is_numeric($row[10] ?? '') ? (float)$row[10] : null;
$costtoolam          = is_numeric($row[11] ?? '') ? (float)$row[11] : null;

$ssnitee             = is_numeric($row[12] ?? '') ? (float)$row[12] : null;
$petra             = is_numeric($row[13] ?? '') ? (float)$row[13] : null;
$taxableincome       = is_numeric($row[14] ?? '') ? (float)$row[14] : null;
$totaltaxdeductions  = is_numeric($row[15] ?? '') ? (float)$row[15] : null;
$totaldeductions     = is_numeric($row[16] ?? '') ? (float)$row[16] : null;

// Take Home Pay – cleaned
$rawTakehome         = trim($row[17] ?? '');
$cleanTakehome       = preg_replace('/[^0-9.-]/', '', $rawTakehome);
$takehomepay         = is_numeric($cleanTakehome) ? (float)$cleanTakehome : null;

$bankaa              = trim($row[18] ?? '') ?: null;
$accountnumberaa     = trim($row[19] ?? '') ?: null;
$branchaa            = trim($row[20] ?? '') ?: null;
$ssnitaa             = trim($row[21] ?? '') ?: null;



 // Validation
 if (empty($StaffID)) {
 $errors[] = "Row " . ($index + 2) . ": Staff ID cannot be empty.";
 continue;
 }
 if (empty($employeename)) {
 $errors[] = "Row " . ($index + 2) . ": Name cannot be empty.";
 continue;
 }
 if (is_null($takehomepay )) {
 $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number (found: '$rawNetPay').";
 continue;
 }

 if (in_array($StaffID, $existingStaffIds) || in_array($StaffID, $duplicateStaffIds)) {
 $duplicateStaffIds[] = $StaffID;
 continue;
 }

 $data = [
    'StaffID'             => $StaffID,
    'employeename'        => $employeename,
    'workarea'            => $workarea,
    'basicsalary'         => $basicsalary,
    'arrears'             => $arrears,
    'gross'               => $gross,
    'servicecharge'       => $servicecharge,
    'getfund'             => $getfund,
    'nhil'                => $nhil,
    
    'vat'                 => $vat,
    'ssf'                 => $ssf,
    'costtoolam'          => $costtoolam,
    'ssnitee'             => $ssnitee,
    'petra'             => $petra,
    'taxableincome'       => $taxableincome,
    'totaltaxdeductions'  => $totaltaxdeductions,
    'totaldeductions'     => $totaldeductions,
    'takehomepay'         => $takehomepay,
    'bankaa'              => $bankaa,
    'accountnumberaa'     => $accountnumberaa,
    'branchaa'            => $branchaa,
    'ssnitaa'             => $ssnitaa,
];

 $validRecords[] = $data;
 $existingStaffIds[] = $StaffID;
 }

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('ofioffloadpay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('ofioffloadpay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('ofioffloadpay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('ofioffloadpay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'offload' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('ofioffloadpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('ofioffloadpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('ofioffloadpay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 public function downloadTemplate()
 {
 $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

 // Set headers
$headers = [
    'Staff ID','Employee Name','Work Area',
    'Basic Salary','Arrears','Gross Salary',
    'Service Charge','GETFund','NHIL','VAT','SSF','Cost to Olam',
    'SSNIT EE','PETRA','Taxable Income','Total Tax Deductions','Total Deductions',
    'Take Home Pay',
    'Bank','Account Number','Branch','SSNIT No'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:V1')->getFont()->setBold(true);




 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'ofioffload_template.xlsx';
 $tempFile = tempnam(sys_get_temp_dir(), $fileName);
 $writer->save($tempFile);

 return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
 }
}
