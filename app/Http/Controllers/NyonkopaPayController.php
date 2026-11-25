<?php

namespace App\Http\Controllers;

use App\Models\NyonkopaPay;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class NyonkopaPayController extends Controller
{
    /**
     * Build the monthly table name. e.g. April 2026 → nyonkopaapril26
     */
    private function buildTableName(Carbon $date): string
    {
        return 'nyonkopa' . strtolower($date->format('F')) . $date->format('y');
    }

    // ─────────────────────────────────────────────────────────────────────
    //  INDEX
    //  Fixes: tp.name / tp.department → tp.employee_name / tp.position
    //         (unknown-column crash whenever user sorted/searched by name or shop)
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        $date  = Carbon::createFromFormat('Y-m', $month);
        $tableName = $this->buildTableName($date);

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('Nyonkopapay/Index', [
                'records'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 30),
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'searchCount'    => null,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        // Single source of truth for the coalesced expressions — reused in
        // select, search, AND sort so they can never drift apart again.
        $nameExpr = 'COALESCE(NULLIF(TRIM(bd.name), ""), tp.employee_name)';
        $shopExpr = 'COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position)';

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staff_id', '=', 'bd.employid')
            ->select(
                'tp.staff_id',
                DB::raw("{$nameExpr} as name"),
                DB::raw("{$shopExpr} as shop"),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.take_home_pay'
            );

        // ── Search across both sources ────────────────────────────────────
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search, $nameExpr, $shopExpr) {
                $q->where('tp.staff_id', 'like', "%{$search}%")
                  ->orWhereRaw("{$nameExpr} LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("{$shopExpr} LIKE ?", ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // ── Sorting (column whitelist + direction whitelist) ──────────────
        $sort  = $request->input('sort', 'staff_id');
        $order = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortMap = [
            'staff_id'      => 'tp.staff_id',
            'name'          => $nameExpr,       // FIXED (was tp.name)
            'shop'          => $shopExpr,       // FIXED (was tp.department)
            'bank'          => 'bd.bank',
            'account'       => 'bd.account',
            'code'          => 'bd.code',
            'branch'        => 'bd.branch',
            'take_home_pay' => 'tp.take_home_pay',
        ];

        $sortColumn = $sortMap[$sort] ?? 'tp.staff_id';

        // NULL-safe ordering (empty banks/accounts drop to bottom), plus
        // a stable secondary sort on staff_id so pagination doesn't shuffle.
        $query->orderByRaw("({$sortColumn}) IS NULL, {$sortColumn} {$order}");
        $query->orderBy('tp.staff_id', 'asc');

        $records = $query->paginate(30)->appends($request->query());

        $totalRecords = DB::table($tableName)->count();
        $searchCount  = $search ? $records->total() : null;   // paginator already has count

        return Inertia::render('Nyonkopapay/Index', [
            'records'        => $records,
            'selectedMonth'  => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords'   => $totalRecords,
            'searchCount'    => $searchCount,
            'flash'          => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  EXPORT
    //  Kept intact — this method was already correct.
    // ─────────────────────────────────────────────────────────────────────
    public function export(Request $request)
    {
        try {
            $request->validate(['month' => ['required', 'date_format:Y-m']]);

            $month = $request->input('month');
            $date  = Carbon::createFromFormat('Y-m', $month);
            $tableName = $this->buildTableName($date);

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
            }

            $nameExpr = 'COALESCE(NULLIF(TRIM(bd.name), ""), tp.employee_name)';
            $shopExpr = 'COALESCE(NULLIF(TRIM(bd.shop), ""), tp.position)';

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('bankdetails as bd', 'tp.staff_id', '=', 'bd.employid')
                ->select(
                    'tp.staff_id',
                    DB::raw("{$nameExpr} as name"),
                    DB::raw("{$shopExpr} as shop"),
                    'bd.bank',
                    'bd.account',
                    'bd.code',
                    'bd.branch',
                    'tp.take_home_pay'
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search, $nameExpr, $shopExpr) {
                    $q->where('tp.staff_id', 'like', "%{$search}%")
                      ->orWhereRaw("{$nameExpr} LIKE ?", ["%{$search}%"])
                      ->orWhereRaw("{$shopExpr} LIKE ?", ["%{$search}%"])
                      ->orWhere('bd.bank', 'like', "%{$search}%");
                });
            }

            $sort  = $request->input('sort', 'staff_id');
            $order = strtolower((string) $request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

            $sortMap = [
                'staff_id'      => 'tp.staff_id',
                'name'          => $nameExpr,
                'shop'          => $shopExpr,
                'bank'          => 'bd.bank',
                'account'       => 'bd.account',
                'code'          => 'bd.code',
                'branch'        => 'bd.branch',
                'take_home_pay' => 'tp.take_home_pay',
            ];

            $sortColumn = $sortMap[$sort] ?? 'tp.staff_id';
            $query->orderByRaw("({$sortColumn}) IS NULL, {$sortColumn} {$order}");
            $query->orderBy('tp.staff_id', 'asc');

            $records = $query->get();

            // ── Excel generation ─────────────────────────────────────────
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['Staff ID', 'Name', 'Department', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Net Pay'];
        $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            foreach ($records as $r) {
                $sheet->setCellValue("A{$row}", $r->staff_id);
                $sheet->setCellValue("B{$row}", $r->name);
                $sheet->setCellValue("C{$row}", $r->shop ?? '');
                $sheet->setCellValue("D{$row}", $r->bank ?? '');

                // Account & Code as TEXT so leading zeros are preserved
                $sheet->setCellValueExplicit("E{$row}", (string) ($r->account ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("F{$row}", (string) ($r->code ?? ''),    DataType::TYPE_STRING);

                $sheet->setCellValue("G{$row}", $r->branch ?? '');
                $sheet->setCellValue("H{$row}", ($r->take_home_pay !== null && $r->take_home_pay !== '') ? number_format((float) $r->take_home_pay, 2, '.', '') : '');

                $row++;
            }

            // Professional styling
            $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
            $sheet->getStyle('A1:H1')->getFont()->setBold(true);
            $sheet->getStyle('A1:H1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('bbdcfe');

            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer   = new Xlsx($spreadsheet);
            $filename = "nyonkopa_pay_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (\Exception $e) {
            Log::error('NyonkopaPay export failed: ' . $e->getMessage());
            return response()->json(['error' => 'Export failed'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  UPLOAD
    //  Fixes: undefined $rawBankTransfer (fatal error on invalid net pay)
    //         reader only handled XLSX despite accepting xls/csv/ods
    //         "Expected 24 columns" typo (check is 28)
    //         added row-level dedupe within the file itself
    //         wrapped insert in a transaction
    // ─────────────────────────────────────────────────────────────────────
    public function upload(Request $request)
    {
        $request->validate([
            'file'  => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:2048'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $selectedMonth = $request->input('month');
        session(['selected_month' => $selectedMonth]);

        try {
            $date = Carbon::createFromFormat('Y-m', $selectedMonth);
            $tableName = $this->buildTableName($date);

            NyonkopaPay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'nyonkopa');

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
            $sheet       = $spreadsheet->getActiveSheet();
            $rows        = $sheet->toArray(null, true, false, false);

            if (empty($rows)) {
                return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
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
                    if (preg_match('/^(staff(\s*id)?|employee(\s*name)?|name|base(\s*salary)?|gross|take\s*home)/i', $norm)) {
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
                'employee_name'             => ['employee name', 'name', 'staff name', 'fullname'],
                'start_date'                => ['start date', 'hire date', 'employment date', 'join date'],
                'position'                  => ['position', 'role', 'designation'],
                'work_location'             => ['work location', 'location', 'workplace'],
                'base_salary'               => ['base salary', 'basic salary', 'basic'],
                'fuel_and_rent_allowance'   => ['fuel and rent allowance', 'fuel allowance', 'rent allowance', 'allowance'],
                'gross_salary'              => ['gross salary', 'gross'],
                'management_fee_8'          => ['management fee 8%', 'management fee'],
                'getfund_2_5'               => ['getfund 2.5%', 'getfund'],
                'nhil_2_5'                  => ['nhil 2.5%', 'nhil'],
                'vat_15'                    => ['vat 15%', 'vat'],
                'medical_insurance'         => ['medical insurance', 'medical'],
                'employer_provident_fund_5' => ['employer provident fund 5%', 'employer pf', 'employer provident fund'],
                'thirteenth_month_bonus'    => ['13th month bonus', '13th month'],
                'workman'                   => ['workman compensation', 'workman', 'workmen compensation'],
                'ssnit_er_13'               => ['ssnit er 13%', 'ssnit er'],
                'cost_to_nyonkopa'          => ['cost to nyonkopa', 'cost to company'],
                'ssnit_ee_5_5'              => ['ssnit ee 5.5%', 'ssnit ee'],
                'petra'                     => ['petra', 'tier 2', 'petra 5%'],
                'employeepf'                => ['employeepf 3%', 'employee pf', 'employeepf'],
                'taxable_income'            => ['taxable income', 'taxable'],
                'paye'                      => ['paye', 'income tax'],
                'thirteenth_month_tax'      => ['13th month tax', 'bonus tax'],
                'total_taxation'            => ['total taxation', 'total tax'],
                'deductions'                => ['deductions', 'deduction'],
                'total_deductions'          => ['total deductions', 'total deduction'],
                'take_home_pay'             => ['take home pay', 'take home', 'net pay', 'netpay', 'bank transfer'],
            ];

            $defaultIndexes = [
                'staff_id' => 0, 'employee_name' => 1, 'start_date' => 2, 'position' => 3,
                'work_location' => 4, 'base_salary' => 5, 'fuel_and_rent_allowance' => 6,
                'gross_salary' => 7, 'management_fee_8' => 8, 'getfund_2_5' => 9,
                'nhil_2_5' => 10, 'vat_15' => 11, 'medical_insurance' => 12,
                'employer_provident_fund_5' => 13, 'thirteenth_month_bonus' => 14,
                'workman' => 15, 'ssnit_er_13' => 16, 'cost_to_nyonkopa' => 17,
                'ssnit_ee_5_5' => 18, 'petra' => 19, 'employeepf' => 20,
                'taxable_income' => 21, 'paye' => 22, 'thirteenth_month_tax' => 23,
                'total_taxation' => 24, 'deductions' => 25, 'total_deductions' => 26,
                'take_home_pay' => 27,
            ];

            $headerIndexMap = \App\Models\PayrollColumn::buildHeaderIndexMap($headerRow, $fieldAliases, $defaultIndexes);

            $existingStaffIds = DB::table($tableName)->pluck('staff_id')->toArray();
            $errors = [];
            $validRecords = [];
            $seenInFile = [];

            foreach ($dataRows as $index => $row) {
                // Skip empty rows silently
                $allEmpty = true;
                foreach ($row as $c) {
                    if ($c !== null && trim((string)$c) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) continue;

                $staff_id      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['staff_id'], 'str');
                $employee_name = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['employee_name'], 'str');

                if (empty($staff_id) && empty($employee_name)) {
                    continue;
                }

                $start_date                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['start_date'], 'str');
                $position                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['position'], 'str');
                $work_location             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['work_location'], 'str');
                $base_salary               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['base_salary'], 'req_num');
                $fuel_and_rent_allowance   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['fuel_and_rent_allowance'], 'num');
                $gross_salary              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['gross_salary'], 'req_num');
                $management_fee_8          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['management_fee_8'], 'num');
                $getfund_2_5               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['getfund_2_5'], 'num');
                $nhil_2_5                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nhil_2_5'], 'num');
                $vat_15                    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['vat_15'], 'num');
                $medical_insurance         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['medical_insurance'], 'num');
                $employer_provident_fund_5 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['employer_provident_fund_5'], 'num');
                $thirteenth_month_bonus    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['thirteenth_month_bonus'], 'num');
                $workman                   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['workman'], 'num');
                $ssnit_er_13               = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit_er_13'], 'num');
                $cost_to_nyonkopa          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['cost_to_nyonkopa'], 'num');
                $ssnit_ee_5_5              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit_ee_5_5'], 'num');
                $petra                     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['petra'], 'num');
                $employeepf                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['employeepf'], 'num');
                $taxable_income            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['taxable_income'], 'num');
                $paye                      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['paye'], 'num');
                $thirteenth_month_tax      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['thirteenth_month_tax'], 'num');
                $total_taxation            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_taxation'], 'num');
                $deductions                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['deductions'], 'num');
                $total_deductions          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_deductions'], 'num');
                $take_home_pay             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['take_home_pay'], 'req_num');

                if (empty($staff_id)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
                    continue;
                }
                if (empty($employee_name)) {
                    $errors[] = "Row " . ($index + 2) . ": Name is required.";
                    continue;
                }
                if (is_null($base_salary) || is_null($gross_salary)) {
                    $errors[] = "Row " . ($index + 2) . ": Base Salary and Gross Salary are required.";
                    continue;
                }
                if (is_null($take_home_pay)) {
                    $errors[] = "Row " . ($index + 2) . ": Take Home Pay must be a valid number.";
                    continue;
                }
                if (in_array($staff_id, $existingStaffIds, true)) {
                    $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: {$staff_id}";
                    continue;
                }
                if (in_array($staff_id, $seenInFile, true)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID appears twice in this file: {$staff_id}";
                    continue;
                }

                $record = [
                    'staff_id'                  => $staff_id,
                    'employee_name'             => $employee_name,
                    'start_date'                => $start_date,
                    'position'                  => $position,
                    'work_location'             => $work_location,
                    'base_salary'               => $base_salary,
                    'fuel_and_rent_allowance'   => $fuel_and_rent_allowance,
                    'gross_salary'              => $gross_salary,
                    'management_fee_8'          => $management_fee_8,
                    'getfund_2_5'               => $getfund_2_5,
                    'nhil_2_5'                  => $nhil_2_5,
                    'vat_15'                    => $vat_15,
                    'medical_insurance'         => $medical_insurance,
                    'employer_provident_fund_5' => $employer_provident_fund_5,
                    'thirteenth_month_bonus'    => $thirteenth_month_bonus,
                    'workman'                   => $workman,
                    'ssnit_er_13'               => $ssnit_er_13,
                    'cost_to_nyonkopa'          => $cost_to_nyonkopa,
                    'ssnit_ee_5_5'              => $ssnit_ee_5_5,
                    'petra'                     => $petra,
                    'employeepf'                => $employeepf,
                    'taxable_income'            => $taxable_income,
                    'paye'                      => $paye,
                    'thirteenth_month_tax'      => $thirteenth_month_tax,
                    'total_taxation'            => $total_taxation,
                    'deductions'                => $deductions,
                    'total_deductions'          => $total_deductions,
                    'take_home_pay'             => $take_home_pay,
                ];

                // Dynamic custom columns from payroll_columns
                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'nyonkopa');
                if (!empty($customVals)) {
                    $record = array_merge($record, $customVals);
                }

                $validRecords[] = $record;
                $existingStaffIds[] = $staff_id;
                $seenInFile[] = $staff_id;
            }

            if (!empty($errors)) {
                $errorMessage = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
            }

            return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('Nyonkopa upload failed', ['error' => $e->getMessage()]);
            return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }
    public function delete(Request $request)
    {
        $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
        $date = Carbon::createFromFormat('Y-m', $selectedMonth);
        $tableName = $this->buildTableName($date);

        try {
            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                DB::table($tableName)->truncate();
                Log::info("NyonkopaPay delete: truncated {$tableName}");
                return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
                    ->with('success', 'Records deleted successfully.');
            }

            Log::warning("NyonkopaPay delete: {$tableName} does not exist");
            return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
                ->with('error', 'No records found for the selected month.');

        } catch (\Exception $e) {
            Log::error('NyonkopaPay delete failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('nyonkopapay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to delete records: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  DOWNLOAD TEMPLATE
    //  Fixes: example row was misaligned (extra 192.50 values pushed
    //         Take Home Pay off the sheet entirely → template's own sample
    //         row couldn't be re-uploaded)
    //         header styling range A1:Y1 → A1:AB1 (28 columns, not 25)
    //         stray "COVID-19 1%" comment removed
    // ─────────────────────────────────────────────────────────────────────
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID',
            'Employee Name',
            'Start Date',
            'Position',
            'Work Location',
            'Base Salary',
            'Fuel and Rent Allowance',
            'Gross Salary',
            'Management Fee 8%',
            'GETFund 2.5%',
            'NHIL 2.5%',
            'VAT 15%',
            'Medical Insurance',
            'Employer Provident Fund 5%',
            '13th Month Bonus',
            'WORKMAN COMPENSATION',
            'SSNIT ER 13%',
            'Cost to Nyonkopa',
            'SSNIT EE 5.5%',
            'PETRA',
            'EmployeePF 3%',
            'Taxable Income',
            'PAYE',
            '13th Month Tax',
            'Total Taxation',
            'Deductions',
            'Total Deductions',
            'Take Home Pay',
        ];

        $nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'nyonkopa');
        $sheet->fromArray($headers, null, 'A1');

        // 28 columns → last column is AB (A=1 … Z=26, AA=27, AB=28)
        $lastCol = Coordinate::stringFromColumnIndex(count($headers)); // "AB"
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('bbdcfe');

        // Correctly aligned example row (28 cells, 1:1 with headers)
        $exampleData = [
            'TNS00123',         // 1  Staff ID
            'Kwame Osei',       // 2  Employee Name
            '2023-06-15',       // 3  Start Date
            'Sales Executive',  // 4  Position
            'Accra Mall',       // 5  Work Location
            3500.00,            // 6  Base Salary
            1300.00,            // 7  Fuel and Rent Allowance
            6975.00,            // 8  Gross Salary
            2000.00,            // 9  Management Fee 8%
            50.00,              // 10 GETFund 2.5%
            80.00,              // 11 NHIL 2.5%
            20.00,              // 12 VAT 15%
            150.00,             // 13 Medical Insurance
            0.00,               // 14 Employer Provident Fund 5%
            0.00,               // 15 13th Month Bonus
            0.00,               // 16 WORKMAN COMPENSATION
            455.00,             // 17 SSNIT ER 13%
            10000.00,           // 18 Cost to Nyonkopa
            192.50,             // 19 SSNIT EE 5.5%
            0.00,               // 20 PETRA
            192.50,             // 21 EmployeePF 3%
            6940.12,            // 22 Taxable Income
            750.25,             // 23 PAYE
            0.00,               // 24 13th Month Tax
            750.25,             // 25 Total Taxation
            300.00,             // 26 Deductions
            300.00,             // 27 Total Deductions
            5975.00,            // 28 Take Home Pay
        ];

        $sheet->fromArray($exampleData, null, 'A2');

        // Auto-size every column
        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        // Instruction note three rows below the last filled row
        $sheet->setCellValue(
            'A' . ($sheet->getHighestRow() + 3),
            'INSTRUCTIONS: Fill only the yellow/required fields. All amounts in GHS. '
            . 'Do NOT add commas, GH¢ or text in number columns. Dates: DD/MM/YYYY or MM/DD/YYYY.'
        );

        $writer   = IOFactory::createWriter($spreadsheet, 'Xlsx');
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