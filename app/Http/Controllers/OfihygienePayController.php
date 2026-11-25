<?php

namespace App\Http\Controllers;

use App\Models\Ofihygienepay;
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

class OfihygienePayController extends Controller
{
 public function index(Request $request)
 {
 $request->validate([
 'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
 ]);

 $month = $request->input('month', now()->format('Y-m'));
 Log::info("Index: Received month: {$month}");

 $date = Carbon::createFromFormat('Y-m', $month);
 $tableName = 'olamhygiene' . strtolower($date->format('F')) . $date->format('y');
 Log::info("Index: Generated table name: {$tableName}");

 if (!DB::getSchemaBuilder()->hasTable($tableName)) {
 Log::warning("Index: Table {$tableName} does not exist");
 return Inertia::render('Ofihygienepay/Index', [
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

 return Inertia::render('Ofihygienepay/Index', [
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
        $tableName = 'olamhygiene' . strtolower($date->format('F')) . $date->format('y');

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

        // Sorting – works perfectly on computed columns
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
        $filename = "olam_hygiene_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("OfihygienePay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            $tableName = 'olamhygiene' . strtolower($date->format('F')) . $date->format('y');

            OfihygienePay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'olamhygiene');

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
                return redirect()->route('ofihygienepay.index', ['month' => $selectedMonth])
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
                    if (preg_match('/^(staff(\s*id)?|id|name|names|take\s*home|net(\s*pay)?)/i', $norm)) {
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
                'id'                => ['staff id', 'id', 'staffid', 'employee id'],
                'names'             => ['names', 'name', 'staff name', 'employee name'],
                'dept'              => ['dept', 'department'],
                'startdate'         => ['start date', 'startdate', 'hire date'],
                'noofdays'          => ['no. of days', 'no of days', 'days'],
                'extraldays'        => ['extra days', 'extraldays'],
                'total1'            => ['total days', 'total1'],
                'rate'              => ['rate', 'basic rate'],
                'total2'            => ['total basic', 'total2', 'basic'],
                'overtimehr'        => ['ot hours', 'overtimehr', 'overtime hours'],
                'overtimetotal'     => ['ot total', 'overtimetotal'],
                'overtimeweekend'   => ['weekend ot hours', 'overtimeweekend'],
                'overtimetotal2'    => ['weekend ot total', 'overtimetotal2'],
                'totalamount'       => ['total earnings', 'totalamount'],
                'transport'         => ['transport', 'transport allowance'],
                'servicecharge'     => ['service charge', 'servicecharge'],
                'getfund'           => ['getfund', 'get fund'],
                'nhil'              => ['nhil'],
                'vat'               => ['vat'],
                'ssf'               => ['ssf', 'ssf 13%'],
                'leave1'            => ['leave allowance', 'leave1'],
                'night'             => ['night days', 'night'],
                'nightallowance'    => ['night allowance', 'nightallowance'],
                'medical'           => ['medical', 'medical insurance'],
                'costofolam'        => ['cost to olam', 'costofolam'],
                'ssnitee'           => ['ssnit ee', 'ssnitee'],
                'petra'             => ['petra', 'tier 2', 'petra 5%'],
                'taxableincome'     => ['taxable income', 'taxableincome'],
                'totaltaxdeduction' => ['total tax deduction', 'totaltaxdeduction'],
                'totaldeduction'    => ['total deduction', 'totaldeduction'],
                'takehome'          => ['take home pay', 'take home', 'takehome', 'bank transfer', 'net pay', 'netpay'],
            ];

            $defaultIndexes = [
                'id' => 0, 'names' => 1, 'dept' => 2, 'startdate' => 3,
                'noofdays' => 4, 'extraldays' => 5, 'total1' => 6, 'rate' => 7, 'total2' => 8,
                'overtimehr' => 9, 'overtimetotal' => 10, 'overtimeweekend' => 11, 'overtimetotal2' => 12,
                'totalamount' => 13, 'transport' => 14, 'servicecharge' => 15, 'getfund' => 16,
                'nhil' => 17, 'vat' => 18, 'ssf' => 19, 'leave1' => 20, 'night' => 21,
                'nightallowance' => 22, 'medical' => 23, 'costofolam' => 24, 'ssnitee' => 25,
                'petra' => 26, 'taxableincome' => 27, 'totaltaxdeduction' => 28, 'totaldeduction' => 29,
                'takehome' => 30,
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

                $id    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['id'], 'str');
                $names = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['names'], 'str');

                if (empty($id) && empty($names)) continue;

                $dept              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['dept'], 'str');
                $startdate         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['startdate'], 'str');
                $noofdays          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['noofdays'], 'num');
                $extraldays        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['extraldays'], 'num');
                $total1            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total1'], 'num');
                $rate              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['rate'], 'num');
                $total2            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total2'], 'num');
                $overtimehr        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['overtimehr'], 'num');
                $overtimetotal     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['overtimetotal'], 'num');
                $overtimeweekend   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['overtimeweekend'], 'num');
                $overtimetotal2    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['overtimetotal2'], 'num');
                $totalamount       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totalamount'], 'num');
                $transport         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['transport'], 'num');
                $servicecharge     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['servicecharge'], 'num');
                $getfund           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['getfund'], 'num');
                $nhil              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nhil'], 'num');
                $vat               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['vat'], 'num');
                $ssf               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssf'], 'num');
                $leave1            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['leave1'], 'num');
                $night             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['night'], 'num');
                $nightallowance    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nightallowance'], 'num');
                $medical           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['medical'], 'num');
                $costofolam        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['costofolam'], 'num');
                $ssnitee           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnitee'], 'num');
                $petra             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['petra'], 'num');
                $taxableincome     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['taxableincome'], 'num');
                $totaltaxdeduction = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totaltaxdeduction'], 'num');
                $totaldeduction    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totaldeduction'], 'num');
                $takehome          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['takehome'], 'req_num');

                if (empty($id)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID cannot be empty.";
                    continue;
                }
                if (empty($names)) {
                    $errors[] = "Row " . ($index + 2) . ": Name cannot be empty.";
                    continue;
                }
                if (is_null($takehome)) {
                    $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number.";
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
                    'startdate'           => $startdate,
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
                    'medical'             => $medical,
                    'costofolam'          => $costofolam,
                    'ssnitee'             => $ssnitee,
                    'petra'               => $petra,
                    'taxableincome'       => $taxableincome,
                    'totaltaxdeduction'   => $totaltaxdeduction,
                    'totaldeduction'      => $totaldeduction,
                    'takehome'            => $takehome,
                ];

                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'olamhygiene');
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
                return redirect()->route('ofihygienepay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
            }

            return redirect()->route('ofihygienepay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('Upload failed: ' . $e->getMessage());
            return redirect()->route('ofihygienepay.index', ['month' => $selectedMonth])
                ->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'olamhygiene' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('ofihygienepay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('ofihygienepay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('ofihygienepay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 public function downloadTemplate()
 {
 $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

 // Set headers
 $headers = [
    'Staff ID','Name','Department','Start Date',
    'No. of Days','Extra Days','Total Days','Rate','Total Basic',
    'OT Hours','OT Total','Weekend OT Hours','Weekend OT Total','Total Earnings',
    'Transport','Service Charge','GETFund','NHIL','VAT','SSF',
    'Leave Allowance','Night Days','Night Allowance','Medical','Cost to Olam',
    'SSNIT EE','PETRA','Taxable Income','Total Tax Deduction','Total Deduction',
    'Take Home Pay'
];

$nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'olamhygiene');
        $sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:AF1')->getFont()->setBold(true);





 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'ofihygiene_template.xlsx';
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
