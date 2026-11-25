<?php

namespace App\Http\Controllers;

use App\Models\A2feedpay;
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

class A2feedPayController extends Controller
{
 public function index(Request $request)
{
    $request->validate([
        'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
    ]);

    $month = $request->input('month', now()->format('Y-m'));
    $date = Carbon::createFromFormat('Y-m', $month);
    $tableName = 'a2feed' . strtolower($date->format('F')) . $date->format('y');

    if (!DB::getSchemaBuilder()->hasTable($tableName)) {
        return Inertia::render('A2feedpay/Index', [
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

    return Inertia::render('A2feedpay/Index', [
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
        $tableName = 'a2feed' . strtolower($date->format('F')) . $date->format('y');

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

        $headers = ['Staff ID', 'Name', 'Position/Department', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Take Home Pay'];
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

        // Beautiful styling – same as all your other exports
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
        $filename = "a2feed_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("A2FeedPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            $tableName = 'a2feed' . strtolower($date->format('F')) . $date->format('y');

            A2feedPay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'a2feed');

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
                return redirect()->route('a2feedpay.index', ['month' => $selectedMonth])
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
                'staff_id'                  => ['staff id', 'staffid', 'employee id', 'staff_id'],
                'name'                      => ['name', 'staff name', 'employee name', 'fullname'],
                'position'                  => ['position', 'role', 'designation'],
                'join_date'                 => ['join date', 'hire date', 'start date'],
                'basic_salary'              => ['basic salary', 'basic', 'basic pay'],
                'days_worked'               => ['days worked', 'days_worked', 'work days'],
                'absenteeism_days'          => ['absenteeism days', 'absent days'],
                'absent_days_deductions'    => ['absent deductions', 'absent days deductions', 'absent deduction'],
                'basic_salary_earned'       => ['basic earned', 'basic salary earned'],
                'risk_allowance'            => ['risk allowance'],
                'rent_allowance'            => ['rent allowance'],
                'transport_standard'        => ['transport standard'],
                'transport_earned'          => ['transport earned'],
                'weekday_overtime_hours'    => ['weekday ot hours', 'weekday overtime hours'],
                'weekday_overtime_rate'     => ['weekday ot rate', 'weekday overtime rate'],
                'weekday_overtime_amount'   => ['weekday ot amount', 'weekday overtime amount'],
                'sunday_overtime_hours'     => ['sunday ot hours', 'sunday overtime hours'],
                'sunday_overtime_rate'      => ['sunday ot rate', 'sunday overtime rate'],
                'sunday_overtime_amount'    => ['sunday ot amount', 'sunday overtime amount'],
                'total_overtime_amount'     => ['total overtime amount', 'total overtime'],
                'gross_salary'              => ['gross salary', 'gross', 'gross pay'],
                'management_fee_8'          => ['management fee 8%', 'management fee', 'management_fee_8'],
                'getfund_2_5'               => ['getfund 2.5%', 'getfund', 'getfund_2_5'],
                'nhil_2_5'                  => ['nhil 2.5%', 'nhil', 'nhil_2_5'],
                'vat_15'                    => ['vat 15%', 'vat', 'vat_15'],
                'ssf_13'                    => ['ssf 13%', 'ssf', 'ssf_13'],
                'cost_to_a2_agro'           => ['cost to a2 agro', 'cost to a2', 'cost_to_a2_agro'],
                'gross_salary_repeat'       => ['gross salary (repeat)', 'gross salary repeat'],
                'ssnit_ee_5_5'              => ['ssnit ee 5.5%', 'ssnit ee', 'ssnit_ee_5_5'],
                'petra'                     => ['petra', 'tier 2', 'petra 5%'],
                'taxable_income'            => ['taxable income', 'taxable'],
                'paye'                      => ['paye', 'income tax'],
                'overtime_tax'              => ['overtime tax', 'ot tax'],
                'other_deductions'          => ['other deductions', 'other deduction'],
                'total_deductions'          => ['total deductions', 'total deduction'],
                'take_home_pay'             => ['take home pay', 'take home', 'takehome', 'bank transfer', 'net pay', 'netpay'],
                'ssnit_number'              => ['ssnit number', 'ssnit no', 'ssnit'],
                'gh_card'                   => ['ghana card', 'gh card'],
                'bank'                      => ['bank', 'bank name'],
                'account_number'            => ['account number', 'account no'],
                'branch'                    => ['branch'],
            ];

            $defaultIndexes = [
                'staff_id' => 0, 'name' => 1, 'position' => 2, 'join_date' => 3,
                'basic_salary' => 4, 'days_worked' => 5, 'absenteeism_days' => 6,
                'absent_days_deductions' => 7, 'basic_salary_earned' => 8, 'risk_allowance' => 9,
                'rent_allowance' => 10, 'transport_standard' => 11, 'transport_earned' => 12,
                'weekday_overtime_hours' => 13, 'weekday_overtime_rate' => 14, 'weekday_overtime_amount' => 15,
                'sunday_overtime_hours' => 16, 'sunday_overtime_rate' => 17, 'sunday_overtime_amount' => 18,
                'total_overtime_amount' => 19, 'gross_salary' => 20, 'management_fee_8' => 21,
                'getfund_2_5' => 22, 'nhil_2_5' => 23, 'vat_15' => 24, 'ssf_13' => 25,
                'cost_to_a2_agro' => 26, 'gross_salary_repeat' => 27, 'ssnit_ee_5_5' => 28,
                'petra' => 29, 'taxable_income' => 30, 'paye' => 31, 'overtime_tax' => 32,
                'other_deductions' => 33, 'total_deductions' => 34, 'take_home_pay' => 35,
                'ssnit_number' => 36, 'gh_card' => 37, 'bank' => 38, 'account_number' => 39,
                'branch' => 40,
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

                $position               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['position'], 'str');
                $join_date              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['join_date'], 'str');
                $basic_salary           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic_salary'], 'req_num');
                $days_worked            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['days_worked'], 'num');
                $absenteeism_days       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['absenteeism_days'], 'num');
                $absent_days_deductions = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['absent_days_deductions'], 'num');
                $basic_salary_earned    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic_salary_earned'], 'num');
                $risk_allowance         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['risk_allowance'], 'num');
                $rent_allowance         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['rent_allowance'], 'num');
                $transport_standard     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['transport_standard'], 'num');
                $transport_earned       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['transport_earned'], 'num');
                $weekday_overtime_hours = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekday_overtime_hours'], 'num');
                $weekday_overtime_rate  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekday_overtime_rate'], 'num');
                $weekday_overtime_amount= \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['weekday_overtime_amount'], 'num');
                $sunday_overtime_hours  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['sunday_overtime_hours'], 'num');
                $sunday_overtime_rate   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['sunday_overtime_rate'], 'num');
                $sunday_overtime_amount = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['sunday_overtime_amount'], 'num');
                $total_overtime_amount  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_overtime_amount'], 'num');
                $gross_salary           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['gross_salary'], 'req_num');
                $management_fee_8       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['management_fee_8'], 'num');
                $getfund_2_5            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['getfund_2_5'], 'num');
                $nhil_2_5               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nhil_2_5'], 'num');
                $vat_15                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['vat_15'], 'num');
                $ssf_13                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssf_13'], 'num');
                $cost_to_a2_agro        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['cost_to_a2_agro'], 'num');
                $gross_salary_repeat    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['gross_salary_repeat'], 'num');
                $ssnit_ee_5_5           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit_ee_5_5'], 'num');
                $petra                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['petra'], 'num');
                $taxable_income         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['taxable_income'], 'num');
                $paye                   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['paye'], 'num');
                $overtime_tax           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['overtime_tax'], 'num');
                $other_deductions       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['other_deductions'], 'num');
                $total_deductions       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_deductions'], 'num');
                $take_home_pay          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['take_home_pay'], 'req_num');
                $ssnit_number           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit_number'], 'str');
                $gh_card                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['gh_card'], 'str');
                $bank                   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['bank'], 'str');
                $account_number         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['account_number'], 'str');
                $branch                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['branch'], 'str');

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
                    'staff_id'               => $staff_id,
                    'name'                   => $name,
                    'position'               => $position,
                    'join_date'              => $join_date,
                    'basic_salary'           => $basic_salary,
                    'days_worked'            => $days_worked,
                    'absenteeism_days'       => $absenteeism_days,
                    'absent_days_deductions' => $absent_days_deductions,
                    'basic_salary_earned'    => $basic_salary_earned,
                    'risk_allowance'         => $risk_allowance,
                    'rent_allowance'         => $rent_allowance,
                    'transport_standard'     => $transport_standard,
                    'transport_earned'       => $transport_earned,
                    'weekday_overtime_hours' => $weekday_overtime_hours,
                    'weekday_overtime_rate'  => $weekday_overtime_rate,
                    'weekday_overtime_amount'=> $weekday_overtime_amount,
                    'sunday_overtime_hours'  => $sunday_overtime_hours,
                    'sunday_overtime_rate'   => $sunday_overtime_rate,
                    'sunday_overtime_amount' => $sunday_overtime_amount,
                    'total_overtime_amount'  => $total_overtime_amount,
                    'gross_salary'           => $gross_salary,
                    'management_fee_8'       => $management_fee_8,
                    'getfund_2_5'            => $getfund_2_5,
                    'nhil_2_5'               => $nhil_2_5,
                    'vat_15'                 => $vat_15,
                    'ssf_13'                 => $ssf_13,
                    'cost_to_a2_agro'        => $cost_to_a2_agro,
                    'gross_salary_repeat'    => $gross_salary_repeat,
                    'ssnit_ee_5_5'           => $ssnit_ee_5_5,
                    'petra'                  => $petra,
                    'taxable_income'         => $taxable_income,
                    'paye'                   => $paye,
                    'overtime_tax'           => $overtime_tax,
                    'other_deductions'       => $other_deductions,
                    'total_deductions'       => $total_deductions,
                    'take_home_pay'          => $take_home_pay,
                    'ssnit_number'           => $ssnit_number,
                    'gh_card'                => $gh_card,
                    'bank'                   => $bank,
                    'account_number'         => $account_number,
                    'branch'                 => $branch,
                ];

                // Dynamic custom columns
                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'a2feed');
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
                return redirect()->route('a2feedpay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
            } else {
                Log::warning("Upload: No valid records to insert into {$tableName}");
            }

            return redirect()->route('a2feedpay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('a2feedpay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'a2feed' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('a2feedpay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('a2feedpay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('a2feedpay.index', ['month' => $selectedMonth])
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
    'Staff ID','Name','Position','Join Date',
    'Basic Salary','Days Worked','Absenteeism Days','Absent Deductions','Basic Earned',
    'Risk Allowance','Rent Allowance','Transport Standard','Transport Earned',
    'Weekday OT Hours','Weekday OT Rate','Weekday OT Amount',
    'Sunday OT Hours','Sunday OT Rate','Sunday OT Amount',
    'Total Overtime Amount',
    'Gross Salary',
    'Management Fee 8%','GETFund 2.5%','NHIL 2.5%','VAT 15%','SSF 13%',
    'Cost to A2 Agro',
    'Gross Salary (Repeat)',
    'SSNIT EE 5.5%','PETRA','Taxable Income','PAYE','Overtime Tax',
    'Other Deductions','Total Deductions',
    'Take Home Pay',
    'SSNIT Number','Ghana Card','Bank','Account Number','Branch'
];

$nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'a2feed');
        $sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:Y1')->getFont()->setBold(true);  // Y1 because now only 25 columns (A to Y)





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
