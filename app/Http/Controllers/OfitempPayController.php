<?php

namespace App\Http\Controllers;

use App\Models\Ofitemppay;
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

class OfitempPayController extends Controller
{
 public function index(Request $request)
 {
 $request->validate([
 'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
 ]);

 $month = $request->input('month', now()->format('Y-m'));
 Log::info("Index: Received month: {$month}");

 $date = Carbon::createFromFormat('Y-m', $month);
 $tableName = 'olamtem' . strtolower($date->format('F')) . $date->format('y');
 Log::info("Index: Generated table name: {$tableName}");

 if (!DB::getSchemaBuilder()->hasTable($tableName)) {
 Log::warning("Index: Table {$tableName} does not exist");
 return Inertia::render('Ofitemppay/Index', [
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
 'tp.names',
 'bd.shop',
 'bd.bank',
 'bd.account',
 'bd.code',
 'bd.branch',
 'tp.takehome'
 );

 // Apply search
 if ($search = $request->input('search')) {
 $query->where(function ($q) use ($search, $tableName) {
 $q->where("tp.id", 'like', "%{$search}%")
 ->orWhere("tp.names", 'like', "%{$search}%")
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

 return Inertia::render('Ofitemppay/Index', [
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
        $tableName = 'olamtem' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.id', '=', 'bd.employid')
            ->select(
                'tp.id as staff_id',
                'tp.names as name',
                'bd.shop',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.takehome as net_pay'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.id', 'like', "%{$search}%")
                  ->orWhere('tp.names', 'like', "%{$search}%")
                  ->orWhere('bd.shop', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting – fully compatible with frontend
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'id'        => 'tp.id',
            'staff_id'  => 'tp.id',
            'names'     => 'tp.names',
            'name'      => 'tp.names',
            'shop'      => 'bd.shop',
            'bank'      => 'bd.bank',
            'account'   => 'bd.account',
            'code'      => 'bd.code',
            'branch'    => 'bd.branch',
            'takehome'  => 'tp.takehome',
            'net_pay'   => 'tp.takehome',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.id', $order);

        $records = $query->get();

        // Generate Real Excel File
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Name', 'Department/Shop', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Take Home Pay'];
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

        // Professional Styling – same as ALL your other exports
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
        $filename = "olam_temp_staff_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("OfitempPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
 $tableName = 'olamtem' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 OfitempPay::createTableIfNotExists($tableName);

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
 if (count($row) < 36) {
 $errors[] = "Row " . ($index + 2) . ": Expected 34 columns, found " . count($row);
 continue;
 }

 // === 36-COLUMN MAPPING (0 → 35) ===
$id                  = trim($row[0] ?? '') ?: null;      // Staff ID
$names               = trim($row[1] ?? '') ?: null;      // Full Name
$dept                = trim($row[2] ?? '') ?: null;      // Department

$noofdays            = is_numeric($row[3] ?? '') ? (float)$row[3] : null;
$extraldays          = is_numeric($row[4] ?? '') ? (float)$row[4] : null;
$total1              = is_numeric($row[5] ?? '') ? (float)$row[5] : null;
$rate                = is_numeric($row[6] ?? '') ? (float)$row[6] : null;
$total2              = is_numeric($row[7] ?? '') ? (float)$row[7] : null;

$overtimehr          = is_numeric($row[8] ?? '') ? (float)$row[8] : null;
$overtimetotal       = is_numeric($row[9] ?? '') ? (float)$row[9] : null;
$overtimeweekend     = is_numeric($row[10] ?? '') ? (float)$row[10] : null;
$overtimetotal2      = is_numeric($row[11] ?? '') ? (float)$row[11] : null;

$totalamount         = is_numeric($row[12] ?? '') ? (float)$row[12] : null;

$transport           = is_numeric($row[13] ?? '') ? (float)$row[13] : null;
$servicecharge       = is_numeric($row[14] ?? '') ? (float)$row[14] : null;
$getfund             = is_numeric($row[15] ?? '') ? (float)$row[15] : null;
$nhil                = is_numeric($row[16] ?? '') ? (float)$row[16] : null;
$vat                 = is_numeric($row[17] ?? '') ? (float)$row[17] : null;
$ssf                 = is_numeric($row[18] ?? '') ? (float)$row[18] : null;

$leave1              = is_numeric($row[19] ?? '') ? (float)$row[19] : null;
$night               = is_numeric($row[20] ?? '') ? (float)$row[20] : null;
$nightallowance      = is_numeric($row[21] ?? '') ? (float)$row[21] : null;
$crane               = is_numeric($row[22] ?? '') ? (float)$row[22] : null;
$medical             = is_numeric($row[23] ?? '') ? (float)$row[23] : null;
$costofolam          = is_numeric($row[24] ?? '') ? (float)$row[24] : null;

$ssnitee             = is_numeric($row[25] ?? '') ? (float)$row[25] : null;
$petra             = is_numeric($row[26] ?? '') ? (float)$row[26] : null;
$taxableincome       = is_numeric($row[27] ?? '') ? (float)$row[27] : null;
$totaltaxdeduction   = is_numeric($row[28] ?? '') ? (float)$row[28] : null;
$totaldeduction      = is_numeric($row[29] ?? '') ? (float)$row[29] : null;

// Take Home Pay – cleaned
$rawTakehome         = trim($row[30] ?? '');
$cleanTakehome       = preg_replace('/[^0-9.-]/', '', $rawTakehome);
$takehome            = is_numeric($cleanTakehome) ? (float)$cleanTakehome : null;

$bank1               = trim($row[31] ?? '') ?: null;
$account1            = trim($row[32] ?? '') ?: null;
$branch1             = trim($row[33] ?? '') ?: null;
$ssnit12             = trim($row[34] ?? '') ?: null;
$ghcard1             = trim($row[35] ?? '') ?: null;



 // Validation
 if (empty($id)) {
 $errors[] = "Row " . ($index + 2) . ": Staff ID cannot be empty.";
 continue;
 }
 if (empty($names)) {
 $errors[] = "Row " . ($index + 2) . ": Name cannot be empty.";
 continue;
 }
 if (is_null($takehome )) {
 $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number (found: '$rawNetPay').";
 continue;
 }

 if (in_array($id, $existingStaffIds) || in_array($id, $duplicateStaffIds)) {
 $duplicateStaffIds[] = $id;
 continue;
 }

 $data = [
    'id'                  => $id,
    'names'               => $names,
    'dept'                => $dept,
    'noofdays'            => $noofdays,
    'extraldays'          => $extraldays,
    'total1'              => $total1,
    'rate'                => $rate,
    'total2'              => $total2,
    'overtimehr'          => $overtimehr,
    'overtimetotal'       => $overtimetotal,
    'overtimeweekend'     => $overtimeweekend,
    'overtimetotal2'      => $overtimetotal2,
    'totalamount'         => $totalamount,
    'transport'           => $transport,
    'servicecharge'       => $servicecharge,
    'getfund'             => $getfund,
    'nhil'                => $nhil,
    
    'vat'                 => $vat,
    'ssf'                 => $ssf,
    'leave1'              => $leave1,
    'night'               => $night,
    'nightallowance'      => $nightallowance,
    'crane'               => $crane,
    'medical'             => $medical,
    'costofolam'          => $costofolam,
    'ssnitee'             => $ssnitee,
    'petra'             => $petra,
    'taxableincome'       => $taxableincome,
    'totaltaxdeduction'   => $totaltaxdeduction,
    'totaldeduction'      => $totaldeduction,
    'takehome'            => $takehome,
    'bank1'               => $bank1,
    'account1'            => $account1,
    'branch1'             => $branch1,
    'ssnit12'             => $ssnit12,
    'ghcard1'             => $ghcard1,
];

 $validRecords[] = $data;
 $existingStaffIds[] = $id;
 }

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('ofitemppay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('ofitemppay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('ofitemppay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('ofitemppay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'olamtem' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('ofitemppay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('ofitemppay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('ofitemppay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 public function downloadTemplate()
 {
 $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

 // Set headers
$headers = [
    'Staff ID','Name','Department',
    'No. of Days','Extra Days','Total Days','Rate','Total Basic',
    'OT Hours','OT Total','Weekend OT Hours','Weekend OT Total','Total Earnings',
    'Transport','Service Charge','GETFund','NHIL','VAT','SSF',
    'Leave Allowance','Night Days','Night Allowance','Crane Allowance','Medical',
    'Cost to Olam','SSNIT EE','PETRA','Taxable Income','Total Tax Deduction','Total Deduction',
    'Take Home Pay',
    'Bank','Account No','Branch','SSNIT No','Ghana Card'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:AJ1')->getFont()->setBold(true);  // 36 columns = A to AJ




 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'ofitemp_template.xlsx';
 $tempFile = tempnam(sys_get_temp_dir(), $fileName);
 $writer->save($tempFile);

 return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
 }
}
