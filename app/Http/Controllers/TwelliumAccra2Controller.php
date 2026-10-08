<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TwelliumAccra2Controller extends Controller
{
    private function cleanMonthString(Carbon $date): string
    {
        return strtolower($date->format('F')) . $date->format('y');
    }

    private function getColumnLetter(int $index): string
    {
        $col = '';
        do {
            $col = chr(65 + ($index % 26)) . $col;
            $index = intdiv($index, 26) - 1;
        } while ($index >= 0);
        return $col;
    }

    private function toDecimal($val): float
    {
        if ($val === null || $val === '') {
            return 0.00;
        }
        $cleaned = str_replace([',', ' ', 'GHS', 'GH¢'], '', (string) $val);
        return is_numeric($cleaned) ? (float) $cleaned : 0.00;
    }

    private function toInt($val): ?int
    {
        if ($val === null || $val === '') {
            return null;
        }
        $cleaned = str_replace([',', ' '], '', (string) $val);
        return is_numeric($cleaned) ? (int) $cleaned : null;
    }

    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $cleanMonth = $this->cleanMonthString($date);

        $basicTable = "twelliumaccrabasic{$cleanMonth}";
        $overtimeTable = "twelliumaccraovertime{$cleanMonth}";

        $basicExists = Schema::hasTable($basicTable);
        $overtimeExists = Schema::hasTable($overtimeTable);

        if (!$basicExists || !$overtimeExists) {
            return Inertia::render('TwelliumAccra2/Index', [
                'records'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'searchCount'    => null,
                'hasData'        => false,
                'summary'        => [
                    'totalBasic'    => 0,
                    'totalOvertime' => 0,
                    'totalTakeHome' => 0,
                    'totalCtc'      => 0,
                ],
                'flash'          => [
                    'success' => session('success'),
                    'error'   => $basicExists || $overtimeExists ? null : "No payroll data found for {$date->format('F Y')}. Please upload both Basic and Overtime files.",
                ],
            ]);
        }

        $query = DB::table("{$basicTable} as b")
            ->leftJoin("{$overtimeTable} as o", 'b.clock_in_id', '=', 'o.clock_in_id');

        if (Schema::hasTable('twelliumprimaryaccra')) {
            $query->leftJoin('twelliumprimaryaccra as p', 'b.clock_in_id', '=', 'p.STAFFID')
                ->select([
                    DB::raw('COALESCE(p.SBPSTAFFID, b.clock_in_id) AS sbpstaffid'),
                    'b.clock_in_id',
                    'b.name',
                    'b.designation',
                    'b.department',
                    'p.STARTDATE as startdate',
                    'b.expecteddays as expected_days',
                    'b.actual_days',
                    'b.basic_salary',
                    'b.absent_deduction',
                    'b.snnit_13',
                    'b.EOY as eoy',
                    'b.wmk_2',
                    'b.ppe',
                    'b.medical6 as medical_6',
                    'b.refund',
                    'b.staffcost as staff_cost',
                    'b.mgt_fee',
                    'b.nhil_getfund_5',
                    'b.covid_1',
                    'b.vat_15',
                    'b.ctc',
                    'b.taxable_income',
                    'b.paye',
                    'b.take_home',
                    'o.basic_salary as basic_salaryover',
                    'o.weekday_hours',
                    'o.num_saturdays',
                    'o.saturday_hours',
                    'o.num_sundays_holidays',
                    'o.sundays_holiday_overtime',
                    'o.overtimerefund',
                    'o.weekdays_overtime',
                    'o.saturdays_overtime',
                    'o.sundays_holidays_overtime',
                    'o.total_overtime'
                ]);
        } else {
            $query->select([
                'b.clock_in_id as sbpstaffid',
                'b.clock_in_id',
                'b.name',
                'b.designation',
                'b.department',
                DB::raw('NULL as startdate'),
                'b.expecteddays as expected_days',
                'b.actual_days',
                'b.basic_salary',
                'b.absent_deduction',
                'b.snnit_13',
                'b.EOY as eoy',
                'b.wmk_2',
                'b.ppe',
                'b.medical6 as medical_6',
                'b.refund',
                'b.staffcost as staff_cost',
                'b.mgt_fee',
                'b.nhil_getfund_5',
                'b.covid_1',
                'b.vat_15',
                'b.ctc',
                'b.taxable_income',
                'b.paye',
                'b.take_home',
                'o.basic_salary as basic_salaryover',
                'o.weekday_hours',
                'o.num_saturdays',
                'o.saturday_hours',
                'o.num_sundays_holidays',
                'o.sundays_holiday_overtime',
                'o.overtimerefund',
                'o.weekdays_overtime',
                'o.saturdays_overtime',
                'o.sundays_holidays_overtime',
                'o.total_overtime'
            ]);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('b.clock_in_id', 'like', "%{$search}%")
                  ->orWhere('b.name', 'like', "%{$search}%")
                  ->orWhere('b.department', 'like', "%{$search}%")
                  ->orWhere('b.designation', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'name');
        $order = strtolower($request->input('order', 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortMap = [
            'name'           => 'b.name',
            'clock_in_id'    => 'b.clock_in_id',
            'department'     => 'b.department',
            'designation'    => 'b.designation',
            'basic_salary'   => 'b.basic_salary',
            'take_home'      => 'b.take_home',
            'total_overtime' => 'o.total_overtime',
            'ctc'            => 'b.ctc',
        ];

        $sortColumn = $sortMap[$sort] ?? 'b.name';
        $query->orderBy($sortColumn, $order);

        $records = $query->paginate(20)->withQueryString();
        $totalRecords = DB::table($basicTable)->count();
        $searchCount = $search !== '' ? $records->total() : null;

        // Overall summary totals
        $summary = [
            'totalBasic'    => (float) (DB::table($basicTable)->sum('basic_salary') ?? 0),
            'totalOvertime' => (float) (DB::table($overtimeTable)->sum('total_overtime') ?? 0),
            'totalTakeHome' => (float) (DB::table($basicTable)->sum('take_home') ?? 0),
            'totalCtc'      => (float) (DB::table($basicTable)->sum('ctc') ?? 0),
        ];

        return Inertia::render('TwelliumAccra2/Index', [
            'records'        => $records,
            'selectedMonth'  => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords'   => $totalRecords,
            'searchCount'    => $searchCount,
            'hasData'        => true,
            'summary'        => $summary,
            'flash'          => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'month'         => ['required', 'date_format:Y-m'],
            'basic_file'    => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'overtime_file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
        ]);

        $month = $request->input('month');
        $date = Carbon::createFromFormat('Y-m', $month);
        $cleanMonth = $this->cleanMonthString($date);

        $basicTable = "twelliumaccrabasic{$cleanMonth}";
        $overtimeTable = "twelliumaccraovertime{$cleanMonth}";

        try {
            // Load spreadsheets
            $basicFile = $request->file('basic_file');
            $overtimeFile = $request->file('overtime_file');

            $basicSpreadsheet = IOFactory::load($basicFile->getRealPath());
            $basicData = $basicSpreadsheet->getActiveSheet()->toArray(null, true, true, true);

            $overtimeSpreadsheet = IOFactory::load($overtimeFile->getRealPath());
            $overtimeData = $overtimeSpreadsheet->getActiveSheet()->toArray(null, true, true, true);

            if (empty($basicData) || count($basicData) < 2) {
                return redirect()->back()->with('error', 'Basic payroll file is empty or missing data rows.');
            }
            if (empty($overtimeData) || count($overtimeData) < 2) {
                return redirect()->back()->with('error', 'Overtime payroll file is empty or missing data rows.');
            }

            // Remove headers
            $basicHeader = array_shift($basicData);
            $overtimeHeader = array_shift($overtimeData);

            // Re-create basic table
            DB::statement("DROP TABLE IF EXISTS `{$basicTable}`");
            DB::statement("CREATE TABLE `{$basicTable}` (
                `clock_in_id` VARCHAR(50) PRIMARY KEY,
                `name` VARCHAR(255) NULL,
                `designation` VARCHAR(150) NULL,
                `department` VARCHAR(255) NULL,
                `expecteddays` INT NULL,
                `actual_days` INT NULL,
                `basic_salary` DECIMAL(12,2) DEFAULT 0.00,
                `absent_deduction` DECIMAL(12,2) DEFAULT 0.00,
                `snnit_13` DECIMAL(12,2) DEFAULT 0.00,
                `EOY` DECIMAL(12,2) DEFAULT 0.00,
                `wmk_2` DECIMAL(12,2) DEFAULT 0.00,
                `ppe` DECIMAL(12,2) DEFAULT 0.00,
                `medical6` DECIMAL(12,2) DEFAULT 0.00,
                `refund` DECIMAL(12,2) DEFAULT 0.00,
                `staffcost` DECIMAL(12,2) DEFAULT 0.00,
                `mgt_fee` DECIMAL(12,2) DEFAULT 0.00,
                `nhil_getfund_5` DECIMAL(12,2) DEFAULT 0.00,
                `covid_1` DECIMAL(12,2) DEFAULT 0.00,
                `vat_15` DECIMAL(12,2) DEFAULT 0.00,
                `ctc` DECIMAL(12,2) DEFAULT 0.00,
                `taxable_income` DECIMAL(12,2) DEFAULT 0.00,
                `paye` DECIMAL(12,2) DEFAULT 0.00,
                `take_home` DECIMAL(12,2) DEFAULT 0.00
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Re-create overtime table
            DB::statement("DROP TABLE IF EXISTS `{$overtimeTable}`");
            DB::statement("CREATE TABLE `{$overtimeTable}` (
                `clock_in_id` VARCHAR(50) PRIMARY KEY,
                `name` VARCHAR(255) NULL,
                `designation` VARCHAR(255) NULL,
                `department` VARCHAR(255) NULL,
                `basic_salary` DECIMAL(12,2) DEFAULT 0.00,
                `weekday_hours` DECIMAL(8,2) DEFAULT 0.00,
                `num_saturdays` INT NULL,
                `saturday_hours` DECIMAL(8,2) DEFAULT 0.00,
                `num_sundays_holidays` INT NULL,
                `sundays_holiday_overtime` DECIMAL(8,2) DEFAULT 0.00,
                `overtimerefund` DECIMAL(12,2) DEFAULT 0.00,
                `weekdays_overtime` DECIMAL(12,2) DEFAULT 0.00,
                `saturdays_overtime` DECIMAL(12,2) DEFAULT 0.00,
                `sundays_holidays_overtime` DECIMAL(12,2) DEFAULT 0.00,
                `total_overtime` DECIMAL(12,2) DEFAULT 0.00
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Prepare basic records
            $basicRows = [];
            $seenBasicIds = [];
            foreach ($basicData as $row) {
                $clockInId = trim((string) ($row['A'] ?? ''));
                if ($clockInId === '' || isset($seenBasicIds[$clockInId])) {
                    continue;
                }
                $seenBasicIds[$clockInId] = true;

                $basicRows[] = [
                    'clock_in_id'      => $clockInId,
                    'name'             => trim((string) ($row['B'] ?? '')),
                    'designation'      => trim((string) ($row['C'] ?? '')),
                    'department'       => trim((string) ($row['D'] ?? '')),
                    'expecteddays'     => $this->toInt($row['E'] ?? null),
                    'actual_days'      => $this->toInt($row['F'] ?? null),
                    'basic_salary'     => $this->toDecimal($row['G'] ?? 0),
                    'absent_deduction' => $this->toDecimal($row['H'] ?? 0),
                    'snnit_13'         => $this->toDecimal($row['I'] ?? 0),
                    'EOY'              => $this->toDecimal($row['J'] ?? 0),
                    'wmk_2'            => $this->toDecimal($row['K'] ?? 0),
                    'ppe'              => $this->toDecimal($row['L'] ?? 0),
                    'medical6'         => $this->toDecimal($row['M'] ?? 0),
                    'refund'           => $this->toDecimal($row['N'] ?? 0),
                    'staffcost'        => $this->toDecimal($row['O'] ?? 0),
                    'mgt_fee'          => $this->toDecimal($row['P'] ?? 0),
                    'nhil_getfund_5'   => $this->toDecimal($row['Q'] ?? 0),
                    'covid_1'          => $this->toDecimal($row['R'] ?? 0),
                    'vat_15'           => $this->toDecimal($row['S'] ?? 0),
                    'ctc'              => $this->toDecimal($row['T'] ?? 0),
                    'taxable_income'   => $this->toDecimal($row['U'] ?? 0),
                    'paye'             => $this->toDecimal($row['V'] ?? 0),
                    'take_home'        => $this->toDecimal($row['W'] ?? 0),
                ];
            }

            // Prepare overtime records
            $overtimeRows = [];
            $seenOvertimeIds = [];
            foreach ($overtimeData as $row) {
                $clockInId = trim((string) ($row['A'] ?? ''));
                if ($clockInId === '' || isset($seenOvertimeIds[$clockInId])) {
                    continue;
                }
                $seenOvertimeIds[$clockInId] = true;

                $overtimeRows[] = [
                    'clock_in_id'              => $clockInId,
                    'name'                     => trim((string) ($row['B'] ?? '')),
                    'designation'              => trim((string) ($row['C'] ?? '')),
                    'department'               => trim((string) ($row['D'] ?? '')),
                    'basic_salary'             => $this->toDecimal($row['E'] ?? 0),
                    'weekday_hours'            => $this->toDecimal($row['F'] ?? 0),
                    'num_saturdays'            => $this->toInt($row['G'] ?? null),
                    'saturday_hours'           => $this->toDecimal($row['H'] ?? 0),
                    'num_sundays_holidays'     => $this->toInt($row['I'] ?? null),
                    'sundays_holiday_overtime' => $this->toDecimal($row['J'] ?? 0),
                    'overtimerefund'           => $this->toDecimal($row['K'] ?? 0),
                    'weekdays_overtime'        => $this->toDecimal($row['L'] ?? 0),
                    'saturdays_overtime'       => $this->toDecimal($row['M'] ?? 0),
                    'sundays_holidays_overtime'=> $this->toDecimal($row['N'] ?? 0),
                    'total_overtime'           => $this->toDecimal($row['O'] ?? 0),
                ];
            }

            // Insert chunks
            foreach (array_chunk($basicRows, 100) as $chunk) {
                DB::table($basicTable)->insert($chunk);
            }
            foreach (array_chunk($overtimeRows, 100) as $chunk) {
                DB::table($overtimeTable)->insert($chunk);
            }

            return redirect()->route('twelliumaccra2.index', ['month' => $month])
                ->with('success', "Payroll data for {$date->format('F Y')} processed successfully! Inserted " . count($basicRows) . " Basic and " . count($overtimeRows) . " Overtime records.");

        } catch (\Throwable $e) {
            Log::error('Twellium Accra 2 Upload Exception: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error processing files: ' . $e->getMessage());
        }
    }

    public function downloadTemplate(string $type)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($type === 'basic') {
            $headers = [
                'CLOCK_IN_ID', 'NAME', 'DESIGNATION', 'DEPARTMENT', 'EXPECTED_DAYS', 'ACTUAL_DAYS', 'BASIC_SALARY',
                'ABSENT_DEDUCTION', 'SNNIT_13', 'EOY', 'WMK_2', 'PPE', 'MEDICAL_6', 'REFUND', 'STAFF_COST',
                'MGT_FEE', 'NHIL_GETFUND_5', 'COVID_1', 'VAT_15', 'CTC', 'TAXABLE_INCOME',
                'PAYE', 'TAKE_HOME'
            ];
            $filename = 'twellium_accra_basic_template.xlsx';
            $sheet->setTitle('Basic Payroll');
        } else {
            $headers = [
                'CLOCK_IN_ID', 'NAME', 'DESIGNATION', 'DEPARTMENT', 'BASIC_SALARY', 'WEEKDAY_HOURS', 'NUM_SATURDAYS',
                'SATURDAY_HOURS', 'NUM_SUNDAYS_HOLIDAYS', 'SUNDAYS_HOLIDAY_OVERTIME', 'OVERTIMEREFUND',
                'WEEKDAYS_OVERTIME', 'SATURDAYS_OVERTIME', 'SUNDAYS_HOLIDAYS_OVERTIME', 'TOTAL_OVERTIME'
            ];
            $filename = 'twellium_accra_overtime_template.xlsx';
            $sheet->setTitle('Overtime');
        }

        foreach ($headers as $index => $header) {
            $col = $this->getColumnLetter($index);
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->getFont()->setBold(true);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function export(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $cleanMonth = $this->cleanMonthString($date);

        $basicTable = "twelliumaccrabasic{$cleanMonth}";
        $overtimeTable = "twelliumaccraovertime{$cleanMonth}";

        if (!Schema::hasTable($basicTable) || !Schema::hasTable($overtimeTable)) {
            return redirect()->back()->with('error', "No data exists for {$date->format('F Y')} to export.");
        }

        $query = DB::table("{$basicTable} as b")
            ->leftJoin("{$overtimeTable} as o", 'b.clock_in_id', '=', 'o.clock_in_id');

        if (Schema::hasTable('twelliumprimaryaccra')) {
            $query->leftJoin('twelliumprimaryaccra as p', 'b.clock_in_id', '=', 'p.STAFFID')
                ->select([
                    DB::raw('COALESCE(p.SBPSTAFFID, b.clock_in_id) AS SBPSTAFFID'),
                    'b.clock_in_id',
                    'b.name',
                    'b.designation',
                    'b.department',
                    'p.STARTDATE',
                    'b.expecteddays AS expected_days',
                    'b.actual_days',
                    'b.basic_salary',
                    'b.absent_deduction',
                    'b.snnit_13',
                    'b.EOY',
                    'b.wmk_2',
                    'b.ppe',
                    'b.medical6 AS medical_6',
                    'b.refund',
                    'b.staffcost AS staff_cost',
                    'b.mgt_fee',
                    'b.nhil_getfund_5',
                    'b.covid_1',
                    'b.vat_15',
                    'b.ctc',
                    'b.taxable_income',
                    'b.paye',
                    'b.take_home',
                    'o.basic_salary AS basic_salaryover',
                    'o.weekday_hours',
                    'o.num_saturdays',
                    'o.saturday_hours',
                    'o.num_sundays_holidays',
                    'o.sundays_holiday_overtime',
                    'o.overtimerefund',
                    'o.weekdays_overtime',
                    'o.saturdays_overtime',
                    'o.sundays_holidays_overtime',
                    'o.total_overtime'
                ]);
        } else {
            $query->select([
                'b.clock_in_id AS SBPSTAFFID',
                'b.clock_in_id',
                'b.name',
                'b.designation',
                'b.department',
                DB::raw('NULL AS STARTDATE'),
                'b.expecteddays AS expected_days',
                'b.actual_days',
                'b.basic_salary',
                'b.absent_deduction',
                'b.snnit_13',
                'b.EOY',
                'b.wmk_2',
                'b.ppe',
                'b.medical6 AS medical_6',
                'b.refund',
                'b.staffcost AS staff_cost',
                'b.mgt_fee',
                'b.nhil_getfund_5',
                'b.covid_1',
                'b.vat_15',
                'b.ctc',
                'b.taxable_income',
                'b.paye',
                'b.take_home',
                'o.basic_salary AS basic_salaryover',
                'o.weekday_hours',
                'o.num_saturdays',
                'o.saturday_hours',
                'o.num_sundays_holidays',
                'o.sundays_holiday_overtime',
                'o.overtimerefund',
                'o.weekdays_overtime',
                'o.saturdays_overtime',
                'o.sundays_holidays_overtime',
                'o.total_overtime'
            ]);
        }

        $query->orderBy('b.department', 'ASC')->orderBy('b.name', 'ASC');
        $records = $query->get();

        if ($records->isEmpty()) {
            return redirect()->back()->with('error', "No records found for {$date->format('F Y')}.");
        }

        $spreadsheet = new Spreadsheet();

        // Group data by department
        $dataByDept = [];
        foreach ($records as $row) {
            $dept = trim((string) ($row->department ?? ''));
            if ($dept === '' || strtolower($dept) === 'unknown') {
                $dept = 'No Department';
            }
            $dataByDept[$dept][] = (array) $row;
        }
        ksort($dataByDept);

        $headers = [
            'SBPSTAFFID', 'CLOCK_IN_ID', 'NAME', 'DESIGNATION', 'DEPARTMENT', 'STARTDATE', 'EXPECTED_DAYS', 'ACTUAL_DAYS', 'BASIC_SALARY',
            'ABSENT_DEDUCTION', 'SNNIT_13', 'EOY', 'WMK_2', 'PPE', 'MEDICAL_6', 'REFUND', 'STAFF_COST', 'MGT_FEE',
            'NHIL_GETFUND_5', 'COVID_1', 'VAT_15', 'CTC', 'TAXABLE_INCOME', 'PAYE', 'TAKE_HOME', 'BASIC_SALARYOVER',
            'WEEKDAY_HOURS', 'NUM_SATURDAYS', 'SATURDAY_HOURS', 'NUM_SUNDAYS_HOLIDAYS', 'SUNDAYS_HOLIDAY_OVERTIME',
            'OVERTIMEREFUND', 'WEEKDAYS_OVERTIME', 'SATURDAYS_OVERTIME', 'SUNDAYS_HOLIDAYS_OVERTIME', 'TOTAL_OVERTIME'
        ];

        $numericIndices = [6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35];
        $fieldKeys = [
            'SBPSTAFFID', 'clock_in_id', 'name', 'designation', 'department', 'STARTDATE',
            'expected_days', 'actual_days', 'basic_salary', 'absent_deduction', 'snnit_13',
            'EOY', 'wmk_2', 'ppe', 'medical_6', 'refund', 'staff_cost', 'mgt_fee',
            'nhil_getfund_5', 'covid_1', 'vat_15', 'ctc', 'taxable_income', 'paye',
            'take_home', 'basic_salaryover', 'weekday_hours', 'num_saturdays',
            'saturday_hours', 'num_sundays_holidays', 'sundays_holiday_overtime',
            'overtimerefund', 'weekdays_overtime', 'saturdays_overtime',
            'sundays_holidays_overtime', 'total_overtime'
        ];

        $sheetIndex = 0;
        foreach ($dataByDept as $deptName => $employees) {
            $sheet = $sheetIndex === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $safeTitle = preg_replace('/[\/:*?"<>|]/', '_', substr($deptName, 0, 31));
            $sheet->setTitle($safeTitle);
            $sheetIndex++;

            // Headers
            foreach ($headers as $colIdx => $hdr) {
                $col = $this->getColumnLetter($colIdx);
                $sheet->setCellValue($col . '1', $hdr);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
            }

            $totals = array_fill_keys($numericIndices, 0.0);
            $currentRow = 2;

            foreach ($employees as $data) {
                foreach ($fieldKeys as $colIdx => $key) {
                    $col = $this->getColumnLetter($colIdx);
                    $val = $data[$key] ?? '';
                    $sheet->setCellValue($col . $currentRow, $val);

                    if (in_array($colIdx, $numericIndices)) {
                        $totals[$colIdx] += is_numeric($val) ? (float) $val : 0.0;
                    }
                }
                $currentRow++;
            }

            // Totals row
            $sheet->setCellValue($this->getColumnLetter(0) . $currentRow, 'Totals');
            $sheet->getStyle($this->getColumnLetter(0) . $currentRow)->getFont()->setBold(true)->setSize(11);

            foreach ($numericIndices as $colIdx) {
                $col = $this->getColumnLetter($colIdx);
                $sheet->setCellValue($col . $currentRow, $totals[$colIdx]);
                $sheet->getStyle($col . $currentRow)->getFont()->setBold(true)->setSize(11);
            }

            for ($i = 0; $i < count($headers); $i++) {
                $sheet->getColumnDimension($this->getColumnLetter($i))->setAutoSize(true);
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename=\"twellium_accra_payroll_{$cleanMonth}.xlsx\"");
        header('Cache-Control: max-age=0');
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function deleteMonth(Request $request)
    {
        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $month = $request->input('month');
        $date = Carbon::createFromFormat('Y-m', $month);
        $cleanMonth = $this->cleanMonthString($date);

        $basicTable = "twelliumaccrabasic{$cleanMonth}";
        $overtimeTable = "twelliumaccraovertime{$cleanMonth}";

        DB::statement("DROP TABLE IF EXISTS `{$basicTable}`");
        DB::statement("DROP TABLE IF EXISTS `{$overtimeTable}`");

        return redirect()->route('twelliumaccra2.index', ['month' => $month])
            ->with('success', "Payroll tables for {$date->format('F Y')} deleted successfully.");
    }
}
