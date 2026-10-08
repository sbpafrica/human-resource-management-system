<?php

namespace App\Http\Controllers;

use App\Models\Gmoneypay;
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
            'file'  => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $selectedMonth = $request->input('month');

        try {
            $tableName = Gmoneypay::tableName($selectedMonth);
            Gmoneypay::createTableIfNotExists($tableName);

            $reader      = new XlsxReader();
            $spreadsheet = $reader->load($request->file('file')->getRealPath());
            $rows        = $spreadsheet->getActiveSheet()->toArray();
            array_shift($rows); // remove header row

            $existingIds  = DB::table($tableName)->pluck('staffid')->toArray();
            $errors       = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
                if (empty(trim((string) ($row[0] ?? '')))) continue;

                $rowNum  = $index + 2;
                $staffid = trim((string) ($row[0] ?? ''));
                $name    = trim((string) ($row[1] ?? ''));

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

                // Validate take_home_pay (column index 27)
                $takeHomePay = $this->toDecimal($row[26] ?? null);
                if ($takeHomePay === null) {
                    $errors[] = "Row {$rowNum}: Take Home Pay is required and must be a valid number.";
                    continue;
                }

                // Parse date_of_employment (column 2)
                $dateOfEmployment = null;
                $rawDate          = trim((string) ($row[2] ?? ''));
                if (!empty($rawDate)) {
                    try {
                        $dateOfEmployment = is_numeric($rawDate)
                            ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $rawDate)->format('Y-m-d')
                            : Carbon::parse($rawDate)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // not required — leave null
                    }
                }

                // Column mapping (0-based) matching the table structure:
                // 0  staffid                      14 tt_logistics_allowance_earned
                // 1  name                         15 gross_salary
                // 2  date_of_employment           16 management_fee
                // 3  position                     17 getfund
                // 4  location                     18 nhil
                // 5  basic_salary                 19 vat
                // 6  legal_work_days              20 ssf
                // 7  number_of_days_worked        21 cost_to_gmoney
                // 8  leave_days                   22 ssnit_ee
                // 9  total_days_worked            23 petra
                // 10 number_of_absent_days        24 taxable_income
                // 11 absent_days_deductions       25 paye
                // 12 basic_salary_earned          26 total_deductions
                // 13 tt_logistics_allowance       27 take_home_pay
                // 28 bank   29 gmoney_number   30 wallet_name
                // 31 ssnit_number   32 gh_card_number

                $validRecords[] = [
                    'staffid'                       => $staffid,
                    'name'                          => $name,
                    'date_of_employment'            => $dateOfEmployment,
                    'position'                      => trim((string) ($row[3] ?? '')) ?: null,
                    'location'                      => trim((string) ($row[4] ?? '')) ?: null,
                    'basic_salary'                  => $this->toDecimal($row[5]  ?? null),
                    'legal_work_days'               => $this->toInt($row[6]      ?? null),
                    'number_of_days_worked'         => $this->toInt($row[7]      ?? null),
                    'leave_days'                    => $this->toInt($row[8]      ?? null),
                    'total_days_worked'             => $this->toInt($row[9]      ?? null),
                    'number_of_absent_days'         => $this->toInt($row[10]     ?? null),
                    'absent_days_deductions'        => $this->toDecimal($row[11] ?? null),
                    'basic_salary_earned'           => $this->toDecimal($row[12] ?? null),
                    'bonus_earned'                  => $this->toDecimal($row[13] ?? null),
                    'gross_salary'                  => $this->toDecimal($row[14] ?? null),
                    'management_fee'                => $this->toDecimal($row[15] ?? null),
                    'getfund'                       => $this->toDecimal($row[16] ?? null),
                    'nhil'                          => $this->toDecimal($row[17] ?? null),
                    'vat'                           => $this->toDecimal($row[18] ?? null),
                    'ssf'                           => $this->toDecimal($row[19] ?? null),
                    'cost_to_gmoney'                => $this->toDecimal($row[20] ?? null),
                    'ssnit_ee'                      => $this->toDecimal($row[21] ?? null),
                    'petra'                         => $this->toDecimal($row[22] ?? null),
                    'taxable_income'                => $this->toDecimal($row[23] ?? null),
                    'paye'                          => $this->toDecimal($row[24] ?? null),
                    'total_deductions'              => $this->toDecimal($row[25] ?? null),
                    'take_home_pay'                 => $takeHomePay,
                    'bank'                          => trim((string) ($row[27] ?? '')) ?: null,
                    'gmoney_number'                 => trim((string) ($row[28] ?? '')) ?: null,
                    'wallet_name'                   => trim((string) ($row[29] ?? '')) ?: null,
                    'ssnit_number'                  => trim((string) ($row[30] ?? '')) ?: null,
                    'gh_card_number'                => trim((string) ($row[31] ?? '')) ?: null,
                ];

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

    // ─────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────
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
        $tempFile = tempnam(sys_get_temp_dir(), $filename);
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }
}