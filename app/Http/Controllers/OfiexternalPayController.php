<?php

namespace App\Http\Controllers;

use App\Models\Ofiexternalpay;
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

class OfiexternalPayController extends Controller
{
 public function index(Request $request)
 {
 $request->validate([
 'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
 ]);

 $month = $request->input('month', now()->format('Y-m'));
 Log::info("Index: Received month: {$month}");

 $date = Carbon::createFromFormat('Y-m', $month);
 $tableName = 'hygieneexternal' . strtolower($date->format('F')) . $date->format('y');
 Log::info("Index: Generated table name: {$tableName}");

 if (!DB::getSchemaBuilder()->hasTable($tableName)) {
 Log::warning("Index: Table {$tableName} does not exist");
 return Inertia::render('Ofiexternalpay/Index', [
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
 ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
 ->select(
 'tp.staffid',
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
 $q->where("tp.staffid", 'like', "%{$search}%")
 ->orWhere("tp.employeename", 'like', "%{$search}%")
 ->orWhere('bd.bank', 'like', "%{$search}%");
 });
 }

 // Apply sorting
 $sort = $request->input('sort', 'staffid');
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

 return Inertia::render('Ofiexternalpay/Index', [
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
        $tableName = 'hygieneexternal' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
            ->select(
                'tp.staffid as staff_id',
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
                $q->where('tp.staffid', 'like', "%{$search}%")
                  ->orWhere('tp.employeename', 'like', "%{$search}%")
                  ->orWhere('bd.shop', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting – works perfectly on all columns including merged ones
        $sort = $request->input('sort', 'staffid');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'staffid'     => 'tp.staffid',
            'staff_id'    => 'tp.staffid',
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

        $query->orderBy($sortMap[$sort] ?? 'tp.staffid', $order);

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

        // Professional Styling – identical to all your other exports
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
        $filename = "olam_hygiene_external_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("OfiexternalPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            $tableName = 'hygieneexternal' . strtolower($date->format('F')) . $date->format('y');

            OfiexternalPay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'hygieneexternal');

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
                return redirect()->route('ofiexternalpay.index', ['month' => $selectedMonth])
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
                    if (preg_match('/^(staff(\s*id)?|employee(\s*name)?|name|basic|take\s*home|net(\s*pay)?)/i', $norm)) {
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
                'staffid'              => ['staff id', 'staffid', 'employee id'],
                'employeename'         => ['employee name', 'name', 'staff name', 'fullname'],
                'workarea'             => ['work area', 'workarea', 'department'],
                'startdate'            => ['start date', 'startdate', 'hire date'],
                'basicsalary'          => ['basic salary', 'basic', 'basic pay'],
                'legalworkdays'        => ['legal work days', 'legal workdays', 'work days'],
                'noofdayspresent'      => ['days present', 'no of days present', 'days worked'],
                'basicsalaryearned'    => ['basic earned', 'basic salary earned'],
                'weekdayovertimehr'    => ['weekday ot hours', 'weekday overtime hours'],
                'weekdayovertimetotal' => ['weekday ot amount', 'weekday overtime total'],
                'weekendovertimehr'    => ['weekend ot hours', 'weekend overtime hours'],
                'weekendovertimetotal' => ['weekend ot amount', 'weekend overtime total'],
                'totalamount'          => ['total earnings', 'total amount'],
                'transport'            => ['transport', 'transport allowance'],
                'servicecharge'        => ['service charge'],
                'getfund'              => ['getfund', 'get fund'],
                'nhil'                 => ['nhil'],
                'vat'                  => ['vat'],
                'ssf'                  => ['ssf', 'ssf 13%'],
                'costtoolam'           => ['cost to olam', 'cost to company'],
                'ssnitee'              => ['ssnit ee', 'ssnitee'],
                'petra'                => ['petra', 'tier 2', 'petra 5%'],
                'taxableincome'        => ['taxable income', 'taxable'],
                'totaltaxdeductions'   => ['total tax deductions', 'total tax deduction', 'total tax'],
                'totaldeductions'      => ['total deductions', 'total deduction'],
                'takehomepay'          => ['take home pay', 'take home', 'takehome', 'bank transfer', 'net pay', 'netpay'],
                'bank'                 => ['bank', 'bank name'],
                'accountnumber'        => ['account number', 'account no'],
                'branch'               => ['branch'],
                'ssnit'                => ['ssnit no', 'ssnit number', 'ssnit'],
                'ghcard'               => ['ghana card', 'gh card'],
            ];

            $defaultIndexes = [
                'staffid' => 0, 'employeename' => 1, 'workarea' => 2, 'startdate' => 3,
                'basicsalary' => 4, 'legalworkdays' => 5, 'noofdayspresent' => 6, 'basicsalaryearned' => 7,
                'weekdayovertimehr' => 8, 'weekdayovertimetotal' => 9, 'weekendovertimehr' => 10,
                'weekendovertimetotal' => 11, 'totalamount' => 12, 'transport' => 13, 'servicecharge' => 14,
                'getfund' => 15, 'nhil' => 16, 'vat' => 17, 'ssf' => 18, 'costtoolam' => 19,
                'ssnitee' => 20, 'petra' => 21, 'taxableincome' => 22, 'totaltaxdeductions' => 23,
                'totaldeductions' => 24, 'takehomepay' => 25, 'bank' => 26, 'accountnumber' => 27,
                'branch' => 28, 'ssnit' => 29, 'ghcard' => 30,
            ];

            $headerIndexMap = \App\Models\PayrollColumn::buildHeaderIndexMap($headerRow, $fieldAliases, $defaultIndexes);

            $existingStaffIds = DB::table($tableName)->pluck('staffid')->toArray();
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

                $staffid      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['staffid'], 'str');
                $employeename = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['employeename'], 'str');

                if (empty($staffid) && empty($employeename)) continue;

                $workarea             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['workarea'], 'str');
                $startdate            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['startdate'], 'str');
                $basicsalary          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basicsalary'], 'req_num');
                $legalworkdays        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['legalworkdays'], 'int');
                $noofdayspresent      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['noofdayspresent'], 'num');
                $basicsalaryearned    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basicsalaryearned'], 'num');
                $weekdayovertimehr    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekdayovertimehr'], 'num');
                $weekdayovertimetotal = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekdayovertimetotal'], 'num');
                $weekendovertimehr    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekendovertimehr'], 'num');
                $weekendovertimetotal = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekendovertimetotal'], 'num');
                $totalamount          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totalamount'], 'num');
                $transport            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['transport'], 'num');
                $servicecharge        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['servicecharge'], 'num');
                $getfund              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['getfund'], 'num');
                $nhil                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nhil'], 'num');
                $vat                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['vat'], 'num');
                $ssf                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssf'], 'num');
                $costtoolam           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['costtoolam'], 'num');
                $ssnitee              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnitee'], 'num');
                $petra                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['petra'], 'num');
                $taxableincome        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['taxableincome'], 'num');
                $totaltaxdeductions   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totaltaxdeductions'], 'num');
                $totaldeductions      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totaldeductions'], 'num');
                $takehomepay          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['takehomepay'], 'req_num');
                $bank                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['bank'], 'str');
                $accountnumber        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['accountnumber'], 'str');
                $branch               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['branch'], 'str');
                $ssnit                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit'], 'str');
                $ghcard               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ghcard'], 'str');

                if (empty($staffid)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID cannot be empty.";
                    continue;
                }
                if (empty($employeename)) {
                    $errors[] = "Row " . ($index + 2) . ": Name cannot be empty.";
                    continue;
                }
                if (is_null($takehomepay)) {
                    $errors[] = "Row " . ($index + 2) . ": Take Home Pay must be a valid number.";
                    continue;
                }

                if (in_array($staffid, $existingStaffIds) || in_array($staffid, $duplicateStaffIds)) {
                    $duplicateStaffIds[] = $staffid;
                    continue;
                }

                $data = [
                    'staffid'              => $staffid,
                    'employeename'         => $employeename,
                    'workarea'             => $workarea,
                    'startdate'            => $startdate,
                    'basicsalary'          => $basicsalary,
                    'legalworkdays'        => $legalworkdays,
                    'noofdayspresent'      => $noofdayspresent,
                    'basicsalaryearned'    => $basicsalaryearned,
                    'weekdayovertimehr'    => $weekdayovertimehr,
                    'weekdayovertimetotal' => $weekdayovertimetotal,
                    'weekendovertimehr'    => $weekendovertimehr,
                    'weekendovertimetotal' => $weekendovertimetotal,
                    'totalamount'          => $totalamount,
                    'transport'            => $transport,
                    'servicecharge'        => $servicecharge,
                    'getfund'              => $getfund,
                    'nhil'                 => $nhil,
                    'vat'                  => $vat,
                    'ssf'                  => $ssf,
                    'costtoolam'           => $costtoolam,
                    'ssnitee'              => $ssnitee,
                    'petra'                => $petra,
                    'taxableincome'        => $taxableincome,
                    'totaltaxdeductions'   => $totaltaxdeductions,
                    'totaldeductions'      => $totaldeductions,
                    'takehomepay'          => $takehomepay,
                    'bank'                 => $bank,
                    'accountnumber'        => $accountnumber,
                    'branch'               => $branch,
                    'ssnit'                => $ssnit,
                    'ghcard'               => $ghcard,
                ];

                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'hygieneexternal');
                if (!empty($customVals)) {
                    $data = array_merge($data, $customVals);
                }

                $validRecords[] = $data;
                $existingStaffIds[] = $staffid;
            }

            if (!empty($errors)) {
                Log::error("Upload errors: " . implode(' | ', $errors));
                $errorMessage = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('ofiexternalpay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
            }

            return redirect()->route('ofiexternalpay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('Upload failed: ' . $e->getMessage());
            return redirect()->route('ofiexternalpay.index', ['month' => $selectedMonth])
                ->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'hygieneexternal' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('ofiexternalpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('ofiexternalpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('ofiexternalpay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 public function downloadTemplate()
 {
 $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

 $headers = [
    'Staff ID','Employee Name','Work Area','Start Date',
    'Basic Salary','Legal Work Days','Days Present','Basic Earned',
    'Weekday OT Hours','Weekday OT Amount','Weekend OT Hours','Weekend OT Amount',
    'Total Earnings','Transport','Service Charge','GETFund','NHIL','VAT','SSF',
    'Cost to Olam','SSNIT EE','PETRA','Taxable Income','Total Tax Deductions','Total Deductions',
    'Take Home Pay',
    'Bank','Account Number','Branch','SSNIT No','Ghana Card'
];

$nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'hygieneexternal');
        $sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:AE1')->getFont()->setBold(true);




 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'ofiexternal_template.xlsx';
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
