<?php

namespace App\Http\Controllers;

use App\Models\Twelliumpay;
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

class TwelliumPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'twellium' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Twelliumpay/Index', [
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
            DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
            DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) as shop'),
            'bd.bank',
            'bd.account',
            'bd.code',
            'bd.branch',
            'tp.netpay'
        );

    // Search across both sources
    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search, $tableName) {
            $q->where('tp.employ', 'like', "%{$search}%")
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) LIKE ?', ["%{$search}%"])
              ->orWhere('bd.bank', 'like', "%{$search}%");
        });
    }

    // Sorting - now works correctly on computed columns
    $sort = $request->input('sort', 'employ');
    $order = $request->input('order', 'asc');

    $sortMap = [
        'employ' => 'tp.employ',
        'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
        'shop'   => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
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

    return Inertia::render('Twelliumpay/Index', [
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
        $tableName = 'twellium' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.employ', '=', 'bd.employid')
            ->select(
                'tp.employ as staff_id',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as employee_name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) as shop_department'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay as net_pay'
            );

        // Search (works on merged name/shop)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.employ', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(TRIM(bd.shop), ""), tp.department) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting – supports sorting on computed columns
        $sort = $request->input('sort', 'employ');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'employ'     => 'tp.employ',
            'staff_id'   => 'tp.employ',
            'name'       => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
            'employee_name' => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
            'shop'       => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
            'shop_department' => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
            'bank'       => 'bd.bank',
            'account'    => 'bd.account',
            'code'       => 'bd.code',
            'branch'     => 'bd.branch',
            'netpay'     => 'tp.netpay',
            'net_pay'    => 'tp.netpay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.employ', $order);

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
            'Net Pay (GHS)'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staff_id);
            $sheet->setCellValue("B{$row}", $r->employee_name);
            $sheet->setCellValue("C{$row}", $r->shop_department);
            $sheet->setCellValue("D{$row}", $r->bank ?? '');
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->net_pay ? number_format((float)$r->net_pay, 2, '.', '') : '');
            $row++;
        }

        // Professional Styling – matches all your other exports
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
        $filename = "twellium_payroll_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);

    } catch (\Exception $e) {
        Log::error("TwelliumPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Export failed. Please try again.'], 500);
    }
}

/**
     * Upload Excel file
     */
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
            $tableName = 'twellium' . strtolower($date->format('F')) . $date->format('y');

            TwelliumPay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'twellium');

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
                return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
                    ->with('error', 'Uploaded spreadsheet is empty.');
            }

            // Find actual header row dynamically
            $headerRowIndex = null;
            $headerRow = [];
            foreach ($rows as $rIdx => $r) {
                if ($rIdx > 10) break;
                $matchCount = 0;
                foreach ($r as $cellVal) {
                    if (!is_string($cellVal)) continue;
                    $norm = strtolower(trim(preg_replace('/[\s\xc2\xa0]+/u', ' ', $cellVal)));
                    if (preg_match('/^(staff(\s*id)?|name|employ|basic|gross|net(\s*pay)?|take\s*home)/i', $norm)) {
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
                'employ'                        => ['staff id', 'staffid', 'employee id', 'employ'],
                'name'                          => ['name', 'staff name', 'employee name', 'fullname'],
                'department'                    => ['department', 'dept'],
                'dateemploy'                    => ['date employed', 'date of employment', 'date of joining', 'dateemploy', 'hire date'],
                'expected_work_days'            => ['expected work days', 'expected days'],
                'total_hours_worked'            => ['total hours worked', 'total hours', 'hours worked'],
                'basic'                         => ['basic', 'basic salary'],
                'basic_earned'                  => ['basic earned', 'basic salary earned'],
                'food_allowance'                => ['food allowance', 'food'],
                'night_allowance'               => ['night allowance', 'night'],
                'refund'                        => ['refund'],
                'allowance'                     => ['allowance', 'allowances'],
                'deduction'                     => ['deduction', 'deductions'],
                'ssnit_ee'                      => ['ssnit ee', 'ssnit_ee', 'ssnit'],
                'petra'                         => ['petra', 'tier 2', 'petra 5%'],
                'taxable_income'                => ['taxable income', 'taxable'],
                'paye'                          => ['paye', 'income tax'],
                'ssf'                           => ['ssf', 'ssf 13%'],
                'welfare'                       => ['welfare'],
                'medical_insurance'             => ['medical insurance', 'medical'],
                'workmens_compensation'          => ['workmens compensation', 'workmen compensation', 'workmen'],
                'risk_allowance'                => ['risk allowance'],
                'end_of_year'                   => ['end of year', 'end of year bonus'],
                'ppe'                           => ['ppe'],
                'staff_cost'                    => ['staff cost'],
                'mgt_fee'                       => ['mgt fee', 'management fee', 'mgt_fee'],
                'gfl'                           => ['gfl', 'getfund'],
                'nhil'                          => ['nhil'],
                'covid'                         => ['covid', 'covid-19'],
                'vat'                           => ['vat'],
                'c2c'                           => ['c2c', 'cost to company', 'cost to twellium'],
                'weekday_overtime_hours'        => ['weekday ot hours', 'weekday overtime hours'],
                'weekday_overtime_rate'         => ['weekday ot rate', 'weekday overtime rate'],
                'weekday_overtime_amount'       => ['weekday ot amount', 'weekday overtime amount'],
                'offday_holiday_overtime_hours' => ['offday/holiday ot hours', 'offday ot hours', 'holiday ot hours'],
                'offday_holiday_overtime_rate'  => ['offday/holiday ot rate', 'offday ot rate', 'holiday ot rate'],
                'offday_holiday_overtime_total' => ['offday/holiday ot total', 'offday ot total', 'holiday ot total', 'offday/holiday ot amount'],
                'special_day_overtime_hours'    => ['special day ot hours', 'special ot hours'],
                'special_day_overtime_rate'     => ['special day ot rate', 'special ot rate'],
                'special_day_overtime_amount'   => ['special day ot amount', 'special ot amount'],
                'total_overtime_amount'         => ['total overtime amount', 'total overtime', 'total ot amount'],
                'overtime_tax'                  => ['overtime tax', 'ot tax'],
                'total_taxation'                => ['total taxation', 'total tax'],
                'total_deductions'              => ['total deductions', 'total deduction'],
                'netpay'                        => ['net pay', 'netpay', 'take home pay', 'take home', 'bank transfer'],
            ];

            $defaultIndexes = [
                'employ' => 0, 'name' => 1, 'department' => 2, 'dateemploy' => 3,
                'expected_work_days' => 4, 'total_hours_worked' => 5,
                'basic' => 6, 'basic_earned' => 7, 'food_allowance' => 8, 'night_allowance' => 9,
                'refund' => 10, 'allowance' => 11, 'deduction' => 12,
                'ssnit_ee' => 13, 'petra' => 14, 'taxable_income' => 15, 'paye' => 16,
                'ssf' => 17, 'welfare' => 18, 'medical_insurance' => 19, 'workmens_compensation' => 20,
                'risk_allowance' => 21, 'end_of_year' => 22, 'ppe' => 23, 'staff_cost' => 24,
                'mgt_fee' => 25, 'gfl' => 26, 'nhil' => 27, 'covid' => 28, 'vat' => 29,
                'c2c' => 30, 'weekday_overtime_hours' => 31, 'weekday_overtime_rate' => 32,
                'weekday_overtime_amount' => 33, 'offday_holiday_overtime_hours' => 34,
                'offday_holiday_overtime_rate' => 35, 'offday_holiday_overtime_total' => 36,
                'special_day_overtime_hours' => 37, 'special_day_overtime_rate' => 38,
                'special_day_overtime_amount' => 39, 'total_overtime_amount' => 40,
                'overtime_tax' => 41, 'total_taxation' => 42, 'total_deductions' => 43,
                'netpay' => 44,
            ];

            $headerIndexMap = \App\Models\PayrollColumn::buildHeaderIndexMap($headerRow, $fieldAliases, $defaultIndexes);

            // Check for existing staff IDs
            $existingStaffIds = DB::table($tableName)->pluck('employ')->toArray();
            $duplicateStaffIds = [];
            $validRecords = [];
            $errors = [];

            foreach ($dataRows as $index => $row) {
                // Skip empty rows
                $allEmpty = true;
                foreach ($row as $c) {
                    if ($c !== null && trim((string)$c) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) continue;

                $employ = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['employ'], 'str');
                $name   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['name'], 'str');

                if (empty($employ) && empty($name)) {
                    continue;
                }

                $department                    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['department'], 'str');
                $dateemploy                    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['dateemploy'], 'str');
                $expected_work_days            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['expected_work_days'], 'num');
                $total_hours_worked            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_hours_worked'], 'num');
                $basic                         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic'], 'req_num');
                $basic_earned                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic_earned'], 'num');
                $food_allowance                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['food_allowance'], 'num');
                $night_allowance               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['night_allowance'], 'num');
                $refund                        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['refund'], 'num');
                $allowance                     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['allowance'], 'num');
                $deduction                     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['deduction'], 'num');
                $ssnit_ee                      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit_ee'], 'num');
                $petra                         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['petra'], 'num');
                $taxable_income                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['taxable_income'], 'num');
                $paye                          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['paye'], 'num');
                $ssf                           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssf'], 'num');
                $welfare                       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['welfare'], 'num');
                $medical_insurance             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['medical_insurance'], 'num');
                $workmens_compensation         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['workmens_compensation'], 'num');
                $risk_allowance                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['risk_allowance'], 'num');
                $end_of_year                   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['end_of_year'], 'num');
                $ppe                           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ppe'], 'num');
                $staff_cost                    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['staff_cost'], 'num');
                $mgt_fee                       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['mgt_fee'], 'num');
                $gfl                           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['gfl'], 'num');
                $nhil                          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nhil'], 'num');
                $covid                         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['covid'], 'num');
                $vat                           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['vat'], 'num');
                $c2c                           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['c2c'], 'num');
                $weekday_overtime_hours        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekday_overtime_hours'], 'num');
                $weekday_overtime_rate         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekday_overtime_rate'], 'num');
                $weekday_overtime_amount       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekday_overtime_amount'], 'num');
                $offday_holiday_overtime_hours = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['offday_holiday_overtime_hours'], 'num');
                $offday_holiday_overtime_rate  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['offday_holiday_overtime_rate'], 'num');
                $offday_holiday_overtime_total = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['offday_holiday_overtime_total'], 'num');
                $special_day_overtime_hours    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['special_day_overtime_hours'], 'num');
                $special_day_overtime_rate     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['special_day_overtime_rate'], 'num');
                $special_day_overtime_amount   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['special_day_overtime_amount'], 'num');
                $total_overtime_amount         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_overtime_amount'], 'num');
                $overtime_tax                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['overtime_tax'], 'num');
                $total_taxation                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_taxation'], 'num');
                $total_deductions              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_deductions'], 'num');
                $netpay                        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['netpay'], 'req_num');

                if (empty($employ)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
                    continue;
                }
                if (empty($name)) {
                    $errors[] = "Row " . ($index + 2) . ": Name is required.";
                    continue;
                }
                if (is_null($basic)) {
                    $errors[] = "Row " . ($index + 2) . ": Basic is required.";
                    continue;
                }
                if (is_null($netpay)) {
                    $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number.";
                    continue;
                }

                if (in_array($employ, $existingStaffIds)) {
                    $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $employ";
                    continue;
                }

                $data = [
                    'employ'                        => $employ,
                    'name'                          => $name,
                    'department'                    => $department,
                    'dateemploy'                    => $dateemploy,
                    'expected_work_days'            => $expected_work_days,
                    'total_hours_worked'            => $total_hours_worked,
                    'basic'                         => $basic,
                    'basic_earned'                  => $basic_earned,
                    'food_allowance'                => $food_allowance,
                    'night_allowance'               => $night_allowance,
                    'refund'                        => $refund,
                    'allowance'                     => $allowance,
                    'deduction'                     => $deduction,
                    'ssnit_ee'                      => $ssnit_ee,
                    'petra'                         => $petra,
                    'taxable_income'                => $taxable_income,
                    'paye'                          => $paye,
                    'ssf'                           => $ssf,
                    'welfare'                       => $welfare,
                    'medical_insurance'             => $medical_insurance,
                    'workmens_compensation'         => $workmens_compensation,
                    'risk_allowance'                => $risk_allowance,
                    'end_of_year'                   => $end_of_year,
                    'ppe'                           => $ppe,
                    'staff_cost'                    => $staff_cost,
                    'mgt_fee'                       => $mgt_fee,
                    'gfl'                           => $gfl,
                    'nhil'                          => $nhil,
                    'covid'                         => $covid,
                    'vat'                           => $vat,
                    'c2c'                           => $c2c,
                    'weekday_overtime_hours'        => $weekday_overtime_hours,
                    'weekday_overtime_rate'         => $weekday_overtime_rate,
                    'weekday_overtime_amount'       => $weekday_overtime_amount,
                    'offday_holiday_overtime_hours' => $offday_holiday_overtime_hours,
                    'offday_holiday_overtime_rate'  => $offday_holiday_overtime_rate,
                    'offday_holiday_overtime_total' => $offday_holiday_overtime_total,
                    'special_day_overtime_hours'    => $special_day_overtime_hours,
                    'special_day_overtime_rate'     => $special_day_overtime_rate,
                    'special_day_overtime_amount'   => $special_day_overtime_amount,
                    'total_overtime_amount'         => $total_overtime_amount,
                    'overtime_tax'                  => $overtime_tax,
                    'total_taxation'                => $total_taxation,
                    'total_deductions'              => $total_deductions,
                    'netpay'                        => $netpay,
                ];

                // Dynamic custom columns from payroll_columns
                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'twellium');
                if (!empty($customVals)) {
                    $data = array_merge($data, $customVals);
                }

                $validRecords[] = $data;
                $existingStaffIds[] = $employ;
            }

            if (!empty($errors)) {
                Log::error("Upload errors: " . implode(' | ', $errors));
                $errorMessage = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
            } else {
                Log::warning("Upload: No valid records to insert into {$tableName}");
            }

            return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'twellium' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('twelliumpay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 /**
     * Download Excel template
     */
    public function downloadTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID', 'Name', 'Department', 'Date Employed',
            'Expected Work Days', 'Total Hours Worked',
            'Basic', 'Basic Earned', 'Food Allowance', 'Night Allowance',
            'Refund', 'Allowance', 'Total Cash Emolument', 'Deduction', 'Total Assessable Income',
            'SSNIT EE', 'PETRA', 'Taxable Income', 'PAYE', 'SSF', 'Welfare',
            'Medical Insurance', 'Workmens Compensation', 'Risk Allowance',
            'End of Year', 'PPE', 'Staff Cost', 'Mgt Fee', 'GFL', 'NHIL',
            'COVID', 'VAT', 'C2C',
            'Weekday OT Hours', 'Weekday OT Rate', 'Weekday OT Amount',
            'Offday/Holiday OT Hours', 'Offday/Holiday OT Rate', 'Offday/Holiday OT Total',
            'Special Day OT Hours', 'Special Day OT Rate', 'Special Day OT Amount',
            'Total Overtime Amount', 'Overtime Tax',
            'Total Taxation', 'Total Deductions', 'Net Pay'
        ];

        $nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'twellium');
        $sheet->fromArray($headers, null, 'A1');

        // Example row
        $exampleData = [
            'EMP00123', 'Ama Serwaa', 'Operations', '2022-03-15',
            22, 176.5,
            4800.00, 4800.00, 400.00, 600.00,
            150.00, 1150.00, 7100.00, 200.00, 6900.00,
            264.00, 240.00, 6396.00, 850.00, 100.00, 180.00,
            120.00, 300.00, 0.00,
            80.00, 80.00, 7434.00, 1200.00, 135.00, 135.00,
            57.00, 150.00, 8916.00,
            12.5, 45.00, 562.50,
            8.0, 90.00, 720.00,
            0.0, 0.00, 0.00,
            1282.50, 192.38,
            1042.38, 1656.38, 5897.62
        ];

        $sheet->fromArray($exampleData, null, 'A2');

        // Professional Styling
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headerStyle = $sheet->getStyle('A1:AU1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDEFE');

        // Highlight the two new columns (M1: Total Cash Emolument, O1: Total Assessable Income)
        $sheet->getStyle('M1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
        $sheet->getStyle('O1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');

        // Auto-fit all columns A to AU
        for ($col = 1; $col <= count($headers); $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        // Instruction note
        $sheet->setCellValue('A4',
            'INSTRUCTIONS: Fill only the required fields. All amounts in GHS. Do NOT add commas, GH¢ or text in number columns. Dates: YYYY-MM-DD or DD/MM/YYYY. Yellow columns M and O are the newly added Total Cash Emolument and Total Assessable Income.'
        );

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'twellium_template.xlsx';
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
