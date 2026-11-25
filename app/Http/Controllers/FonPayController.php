<?php

namespace App\Http\Controllers;

use App\Models\Fonpay;
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

class FonPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'fonpackage' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Fonpay/Index', [
            'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
            'selectedMonth' => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords' => 0,
            'searchCount' => null,
            'flash' => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
        ]);
    }

    $query = DB::table($tableName . ' as tp')
        ->leftJoin('bankdetails as bd', 'tp.staff_id', '=', 'bd.employid')
        ->select(
            'tp.staff_id',
            // Use payroll name/shop if bankdetails missing or null
            DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
            DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position) as shop'),
            'bd.bank',
            'bd.account',
            'bd.code',
            'bd.branch',
            'tp.take_home_pay'
        );

    // Search across both sources
    if ($search = $request->input('search')) {
        $query->where(function ($q) use ($search, $tableName) {
            $q->where('tp.staff_id', 'like', "%{$search}%")
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
              ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position) LIKE ?', ["%{$search}%"])
              ->orWhere('bd.bank', 'like', "%{$search}%");
        });
    }

    // Sorting - now works correctly on computed columns
    $sort = $request->input('sort', 'staff_id');
    $order = $request->input('order', 'asc');

    $sortMap = [
        'staff_id' => 'tp.staff_id',
        'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
        'shop'   => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position)'),
        'bank'   => 'bd.bank',
        'account'=> 'bd.account',
        'code'   => 'bd.code',
        'branch' => 'bd.branch',
        'take_home_pay' => 'tp.take_home_pay',
    ];

    $sortColumn = $sortMap[$sort] ?? 'tp.staff_id';
    $query->orderBy($sortColumn, $order);

    $perPage = 30;
    $records = $query->paginate($perPage)->appends($request->query());

    $totalRecords = DB::table($tableName)->count();
    $searchCount = $search ? $query->count() : null;

    return Inertia::render('Fonpay/Index', [
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
        $tableName = 'fonpackage' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staff_id', '=', 'bd.employid')
            ->select(
                'tp.staff_id',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position) as shop'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.take_home_pay'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staff_id', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting – works perfectly on computed columns
        $sort = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'staff_id'      => 'tp.staff_id',
            'name'          => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
            'shop'          => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position)'),
            'bank'          => 'bd.bank',
            'account'       => 'bd.account',
            'code'          => 'bd.code',
            'branch'        => 'bd.branch',
            'take_home_pay' => 'tp.take_home_pay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.staff_id', $order);

        $records = $query->get();

        // Generate Real Excel File
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Name', 'Department', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Take Home Pay'];
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

        // Styling – same beautiful look as all your other exports
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
        $filename = "fon_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("FonPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            $tableName = 'fonpackage' . strtolower($date->format('F')) . $date->format('y');

            FonPay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'fonpackage');

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
                return redirect()->route('fonpay.index', ['month' => $selectedMonth])
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
                    if (preg_match('/^(staff(\s*id)?|name|position|basic|gross|take\s*home|net(\s*pay)?)/i', $norm)) {
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
                'staff_id'                      => ['staff id', 'staffid', 'employee id', 'staff_id'],
                'name'                          => ['name', 'staff name', 'employee name', 'fullname'],
                'position'                      => ['position', 'role', 'designation'],
                'date_of_employment'            => ['date of employment', 'employment date', 'hire date', 'join date'],
                'basic_salary'                  => ['basic salary', 'basic', 'basic pay'],
                'legal_work_days'               => ['legal work days', 'legal workdays', 'work days'],
                'number_of_days_worked'         => ['number of days worked', 'days worked', 'worked days'],
                'no_of_absent_days'             => ['no of absent days', 'absent days', 'absenteeism days'],
                'absent_days_deduction'         => ['absent days deduction', 'absent deduction', 'absent deductions'],
                'basic_salary_earned'           => ['basic salary earned', 'basic earned'],
                'number_of_days_on_night_duty'  => ['number of days on night duty', 'night duty days', 'night days'],
                'normal_weekday_overtime_hours' => ['normal weekday overtime hours', 'weekday ot hours', 'weekday overtime hours'],
                'normal_weekday_overtime_rate'  => ['normal weekday overtime rate', 'weekday ot rate', 'weekday overtime rate'],
                'weekday_overtime_total'        => ['weekday overtime total', 'weekday ot total', 'weekday ot amount'],
                'saturday_overtime_hours'       => ['saturday overtime hours', 'saturday ot hours'],
                'saturday_overtime_rate'        => ['saturday overtime rate', 'saturday ot rate'],
                'saturday_overtime_total'       => ['saturday overtime total', 'saturday ot total', 'saturday ot amount'],
                'sunday_holiday_overtime_hours' => ['sunday/holiday overtime hours', 'sunday holiday ot hours', 'sunday ot hours', 'holiday ot hours'],
                'sunday_holiday_overtime_rate'  => ['sunday/holiday overtime rate', 'sunday holiday ot rate', 'sunday ot rate', 'holiday ot rate'],
                'sunday_holiday_overtime_total' => ['sunday/holiday overtime total', 'sunday holiday ot total', 'sunday ot total', 'holiday ot total', 'sunday ot amount'],
                'total_overtime_amount'         => ['total overtime amount', 'total overtime', 'total ot amount'],
                'night_duty_allowance'          => ['night duty allowance', 'night allowance'],
                'gross_salary'                  => ['gross salary', 'gross', 'gross pay'],
                'ssf_13'                        => ['ssf 13%', 'ssf 13', 'ssf_13', 'ssf'],
                'medical_insurance'             => ['medical insurance', 'medical'],
                'ppe'                           => ['ppe'],
                'workmen_compensation'          => ['workmen compensation', 'workmen'],
                'management_fee_10'             => ['management fee 10%', 'management fee 10', 'management fee', 'management_fee_10'],
                'getfund_2_5'                   => ['getfund 2.5%', 'getfund', 'getfund_2_5'],
                'nhil_2_5'                      => ['nhil 2.5%', 'nhil', 'nhil_2_5'],
                'vat_15'                        => ['vat 15%', 'vat', 'vat_15'],
                'cost_to_fon_packaging'         => ['cost to fon packaging', 'cost to fon', 'cost_to_fon_packaging'],
                'gross_salary_repeat'           => ['gross salary (repeat)', 'gross salary repeat'],
                'ssnit_ee_5_5'                  => ['ssnit ee 5.5%', 'ssnit ee', 'ssnit_ee_5_5', 'ssnit 5.5%'],
                'petra'                         => ['petra', 'petra 5%', 'tier 2'],
                'taxable_income'                => ['taxable income', 'taxable'],
                'paye'                          => ['paye', 'income tax'],
                'overtime_tax'                  => ['overtime tax', 'ot tax'],
                'total_taxation'                => ['total taxation', 'total tax'],
                'total_deductions'              => ['total deductions', 'total deduction'],
                'take_home_pay'                 => ['take home pay', 'take home', 'takehome', 'bank transfer', 'net pay', 'netpay'],
            ];

            $defaultIndexes = [
                'staff_id' => 0, 'name' => 1, 'position' => 2, 'date_of_employment' => 3,
                'basic_salary' => 4, 'legal_work_days' => 5, 'number_of_days_worked' => 6,
                'no_of_absent_days' => 7, 'absent_days_deduction' => 8, 'basic_salary_earned' => 9,
                'number_of_days_on_night_duty' => 10, 'normal_weekday_overtime_hours' => 11,
                'normal_weekday_overtime_rate' => 12, 'weekday_overtime_total' => 13,
                'saturday_overtime_hours' => 14, 'saturday_overtime_rate' => 15,
                'saturday_overtime_total' => 16, 'sunday_holiday_overtime_hours' => 17,
                'sunday_holiday_overtime_rate' => 18, 'sunday_holiday_overtime_total' => 19,
                'total_overtime_amount' => 20, 'night_duty_allowance' => 21,
                'gross_salary' => 22, 'ssf_13' => 23, 'medical_insurance' => 24,
                'ppe' => 25, 'workmen_compensation' => 26, 'management_fee_10' => 27,
                'getfund_2_5' => 28, 'nhil_2_5' => 29, 'vat_15' => 30,
                'cost_to_fon_packaging' => 31, 'gross_salary_repeat' => 32,
                'ssnit_ee_5_5' => 33, 'petra' => 34, 'taxable_income' => 35,
                'paye' => 36, 'overtime_tax' => 37, 'total_taxation' => 38,
                'total_deductions' => 39, 'take_home_pay' => 40,
            ];

            $headerIndexMap = \App\Models\PayrollColumn::buildHeaderIndexMap($headerRow, $fieldAliases, $defaultIndexes);

            // Check for existing staff IDs
            $existingStaffIds = DB::table($tableName)->pluck('staff_id')->toArray();
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

                $staff_id = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['staff_id'], 'str');
                $name     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['name'], 'str');

                if (empty($staff_id) && empty($name)) {
                    continue;
                }

                $position                      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['position'], 'str');
                $date_of_employment            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['date_of_employment'], 'str');
                $basic_salary                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic_salary'], 'req_num');
                $legal_work_days               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['legal_work_days'], 'int');
                $number_of_days_worked         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['number_of_days_worked'], 'num');
                $no_of_absent_days             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['no_of_absent_days'], 'num');
                $absent_days_deduction         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['absent_days_deduction'], 'num');
                $basic_salary_earned           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic_salary_earned'], 'num');
                $number_of_days_on_night_duty  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['number_of_days_on_night_duty'], 'num');
                $normal_weekday_overtime_hours = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['normal_weekday_overtime_hours'], 'num');
                $normal_weekday_overtime_rate  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['normal_weekday_overtime_rate'], 'num');
                $weekday_overtime_total        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekday_overtime_total'], 'num');
                $saturday_overtime_hours       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['saturday_overtime_hours'], 'num');
                $saturday_overtime_rate        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['saturday_overtime_rate'], 'num');
                $saturday_overtime_total       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['saturday_overtime_total'], 'num');
                $sunday_holiday_overtime_hours = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['sunday_holiday_overtime_hours'], 'num');
                $sunday_holiday_overtime_rate  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['sunday_holiday_overtime_rate'], 'num');
                $sunday_holiday_overtime_total = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['sunday_holiday_overtime_total'], 'num');
                $total_overtime_amount         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_overtime_amount'], 'num');
                $night_duty_allowance          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['night_duty_allowance'], 'num');
                $gross_salary                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['gross_salary'], 'req_num');
                $ssf_13                        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssf_13'], 'num');
                $medical_insurance             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['medical_insurance'], 'num');
                $ppe                           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ppe'], 'num');
                $workmen_compensation          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['workmen_compensation'], 'num');
                $management_fee_10             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['management_fee_10'], 'num');
                $getfund_2_5                   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['getfund_2_5'], 'num');
                $nhil_2_5                      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nhil_2_5'], 'num');
                $vat_15                        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['vat_15'], 'num');
                $cost_to_fon_packaging         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['cost_to_fon_packaging'], 'num');
                $gross_salary_repeat           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['gross_salary_repeat'], 'num');
                $ssnit_ee_5_5                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit_ee_5_5'], 'num');
                $petra                         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['petra'], 'num');
                $taxable_income                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['taxable_income'], 'num');
                $paye                          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['paye'], 'num');
                $overtime_tax                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['overtime_tax'], 'num');
                $total_taxation                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_taxation'], 'num');
                $total_deductions              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_deductions'], 'num');
                $take_home_pay                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['take_home_pay'], 'req_num');

                // Validation
                if (empty($staff_id)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
                    continue;
                }
                if (empty($name)) {
                    $errors[] = "Row " . ($index + 2) . ": Name is required.";
                    continue;
                }
                if (is_null($basic_salary)) {
                    $errors[] = "Row " . ($index + 2) . ": Basic is required.";
                    continue;
                }
                if (is_null($take_home_pay)) {
                    $errors[] = "Row " . ($index + 2) . ": Bank Transfer (Net Pay) must be a valid number.";
                    continue;
                }

                // Duplicate check
                if (in_array($staff_id, $existingStaffIds)) {
                    $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $staff_id";
                    continue;
                }

                $data = [
                    'staff_id'                        => $staff_id,
                    'name'                            => $name,
                    'position'                        => $position,
                    'date_of_employment'              => $date_of_employment,
                    'basic_salary'                    => $basic_salary,
                    'legal_work_days'                 => $legal_work_days,
                    'number_of_days_worked'           => $number_of_days_worked,
                    'no_of_absent_days'               => $no_of_absent_days,
                    'absent_days_deduction'           => $absent_days_deduction,
                    'basic_salary_earned'             => $basic_salary_earned,
                    'number_of_days_on_night_duty'    => $number_of_days_on_night_duty,
                    'normal_weekday_overtime_hours'   => $normal_weekday_overtime_hours,
                    'normal_weekday_overtime_rate'    => $normal_weekday_overtime_rate,
                    'weekday_overtime_total'          => $weekday_overtime_total,
                    'saturday_overtime_hours'         => $saturday_overtime_hours,
                    'saturday_overtime_rate'          => $saturday_overtime_rate,
                    'saturday_overtime_total'         => $saturday_overtime_total,
                    'sunday_holiday_overtime_hours'   => $sunday_holiday_overtime_hours,
                    'sunday_holiday_overtime_rate'    => $sunday_holiday_overtime_rate,
                    'sunday_holiday_overtime_total'   => $sunday_holiday_overtime_total,
                    'total_overtime_amount'           => $total_overtime_amount,
                    'night_duty_allowance'            => $night_duty_allowance,
                    'gross_salary'                    => $gross_salary,
                    'ssf_13'                          => $ssf_13,
                    'medical_insurance'               => $medical_insurance,
                    'ppe'                             => $ppe,
                    'workmen_compensation'            => $workmen_compensation,
                    'management_fee_10'               => $management_fee_10,
                    'getfund_2_5'                     => $getfund_2_5,
                    'nhil_2_5'                        => $nhil_2_5,
                    'vat_15'                          => $vat_15,
                    'cost_to_fon_packaging'           => $cost_to_fon_packaging,
                    'gross_salary_repeat'             => $gross_salary_repeat,
                    'ssnit_ee_5_5'                    => $ssnit_ee_5_5,
                    'petra'                           => $petra,
                    'taxable_income'                  => $taxable_income,
                    'paye'                            => $paye,
                    'overtime_tax'                    => $overtime_tax,
                    'total_taxation'                  => $total_taxation,
                    'total_deductions'                => $total_deductions,
                    'take_home_pay'                   => $take_home_pay,
                ];

                // Dynamic custom columns from payroll_columns
                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'fonpackage');
                if (!empty($customVals)) {
                    $data = array_merge($data, $customVals);
                }

                $validRecords[] = $data;
                $existingStaffIds[] = $staff_id;
            }

            if (!empty($errors)) {
                Log::error("Upload errors: " . implode(' | ', $errors));
                $errorMessage = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('fonpay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
            } else {
                Log::warning("Upload: No valid records to insert into {$tableName}");
            }

            return redirect()->route('fonpay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('fonpay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'fonpackage' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('fonpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('fonpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('fonpay.index', ['month' => $selectedMonth])
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
    'Staff ID','Name','Position','Date of Employment',
    'Basic Salary','Legal Work Days','Days Worked','Absent Days','Absent Deduction','Basic Earned',
    'Night Duty Days',
    'Weekday OT Hours','Weekday OT Rate','Weekday OT Total',
    'Saturday OT Hours','Saturday OT Rate','Saturday OT Total',
    'Sunday/Holiday OT Hours','Sunday/Holiday OT Rate','Sunday/Holiday OT Total',
    'Total Overtime Amount','Night Duty Allowance',
    'Gross Salary',
    'SSF 13%','Medical Insurance','PPE','Workmen Compensation',
    'Management Fee 10%','GETFund 2.5%','NHIL 2.5%','VAT 15%','Cost to FON Packaging',
    'Gross Salary (Repeat)',
    'SSNIT EE 5.5%','PETRA','Taxable Income','PAYE','Overtime Tax','Total Taxation',
    'Total Deductions','Take Home Pay'
];

$nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'fonpackage');
        $sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:Y1')->getFont()->setBold(true);  // Y1 because now only 25 columns (A to Y)



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
