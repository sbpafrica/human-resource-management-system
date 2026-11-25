<?php

namespace App\Http\Controllers;

use App\Models\Twelliumaccrapay;
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

class TwelliumaccraPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'twelliumaccra' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('Twelliumaccrapay/Index', [
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

    return Inertia::render('Twelliumaccrapay/Index', [
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
        $tableName = 'twelliumaccra' . strtolower($date->format('F')) . $date->format('y');

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

        // Apply search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staff_id', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Apply sorting using safe COALESCE columns
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

        // Generate Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID',
            'Name',
            'Department',
            'Bank Name',
            'Account No.',
            'Branch Code',
            'Branch',
            'Take Home Pay'
        ];
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

        // Styling — exactly like all your other exports
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headerStyle = $sheet->getStyle('A1:H1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDEFE'); // Light blue

        // Auto-size all columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "twelliumaccra_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("TwelliumaccraPay export failed: " . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
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
            $tableName = 'twelliumaccra' . strtolower($date->format('F')) . $date->format('y');

            TwelliumaccraPay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'twelliumaccra');

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
                return redirect()->route('twelliumaccrapay.index', ['month' => $selectedMonth])
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
                'staff_id'                    => ['staff id', 'staffid', 'employee id', 'staff_id'],
                'name'                        => ['name', 'staff name', 'employee name', 'fullname'],
                'position'                    => ['position', 'role', 'designation'],
                'start_date'                  => ['start date', 'date employed', 'date of employment', 'hire date'],
                'basic_salary'                => ['basic salary', 'basic', 'basic pay'],
                'legal_work_days'             => ['legal work days', 'work days', 'legal workdays'],
                'days_worked'                 => ['days worked', 'number of days worked'],
                'absent_days'                 => ['absent days', 'absenteeism days'],
                'absent_deduction'            => ['absent deduction', 'absent deductions'],
                'basic_earned'                => ['basic earned', 'basic salary earned'],
                'weekday_hours'               => ['weekday hours', 'weekday ot hours'],
                'weekday_overtime_amt'        => ['weekday ot amount', 'weekday overtime amt', 'weekday ot total'],
                'saturday_hours'              => ['saturday hours', 'saturday ot hours'],
                'saturday_overtime_amt'       => ['saturday ot amount', 'saturday overtime amt', 'saturday ot total'],
                'num_sundays_holidays'        => ['no. sundays/holidays', 'sundays/holidays', 'sunday hours', 'num sundays holidays'],
                'sunday_holiday_overtime_amt' => ['sunday/holiday ot amount', 'sunday ot amount', 'holiday ot amount', 'sunday holiday overtime amt'],
                'overtimerefund'              => ['overtime refund', 'ot refund'],
                'total_overtime_amt'          => ['total overtime amount', 'total overtime amt', 'total overtime'],
                'gross_salary'                => ['gross salary', 'gross', 'gross pay'],
                'management_fee'              => ['management fee', 'mgt fee'],
                'getfund'                     => ['getfund', 'get fund'],
                'nhil'                        => ['nhil'],
                'vat_15'                      => ['vat 15%', 'vat'],
                'ssf_13'                      => ['ssf 13%', 'ssf'],
                'medical_insurance'           => ['medical insurance', 'medical'],
                'end_of_year'                 => ['end of year'],
                'ppe'                         => ['ppe'],
                'workmen_compensation'        => ['workmen compensation', 'workmen'],
                'cost_to_twellium'            => ['cost to twellium', 'cost to company'],
                'ssnit_ee'                    => ['ssnit ee', 'ssnit_ee', 'ssnit'],
                'petra'                       => ['petra', 'tier 2', 'petra 5%'],
                'taxable_income'              => ['taxable income', 'taxable'],
                'paye'                        => ['paye', 'income tax'],
                'deductions'                  => ['deductions', 'deduction'],
                'total_deductions'            => ['total deductions', 'total deduction'],
                'net_salary'                  => ['net salary', 'net_salary'],
                'refund'                      => ['refund'],
                'take_home_pay'               => ['take home pay', 'take home', 'takehome', 'bank transfer', 'net pay', 'netpay'],
            ];

            $defaultIndexes = [
                'staff_id' => 0, 'name' => 1, 'position' => 2, 'start_date' => 3,
                'basic_salary' => 4, 'legal_work_days' => 5, 'days_worked' => 6,
                'absent_days' => 7, 'absent_deduction' => 8, 'basic_earned' => 9,
                'weekday_hours' => 10, 'weekday_overtime_amt' => 11, 'saturday_hours' => 12,
                'saturday_overtime_amt' => 13, 'num_sundays_holidays' => 14,
                'sunday_holiday_overtime_amt' => 15, 'overtimerefund' => 16,
                'total_overtime_amt' => 17, 'gross_salary' => 18, 'management_fee' => 19,
                'getfund' => 20, 'nhil' => 21, 'vat_15' => 22, 'ssf_13' => 23,
                'medical_insurance' => 24, 'end_of_year' => 25, 'ppe' => 26,
                'workmen_compensation' => 27, 'cost_to_twellium' => 28, 'ssnit_ee' => 29,
                'petra' => 30, 'taxable_income' => 31, 'paye' => 32, 'deductions' => 33,
                'total_deductions' => 34, 'net_salary' => 35, 'refund' => 36,
                'take_home_pay' => 37,
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

                $position                    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['position'], 'str');
                $start_date                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['start_date'], 'str');
                $basic_salary                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic_salary'], 'req_num');
                $legal_work_days             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['legal_work_days'], 'int');
                $days_worked                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['days_worked'], 'num');
                $absent_days                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['absent_days'], 'num');
                $absent_deduction            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['absent_deduction'], 'num');
                $basic_earned                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic_earned'], 'num');
                $weekday_hours               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekday_hours'], 'num');
                $weekday_overtime_amt        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekday_overtime_amt'], 'num');
                $saturday_hours              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['saturday_hours'], 'num');
                $saturday_overtime_amt       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['saturday_overtime_amt'], 'num');
                $num_sundays_holidays        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['num_sundays_holidays'], 'num');
                $sunday_holiday_overtime_amt = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['sunday_holiday_overtime_amt'], 'num');
                $overtimerefund              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['overtimerefund'], 'num');
                $total_overtime_amt          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_overtime_amt'], 'num');
                $gross_salary                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['gross_salary'], 'req_num');
                $management_fee              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['management_fee'], 'num');
                $getfund                     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['getfund'], 'num');
                $nhil                        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nhil'], 'num');
                $vat_15                      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['vat_15'], 'num');
                $ssf_13                      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssf_13'], 'num');
                $medical_insurance           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['medical_insurance'], 'num');
                $end_of_year                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['end_of_year'], 'num');
                $ppe                         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ppe'], 'num');
                $workmen_compensation        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['workmen_compensation'], 'num');
                $cost_to_twellium            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['cost_to_twellium'], 'num');
                $ssnit_ee                    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit_ee'], 'num');
                $petra                       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['petra'], 'num');
                $taxable_income              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['taxable_income'], 'num');
                $paye                        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['paye'], 'num');
                $deductions                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['deductions'], 'num');
                $total_deductions            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_deductions'], 'num');
                $net_salary                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['net_salary'], 'num');
                $refund                      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['refund'], 'num');
                $take_home_pay               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['take_home_pay'], 'req_num');

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

                if (in_array($staff_id, $existingStaffIds)) {
                    $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $staff_id";
                    continue;
                }

                $data = [
                    'staff_id'                    => $staff_id,
                    'name'                        => $name,
                    'position'                    => $position,
                    'start_date'                  => $start_date,
                    'basic_salary'                => $basic_salary,
                    'legal_work_days'             => $legal_work_days,
                    'days_worked'                 => $days_worked,
                    'absent_days'                 => $absent_days,
                    'absent_deduction'            => $absent_deduction,
                    'basic_earned'                => $basic_earned,
                    'weekday_hours'               => $weekday_hours,
                    'weekday_overtime_amt'        => $weekday_overtime_amt,
                    'saturday_hours'              => $saturday_hours,
                    'saturday_overtime_amt'       => $saturday_overtime_amt,
                    'num_sundays_holidays'        => $num_sundays_holidays,
                    'sunday_holiday_overtime_amt' => $sunday_holiday_overtime_amt,
                    'overtimerefund'              => $overtimerefund,
                    'total_overtime_amt'          => $total_overtime_amt,
                    'gross_salary'                => $gross_salary,
                    'management_fee'              => $management_fee,
                    'getfund'                     => $getfund,
                    'nhil'                        => $nhil,
                    'vat_15'                      => $vat_15,
                    'ssf_13'                      => $ssf_13,
                    'medical_insurance'           => $medical_insurance,
                    'end_of_year'                 => $end_of_year,
                    'ppe'                         => $ppe,
                    'workmen_compensation'        => $workmen_compensation,
                    'cost_to_twellium'            => $cost_to_twellium,
                    'ssnit_ee'                    => $ssnit_ee,
                    'petra'                       => $petra,
                    'taxable_income'              => $taxable_income,
                    'paye'                        => $paye,
                    'deductions'                  => $deductions,
                    'total_deductions'            => $total_deductions,
                    'net_salary'                  => $net_salary,
                    'refund'                      => $refund,
                    'take_home_pay'               => $take_home_pay,
                ];

                // Dynamic custom columns from payroll_columns
                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'twelliumaccra');
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
                return redirect()->route('twelliumaccrapay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
            } else {
                Log::warning("Upload: No valid records to insert into {$tableName}");
            }

            return redirect()->route('twelliumaccrapay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('twelliumaccrapay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'twelliumaccra' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('twelliumaccrapay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('twelliumaccrapay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('twelliumaccrapay.index', ['month' => $selectedMonth])
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
    'Staff ID','Name','Position','Start Date',
    'Basic Salary','Legal Work Days','Days Worked','Absent Days','Absent Deduction','Basic Earned',
    'Weekday Hours','Weekday OT Amount','Saturday Hours','Saturday OT Amount',
    'No. Sundays/Holidays','Sunday/Holiday OT Amount','Overtime Refund','Total Overtime Amount',
    'Gross Salary',
    'Management Fee','GETFund','NHIL','VAT 15%','SSF 13%','Medical Insurance',
    'End of Year','PPE','Workmen Compensation','Cost to Twellium',
    'SSNIT EE','PETRA','Taxable Income','PAYE','Deductions','Total Deductions',
    'Net Salary','Refund','Take Home Pay'
];

$nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'twelliumaccra');
        $sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:Y1')->getFont()->setBold(true);  // Y1 because now only 25 columns (A to Y)

// Example row matching the new structure exactly
$exampleData = [
    'TWL001', 'Kwabena Mensah', 'Production Supervisor', '2021-08-10',
    5200.00, 22, 20.0, 2.0, 472.73, 4727.27,
    15.5, 850.00, 8.0, 1200.00,
    2, 900.00, 0.00, 2950.00,
    7677.27,
    1500.00, 127.50, 127.50, 76.77, 0.00, 998.14, 200.00,
    0.00, 150.00,150.00, 120.00, 10937.18,
    260.00, 7417.27, 985.50, 1545.50,
    6131.77, 200.00, 6331.77
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
