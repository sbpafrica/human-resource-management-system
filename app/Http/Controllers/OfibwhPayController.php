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
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:2048'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        try {
            $selectedMonth = $request->input('month');
            session(['selected_month' => $selectedMonth]);
            $date = Carbon::createFromFormat('Y-m', $selectedMonth);
            $tableName = 'olambwh' . strtolower($date->format('F')) . $date->format('y');

            OfibwhPay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'olambwh');

            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            if ($ext === 'csv') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
            } elseif ($ext === 'xls') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            } elseif ($ext === 'ods') {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Ods();
            } else {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            }
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, false, false);

            if (empty($rows)) {
                return redirect()->route('ofibwhpay.index', ['month' => $selectedMonth])
                    ->with('error', 'Uploaded spreadsheet is empty.');
            }

            $headerRowIndex = null;
            $headerRow = [];
            foreach ($rows as $rIdx => $r) {
                if ($rIdx > 10) break;
                $matchCount = 0;
                foreach ($r as $cellVal) {
                    if (!is_string($cellVal)) continue;
                    $norm = strtolower(trim(preg_replace('/[\s\xc2\xa0]+/u', ' ', $cellVal)));
                    if (preg_match('/^(staff(\s*id)?|id|name|basic|net(\s*pay)?)/i', $norm)) {
                        $matchCount++;
                    }
                }
                if ($matchCount >= 2) {
                    $headerRowIndex = $rIdx;
                    $headerRow = $r;
                    break;
                }
            }

            if ($headerRowIndex === null) {
                $headerRowIndex = 0;
                $headerRow = $rows[0] ?? [];
            }

            $dataRows = array_slice($rows, $headerRowIndex + 1);

            $fieldAliases = [
                'id'              => ['staff id', 'id', 'staffid', 'employee id'],
                'name'            => ['name', 'staff name', 'employee name'],
                'basic'           => ['basic salary', 'basic', 'basic pay'],
                'workdays'        => ['work days', 'workdays', 'days worked'],
                'f&trate'         => ['f&t rate', 'f&trate'],
                'weekdayhr'       => ['weekday hours', 'weekdayhr'],
                'weekdaytonage'   => ['weekday tonnage', 'weekdaytonage'],
                'weekdayrate'     => ['weekday rate', 'weekdayrate'],
                'amt'             => ['amount', 'amt'],
                'weekdayothr'     => ['weekday ot hours', 'weekdayothr'],
                'weekdayottonage' => ['weekday ot tonnage', 'weekdayottonage'],
                'weekdayotrate'   => ['weekday ot rate', 'weekdayotrate'],
                'weekdayotamt1'   => ['weekday ot amount', 'weekdayotamt1'],
                'weekendhr'       => ['weekend hours', 'weekendhr'],
                'weekendtonage'   => ['weekend tonnage', 'weekendtonage'],
                'weekendrate'     => ['weekend rate', 'weekendrate'],
                'amount'          => ['weekend amount', 'amount'],
                'leavedays'       => ['leave days', 'leavedays'],
                'totalamount'     => ['total amount', 'totalamount'],
                'phonecredit'     => ['phone credit', 'phonecredit'],
                'ssnit13'         => ['ssnit 13%', 'ssnit13'],
                'f&tamount'       => ['f&t amount', 'f&tamount'],
                'servicecharge'   => ['service charge', 'servicecharge'],
                'getfund'         => ['getfund', 'get fund'],
                'nhil'            => ['nhil'],
                'vat'             => ['vat'],
                'costtoolam'      => ['cost to olam', 'costtoolam'],
                'ssnitee'         => ['ssnit ee', 'ssnitee'],
                'petra'           => ['petra', 'tier 2', 'petra 5%'],
                'taxincome'       => ['taxable income', 'taxincome'],
                'totaltax'        => ['total tax', 'totaltax'],
                'totaldeduction'  => ['total deduction', 'totaldeduction'],
                'netpay'          => ['net pay', 'netpay', 'take home pay', 'take home', 'bank transfer'],
                'bank1'           => ['bank', 'bank1'],
                'account2'        => ['account number', 'account2', 'account no'],
                'branch'          => ['branch'],
                'ssnit2'          => ['ssnit no', 'ssnit2', 'ssnit'],
                'ghcard1'         => ['ghana card', 'ghcard1', 'gh card'],
            ];

            $defaultIndexes = [
                'id' => 0, 'name' => 1, 'basic' => 2, 'workdays' => 3, 'f&trate' => 4,
                'weekdayhr' => 5, 'weekdaytonage' => 6, 'weekdayrate' => 7, 'amt' => 8,
                'weekdayothr' => 9, 'weekdayottonage' => 10, 'weekdayotrate' => 11,
                'weekdayotamt1' => 12, 'weekendhr' => 13, 'weekendtonage' => 14,
                'weekendrate' => 15, 'amount' => 16, 'leavedays' => 17, 'totalamount' => 18,
                'phonecredit' => 19, 'ssnit13' => 20, 'f&tamount' => 21, 'servicecharge' => 22,
                'getfund' => 23, 'nhil' => 24, 'vat' => 25, 'costtoolam' => 26, 'ssnitee' => 27,
                'petra' => 28, 'taxincome' => 29, 'totaltax' => 30, 'totaldeduction' => 31,
                'netpay' => 32, 'bank1' => 33, 'account2' => 34, 'branch' => 35,
                'ssnit2' => 36, 'ghcard1' => 37,
            ];

            $headerIndexMap = \App\Models\PayrollColumn::buildHeaderIndexMap($headerRow, $fieldAliases, $defaultIndexes);

            $existingStaffIds = DB::table($tableName)->pluck('id')->toArray();
            $duplicateStaffIds = [];
            $validRecords = [];
            $errors = [];

            foreach ($dataRows as $index => $row) {
                $allEmpty = true;
                foreach ($row as $c) {
                    if ($c !== null && trim((string)$c) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) continue;

                $id   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['id'], 'str');
                $name = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['name'], 'str');

                if (empty($id) && empty($name)) continue;

                $basic           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic'], 'req_num');
                $workdays        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['workdays'], 'num');
                $f_and_t_rate    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['f&trate'], 'num');
                $weekdayhr       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekdayhr'], 'num');
                $weekdaytonage   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekdaytonage'], 'num');
                $weekdayrate     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekdayrate'], 'num');
                $amt             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['amt'], 'num');
                $weekdayothr     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekdayothr'], 'num');
                $weekdayottonage = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekdayottonage'], 'num');
                $weekdayotrate   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekdayotrate'], 'num');
                $weekdayotamt1   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekdayotamt1'], 'num');
                $weekendhr       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekendhr'], 'num');
                $weekendtonage   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekendtonage'], 'num');
                $weekendrate     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekendrate'], 'num');
                $amount          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['amount'], 'num');
                $leavedays       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['leavedays'], 'num');
                $totalamount     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totalamount'], 'num');
                $phonecredit     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['phonecredit'], 'num');
                $ssnit13         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit13'], 'num');
                $f_and_t_amount  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['f&tamount'], 'num');
                $servicecharge   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['servicecharge'], 'num');
                $getfund         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['getfund'], 'num');
                $nhil            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nhil'], 'num');
                $vat             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['vat'], 'num');
                $costtoolam      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['costtoolam'], 'num');
                $ssnitee         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnitee'], 'num');
                $petra           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['petra'], 'num');
                $taxincome       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['taxincome'], 'num');
                $totaltax        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totaltax'], 'num');
                $totaldeduction  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totaldeduction'], 'num');
                $netpay          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['netpay'], 'req_num');
                $bank1           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['bank1'], 'str');
                $account2        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['account2'], 'str');
                $branch          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['branch'], 'str');
                $ssnit2          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit2'], 'str');
                $ghcard1         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ghcard1'], 'str');

                if (empty($id)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID cannot be empty.";
                    continue;
                }
                if (empty($name)) {
                    $errors[] = "Row " . ($index + 2) . ": Name cannot be empty.";
                    continue;
                }
                if (is_null($netpay)) {
                    $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number.";
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
                    'petra'            => $petra,
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

                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'olambwh');
                if (!empty($customVals)) {
                    $data = array_merge($data, $customVals);
                }

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

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
            }

            return redirect()->route('ofibwhpay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('Upload failed: ' . $e->getMessage());
            return redirect()->route('ofibwhpay.index', ['month' => $selectedMonth])
                ->with('error', 'Upload failed: ' . $e->getMessage());
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

$nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'olambwh');
        $sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:AL1')->getFont()->setBold(true);





 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'ofibwh_template.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma'              => 'public',
        ]);
 }
}
