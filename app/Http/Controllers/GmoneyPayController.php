<?php

namespace App\Http\Controllers;

use App\Models\Gmoneypay;
use App\Models\PayrollColumn;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

class GmoneyPayController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    private function toDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        $cleaned = preg_replace('/[^\d.\-]/', '', str_replace(',', '', (string) $value));
        if (!is_numeric($cleaned)) return null;
        return round((float) $cleaned, 2);
    }

    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        $cleaned = preg_replace('/[^\d\-]/', '', (string) $value);
        return is_numeric($cleaned) ? (int) $cleaned : null;
    }

    // ─────────────────────────────────────────────────────────────────
    // INDEX
    // ─────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month     = $request->input('month', now()->format('Y-m'));
        $date      = Carbon::createFromFormat('Y-m', $month);
        $tableName = Gmoneypay::tableName($month);

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('Gmoneypay/Index', [
                'records'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'searchCount'    => null,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        // Join bankdetails on staffid for bank/account/branch
        // Fall back to the bank, gmoney_number, wallet_name stored in the payroll table
        $query = DB::table("{$tableName} as tp")
            ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
            ->select(
                'tp.staffid as staffid',
                'tp.staffid as staffida',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
                'tp.location as location',
                'tp.location as shop',
                DB::raw('COALESCE(NULLIF(TRIM(bd.bank), ""), tp.bank) as bank'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.account), ""), tp.gmoney_number) as account'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.code), ""), "") as code'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.branch), ""), tp.wallet_name) as branch'),
                'tp.take_home_pay as take_home_pay',
                'tp.take_home_pay as netpay'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffid',  'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                  ->orWhere('tp.location', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.bank), ""), tp.bank) LIKE ?', ["%{$search}%"]);
            });
        }

        $sort    = $request->input('sort', 'staffid');
        $order   = $request->input('order', 'asc');
        $sortMap = [
            'staffid'       => 'tp.staffid',
            'name'          => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
            'location'      => 'tp.location',
            'bank'          => DB::raw('COALESCE(NULLIF(TRIM(bd.bank), ""), tp.bank)'),
            'take_home_pay' => 'tp.take_home_pay',
        ];
        $query->orderBy($sortMap[$sort] ?? 'tp.staffid', $order);

        $records      = $query->paginate(30)->appends($request->query());
        $totalRecords = DB::table($tableName)->count();
        $searchCount  = $search ? $query->getCountForPagination() : null;

        return Inertia::render('Gmoneypay/Index', [
            'records'        => $records,
            'selectedMonth'  => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords'   => $totalRecords,
            'searchCount'    => $searchCount,
            'flash'          => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // UPLOAD
    // ─────────────────────────────────────────────────────────────────
    public function upload(Request $request)
    {
        $request->validate([
            'file'  => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:10240'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $selectedMonth = $request->input('month');

        try {
            $tableName = Gmoneypay::tableName($selectedMonth);
            Gmoneypay::createTableIfNotExists($tableName);

            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (empty($rows)) {
                return redirect()->route('gmoneypay.index', ['month' => $selectedMonth])
                    ->with('error', 'The uploaded file is empty.');
            }

            // Detect header row dynamically
            $headerRowIndex = 0;
            $headerRow = [];
            foreach (array_slice($rows, 0, 10, true) as $rIdx => $candidateRow) {
                $nonEmpty = array_filter($candidateRow, fn($c) => trim((string)$c) !== '');
                if (count($nonEmpty) >= 3) {
                    $rowStr = strtolower(implode(' ', $candidateRow));
                    if (str_contains($rowStr, 'staff') || str_contains($rowStr, 'name') || str_contains($rowStr, 'basic') || str_contains($rowStr, 'salary') || str_contains($rowStr, 'gross') || str_contains($rowStr, 'take home') || str_contains($rowStr, 'net')) {
                        $headerRowIndex = $rIdx;
                        $headerRow = $candidateRow;
                        break;
                    }
                }
            }
            if (empty($headerRow) && !empty($rows)) {
                $headerRow = $rows[0];
                $headerRowIndex = 0;
            }

            $headerIndexMap = PayrollColumn::buildHeaderIndexMap($headerRow);
            $customCols = PayrollColumn::getColumnsForClient('gmoney');
            $dataRows = array_slice($rows, $headerRowIndex + 1);

            $existingIds  = DB::table($tableName)->pluck('staffid')->toArray();
            $errors       = [];
            $validRecords = [];

            foreach ($dataRows as $index => $row) {
                if (empty(array_filter($row, fn($c) => trim((string)$c) !== ''))) continue;

                $rowNum  = $headerRowIndex + $index + 2;
                $staffid = trim((string) PayrollColumn::extractValue($row, $headerIndexMap, ['staffid', 'staff_id', 'staff id', 'id', 'emp id', 'employee id', 'staff no'], 0, 'string'));
                $name    = trim((string) PayrollColumn::extractValue($row, $headerIndexMap, ['name', 'staff name', 'employee name', 'full name', 'fullname'], 1, 'string'));

                // Skip repeated headers
                if (in_array(strtolower($staffid), ['staff id', 'staffid', 'staff_id', 'id', 'staff no', 'name', 's/n', 'no']) ||
                    in_array(strtolower($name), ['name', 'staff name', 'employee name', 'full name'])) {
                    continue;
                }

                if (empty($staffid)) {
                    $errors[] = "Row {$rowNum}: Staff ID is required.";
                    continue;
                }
                if (empty($name)) {
                    $errors[] = "Row {$rowNum}: Name is required.";
                    continue;
                }
                if (in_array($staffid, $existingIds)) {
                    $errors[] = "Row {$rowNum}: Duplicate Staff ID: {$staffid}";
                    continue;
                }

                // Parse date_of_employment (column 2)
                $dateOfEmployment = null;
                $rawDate = trim((string) PayrollColumn::extractValue($row, $headerIndexMap, ['date of employment', 'employment date', 'date_of_employment', 'start date', 'join date'], 2, 'string'));
                if (!empty($rawDate)) {
                    try {
                        $dateOfEmployment = is_numeric($rawDate)
                            ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $rawDate)->format('Y-m-d')
                            : Carbon::parse($rawDate)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // leave null
                    }
                }

                $basicSalary = PayrollColumn::extractValue($row, $headerIndexMap, ['basic salary', 'basic_salary', 'basic', 'base salary'], 5, 'decimal');
                $grossSalary = PayrollColumn::extractValue($row, $headerIndexMap, ['gross salary', 'gross_salary', 'gross pay', 'gross'], 14, 'decimal');
                $takeHomePay = PayrollColumn::extractValue($row, $headerIndexMap, ['take home pay', 'take_home_pay', 'net pay', 'netpay', 'take home', 'net salary'], 26, 'decimal');

                if ($takeHomePay === null && $grossSalary !== null) {
                    $totalDed = PayrollColumn::extractValue($row, $headerIndexMap, ['total deductions', 'total_deductions', 'deductions'], 25, 'decimal') ?? 0;
                    $takeHomePay = round(max(0, (float)$grossSalary - (float)$totalDed), 2);
                }

                if ($takeHomePay === null) {
                    $errors[] = "Row {$rowNum}: Take Home Pay is required and must be a valid number.";
                    continue;
                }

                $record = [
                    'staffid'                       => $staffid,
                    'name'                          => $name,
                    'date_of_employment'            => $dateOfEmployment,
                    'position'                      => trim((string) PayrollColumn::extractValue($row, $headerIndexMap, ['position', 'designation', 'job title', 'role'], 3, 'string')) ?: null,
                    'location'                      => trim((string) PayrollColumn::extractValue($row, $headerIndexMap, ['location', 'station', 'branch', 'shop', 'department'], 4, 'string')) ?: null,
                    'basic_salary'                  => $basicSalary,
                    'legal_work_days'               => PayrollColumn::extractValue($row, $headerIndexMap, ['legal work days', 'legal_work_days', 'work days', 'expected days'], 6, 'int'),
                    'number_of_days_worked'         => PayrollColumn::extractValue($row, $headerIndexMap, ['number of days worked', 'number_of_days_worked', 'days worked', 'actual days'], 7, 'int'),
                    'leave_days'                    => PayrollColumn::extractValue($row, $headerIndexMap, ['leave days', 'leave_days', 'leave'], 8, 'int'),
                    'total_days_worked'             => PayrollColumn::extractValue($row, $headerIndexMap, ['total days worked', 'total_days_worked', 'total work days'], 9, 'int'),
                    'number_of_absent_days'         => PayrollColumn::extractValue($row, $headerIndexMap, ['number of absent days', 'number_of_absent_days', 'absent days'], 10, 'int'),
                    'absent_days_deductions'        => PayrollColumn::extractValue($row, $headerIndexMap, ['absent days deductions', 'absent_days_deductions', 'absent deduction'], 11, 'decimal'),
                    'basic_salary_earned'           => PayrollColumn::extractValue($row, $headerIndexMap, ['basic salary earned', 'basic_salary_earned', 'earned basic'], 12, 'decimal') ?? $basicSalary,
                    'bonus_earned'                  => PayrollColumn::extractValue($row, $headerIndexMap, ['bonus earned', 'bonus_earned', 'bonus', 'tt logistics allowance', 'tt_logistics_allowance_earned'], 13, 'decimal'),
                    'gross_salary'                  => $grossSalary,
                    'management_fee'                => PayrollColumn::extractValue($row, $headerIndexMap, ['management fee', 'management_fee', 'mgt fee'], 15, 'decimal'),
                    'getfund'                       => PayrollColumn::extractValue($row, $headerIndexMap, ['getfund', 'get fund'], 16, 'decimal'),
                    'nhil'                          => PayrollColumn::extractValue($row, $headerIndexMap, ['nhil'], 17, 'decimal'),
                    'vat'                           => PayrollColumn::extractValue($row, $headerIndexMap, ['vat'], 18, 'decimal'),
                    'ssf'                           => PayrollColumn::extractValue($row, $headerIndexMap, ['ssf', 'ssf 13%', 'employer ssnit', 'ssnit 13%'], 19, 'decimal'),
                    'cost_to_gmoney'                => PayrollColumn::extractValue($row, $headerIndexMap, ['cost to gmoney', 'cost_to_gmoney', 'cost to client', 'ctc'], 20, 'decimal'),
                    'ssnit_ee'                      => PayrollColumn::extractValue($row, $headerIndexMap, ['ssnit ee', 'ssnit_ee', 'ssnit 5.5%', 'employee ssnit'], 21, 'decimal'),
                    'petra'                         => PayrollColumn::extractValue($row, $headerIndexMap, ['petra', 'tier 2', 'tier 2 5%'], 22, 'decimal'),
                    'taxable_income'                => PayrollColumn::extractValue($row, $headerIndexMap, ['taxable income', 'taxable_income', 'taxable pay'], 23, 'decimal'),
                    'paye'                          => PayrollColumn::extractValue($row, $headerIndexMap, ['paye', 'income tax', 'tax'], 24, 'decimal'),
                    'total_deductions'              => PayrollColumn::extractValue($row, $headerIndexMap, ['total deductions', 'total_deductions', 'deductions'], 25, 'decimal'),
                    'take_home_pay'                 => $takeHomePay,
                    'bank'                          => trim((string) PayrollColumn::extractValue($row, $headerIndexMap, ['bank', 'bank name'], 27, 'string')) ?: null,
                    'gmoney_number'                 => trim((string) PayrollColumn::extractValue($row, $headerIndexMap, ['gmoney number', 'gmoney_number', 'phone', 'account number', 'momo number'], 28, 'string')) ?: null,
                    'wallet_name'                   => trim((string) PayrollColumn::extractValue($row, $headerIndexMap, ['wallet name', 'wallet_name', 'account name'], 29, 'string')) ?: null,
                    'ssnit_number'                  => trim((string) PayrollColumn::extractValue($row, $headerIndexMap, ['ssnit number', 'ssnit_number', 'ssnit no', 'ssnit'], 30, 'string')) ?: null,
                    'gh_card_number'                => trim((string) PayrollColumn::extractValue($row, $headerIndexMap, ['gh card number', 'gh_card_number', 'ghana card', 'ghcard'], 31, 'string')) ?: null,
                ];

                $customValues = PayrollColumn::extractCustomColumnValues($row, $headerIndexMap, $customCols, $tableName);
                $record = array_merge($record, $customValues);

                $validRecords[] = $record;
                $existingIds[] = $staffid;
            }

            if (!empty($errors)) {
                $msg = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('gmoneypay.index', ['month' => $selectedMonth])
                    ->with('error', $msg);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("GmoneyPay upload: inserted " . count($validRecords) . " rows into {$tableName}");
            }

            return redirect()->route('gmoneypay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');

        } catch (\Exception $e) {
            Log::error('GmoneyPay upload failed', ['error' => $e->getMessage()]);
            return redirect()->route('gmoneypay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }
    public function delete(Request $request)
    {
        $selectedMonth = $request->input('month', now()->format('Y-m'));
        $tableName     = Gmoneypay::tableName($selectedMonth);

        try {
            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                DB::table($tableName)->truncate();
                return redirect()->route('gmoneypay.index', ['month' => $selectedMonth])
                    ->with('success', 'Records deleted successfully.');
            }
            return redirect()->route('gmoneypay.index', ['month' => $selectedMonth])
                ->with('error', 'No records found for the selected month.');
        } catch (\Exception $e) {
            Log::error('GmoneyPay delete failed', ['error' => $e->getMessage()]);
            return redirect()->route('gmoneypay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to delete records: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORT
    // Columns: No. | Staff ID | Name | Location | Bank | Account |
    //          Branch Code | Branch | Take Home Pay
    // ─────────────────────────────────────────────────────────────────
    public function export(Request $request)
    {
        try {
            $request->validate(['month' => ['required', 'date_format:Y-m']]);

            $month     = $request->input('month');
            $date      = Carbon::createFromFormat('Y-m', $month);
            $tableName = Gmoneypay::tableName($month);

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
            }

            $query = DB::table("{$tableName} as tp")
                ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
                ->select(
                    'tp.staffid',
                    DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
                    'tp.location',
                    DB::raw('COALESCE(NULLIF(TRIM(bd.bank), ""), tp.bank) as bank'),
                    DB::raw('COALESCE(NULLIF(TRIM(bd.account), ""), tp.gmoney_number) as account'),
                    DB::raw('COALESCE(NULLIF(TRIM(bd.code), ""), "") as code'),
                    DB::raw('COALESCE(NULLIF(TRIM(bd.branch), ""), tp.wallet_name) as branch'),
                    'tp.take_home_pay'
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.staffid',   'like', "%{$search}%")
                      ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                      ->orWhere('tp.location', 'like', "%{$search}%")
                      ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.bank), ""), tp.bank) LIKE ?', ["%{$search}%"]);
                });
            }

            $sort    = $request->input('sort', 'staffid');
            $order   = $request->input('order', 'asc');
            $sortMap = [
                'staffid'       => 'tp.staffid',
                'name'          => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
                'location'      => 'tp.location',
                'bank'          => DB::raw('COALESCE(NULLIF(TRIM(bd.bank), ""), tp.bank)'),
                'take_home_pay' => 'tp.take_home_pay',
            ];
            $query->orderBy($sortMap[$sort] ?? 'tp.staffid', $order);

            $records = $query->get();

            // ── Build spreadsheet ────────────────────────────────────
            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Salary Summary');

            $headers = [
                'No.',
                'Staff ID',
                'Name',
                'Location',
                'Bank',
                'Account / G-Money Number',
                'Branch Code',
                'Branch / Wallet Name',
                'Take Home Pay (GHC)',
            ];
        $sheet->fromArray($headers, null, 'A1');

            $sheet->getStyle('A1:I1')->getFont()->setBold(true)->setSize(11)->setName('Calibri');
            $sheet->getStyle('A1:I1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('BBDEFE');

            $row = 2;
            foreach ($records as $i => $r) {
                $sheet->setCellValue("A{$row}", $i + 1);
                $sheet->setCellValue("B{$row}", $r->staffid);
                $sheet->setCellValue("C{$row}", $r->name);
                $sheet->setCellValue("D{$row}", $r->location ?? '');
                $sheet->setCellValue("E{$row}", $r->bank ?? '');
                $sheet->setCellValueExplicit("F{$row}", $r->account ?? '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("G{$row}", $r->code ?? '', DataType::TYPE_STRING);
                $sheet->setCellValue("H{$row}", $r->branch ?? '');
                $sheet->setCellValue("I{$row}",
                    $r->take_home_pay !== null
                        ? number_format((float) $r->take_home_pay, 2, '.', '')
                        : ''
                );
                $row++;
            }

            // Totals row
            $totalNetPay = $records->sum(fn($r) => (float) ($r->take_home_pay ?? 0));
            $sheet->setCellValue("H{$row}", 'TOTAL');
            $sheet->setCellValue("I{$row}", number_format($totalNetPay, 2, '.', ''));
            $sheet->getStyle("H{$row}:I{$row}")->getFont()->setBold(true);
            $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal('right');

            // Column widths
            $sheet->getColumnDimension('A')->setWidth(6);
            $sheet->getColumnDimension('B')->setWidth(14);
            $sheet->getColumnDimension('C')->setWidth(28);
            $sheet->getColumnDimension('D')->setWidth(20);
            $sheet->getColumnDimension('E')->setWidth(20);
            $sheet->getColumnDimension('F')->setWidth(22);
            $sheet->getColumnDimension('G')->setWidth(14);
            $sheet->getColumnDimension('H')->setWidth(22);
            $sheet->getColumnDimension('I')->setWidth(18);

            $sheet->getStyle("I2:I{$row}")->getAlignment()->setHorizontal('right');
            $sheet->freezePane('A2');

            $writer   = new Xlsx($spreadsheet);
            $filename = "gmoney_salary_summary_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);

        } catch (\Exception $e) {
            Log::error('GmoneyPay export failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed. Please try again.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // DOWNLOAD TEMPLATE
    // ─────────────────────────────────────────────────────────────────
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $headers = [
            'staffid',
            'name',
            'date_of_employment',
            'position',
            'location',
            'basic_salary',
            'legal_work_days',
            'number_of_days_worked',
            'leave_days',
            'total_days_worked',
            'number_of_absent_days',
            'absent_days_deductions',
            'basic_salary_earned',
            'bonus_earned',
            'gross_salary',
            'management_fee',
            'getfund',
            'nhil',
            'vat',
            'ssf',
            'cost_to_gmoney',
            'ssnit_ee',
            'petra',
            'taxable_income',
            'paye',
            'total_deductions',
            'take_home_pay',
            'bank',
            'gmoney_number',
            'wallet_name',
            'ssnit_number',
            'gh_card_number',
        ];

        $nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'gmoney');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:AG1')->getFont()->setBold(true);
        $sheet->getStyle('A1:AG1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDEFE');

        // Sample row
        $sample = [
            'GMN001', 'Ama Serwaa', '2024-01-15', 'Relationship Manager', 'Accra',
            3500.00, 26, 26, 0, 26, 0, 0.00,
            3500.00, 400.00, 400.00,
            3900.00, 312.00, 97.50, 97.50, 585.00,5499.00,
            192.50, 195.00,
            3300.00, 346.00, 538.50, 3361.50,
            'GCB Bank', '0244123456', 'Ama Serwaa',
            'C1234567890', 'GHA-000111222-3',
        ];

        $sheet->fromArray($sample, null, 'A2');

        foreach (range('A', 'Z') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        foreach (['AA', 'AB', 'AC', 'AD', 'AE', 'AF', 'AG'] as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $writer   = new Xlsx($spreadsheet);
        $filename = 'gmoney_payroll_template_' . date('M-Y') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma'              => 'public',
        ]);
    }
}