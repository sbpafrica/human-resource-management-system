<?php

namespace App\Http\Controllers;

use App\Models\CenturyPay;
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

class CenturyPayController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'centurypay' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('Centurypay/Index', [
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
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname) as shop'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.employ', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

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
            'netpay' => 'tp.netpay',
        ];

        $sortColumn = $sortMap[$sort] ?? 'tp.employ';
        $query->orderBy($sortColumn, $order);

        $perPage = 10;
        $records = $query->paginate($perPage)->appends($request->query());

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        return Inertia::render('Centurypay/Index', [
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
        $tableName = 'centurypay' . strtolower($date->format('F')) . $date->format('y');

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
                'tp.netpay as take_home_pay'
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
            'netpay'        => 'tp.netpay',
            'take_home_pay' => 'tp.netpay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.employ', $order);

        $records = $query->get();

        // Generate Real Excel File
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Name', 'Shop/Branch', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Net Pay'];
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
        $filename = "century_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("CenturyPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            $tableName = 'centurypay' . strtolower($date->format('F')) . $date->format('y');

            CenturyPay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'centurypay');

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
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

            if (empty($rows)) {
                return redirect()->route('centurypay.index', ['month' => $selectedMonth])
                    ->with('error', 'Uploaded spreadsheet is empty.');
            }

            // Find actual header row dynamically (scanning first 10 rows)
            $headerRowIndex = null;
            $headerRow = [];
            foreach ($rows as $rIdx => $r) {
                if ($rIdx > 10) break;
                $matchCount = 0;
                foreach ($r as $cellVal) {
                    if (!is_string($cellVal)) continue;
                    $norm = strtolower(trim(preg_replace('/[\s\xc2\xa0]+/u', ' ', $cellVal)));
                    if (preg_match('/^(staff(\s*id)?|employee(\s*id)?|employ|name|basic|gross|net(\s*pay)?|bank\s*transfer)/i', $norm)) {
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
                'employ'            => ['staff id', 'staffid', 'employee id', 'employ', 'id', 'staff_id'],
                'empoyeename'       => ['employee name', 'name', 'employeename', 'staff name', 'fullname', 'names'],
                'outsourced'        => ['outsourced', 'outsource'],
                'desig'             => ['designation', 'desig', 'position', 'role', 'title'],
                'joindate'          => ['join date', 'joindate', 'start date'],
                'shopname'          => ['shop name', 'shopname', 'shop', 'branch', 'location'],
                'actualdays'        => ['actual days', 'actualdays', 'days worked', 'workdays'],
                'night'             => ['night', 'night days'],
                'halfdays'          => ['half days', 'halfdays'],
                'satoffdays'        => ['sat off days', 'satoffdays'],
                'sunoffdays'        => ['sun off days', 'sunoffdays'],
                'leavedays'         => ['leave days', 'leavedays'],
                'absentdays'        => ['absent days', 'absentdays'],
                'holiday'           => ['holiday', 'holidays'],
                'weekdaysOT'        => ['weekdays ot', 'weekdaysot', 'weekday ot', 'weekday_ot'],
                'weekendOT'         => ['weekend ot', 'weekendot', 'weekends ot', 'weekend_ot'],
                'basic'             => ['basic salary', 'basic', 'basic pay', 'basicsalary'],
                'absentwithoutpay'  => ['absent without pay', 'absentwithoutpay'],
                'basicentitlement'  => ['basic entitlement', 'basicentitlement', 'basic entitled'],
                'transport'         => ['transport allowance', 'transport', 'transportallowance'],
                'nightallowance'    => ['night allowance', 'nightallowance'],
                'normalovertime'    => ['normal overtime', 'normalovertime'],
                'saturdayovertime'  => ['saturday overtime', 'saturdayovertime'],
                'gross'             => ['gross salary', 'gross', 'gross pay', 'totalearn', 'total earnings'],
                'ssniter'           => ['ssniter', 'ssnit er', 'ssnit (er)'],
                'ssnitee'           => ['ssnitee', 'ssnit ee', 'ssnit (ee)', 'ssnitee0.5', 'ssnit_ee'],
                'ssnit'             => ['ssnitee5', 'ssnit', 'ssnit 5.5%', 'ssnit5'],
                'taxabled'          => ['taxable', 'taxabled', 'taxable income'],
                'paye'              => ['paye', 'income tax'],
                'totalovertime'     => ['total overtime', 'totalovertime'],
                'overtimetax'       => ['overtime tax', 'overtimetax', 'ot tax'],
                'totaltax'          => ['total tax', 'totaltax'],
                'advance'           => ['advance', 'salary advance'],
                'totaldeduction'    => ['total deduction', 'totaldeduction', 'total deductions', 'totaldeduct'],
                'netpay'            => ['net pay', 'bank transfer', 'take home pay', 'take home', 'netpay', 'net_pay', 'banktransfer'],
            ];

            $defaultIndexes = [
                'employ' => 0, 'empoyeename' => 1, 'outsourced' => 2, 'desig' => 3, 'joindate' => 4,
                'shopname' => 5, 'actualdays' => 6, 'night' => 7, 'halfdays' => 8, 'satoffdays' => 9,
                'sunoffdays' => 10, 'leavedays' => 11, 'absentdays' => 12, 'holiday' => 13,
                'weekdaysOT' => 14, 'weekendOT' => 15, 'basic' => 16, 'absentwithoutpay' => 17,
                'basicentitlement' => 18, 'transport' => 19, 'nightallowance' => 20, 'normalovertime' => 21,
                'saturdayovertime' => 22, 'gross' => 23, 'ssniter' => 24, 'ssnitee' => 25,
                'ssnit' => 26, 'taxabled' => 27, 'paye' => 28, 'totalovertime' => 29,
                'overtimetax' => 30, 'totaltax' => 31, 'advance' => 32, 'totaldeduction' => 33, 'netpay' => 34,
            ];

            $headerIndexMap = \App\Models\PayrollColumn::buildHeaderIndexMap($headerRow, $fieldAliases, $defaultIndexes);

            $existingStaffIds = DB::table($tableName)->pluck('employ')->toArray();
            $validRecords = [];
            $errors = [];

            foreach ($dataRows as $index => $row) {
                // Skip completely empty rows
                $allEmpty = true;
                foreach ($row as $c) {
                    if ($c !== null && trim((string)$c) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) continue;

                $employ           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['employ'], 'str');
                $empoyeename      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['empoyeename'], 'str');

                if (empty($employ) && empty($empoyeename)) {
                    continue;
                }

                $outsourced       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['outsourced'], 'str');
                $desig            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['desig'], 'str');
                $joindate         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['joindate'], 'str');
                $shopname         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['shopname'], 'str');
                $actualdays       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['actualdays'], 'int', 0);
                $night            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['night'], 'int', 0);
                $halfdays         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['halfdays'], 'int', 0);
                $satoffdays       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['satoffdays'], 'int', 0);
                $sunoffdays       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['sunoffdays'], 'int', 0);
                $leavedays        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['leavedays'], 'int', 0);
                $absentdays       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['absentdays'], 'int', 0);
                $holiday          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['holiday'], 'int', 0);
                $weekdaysOT       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekdaysOT'], 'int', 0);
                $weekendOT        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekendOT'], 'int', 0);
                $basic            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic'], 'req_num');
                $absentwithoutpay = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['absentwithoutpay'], 'num', 0.0);
                $basicentitlement = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basicentitlement'], 'num', 0.0);
                $transport        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['transport'], 'num', 0.0);
                $nightallowance   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nightallowance'], 'num', 0.0);
                $normalovertime   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['normalovertime'], 'num', 0.0);
                $saturdayovertime = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['saturdayovertime'], 'num', 0.0);
                $gross            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['gross'], 'req_num');
                $ssniter          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssniter'], 'num', 0.0);
                $ssnitee          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnitee'], 'num', 0.0);
                $ssnit            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit'], 'num', 0.0);
                $taxabled         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['taxabled'], 'num', 0.0);
                $paye             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['paye'], 'num', 0.0);
                $totalovertime    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totalovertime'], 'num', 0.0);
                $overtimetax      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['overtimetax'], 'num', 0.0);
                $totaltax         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totaltax'], 'num', 0.0);
                $advance          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['advance'], 'num', 0.0);
                $totaldeduction   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totaldeduction'], 'num', 0.0);
                $netpay           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['netpay'], 'req_num');

                if (empty($employ)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
                    continue;
                }
                if (empty($empoyeename)) {
                    $errors[] = "Row " . ($index + 2) . ": Name is required.";
                    continue;
                }
                if (is_null($basic) || is_null($gross) || is_null($netpay)) {
                    $errors[] = "Row " . ($index + 2) . ": Basic, Gross and Net Pay are required.";
                    continue;
                }
                if (in_array($employ, $existingStaffIds)) {
                    $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $employ";
                    continue;
                }

                $record = [
                    'employ'            => $employ,
                    'empoyeename'       => $empoyeename,
                    'outsourced'        => $outsourced,
                    'desig'             => $desig,
                    'joindate'          => $joindate,
                    'shopname'          => $shopname,
                    'actualdays'        => $actualdays,
                    'night'             => $night,
                    'halfdays'          => $halfdays,
                    'satoffdays'        => $satoffdays,
                    'sunoffdays'        => $sunoffdays,
                    'leavedays'         => $leavedays,
                    'absentdays'        => $absentdays,
                    'holiday'           => $holiday,
                    'weekdaysOT'        => $weekdaysOT,
                    'weekendOT'         => $weekendOT,
                    'basic'             => $basic,
                    'absentwithoutpay'  => $absentwithoutpay,
                    'basicentitlement'  => $basicentitlement,
                    'transport'         => $transport,
                    'nightallowance'    => $nightallowance,
                    'normalovertime'    => $normalovertime,
                    'saturdayovertime'  => $saturdayovertime,
                    'gross'             => $gross,
                    'ssniter'           => $ssniter,
                    'ssnitee'           => $ssnitee,
                    'ssnit'             => $ssnit,
                    'taxabled'          => $taxabled,
                    'paye'              => $paye,
                    'totalovertime'     => $totalovertime,
                    'overtimetax'       => $overtimetax,
                    'totaltax'          => $totaltax,
                    'advance'           => $advance,
                    'totaldeduction'    => $totaldeduction,
                    'netpay'            => $netpay,
                ];

                // Extract all dynamic custom columns from template
                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'centurypay');
                if (!empty($customVals)) {
                    foreach (array_keys($customVals) as $cCol) {
                        if (!\Illuminate\Support\Facades\Schema::hasColumn($tableName, $cCol)) {
                            try {
                                DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `{$cCol}` DOUBLE NULL DEFAULT 0");
                            } catch (\Throwable $e) {}
                        }
                    }
                    $record = array_merge($record, $customVals);
                }

                $validRecords[] = $record;
                $existingStaffIds[] = $employ;
            }

            if (!empty($errors)) {
                $errorMessage = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more.'
                    : implode(' | ', $errors);
                return redirect()->route('centurypay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("Century: Inserted " . count($validRecords) . " records into {$tableName}");
            }

            return redirect()->route('centurypay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');

        } catch (\Exception $e) {
            Log::error('Century upload failed', ['error' => $e->getMessage()]);
            return redirect()->route('centurypay.index', ['month' => $selectedMonth])
                ->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function delete(Request $request)
    {
        $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
        $date = Carbon::createFromFormat('Y-m', $selectedMonth);
        $tableName = 'centurypay' . strtolower($date->format('F')) . $date->format('y');

        if (DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::table($tableName)->truncate();
            return redirect()->route('centurypay.index', ['month' => $selectedMonth])
                ->with('success', 'Records deleted successfully.');
        }

        return redirect()->route('centurypay.index', ['month' => $selectedMonth])
            ->with('error', 'No records found.');
    }

    public function downloadTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID','Name','Outsourced','Designation','Join Date','Shop Name',
            'Actual Days','Night','Half Days','Sat Off Days','Sun Off Days','Leave Days',
            'Absent Days','Holiday','Weekdays OT','Weekend OT',
            'Basic','Absent Without Pay','Basic Entitlement','Transport','Night Allowance',
            'Normal Overtime','Saturday Overtime','Gross','SSNITER', 'SSNITEE0.5', 'SSNITEE5','Taxable','PAYE',
            'Total Overtime','Overtime Tax','Total Tax','Advance','Total Deduction','Net Pay'
        ];

        $nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'centurypay');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);

        $example = [[
            'CEN001','Ama Serwaa','Yes','Sales Assistant','01/06/2022','Century - Osu',
            26,8,0,0,1,0,0,0,5,3,
            1850.00,0.00,1850.00,200.00,400.00,525.00,450.00,00.00,525.00,450.00,
            4425.00,258.00,4167.00,492.00,975.00,142.00,634.00,100.00,934.00,3491.00
        ]];

        $sheet->fromArray($example, null, 'A2');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'Century_Payroll_Template_' . date('M-Y') . '.xlsx';
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
