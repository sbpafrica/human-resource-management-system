<?php

namespace App\Http\Controllers;

use App\Models\TranssnetPay;
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

class TranssnetPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'transsnet' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Transsnetpay/Index', [
            'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
            'selectedMonth' => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords' => 0,
            'searchCount' => null,
            'flash' => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
        ]);
    }

    $query = DB::table($tableName . ' as tp')
        ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
        ->select(
            'tp.staffid',
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
            $q->where('tp.staffid', 'like', "%{$search}%")
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) LIKE ?', ["%{$search}%"])
              ->orWhere('bd.bank', 'like', "%{$search}%");
        });
    }

    // Sorting - now works correctly on computed columns
    $sort = $request->input('sort', 'staffid');
    $order = $request->input('order', 'asc');

    $sortMap = [
        'staffid' => 'tp.staffid',
        'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
        'shop'   => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
        'bank'   => 'bd.bank',
        'account'=> 'bd.account',
        'code'   => 'bd.code',
        'branch' => 'bd.branch',
        'netpay' => 'tp.netpay',
    ];

    $sortColumn = $sortMap[$sort] ?? 'tp.staffid';
    $query->orderBy($sortColumn, $order);

    $perPage = 10;
    $records = $query->paginate($perPage)->appends($request->query());

    $totalRecords = DB::table($tableName)->count();
    $searchCount = $search ? $query->count() : null;

    return Inertia::render('Transsnetpay/Index', [
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
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $month = $request->input('month');
        $date  = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'transsnet' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
            ->select(
                'tp.staffid',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) as shop'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffid', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort  = $request->input('sort', 'staffid');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'staffid' => 'tp.staffid',
            'name'    => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
            'shop'    => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)'),
            'bank'    => 'bd.bank',
            'account' => 'bd.account',
            'code'    => 'bd.code',
            'branch'  => 'bd.branch',
            'netpay'  => 'tp.netpay',
        ];

        $sortColumn = $sortMap[$sort] ?? 'tp.staffid';
        $query->orderBy($sortColumn, $order);

        $records = $query->get();

        // ==================== EXCEL GENERATION ====================
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Name', 'Department', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Net Pay'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staffid);
            $sheet->setCellValue("B{$row}", $r->name);
            $sheet->setCellValue("C{$row}", $r->shop ?? '');
            $sheet->setCellValue("D{$row}", $r->bank ?? '');

            // 100% BULLETPROOF: Account & Code as TEXT
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '',    DataType::TYPE_STRING);

            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", ($r->netpay !== null && $r->netpay !== '') ? number_format((float)$r->netpay, 2, '.', '') : '');

            $row++;
        }

        // Professional styling — same as HondaPay, A2soya, Transsion, Tecno
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('bbdcfe');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "transsnet_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

    } catch (\Exception $e) {
        Log::error("TranssnetPay export failed: " . $e->getMessage());
        return response()->json(['error' => 'Export failed'], 500);
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
            $tableName = 'transsnet' . strtolower($date->format('F')) . $date->format('y');

            TranssnetPay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'transsnet');

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
                return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
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
                    if (preg_match('/^(staff(\s*id)?|name|basic|gross(\s*salary)?|net(\s*pay)?)/i', $norm)) {
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
                'staffid'                  => ['staff id', 'staffid', 'employee id', 'staff_id'],
                'name'                     => ['name', 'staff name', 'employee name', 'fullname'],
                'business_line'            => ['business line', 'businessline'],
                'department'               => ['department', 'dept'],
                'position'                 => ['position', 'role', 'designation'],
                'hired_date'               => ['hired date', 'hire date', 'start date'],
                'last_date'                => ['last date', 'end date'],
                'work_place'               => ['work place', 'workplace', 'location'],
                'staff_category'           => ['staff category', 'category'],
                'expiration_date'          => ['expiration date', 'expiry date'],
                'contracting_entity'       => ['contracting entity', 'entity'],
                'bank_name'                => ['bank name', 'bank'],
                'account_name'             => ['account name'],
                'bank_account_no'          => ['bank account no', 'bank account number', 'account no', 'account number'],
                'branch_code'              => ['branch code', 'branch'],
                'basic'                    => ['basic', 'basic salary', 'basic pay'],
                'performance_basis'        => ['performance basis', 'perf basis'],
                'coefficient'              => ['coefficient'],
                'performance_final'        => ['performance final', 'perf final'],
                'call_allowance'           => ['call allowance'],
                'house_allowance'          => ['house allowance'],
                'traffic_allowance'        => ['traffic allowance'],
                'commission'               => ['commission'],
                'severance_pay'            => ['severance pay', 'severance'],
                'other_income'             => ['other income', 'other'],
                'work_days'                => ['work days', 'workdays', 'days worked'],
                'absent_days'              => ['absent days', 'absentdays'],
                'absent_days_deduction'    => ['absent days deduction', 'absent deduction'],
                'other_deduction'          => ['other deduction', 'other deductions'],
                'gross_salary'             => ['gross salary', 'gross', 'gross pay'],
                'petra_ee_5'               => ['petra ee 5%', 'petra ee', 'petra', 'tier 2'],
                'ssnit_ee_05'              => ['ssnit ee 0.5%', 'ssnit ee', 'ssnit_ee_05', 'ssnit 5.5%'],
                'base_for_paye'            => ['base for paye', 'paye base', 'taxable income'],
                'ee_paye'                  => ['ee paye', 'paye', 'income tax'],
                'ssnit_er_13'              => ['ssnit er 13%', 'ssnit er'],
                'outsourced_service'       => ['outsourced service', 'outsource service'],
                'getfund'                  => ['getfund', 'get fund'],
                'nhiil'                    => ['nhiil', 'nhil'],
                'vat_for_music_ghana'      => ['vat for music ghana', 'vat'],
                'total_cost_of_outsource_service' => ['total cost of outsource service', 'total outsource cost'],
                'ee_cost'                  => ['ee cost'],
                'er_cost'                  => ['er cost'],
                'after_tax_deductions'     => ['after tax deductions', 'after tax deduction'],
                'netpay'                   => ['net pay', 'netpay', 'take home pay', 'take home', 'bank transfer'],
                'total_hr_cost'            => ['total hr cost', 'hr cost'],
            ];

            $defaultIndexes = [
                'staffid' => 0, 'name' => 1, 'business_line' => 2, 'department' => 3, 'position' => 4,
                'hired_date' => 5, 'last_date' => 6, 'work_place' => 7, 'staff_category' => 8,
                'expiration_date' => 9, 'contracting_entity' => 10, 'bank_name' => 11, 'account_name' => 12,
                'bank_account_no' => 13, 'branch_code' => 14, 'basic' => 15, 'performance_basis' => 16,
                'coefficient' => 17, 'performance_final' => 18, 'call_allowance' => 19, 'house_allowance' => 20,
                'traffic_allowance' => 21, 'commission' => 22, 'severance_pay' => 23, 'other_income' => 24,
                'work_days' => 25, 'absent_days' => 26, 'absent_days_deduction' => 27, 'other_deduction' => 28,
                'gross_salary' => 29, 'petra_ee_5' => 30, 'ssnit_ee_05' => 31, 'base_for_paye' => 32,
                'ee_paye' => 33, 'ssnit_er_13' => 34, 'outsourced_service' => 35, 'getfund' => 36,
                'nhiil' => 37, 'vat_for_music_ghana' => 38, 'total_cost_of_outsource_service' => 39,
                'ee_cost' => 40, 'er_cost' => 41, 'after_tax_deductions' => 42, 'netpay' => 43,
                'total_hr_cost' => 44,
            ];

            $headerIndexMap = \App\Models\PayrollColumn::buildHeaderIndexMap($headerRow, $fieldAliases, $defaultIndexes);

            // Check for existing staff IDs
            $existingStaffIds = DB::table($tableName)->pluck('staffid')->toArray();
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

                $staffid = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['staffid'], 'str');
                $name    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['name'], 'str');

                if (empty($staffid) && empty($name)) {
                    continue;
                }

                $business_line         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['business_line'], 'str');
                $department            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['department'], 'str');
                $position              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['position'], 'str');
                $hired_date            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['hired_date'], 'str');
                $last_date             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['last_date'], 'str');
                $work_place            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['work_place'], 'str');
                $staff_category        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['staff_category'], 'str');
                $expiration_date       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['expiration_date'], 'str');
                $contracting_entity    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['contracting_entity'], 'str');
                $bank_name             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['bank_name'], 'str');
                $account_name          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['account_name'], 'str');
                $bank_account_no       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['bank_account_no'], 'str');
                $branch_code           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['branch_code'], 'str');
                $basic                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic'], 'req_num', 0.0);
                $performance_basis     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['performance_basis'], 'str');
                $coefficient           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['coefficient'], 'num', 1.0);
                $performance_final     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['performance_final'], 'num', 0.0);
                $call_allowance        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['call_allowance'], 'num', 0.0);
                $house_allowance       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['house_allowance'], 'num', 0.0);
                $traffic_allowance     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['traffic_allowance'], 'num', 0.0);
                $commission            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['commission'], 'num', 0.0);
                $severance_pay         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['severance_pay'], 'num', 0.0);
                $other_income          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['other_income'], 'num', 0.0);
                $work_days             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['work_days'], 'int', 0);
                $absent_days           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['absent_days'], 'int', 0);
                $absent_days_deduction = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['absent_days_deduction'], 'num', 0.0);
                $other_deduction       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['other_deduction'], 'num', 0.0);
                $gross_salary          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['gross_salary'], 'req_num', 0.0);
                $petra_ee_5            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['petra_ee_5'], 'num', 0.0);
                $ssnit_ee_05           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit_ee_05'], 'num', 0.0);
                $base_for_paye         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['base_for_paye'], 'num', 0.0);
                $ee_paye               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ee_paye'], 'num', 0.0);
                $ssnit_er_13           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit_er_13'], 'num', 0.0);
                $outsourced_service    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['outsourced_service'], 'num', 0.0);
                $getfund               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['getfund'], 'num', 0.0);
                $nhiil                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nhiil'], 'num', 0.0);
                $vat_for_music_ghana   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['vat_for_music_ghana'], 'num', 0.0);
                $total_cost_of_outsource_service = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_cost_of_outsource_service'], 'num', 0.0);
                $ee_cost               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ee_cost'], 'num', 0.0);
                $er_cost               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['er_cost'], 'num', 0.0);
                $after_tax_deductions  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['after_tax_deductions'], 'num', 0.0);
                $netpay                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['netpay'], 'req_num');
                $total_hr_cost         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_hr_cost'], 'num', 0.0);

                // Validation
                if (empty($staffid)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
                    continue;
                }
                if (empty($name)) {
                    $errors[] = "Row " . ($index + 2) . ": Name is required.";
                    continue;
                }
                if (is_null($basic) || is_null($gross_salary)) {
                    $errors[] = "Row " . ($index + 2) . ": Basic and Gross Pay are required.";
                    continue;
                }
                if (is_null($netpay)) {
                    $errors[] = "Row " . ($index + 2) . ": Bank Transfer (Net Pay) must be a valid number.";
                    continue;
                }

                // Duplicate check
                if (in_array($staffid, $existingStaffIds)) {
                    $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $staffid";
                    continue;
                }

                $data = [
                    'staffid'                        => $staffid,
                    'name'                           => $name,
                    'business_line'                  => $business_line,
                    'department'                     => $department,
                    'position'                       => $position,
                    'hired_date'                     => $hired_date,
                    'last_date'                      => $last_date,
                    'work_place'                     => $work_place,
                    'staff_category'                 => $staff_category,
                    'expiration_date'                => $expiration_date,
                    'contracting_entity'             => $contracting_entity,
                    'bank_name'                      => $bank_name,
                    'account_name'                   => $account_name,
                    'bank_account_no'                => $bank_account_no,
                    'branch_code'                    => $branch_code,
                    'basic'                          => $basic,
                    'performance_basis'              => $performance_basis,
                    'coefficient'                    => $coefficient,
                    'performance_final'              => $performance_final,
                    'call_allowance'                 => $call_allowance,
                    'house_allowance'                => $house_allowance,
                    'traffic_allowance'              => $traffic_allowance,
                    'commission'                     => $commission,
                    'severance_pay'                  => $severance_pay,
                    'other_income'                   => $other_income,
                    'work_days'                      => $work_days,
                    'absent_days'                    => $absent_days,
                    'absent_days_deduction'          => $absent_days_deduction,
                    'other_deduction'                => $other_deduction,
                    'gross_salary'                   => $gross_salary,
                    'petra_ee_5'                     => $petra_ee_5,
                    'ssnit_ee_05'                    => $ssnit_ee_05,
                    'base_for_paye'                  => $base_for_paye,
                    'ee_paye'                        => $ee_paye,
                    'ssnit_er_13'                    => $ssnit_er_13,
                    'outsourced_service'             => $outsourced_service,
                    'getfund'                        => $getfund,
                    'nhiil'                          => $nhiil,
                    'vat_for_music_ghana'            => $vat_for_music_ghana,
                    'total_cost_of_outsource_service'=> $total_cost_of_outsource_service,
                    'ee_cost'                        => $ee_cost,
                    'er_cost'                        => $er_cost,
                    'after_tax_deductions'           => $after_tax_deductions,
                    'netpay'                         => $netpay,
                    'total_hr_cost'                  => $total_hr_cost,
                ];

                // Dynamic custom columns
                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'transsnet');
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
                return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
            } else {
                Log::warning("Upload: No valid records to insert into {$tableName}");
            }

            return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'transsnet' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('transsnetpay.index', ['month' => $selectedMonth])
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
            'Staff ID', 'Name', 'Business Line', 'Department', 'Position', 'Hired Date', 'Last Date', 'Work Place',
            'Staff Category', 'Expiration Date', 'Contracting Entity', 'Bank Name', 'Account Name', 'Bank Account No',
            'Branch Code', 'Basic', 'Performance Basis', 'Coefficient', 'Performance Final', 'Call Allowance',
            'House Allowance', 'Traffic Allowance', 'Commission', 'Severance Pay', 'Other Income', 'Work Days',
            'Absent Days', 'Absent Days Deduction', 'Other Deduction', 'Gross Salary', 'Petra EE 5%', 'SSNIT EE 0.5%',
            'Base for PAYE', 'EE PAYE', 'SSNIT ER 13%', 'Outsourced Service', 'GETFund', 'NHIIL', 
            'VAT for Music Ghana', 'Total Cost of Outsource Service', 'EE Cost', 'ER Cost', 'After Tax Deductions',
            'Net Pay', 'Total HR Cost'
        ];

        $nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'transsnet');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:AU1')->getFont()->setBold(true);

        // Example row
        $exampleData = [
            'TNS00123', 'Kwame Osei', 'Retail', 'Sales', 'Sales Executive', '2023-06-15', '', 'Accra Mall',
            'Permanent', '2026-06-14', 'Transsnet Ghana Ltd', 'GCB Bank', 'Kwame Osei', '003201234567', '002',
            3500.00, 'Yes', 1.15, 4025.00, 200.00, 800.00, 300.00, 500.00, 0.00, 150.00, 26, 0, 0.00, 100.00,
            6975.00, 0.00, 34.88, 6940.12, 750.25, 455.00, 2000.00, 50.00,  20.00, 0.00, 2500.00,
            4475.25, 4025.00, 100.00, 200.00, 5975.00, 10000.00
        ];

   $sheet->fromArray($exampleData, null, 'A2');

    // Instruction note
    $sheet->setCellValue('A' . ($sheet->getHighestRow() + 3),
        'INSTRUCTIONS: Fill only the yellow/required fields. All amounts in GHS. Do NOT add commas, GH¢ or text in number columns. Dates: DD/MM/YYYY or MM/DD/YYYY.'
    );

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $fileName = 'Payroll_Upload_Template_' . date('M-Y') . '.xlsx';
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
