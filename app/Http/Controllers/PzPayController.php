<?php

namespace App\Http\Controllers;

use App\Models\Pzpay;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class PzPayController extends Controller
{
    private const DEPARTMENT_OPTIONS = [
        'admin'             => 'ADMIN',
        'it'                => 'IT',
        'finance'           => 'FINANCE',
        'marketing'         => 'MARKETING',
        'sales_supervisors' => 'SALES SUPERVISORS',
        'sales_supply'      => 'SALES-SUPPLY',
        'merchandisers'     => 'MECHANDISERS',
        'omsr'              => 'OMSR',
        'standard'          => 'Standard Payroll Template',
    ];

    public function index(Request $request)
    {
        $request->validate([
            'month'      => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
            'department' => ['nullable', Rule::in(array_keys(self::DEPARTMENT_OPTIONS))],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'pz' . strtolower($date->format('F')) . $date->format('y');

        $department = $request->input('department');   // '' or 'admin' etc.

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('Pzpay/Index', [
                'records'             => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth'       => $month,
                'formattedMonth'      => $date->format('F Y'),
                'totalRecords'        => 0,
                'searchCount'         => null,
                'departments'         => self::DEPARTMENT_OPTIONS,
                'selectedDepartment'  => $department ?? '',
                'availableDepartments'=> [],
                'flash'               => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        $hasDeptColumn = DB::getSchemaBuilder()->hasColumn($tableName, 'department');

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
            ->select(
                'tp.staffid',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept) as shop'),
                $hasDeptColumn ? 'tp.department' : DB::raw('NULL as department'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay'
            );

        // ── Department filter ───────────────────────────────────────────
        if ($hasDeptColumn && $department) {
            $query->where('tp.department', $department);
        }

        // ── Search ──────────────────────────────────────────────────────
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffid', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // ── Sort ────────────────────────────────────────────────────────
        $sort  = $request->input('sort', 'staffid');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'staffid'    => 'tp.staffid',
            'name'       => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
            'shop'       => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept)'),
            'department' => $hasDeptColumn ? 'tp.department' : 'tp.staffid',
            'bank'       => 'bd.bank',
            'account'    => 'bd.account',
            'code'       => 'bd.code',
            'branch'     => 'bd.branch',
            'netpay'     => 'tp.netpay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.staffid', $order);

        // clone before paginate so counts aren't skewed by limit/offset
        $countQuery = clone $query;

        $perPage = 10;
        $records = $query->paginate($perPage)->appends($request->query());

        // ── Distinct payroll departments already in this month's table ──
        $availableDepartments = [];
        if ($hasDeptColumn) {
            $availableDepartments = DB::table($tableName)
                ->whereNotNull('department')
                ->where('department', '!=', '')
                ->distinct()
                ->orderBy('department')
                ->pluck('department')
                ->toArray();
        }

        $totalRecords = DB::table($tableName)->count();
        $hasFilters   = !empty($search) || !empty($department);
        $searchCount  = $hasFilters ? $countQuery->count() : null;

        return Inertia::render('Pzpay/Index', [
            'records'              => $records,
            'selectedMonth'        => $month,
            'formattedMonth'       => $date->format('F Y'),
            'totalRecords'         => $totalRecords,
            'searchCount'          => $searchCount,
            'departments'          => self::DEPARTMENT_OPTIONS,
            'selectedDepartment'   => $department ?? '',
            'availableDepartments' => $availableDepartments,
            'flash'                => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function export(Request $request)
    {
        try {
            $request->validate([
                'month'      => ['required', 'date_format:Y-m'],
                'department' => ['nullable', Rule::in(array_keys(self::DEPARTMENT_OPTIONS))],
            ]);

            $month = $request->input('month');
            $date  = Carbon::createFromFormat('Y-m', $month);
            $tableName = 'pz' . strtolower($date->format('F')) . $date->format('y');

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
            }

            $hasDeptColumn = DB::getSchemaBuilder()->hasColumn($tableName, 'department');
            $department    = $request->input('department');

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
                ->select(
                    'tp.staffid as staff_id',
                    DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
                    DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept) as shop'),
                    $hasDeptColumn ? 'tp.department' : DB::raw('NULL as department'),
                    'bd.bank',
                    'bd.account',
                    'bd.code',
                    'bd.branch',
                    'tp.netpay as take_home_pay'
                );

            if ($hasDeptColumn && $department) {
                $query->where('tp.department', $department);
            }

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.staffid', 'like', "%{$search}%")
                      ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                      ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept) LIKE ?', ["%{$search}%"])
                      ->orWhere('bd.bank', 'like', "%{$search}%");
                });
            }

            $sort  = $request->input('sort', 'staffid');
            $order = $request->input('order', 'asc');
            $sortMap = [
                'staffid'    => 'tp.staffid',
                'name'       => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
                'shop'       => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.dept)'),
                'department' => $hasDeptColumn ? 'tp.department' : 'tp.staffid',
                'bank'       => 'bd.bank',
                'account'    => 'bd.account',
                'code'       => 'bd.code',
                'branch'     => 'bd.branch',
                'netpay'     => 'tp.netpay',
            ];
            $query->orderBy($sortMap[$sort] ?? 'tp.staffid', $order);

            $records = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['Staff ID', 'Payroll Dept', 'Name', 'Department', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Net Pay'];
            $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            foreach ($records as $r) {
                $deptLabel = $r->department
                    ? (self::DEPARTMENT_OPTIONS[$r->department] ?? $r->department)
                    : '';

                $sheet->setCellValue("A{$row}", $r->staff_id);
                $sheet->setCellValue("B{$row}", $deptLabel);
                $sheet->setCellValue("C{$row}", $r->name);
                $sheet->setCellValue("D{$row}", $r->shop ?? '');
                $sheet->setCellValue("E{$row}", $r->bank ?? '');
                $sheet->setCellValueExplicit("F{$row}", $r->account ?? '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("G{$row}", $r->code ?? '', DataType::TYPE_STRING);
                $sheet->setCellValue("H{$row}", $r->branch ?? '');
                $sheet->setCellValue("I{$row}", ($r->take_home_pay !== null && $r->take_home_pay !== '') ? number_format((float)$r->take_home_pay, 2, '.', '') : '');
                $row++;
            }

            $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
            $sheet->getStyle('A1:I1')->getFont()->setBold(true);
            $sheet->getStyle('A1:I1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('bbdcfe');
            foreach (range('A', 'I') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

            $writer = new Xlsx($spreadsheet);
            $filename = "pz_pay_{$month}" . ($department ? "_{$department}" : '') . ".xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (\Exception $e) {
            Log::error("PzPay export failed: " . $e->getMessage());
            return response()->json(['error' => 'Export failed'], 500);
        }
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file'       => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:2048'],
            'month'      => ['required', 'date_format:Y-m'],
            'department' => ['required', Rule::in(array_keys(self::DEPARTMENT_OPTIONS))],
        ]);

        $selectedMonth = $request->input('month');
        session(['selected_month' => $selectedMonth]);

        try {
            $department = $request->input('department');
            $date       = Carbon::createFromFormat('Y-m', $selectedMonth);
            $tableName  = 'pz' . strtolower($date->format('F')) . $date->format('y');

            PzPay::createTableIfNotExists($tableName);
            $this->ensureDepartmentColumn($tableName);

            $columnMap = $this->getDepartmentColumnMap($department);
            $sheet     = $this->resolveWorksheet($request->file('file')->getRealPath(), $department);

            if (!$sheet) {
                return redirect()->route('pzpay.index', ['month' => $selectedMonth])
                    ->with('error', 'Could not open the uploaded workbook.');
            }

            $rows = $sheet->toArray();

            $headerRowIndex = $this->findHeaderRow($rows);
            if ($headerRowIndex === null) {
                return redirect()->route('pzpay.index', ['month' => $selectedMonth])
                    ->with('error', 'Could not find a "STAFF ID" header row in the selected sheet.');
            }

            $headerRow = $rows[$headerRowIndex] ?? [];
            $dynamicHeaderMap = $this->buildDynamicHeaderMap($headerRow);

            $dataRows  = array_slice($rows, $headerRowIndex + 1);
            $rowOffset = $headerRowIndex + 2;

            $existingStaffIds = DB::table($tableName)->pluck('staffid')->toArray();
            $validRecords     = [];
            $errors           = [];

            foreach ($dataRows as $i => $row) {
                $rowNumber = $rowOffset + $i;

                // 1. Base mapping from predefined department column offsets
                $data = $this->mapRow($row, $columnMap);

                // 2. Dynamic AI/header dictionary extraction (extracts by matching column headers dynamically)
                $dynamicData = $this->extractRowByDynamicHeaders($row, $dynamicHeaderMap);
                foreach ($dynamicData as $fKey => $fVal) {
                    if ($fVal !== null && $fVal !== '') {
                        $data[$fKey] = $fVal;
                    }
                }

                // 3. Custom columns from PayrollColumn registry
                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'pz');
                if (!empty($customVals)) {
                    $data = array_merge($data, $customVals);
                }

                // 4. Standard PayrollColumn mapping
                $allMapped = \App\Models\PayrollColumn::mapRowHeadersToDb($row, $headerRow, 'pz');
                foreach ($allMapped as $dbCol => $val) {
                    if ($dbCol !== 'staffid' && $dbCol !== 'name' && $val !== null && $val !== '') {
                        $data[$dbCol] = $val;
                    }
                }

                $staffid = isset($data['staffid']) ? trim((string)$data['staffid']) : null;
                $name    = isset($data['name']) ? trim((string)$data['name']) : null;

                if (($staffid === null || $staffid === '') && ($name === null || $name === '')) {
                    continue;
                }

                if ($staffid === null || $staffid === '') {
                    $errors[] = "Row {$rowNumber}: Staff ID is required.";
                    continue;
                }

                if ($name === null || $name === '') {
                    $errors[] = "Row {$rowNumber}: Name is required.";
                    continue;
                }

                $basic     = $data['basic'] ?? null;
                $totalearn = $data['totalearn'] ?? null;
                $netpay    = $data['netpay'] ?? null;

                if ($basic === null || $totalearn === null) {
                    $errors[] = "Row {$rowNumber}: Basic Salary and Total Earnings are required.";
                    continue;
                }

                if ($netpay === null) {
                    $errors[] = "Row {$rowNumber}: Take Home Pay (Net Pay) must be a valid number.";
                    continue;
                }

                if (in_array($staffid, $existingStaffIds)) {
                    $errors[] = "Row {$rowNumber}: Duplicate Staff ID: {$staffid}";
                    continue;
                }

                // Resolve Dept / Location cleanly with fallbacks - NEVER NULL
                $deptValue = trim((string)($data['dept'] ?? $data['shop'] ?? $data['location'] ?? ''));
                if ($deptValue === '' && !empty($data['description'])) {
                    $deptValue = trim((string)$data['description']);
                }
                if ($deptValue === '') {
                    $deptValue = self::DEPARTMENT_OPTIONS[$department] ?? strtoupper(str_replace('_', ' ', $department));
                }

                $validRecords[] = [
                    'department'     => $department,
                    'staffid'        => $staffid,
                    'name'           => $name,
                    'dept'           => $deptValue,
                    'basic'          => is_numeric($data['basic'] ?? null) ? (float)$data['basic'] : 0.0,
                    'overtime'       => is_numeric($data['overtime'] ?? null) ? (float)$data['overtime'] : 0.0,
                    'incentive'      => is_numeric($data['incentive'] ?? null) ? (float)$data['incentive'] : 0.0,
                    'bonus'          => is_numeric($data['bonus'] ?? null) ? (float)$data['bonus'] : 0.0,
                    'other'          => is_numeric($data['other'] ?? null) ? (float)$data['other'] : 0.0,
                    '13month'        => is_numeric($data['13month'] ?? null) ? (float)$data['13month'] : 0.0,
                    'totalearn'      => is_numeric($data['totalearn'] ?? null) ? (float)$data['totalearn'] : 0.0,
                    'ssnitee'        => is_numeric($data['ssnitee'] ?? null) ? (float)$data['ssnitee'] : 0.0,
                    'overtimetaxt'   => is_numeric($data['overtimetaxt'] ?? null) ? (float)$data['overtimetaxt'] : 0.0,
                    'pf'             => is_numeric($data['pf'] ?? null) ? (float)$data['pf'] : 0.0,
                    'taxable'        => is_numeric($data['taxable'] ?? null) ? (float)$data['taxable'] : 0.0,
                    'ssniter'        => is_numeric($data['ssniter'] ?? null) ? (float)$data['ssniter'] : 0.0,
                    'petra'          => is_numeric($data['petra'] ?? null) ? (float)$data['petra'] : 0.0,
                    '13tax'          => is_numeric($data['13tax'] ?? null) ? (float)$data['13tax'] : 0.0,
                    'totaltaxdeduct' => is_numeric($data['totaltaxdeduct'] ?? null) ? (float)$data['totaltaxdeduct'] : 0.0,
                    'totaldeduct'    => is_numeric($data['totaldeduct'] ?? null) ? (float)$data['totaldeduct'] : 0.0,
                    'netpay'         => (float)$netpay,
                    'bank1'          => trim((string)($data['bank1'] ?? '')),
                    'account1'       => trim((string)($data['account1'] ?? '')),
                    'branch1'        => trim((string)($data['branch1'] ?? '')),
                    'ssnit1'         => trim((string)($data['ssnit1'] ?? '')),
                    'ghcard'         => trim((string)($data['ghcard'] ?? '')),
                ];

                $existingStaffIds[] = $staffid;
            }

            if (!empty($errors)) {
                Log::error("Upload errors ({$department}): " . implode(' | ', $errors));
                $errorMessage = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);

                return redirect()->route('pzpay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                $sanitizedRecords = array_map(function ($r) use ($department) {
                    if (empty($r['dept'])) {
                        $r['dept'] = self::DEPARTMENT_OPTIONS[$department] ?? 'MERCHANDISERS';
                    }
                    if (empty($r['department'])) {
                        $r['department'] = $department;
                    }
                    return $r;
                }, $validRecords);

                foreach (array_chunk($sanitizedRecords, 100) as $chunk) {
                    DB::table($tableName)->insert($chunk);
                }
                Log::info("Upload ({$department}): Inserted " . count($sanitizedRecords) . " records into {$tableName}");
            } else {
                Log::warning("Upload ({$department}): No valid records to insert into {$tableName}");
            }

            return redirect()->route('pzpay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');

        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('pzpay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

    public function delete(Request $request)
    {
        $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
        $date = Carbon::createFromFormat('Y-m', $selectedMonth);
        $tableName = 'pz' . strtolower($date->format('F')) . $date->format('y');

        try {
            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                DB::table($tableName)->truncate();
                Log::info("Delete: Truncated table {$tableName}");
                return redirect()->route('pzpay.index', ['month' => $selectedMonth])
                    ->with('success', 'Records deleted successfully.');
            }

            Log::warning("Delete: Table {$tableName} does not exist");
            return redirect()->route('pzpay.index', ['month' => $selectedMonth])
                ->with('error', 'No records found for the selected month.');
        } catch (\Exception $e) {
            Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('pzpay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to delete records: ' . $e->getMessage());
        }
    }

    public function downloadTemplate(Request $request)
    {
        $department = $request->input('department', 'standard');
        if (!array_key_exists($department, self::DEPARTMENT_OPTIONS)) {
            $department = 'standard';
        }

        $headers = $this->getTemplateHeaders($department);
        $nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'pz');

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');

        $lastColumn = $this->columnLetter(count($headers));
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('bbdcfe');
        foreach (range('A', $lastColumn) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->setCellValue(
            'A' . ($sheet->getHighestRow() + 2),
            'INSTRUCTIONS: Upload this sheet under the "' . self::DEPARTMENT_OPTIONS[$department]
            . '" department. All amounts in GHS. Do NOT add commas or currency symbols in number columns.'
        );

        $writer   = new Xlsx($spreadsheet);
        $fileName = 'Payroll_Upload_Template_' . ($department === 'standard' ? '' : strtoupper($department) . '_') . date('M-Y') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function ensureDepartmentColumn(string $tableName): void
    {
        if (!DB::getSchemaBuilder()->hasColumn($tableName, 'department')) {
            DB::statement("ALTER TABLE `{$tableName}` ADD COLUMN `department` VARCHAR(50) NULL AFTER `staffid`");
        }

        try {
            DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `dept` VARCHAR(255) NULL DEFAULT ''");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `totaltaxdeduct` DOUBLE NULL DEFAULT 0");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `totaldeduct` DOUBLE NULL DEFAULT 0");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `bank1` VARCHAR(255) NULL DEFAULT ''");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `account1` VARCHAR(255) NULL DEFAULT ''");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `branch1` VARCHAR(255) NULL DEFAULT ''");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `ssnit1` VARCHAR(255) NULL DEFAULT ''");
            DB::statement("ALTER TABLE `{$tableName}` MODIFY COLUMN `ghcard` VARCHAR(255) NULL DEFAULT ''");
        } catch (\Throwable $e) {
            // Ignored if table doesn't support or already modified
        }
    }

    /**
     * Dynamically map spreadsheet header labels to DB fields.
     */
    private function buildDynamicHeaderMap(array $headerRow): array
    {
        $map = [];
        $fieldPatterns = [
            'staffid' => [
                '/^staff\s*id$/i', '/^staffid$/i', '/^employee\s*id$/i', '/^emp\s*id$/i', '/^id$/i'
            ],
            'name' => [
                '/^employee\s*name$/i', '/^staff\s*name$/i', '/^full\s*name$/i', '/^name$/i', '/^employee$/i'
            ],
            'dept' => [
                '/^shop\s*[\/\-&]\s*location$/i', '/^shop\s*location$/i', '/^shop$/i', '/^location$/i',
                '/^dept\.?$/i', '/^department$/i', '/^shop\s*dept$/i', '/^station$/i'
            ],
            'description' => [
                '/^description$/i', '/^designation$/i', '/^position$/i', '/^role$/i', '/^job\s*title$/i'
            ],
            'basic' => [
                '/^basicsalary$/i', '/^basic\s*salary$/i', '/^basic$/i', '/^basic\s*pay$/i'
            ],
            'overtime' => [
                '/^overtime\s*earnings?$/i', '/^overtime$/i', '/^ot\s*earnings?$/i', '/^ot$/i'
            ],
            'incentive' => [
                '/^incentives?\s*earnings?$/i', '/^incentives?$/i'
            ],
            'bonus' => [
                '/^bonus\s*earnings?$/i', '/^bonus$/i', '/^performance\s*bonus$/i'
            ],
            'other' => [
                '/^other\s*earnings?$/i', '/^other$/i', '/^transport\s*allowance$/i', '/^transport$/i', '/^allowance$/i'
            ],
            '13month' => [
                '/^13th\s*earnings?$/i', '/^13th\s*month$/i', '/^13\s*month$/i', '/^13th$/i', '/^13th\s*salary$/i'
            ],
            'totalearn' => [
                '/^total\s*earnings?$/i', '/^gross\s*salary$/i', '/^gross\s*pay$/i', '/^gross$/i', '/^total\s*gross$/i', '/^totalearn$/i'
            ],
            'ssnitee' => [
                '/^ssnit\s*ee$/i', '/^ssnitee$/i', '/^ssnit\s*employee$/i', '/^tier\s*1\s*ee$/i', '/^ssnit\s*\(ee\)$/i'
            ],
            'overtimetaxt' => [
                '/^overtime\s*tax$/i', '/^overtimetax$/i', '/^ot\s*tax$/i'
            ],
            'pf' => [
                '/^s-?trust\s*fund/i', '/^provident\s*fund/i', '/^pf$/i', '/^staff\s*trust\s*fund/i', '/^trust\s*fund/i'
            ],
            'taxable' => [
                '/^taxable\s*income$/i', '/^taxableincome$/i', '/^taxable$/i', '/^taxable\s*pay$/i'
            ],
            'ssniter' => [
                '/^ssnit\s*er$/i', '/^ssniter$/i', '/^ssnit\s*employer$/i', '/^tier\s*1\s*er$/i', '/^ssnit\s*\(er\)$/i'
            ],
            'petra' => [
                '/^petra/i', '/^tier\s*2/i'
            ],
            '13tax' => [
                '/^13th\s*tax$/i', '/^13\s*tax$/i', '/^13th\s*month\s*tax$/i'
            ],
            'totaltaxdeduct' => [
                '/^total\s*tax\s*deductions?$/i', '/^totaltaxdeduct/i', '/^total\s*tax$/i', '/^paye$/i', '/^income\s*tax$/i'
            ],
            'totaldeduct' => [
                '/^total\s*deductions?$/i', '/^totaldeductions?$/i', '/^total\s*deduct$/i', '/^totaldeduct$/i'
            ],
            'netpay' => [
                '/^take\s*home\s*pay$/i', '/^takehomepay$/i', '/^net\s*pay$/i', '/^net\s*salary$/i', '/^netpay$/i', '/^amount\s*payable$/i'
            ],
            'bank1' => [
                '/^bank\s*name$/i', '/^bank$/i', '/^bank1$/i'
            ],
            'account1' => [
                '/^account\s*number$/i', '/^accountnumber$/i', '/^account\s*no\.?$/i', '/^account$/i', '/^acc\s*no\.?$/i', '/^acc\s*number$/i', '/^account1$/i'
            ],
            'branch1' => [
                '/^branch$/i', '/^bank\s*branch$/i', '/^branch1$/i'
            ],
            'ssnit1' => [
                '/^ssnit$/i', '/^ssnit\s*no\.?$/i', '/^ssnit\s*number$/i', '/^ssnit1$/i'
            ],
            'ghcard' => [
                '/^gh\s*card$/i', '/^ghcard$/i', '/^ghana\s*card$/i', '/^ghana\s*card\s*no\.?$/i', '/^nia$/i', '/^id\s*number$/i'
            ],
        ];

        foreach ($headerRow as $colIndex => $rawHeader) {
            $header = trim((string)$rawHeader);
            if ($header === '') {
                continue;
            }

            foreach ($fieldPatterns as $field => $patterns) {
                if (isset($map[$colIndex])) {
                    continue;
                }
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $header)) {
                        $map[$colIndex] = $field;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Extract fields dynamically using matched header mapping.
     */
    private function extractRowByDynamicHeaders(array $row, array $headerMap): array
    {
        $data = [];
        $numericFields = [
            'basic', 'overtime', 'incentive', 'bonus', 'other', '13month', 'totalearn',
            'ssnitee', 'overtimetaxt', 'pf', 'taxable', 'ssniter', 'petra', '13tax',
            'totaltaxdeduct', 'totaldeduct', 'netpay',
        ];

        foreach ($headerMap as $colIndex => $field) {
            $raw = $row[$colIndex] ?? null;
            $val = is_string($raw) ? trim($raw) : $raw;
            if ($val === '' || $val === null) {
                continue;
            }

            if (in_array($field, $numericFields, true)) {
                if (is_string($val)) {
                    $cleaned = preg_replace('/[^0-9.\-]/', '', $val);
                    $val = is_numeric($cleaned) ? (float)$cleaned : null;
                } elseif (is_numeric($val)) {
                    $val = (float)$val;
                } else {
                    $val = null;
                }
            }

            if ($val !== null) {
                $data[$field] = $val;
            }
        }

        return $data;
    }

    private function getDepartmentColumnMap(string $department): array
    {
        switch ($department) {
            case 'admin':
            case 'it':
            case 'finance':
            case 'marketing':
                return [
                    1  => 'staffid', 2 => 'name',     3 => 'dept',
                    4  => 'basic',   5 => 'overtime', 6 => 'other',
                    7  => '13month', 8 => 'totalearn',
                    9  => 'ssnitee', 10 => 'petra',   11 => 'overtimetaxt',
                    12 => 'taxable', 13 => 'ssniter', 14 => '13tax',
                    15 => 'totaltaxdeduct', 16 => 'totaldeduct', 17 => 'netpay',
                    18 => 'bank1',   19 => 'account1', 20 => 'branch1',
                    21 => 'ssnit1',  22 => 'ghcard',
                ];

            case 'sales_supervisors':
            case 'sales_supply':
                return [
                    1  => 'staffid', 2 => 'name',     3 => 'dept',
                    4  => 'basic',   5 => 'overtime', 6 => 'other',
                    7  => 'incentive', 8 => '13month', 9 => 'totalearn',
                    10 => 'ssnitee', 11 => 'petra',   12 => 'overtimetaxt',
                    13 => 'taxable', 14 => 'ssniter', 15 => '13tax',
                    16 => 'totaltaxdeduct', 17 => 'totaldeduct', 18 => 'netpay',
                    19 => 'bank1',   20 => 'account1', 21 => 'branch1',
                    22 => 'ssnit1',  23 => 'ghcard',
                ];

            case 'merchandisers':
                return [
                    1  => 'staffid', 2 => 'name',     3 => 'dept',
                    5  => 'basic',   6 => 'bonus',    7 => 'totalearn',
                    8  => 'ssnitee', 9 => 'petra',    10 => 'pf',
                    11 => 'taxable', 12 => 'ssniter', 13 => 'totaltaxdeduct',
                    14 => 'totaldeduct', 15 => 'netpay',
                    16 => 'bank1',   17 => 'account1', 18 => 'branch1',
                    19 => 'ssnit1',  20 => 'ghcard',
                ];

            case 'omsr':
                return [
                    1  => 'staffid', 2 => 'name',     3 => 'dept',
                    5  => 'basic',   6 => 'incentive', 7 => 'bonus',   8 => 'other',
                    9  => 'totalearn',
                    10 => 'ssnitee', 11 => 'petra',   12 => 'pf',
                    13 => 'taxable', 14 => 'ssniter', 15 => 'totaltaxdeduct',
                    16 => 'totaldeduct', 17 => 'netpay',
                    18 => 'bank1',   19 => 'account1', 20 => 'branch1',
                    21 => 'ssnit1',  22 => 'ghcard',
                ];

            case 'standard':
            default:
                return [
                    0  => 'staffid', 1 => 'name',      2 => 'dept',
                    3  => 'basic',   4 => 'overtime',  5 => 'incentive', 6 => 'bonus',
                    7  => 'other',   8 => '13month',   9 => 'totalearn',
                    10 => 'ssnitee', 11 => 'overtimetaxt', 12 => 'pf',   13 => 'taxable',
                    14 => 'ssniter', 15 => 'petra',    16 => '13tax',
                    17 => 'totaltaxdeduct', 18 => 'totaldeduct', 19 => 'netpay',
                    20 => 'bank1',   21 => 'account1', 22 => 'branch1',
                    23 => 'ssnit1',  24 => 'ghcard',
                ];
        }
    }

    private function resolveWorksheet(string $filePath, string $department)
    {
        $spreadsheet = IOFactory::load($filePath);
        $names       = $this->sheetNamesForDepartment($department);

        if (!empty($names)) {
            foreach ($spreadsheet->getWorksheetIterator() as $ws) {
                $title = strtoupper(trim($ws->getTitle()));
                foreach ($names as $candidate) {
                    if ($title === strtoupper(trim($candidate))) {
                        return $ws;
                    }
                }
            }
        }

        return $spreadsheet->getActiveSheet();
    }

    private function sheetNamesForDepartment(string $department): array
    {
        return match ($department) {
            'admin'             => ['ADMIN'],
            'it'                => ['IT', 'IT '],
            'finance'           => ['FINANCE'],
            'marketing'         => ['MARKETING'],
            'sales_supervisors' => ['SALES SUPERVISORS', 'SALES-SUPERVISORS', 'SALES SUPERVISOR'],
            'sales_supply'      => ['SALES-SUPPLY', 'SALES SUPPLY', 'SALESSUPPLY'],
            'merchandisers'     => ['MECHANDISERS', 'MERCHANDISERS'],
            'omsr'              => ['OMSR', 'OMSRS'],
            default             => [],
        };
    }

    private function findHeaderRow(array $rows): ?int
    {
        foreach (array_slice($rows, 0, 10, true) as $index => $row) {
            foreach ($row as $cell) {
                $normalized = strtolower(preg_replace('/[^a-z]/i', '', (string) $cell));
                if ($normalized === 'staffid') {
                    return $index;
                }
            }
        }
        return null;
    }

    private function mapRow(array $row, array $columnMap): array
    {
        $data = [];

        foreach ($columnMap as $index => $field) {
            $raw   = $row[$index] ?? null;
            $value = is_string($raw) ? trim($raw) : $raw;
            $data[$field] = ($value === '') ? null : $value;
        }

        $numericFields = [
            'basic', 'overtime', 'incentive', 'bonus', 'other', '13month', 'totalearn',
            'ssnitee', 'overtimetaxt', 'pf', 'taxable', 'ssniter', 'petra', '13tax',
            'totaltaxdeduct', 'totaldeduct', 'netpay',
        ];

        foreach ($numericFields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            if ($value === null) {
                continue;
            }
            if (is_string($value)) {
                $value = preg_replace('/[^0-9.\-]/', '', $value);
            }
            $data[$field] = is_numeric($value) ? (float) $value : null;
        }

        return $data;
    }

    private function getTemplateHeaders(string $department): array
    {
        switch ($department) {
            case 'admin':
            case 'it':
            case 'finance':
            case 'marketing':
                return [
                    'NO.', 'STAFF ID', 'Employee Name', 'DEPT.', 'Basic Salary',
                    'Overtime Earnings', 'TRANSPORT ALLOWANCE', '13th Earnings', 'Total Earnings',
                    'SSNIT EE', 'PETRA', 'Overtime Tax', 'TAXABLE INCOME', 'SSNIT ER',
                    '13th TAX', 'TOTAL TAX DEDUCTIONS', 'TOTAL DEDUCTIONS', 'TAKE HOME PAY',
                    'BANK', 'ACCOUNT NUMBER', 'BRANCH', 'SSNIT', 'GH CARD',
                ];
            case 'sales_supervisors':
            case 'sales_supply':
                return [
                    'NO.', 'STAFF ID', 'Employee Name', 'Description', 'Basic Salary',
                    'Overtime Earnings', 'TRANSPORT ALLOWANCE', 'INCENTIVES EARNINGS', '13th Earnings',
                    'Total Earnings', 'SSNIT EE', 'PETRA', 'Overtime Tax', 'TAXABLE INCOME',
                    'SSNIT ER', '13th TAX', 'TOTAL TAX DEDUCTIONS', 'TOTAL DEDUCTIONS',
                    'TAKE HOME PAY', 'BANK', 'ACCOUNT NUMBER', 'BRANCH', 'SSNIT', 'GH CARD',
                ];
            case 'merchandisers':
                return [
                    'NO.', 'STAFF ID', 'Employee Name', 'Shop', 'Description', 'Basic Salary',
                    'Bonus Earnings', 'Total Earnings', 'SSNIT EE', 'PETRA',
                    'S-TRUST FUND (5% ON BASIC)', 'TAXABLE INCOME', 'SSNIT ER',
                    'TOTAL TAX DEDUCTIONS', 'TOTAL DEDUCTIONS', 'TAKE HOME PAY',
                    'BANK', 'ACCOUNT NUMBER', 'BRANCH', 'SSNIT', 'GH CARD',
                ];
            case 'omsr':
                return [
                    'NO.', 'STAFF ID', 'Employee Name', 'Shop', 'Description', 'Basic Salary',
                    'Incentive Earnings', 'Bonus Earnings', 'Other Earnings', 'Total Earnings',
                    'SSNIT EE', 'PETRA', 'S-TRUST FUND (5% ON BASIC)', 'TAXABLE INCOME',
                    'SSNIT ER', 'TOTAL TAX DEDUCTIONS', 'TOTAL DEDUCTIONS', 'TAKE HOME PAY',
                    'BANK', 'ACCOUNT NUMBER', 'BRANCH', 'SSNIT', 'GH CARD',
                ];
            case 'standard':
            default:
                return [
                    'Staff ID', 'Name', 'Department', 'Basic Salary', 'Overtime', 'Incentive', 'Bonus',
                    'Other Earnings', '13th Month', 'Total Earnings', 'SSNIT EE', 'Overtime Tax', 'PF',
                    'Taxable Income', 'SSNIT ER', 'PETRA', '13th Month Tax', 'Total Tax Deduction',
                    'Total Deductions', 'Net Pay', 'Bank Name', 'Account Number', 'Branch',
                    'SSNIT Number', 'Ghana Card',
                ];
        }
    }

    private function columnLetter(int $number): string
    {
        $letter = '';
        while ($number > 0) {
            $mod    = ($number - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $number = intdiv($number - 1, 26);
        }
        return $letter;
    }
}