<?php

namespace App\Http\Controllers;

use App\Models\Inhousepay;
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

class InhousePayController extends Controller
{
 public function index(Request $request)
 {
 $request->validate([
 'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
 ]);

 $month = $request->input('month', now()->format('Y-m'));
 Log::info("Index: Received month: {$month}");

 $date = Carbon::createFromFormat('Y-m', $month);
 $tableName = 'inhouse' . strtolower($date->format('F')) . $date->format('y') . 'pay';
 Log::info("Index: Generated table name: {$tableName}");

 if (!DB::getSchemaBuilder()->hasTable($tableName)) {
 Log::warning("Index: Table {$tableName} does not exist");
 return Inertia::render('Inhousepay/Index', [
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

 return Inertia::render('Inhousepay/Index', [
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
        $tableName = 'inhouse' . strtolower($date->format('F')) . $date->format('y') . 'pay';

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.id', '=', 'bd.employid')
            ->select(
                'tp.id as staff_id',
                'tp.name as employee_name',
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

        // Sorting – fully compatible with frontend
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'id'          => 'tp.id',
            'staff_id'    => 'tp.id',
            'name'        => 'tp.name',
            'employee_name'=> 'tp.name',
            'shop'        => 'bd.shop',
            'bank'        => 'bd.bank',
            'account'     => 'bd.account',
            'code'        => 'bd.code',
            'branch'      => 'bd.branch',
            'netpay'      => 'tp.netpay',
            'net_pay'     => 'tp.netpay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.id', $order);

        $records = $query->get();

        // Generate Real Excel File
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID',
            'Employee Name',
            'Department/Shop',
            'Bank Name',
            'Account Number',
            'Branch Code',
            'Branch',
            'Final Net Pay (GHS)'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staff_id);
            $sheet->setCellValue("B{$row}", $r->employee_name);
            $sheet->setCellValue("C{$row}", $r->shop ?? '');
            $sheet->setCellValue("D{$row}", $r->bank ?? '');
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->net_pay ? number_format((float)$r->net_pay, 2, '.', '') : '');
            $row++;
        }

        // Professional Styling – same as ALL your exports
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
        $filename = "inhouse_payroll_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("InhousePay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
 $tableName = 'inhouse' . strtolower($date->format('F')) . $date->format('y') . 'pay';

 // Create table if it doesn't exist
 InhousePay::createTableIfNotExists($tableName);

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
 if (count($row) < 37) {
 $errors[] = "Row " . ($index + 2) . ": Expected 34 columns, found " . count($row);
 continue;
 }

// === 34-COLUMN MAPPING (0 → 33) ===
$id             = trim($row[0] ?? '') ?: null;      // Staff ID
$name           = trim($row[1] ?? '') ?: null;
$position       = trim($row[2] ?? '') ?: null;
$ghcard         = trim($row[3] ?? '') ?: null;
$ssnit          = trim($row[4] ?? '') ?: null;
$datejoin       = trim($row[5] ?? '') ?: null;
$alcnum         = trim($row[6] ?? '') ?: null;      // Account Number
$bankname       = trim($row[7] ?? '') ?: null;
$branch         = trim($row[8] ?? '') ?: null;

$legalwork      = is_numeric($row[9] ?? '') ? (int)$row[9] : null;
$actualwork     = is_numeric($row[10] ?? '') ? (float)$row[10] : null;

$basic          = is_numeric($row[11] ?? '') ? (float)$row[11] : null;
$actualbasic    = is_numeric($row[12] ?? '') ? (float)$row[12] : null;
$kpi            = is_numeric($row[13] ?? '') ? (float)$row[13] : null;
$coefficient    = is_numeric($row[14] ?? '') ? (float)$row[14] : null;
$monthlykpi     = is_numeric($row[15] ?? '') ? (float)$row[15] : null;
$overtime       = is_numeric($row[16] ?? '') ? (float)$row[16] : null;
$transport      = is_numeric($row[17] ?? '') ? (float)$row[17] : null;
$leave1         = is_numeric($row[18] ?? '') ? (float)$row[18] : null;
$total          = is_numeric($row[19] ?? '') ? (float)$row[19] : null;

$ssniter        = is_numeric($row[20] ?? '') ? (float)$row[20] : null;
$ssnitpay       = is_numeric($row[21] ?? '') ? (float)$row[21] : null;
$taxincome      = is_numeric($row[22] ?? '') ? (float)$row[22] : null;
$paye           = is_numeric($row[23] ?? '') ? (float)$row[23] : null;
$overtimetax    = is_numeric($row[24] ?? '') ? (float)$row[24] : null;
$totaltax       = is_numeric($row[25] ?? '') ? (float)$row[25] : null;
$totaldeduction = is_numeric($row[26] ?? '') ? (float)$row[26] : null;
$netpay1        = is_numeric($row[27] ?? '') ? (float)$row[27] : null;
$bonus        = is_numeric($row[28] ?? '') ? (float)$row[28] : null;
$bonustax        = is_numeric($row[29] ?? '') ? (float)$row[29] : null;
$bonusfinal        = is_numeric($row[30] ?? '') ? (float)$row[30] : null;

$numdays        = is_numeric($row[31] ?? '') ? (int)$row[31] : null;
$thmonth      = is_numeric($row[32] ?? '') ? (float)$row[32] : null;
$thtax        = is_numeric($row[33] ?? '') ? (float)$row[33] : null;
$thpay        = is_numeric($row[34] ?? '') ? (float)$row[34] : null;
$loans          = is_numeric($row[35] ?? '') ? (float)$row[35] : null;

// Final Net Pay – cleaned
$rawNet         = trim($row[36] ?? '');
$cleanNet       = preg_replace('/[^0-9.-]/', '', $rawNet);
$netpay         = is_numeric($cleanNet) ? (float)$cleanNet : null;



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
    'id'             => $id,
    'name'           => $name,
    'position'       => $position,
    'ghcard'         => $ghcard,
    'ssnit'          => $ssnit,
    'datejoin'       => $datejoin,
    'alcnum'         => $alcnum,
    'bankname'       => $bankname,
    'branch'         => $branch,
    'legalwork'      => $legalwork,
    'actualwork'     => $actualwork,
    'basic'          => $basic,
    'actualbasic'    => $actualbasic,
    'kpi'            => $kpi,
    'coefficient'    => $coefficient,
    'monthlykpi'     => $monthlykpi,
    'overtime'       => $overtime,
    'transport'      => $transport,
    'leave1'         => $leave1,
    'total'          => $total,
    'ssniter'        => $ssniter,
    'ssnitpay'       => $ssnitpay,
    'taxincome'      => $taxincome,
    'paye'           => $paye,
    'overtimetax'    => $overtimetax,
    'totaltax'       => $totaltax,
    'totaldeduction' => $totaldeduction,
    'netpay1'        => $netpay1,
    'bonus'        => $bonus,
    'bonustax'        => $bonustax,
    'bonusfinal'        => $bonusfinal,
    'numdays'        => $numdays,
    'endyearmonth'      => $thmonth,
    'endyeartax'        => $thtax,
    'endyearpay'        => $thpay,
    'loans'          => $loans,
    'netpay'         => $netpay,
];

 $validRecords[] = $data;
 $existingStaffIds[] = $id;
 }

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'inhouse' . strtolower($date->format('F')) . $date->format('y') . 'pay';

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 public function downloadTemplate()
 {
 $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

 // Set headers
 $headers = [
    'Staff ID','Name','Position','Ghana Card','SSNIT No','Date Joined','Account Number','Bank','Branch',
    'Legal Work Days','Actual Days Worked',
    'Basic Salary','Actual Basic','KPI Target','Coefficient','Monthly KPI Bonus',
    'Overtime','Transport','Leave Pay','Gross Earnings',
    'SSNIT ER','SSNIT EE','Taxable Income','PAYE','Overtime Tax','Total Tax','Total Deduction','Net Pay (Before 13th)','Commission/Bonus','Commission/Bonus Tax','Commissiion/Bonus Final',
    '13th Month Days','13th Month Pay','13th Month Tax','13th Month Net','Loans',
    'Final Net Pay'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:AJ1')->getFont()->setBold(true);





 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'inhouse_template.xlsx';
 $tempFile = tempnam(sys_get_temp_dir(), $fileName);
 $writer->save($tempFile);

 return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
 }
}
