<?php

namespace App\Http\Controllers;

use App\Models\Sheferpay;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class SheferPayController extends Controller
{
    private const SORT_MAP = [
        'staff_id'      => 'tp.staff_id',
        'name'          => 'COALESCE(NULLIF(TRIM(bd.name), ""), tp.name1)',
        'shop'          => 'COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)',
        'bank'          => 'bd.bank',
        'account'       => 'bd.account',
        'code'          => 'bd.code',
        'branch'        => 'bd.branch',
        'take_home_pay' => 'tp.net_pay',
    ];

    // ─────────────────────────────────────────────────────────────────────
    //  INDEX  (unchanged)
    // ─────────────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        $date  = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'shefer' . strtolower($date->format('F')) . $date->format('y');

        if (!Schema::hasTable($tableName)) {
            return Inertia::render('Sheferpay/Index', [
                'records'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 30),
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'searchCount'    => null,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        $nameExpr = 'COALESCE(NULLIF(TRIM(bd.name), ""), tp.name1)';
        $shopExpr = 'COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)';

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staff_id', '=', 'bd.employid')
            ->select(
                'tp.staff_id',
                DB::raw("{$nameExpr} as name"),
                DB::raw("{$shopExpr} as shop"),
                'bd.bank', 'bd.account', 'bd.code', 'bd.branch',
                'tp.net_pay',
                // Expose the 2 new columns to the frontend as well
                'tp.total_cash_emolument',
                'tp.total_assessable_income'
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
        $sortColumn = self::SORT_MAP[$sort] ?? 'tp.staff_id';

        $query->orderByRaw("({$sortColumn}) IS NULL, {$sortColumn} {$order}");
        $query->orderBy('tp.staff_id', 'asc');

        $records = $query->paginate(30)->appends($request->query());

        $totalRecords = DB::table($tableName)->count();
        $searchCount  = $search ? $records->total() : null;

        return Inertia::render('Sheferpay/Index', [
            'records'        => $records,
            'selectedMonth'  => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords'   => $totalRecords,
            'searchCount'    => $searchCount,
            'flash'          => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  EXPORT  (unchanged)
    // ─────────────────────────────────────────────────────────────────────
    public function export(Request $request)
    {
        try {
            $request->validate([
                'month' => ['required', 'date_format:Y-m', 'after_or_equal:2020-01'],
            ]);

            $month = $request->input('month');
            $date  = Carbon::createFromFormat('Y-m', $month);
            $tableName = 'shefer' . strtolower($date->format('F')) . $date->format('y');

            if (!Schema::hasTable($tableName)) {
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $nameExpr = 'COALESCE(NULLIF(TRIM(bd.name), ""), tp.name1)';
            $shopExpr = 'COALESCE(NULLIF(TRIM(bd.shop), ""), tp.department)';

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('bankdetails as bd', 'tp.staff_id', '=', 'bd.employid')
                ->select(
                    'tp.staff_id',
                    DB::raw("{$nameExpr} as name"),
                    DB::raw("{$shopExpr} as shop"),
                    'bd.bank', 'bd.account', 'bd.code', 'bd.branch',
                    'tp.net_pay as take_home_pay'
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
            $sortColumn = self::SORT_MAP[$sort] ?? 'tp.staff_id';
            $query->orderByRaw("({$sortColumn}) IS NULL, {$sortColumn} {$order}");
            $query->orderBy('tp.staff_id', 'asc');

            $records = $query->get();

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
                $sheet->setCellValueExplicit("E{$row}", (string) ($r->account ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("F{$row}", (string) ($r->code ?? ''), DataType::TYPE_STRING);
                $sheet->setCellValue("G{$row}", $r->branch ?? '');
                $sheet->setCellValue("H{$row}", $r->take_home_pay ? number_format((float) $r->take_home_pay, 2, '.', '') : '');
                $row++;
            }

            $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
            $sheet->getStyle('A1:H1')->getFont()->setBold(true);
            $sheet->getStyle('A1:H1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('BBDEFE');

            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = "shefer_pay_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache',
            ]);

        } catch (\Exception $e) {
            Log::error("SheferPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Export failed. Please try again.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  UPLOAD — supports 3 formats:
    //    • 58-col SHEFER official file
    //    • 51-col new template (with 2 new cols)
    //    • 49-col legacy template
    // ─────────────────────────────────────────────────────────────────────
    public function upload(Request $request)
    {
        $request->validate([
            'file'  => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:4096'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $selectedMonth = $request->input('month');
        session(['selected_month' => $selectedMonth]);

        try {
            $date = Carbon::createFromFormat('Y-m', $selectedMonth);
            $tableName = 'shefer' . strtolower($date->format('F')) . $date->format('y');

            // Creates table + auto-ALTERs existing tables to add the 2 new columns
            Sheferpay::createTableIfNotExists($tableName);

            $file   = $request->file('file');
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();

            // formatData = false → raw floats instead of "1,234.56"
            $rows = $sheet->toArray(null, true, false, false);

            if (empty($rows)) {
                return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
                    ->with('error', 'Uploaded file is empty.');
            }

            // ── Detect format from the header row (before shifting) ─────
            $headerRow = array_shift($rows);   // drop header row from $rows

            // Normalize headers (lowercase, collapse whitespace)
            $normalizedHeaders = array_map(
                fn ($h) => strtolower(preg_replace('/\s+/', ' ', trim((string) $h))),
                $headerRow
            );

            $hasClockInId          = in_array('clock in id', $normalizedHeaders, true);
            $hasTotalAssessable    = in_array('total assessable income', $normalizedHeaders, true);
            $hasTotalCashEmolument = in_array('total cash emolument', $normalizedHeaders, true);

            // Trim trailing empty header cells for the fallback count check
            $columnCount = count($headerRow);
            while ($columnCount > 0 &&
                   ($headerRow[$columnCount - 1] === null || trim((string) $headerRow[$columnCount - 1]) === '')) {
                $columnCount--;
            }

            // Primary = header content; fallback = column count
            if ($hasClockInId || $hasTotalAssessable || $columnCount >= 52) {
                $mapper = 'mapShefer58ColumnRow';      // SHEFER official file
            } elseif ($hasTotalCashEmolument || $columnCount === 51) {
                $mapper = 'mapTemplate51ColumnRow';    // new template with 2 new cols
            } elseif ($columnCount >= 49) {
                $mapper = 'mapLegacy49ColumnRow';      // old template
            } else {
                return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
                    ->with('error', "Unrecognized file format: {$columnCount} columns detected. Expected 49, 51 or 58 columns.");
            }

            $existingStaffIds = DB::table($tableName)->pluck('staff_id')->toArray();
            $errors = [];
            $validRecords = [];
            $seenInFile = [];

            foreach ($rows as $index => $row) {
                if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                    continue;   // skip fully-empty rows
                }

                $record = $this->{$mapper}($row);

                if (empty($record['staff_id'])) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
                    continue;
                }
                if (empty($record['name1'])) {
                    $errors[] = "Row " . ($index + 2) . ": Name is required.";
                    continue;
                }
                if (is_null($record['basic']) || is_null($record['gross'])) {
                    $errors[] = "Row " . ($index + 2) . ": Basic Salary and Gross Salary are required.";
                    continue;
                }
                if (is_null($record['net_pay'])) {
                    $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number.";
                    continue;
                }
                if (in_array($record['staff_id'], $existingStaffIds, true)) {
                    $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: {$record['staff_id']}";
                    continue;
                }
                if (in_array($record['staff_id'], $seenInFile, true)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID appears twice in this file: {$record['staff_id']}";
                    continue;
                }

                $validRecords[] = $record;
                $seenInFile[]   = $record['staff_id'];
            }

            if (!empty($errors)) {
                Log::error('SheferPay upload errors: ' . implode(' | ', $errors));
                $message = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);

                return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
                    ->with('error', $message);
            }

            if (empty($validRecords)) {
                return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
                    ->with('error', 'No valid records found in the uploaded file.');
            }

            DB::transaction(function () use ($tableName, $validRecords) {
                foreach (array_chunk($validRecords, 200) as $chunk) {
                    DB::table($tableName)->insert($chunk);
                }
            });

            Log::info("SheferPay upload: inserted " . count($validRecords) . " records into {$tableName}");

            return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');

        } catch (\Exception $e) {
            Log::error('SheferPay upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  MAPPER: SHEFER official 58-col file (A→BF)
    // ─────────────────────────────────────────────────────────────────────
    private function mapShefer58ColumnRow(array $row): array
    {
        return [
            'staff_id'                => trim((string) ($row[2]  ?? '')) ?: null,   // C
            'name1'                   => trim((string) ($row[3]  ?? '')) ?: null,   // D
            'position'                => trim((string) ($row[4]  ?? '')) ?: null,   // E
            'department'              => trim((string) ($row[5]  ?? '')) ?: null,   // F
            'startdate'               => trim((string) ($row[6]  ?? '')) ?: null,   // G
            'basic'                   => $this->num($row[7]  ?? null),              // H
            'total_weekdays'          => $this->num($row[8]  ?? null),              // I
            'no_of_days_worked'       => null,
            'leavepay'                => null,
            'absent_days'             => $this->num($row[11] ?? null),              // L
            'basic_earned'            => $this->num($row[12] ?? null),              // M
            'nightdays'               => $this->num($row[13] ?? null),              // N
            'weekdayOT_hours'         => $this->num($row[14] ?? null),              // O
            'weekdayOT_rate'          => $this->num($row[15] ?? null),              // P
            'weekdayOT_amount'        => $this->num($row[16] ?? null),              // Q
            'weekendOT_hours'         => $this->num($row[17] ?? null),              // R
            'weekendOT_rate'          => $this->num($row[18] ?? null),              // S
            'weekendOT_amount'        => $this->num($row[19] ?? null),              // T
            'sunOT_hours'             => $this->num($row[20] ?? null),              // U
            'sunOT_rate'              => $this->num($row[21] ?? null),              // V
            'sunOT_amount'            => $this->num($row[22] ?? null),              // W
            'fixedotbasic'            => $this->num($row[23] ?? null),              // X
            'fixedot'                 => $this->num($row[24] ?? null),              // Y
            'total_overtime'          => $this->num($row[25] ?? null),              // Z
            'foodbasic'               => $this->num($row[26] ?? null),              // AA
            'food'                    => $this->num($row[27] ?? null),              // AB
            'airtimebasic'            => $this->num($row[28] ?? null),              // AC
            'airtime'                 => $this->num($row[29] ?? null),              // AD
            'nightallo'               => $this->num($row[30] ?? null),              // AE
            'responsebasic'           => $this->num($row[31] ?? null),              // AF
            'responseallo'            => $this->num($row[32] ?? null),              // AG
            'transbasic'              => $this->num($row[33] ?? null),              // AH
            'transport'               => $this->num($row[34] ?? null),              // AI
            'medicalbasic'            => $this->num($row[35] ?? null),              // AJ
            'medical'                 => $this->num($row[36] ?? null),              // AK
            'total_allowance'         => $this->num($row[37] ?? null),              // AL
            'gross'                   => $this->num($row[38] ?? null),              // AM
            'total_cash_emolument'    => $this->num($row[39] ?? null),              // AN  ★
            'total_assessable_income' => $this->num($row[40] ?? null),              // AO  ★
            'emp_ssnit_13_percent'    => $this->num($row[41] ?? null),              // AP
            'ssnit_5_5_percent'       => $this->num($row[42] ?? null),              // AQ
            'petra'                   => $this->num($row[43] ?? null),              // AR
            'taxable_income'          => $this->num($row[44] ?? null),              // AS
            'paye'                    => $this->num($row[45] ?? null),              // AT
            'ot_tax'                  => $this->num($row[46] ?? null),              // AU
            'total_tax'               => $this->num($row[47] ?? null),              // AV
            'absent_without_pay'      => $this->num($row[48] ?? null),              // AW
            'salaryloan'              => $this->num($row[49] ?? null),              // AX
            'total_deductions'        => $this->num($row[50] ?? null),              // AY
            'refunds'                 => $this->num($row[51] ?? null),              // AZ
            'net_pay'                 => $this->num($row[52] ?? null),              // BA
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  MAPPER: NEW 51-col template (legacy 49 + 2 new cols inserted after Gross)
    // ─────────────────────────────────────────────────────────────────────
    private function mapTemplate51ColumnRow(array $row): array
    {
        return [
            'staff_id'                => trim((string) ($row[0]  ?? '')) ?: null,   // A
            'name1'                   => trim((string) ($row[1]  ?? '')) ?: null,   // B
            'position'                => trim((string) ($row[2]  ?? '')) ?: null,   // C
            'department'              => trim((string) ($row[3]  ?? '')) ?: null,   // D
            'startdate'               => trim((string) ($row[4]  ?? '')) ?: null,   // E
            'basic'                   => $this->num($row[5]  ?? null),              // F
            'total_weekdays'          => $this->num($row[6]  ?? null),              // G
            'no_of_days_worked'       => $this->num($row[7]  ?? null),              // H
            'leavepay'                => $this->num($row[8]  ?? null),              // I
            'absent_days'             => $this->num($row[9]  ?? null),              // J
            'basic_earned'            => $this->num($row[10] ?? null),              // K
            'nightdays'               => $this->num($row[11] ?? null),              // L
            'weekdayOT_hours'         => $this->num($row[12] ?? null),              // M
            'weekdayOT_rate'          => $this->num($row[13] ?? null),              // N
            'weekdayOT_amount'        => $this->num($row[14] ?? null),              // O
            'weekendOT_hours'         => $this->num($row[15] ?? null),              // P
            'weekendOT_rate'          => $this->num($row[16] ?? null),              // Q
            'weekendOT_amount'        => $this->num($row[17] ?? null),              // R
            'sunOT_hours'             => $this->num($row[18] ?? null),              // S
            'sunOT_rate'              => $this->num($row[19] ?? null),              // T
            'sunOT_amount'            => $this->num($row[20] ?? null),              // U
            'fixedotbasic'            => $this->num($row[21] ?? null),              // V
            'fixedot'                 => $this->num($row[22] ?? null),              // W
            'total_overtime'          => $this->num($row[23] ?? null),              // X
            'foodbasic'               => $this->num($row[24] ?? null),              // Y
            'food'                    => $this->num($row[25] ?? null),              // Z
            'airtimebasic'            => $this->num($row[26] ?? null),              // AA
            'airtime'                 => $this->num($row[27] ?? null),              // AB
            'nightallo'               => $this->num($row[28] ?? null),              // AC
            'responsebasic'           => $this->num($row[29] ?? null),              // AD
            'responseallo'            => $this->num($row[30] ?? null),              // AE
            'transbasic'              => $this->num($row[31] ?? null),              // AF
            'transport'               => $this->num($row[32] ?? null),              // AG
            'medicalbasic'            => $this->num($row[33] ?? null),              // AH
            'medical'                 => $this->num($row[34] ?? null),              // AI
            'total_allowance'         => $this->num($row[35] ?? null),              // AJ
            'gross'                   => $this->num($row[36] ?? null),              // AK
            'total_cash_emolument'    => $this->num($row[37] ?? null),              // AL  ★
            'total_assessable_income' => $this->num($row[38] ?? null),              // AM  ★
            'emp_ssnit_13_percent'    => $this->num($row[39] ?? null),              // AN
            'ssnit_5_5_percent'       => $this->num($row[40] ?? null),              // AO
            'petra'                   => $this->num($row[41] ?? null),              // AP
            'taxable_income'          => $this->num($row[42] ?? null),              // AQ
            'paye'                    => $this->num($row[43] ?? null),              // AR
            'ot_tax'                  => $this->num($row[44] ?? null),              // AS
            'total_tax'               => $this->num($row[45] ?? null),              // AT
            'absent_without_pay'      => $this->num($row[46] ?? null),              // AU
            'salaryloan'              => $this->num($row[47] ?? null),              // AV
            'total_deductions'        => $this->num($row[48] ?? null),              // AW
            'refunds'                 => $this->num($row[49] ?? null),              // AX
            'net_pay'                 => $this->num($row[50] ?? null),              // AY
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  MAPPER: legacy 49-col file (A→AW)
    // ─────────────────────────────────────────────────────────────────────
    private function mapLegacy49ColumnRow(array $row): array
    {
        return [
            'staff_id'                => trim((string) ($row[0]  ?? '')) ?: null,
            'name1'                   => trim((string) ($row[1]  ?? '')) ?: null,
            'position'                => trim((string) ($row[2]  ?? '')) ?: null,
            'department'              => trim((string) ($row[3]  ?? '')) ?: null,
            'startdate'               => trim((string) ($row[4]  ?? '')) ?: null,
            'basic'                   => $this->num($row[5]  ?? null),
            'total_weekdays'          => $this->num($row[6]  ?? null),
            'no_of_days_worked'       => $this->num($row[7]  ?? null),
            'leavepay'                => $this->num($row[8]  ?? null),
            'absent_days'             => $this->num($row[9]  ?? null),
            'basic_earned'            => $this->num($row[10] ?? null),
            'nightdays'               => $this->num($row[11] ?? null),
            'weekdayOT_hours'         => $this->num($row[12] ?? null),
            'weekdayOT_rate'          => $this->num($row[13] ?? null),
            'weekdayOT_amount'        => $this->num($row[14] ?? null),
            'weekendOT_hours'         => $this->num($row[15] ?? null),
            'weekendOT_rate'          => $this->num($row[16] ?? null),
            'weekendOT_amount'        => $this->num($row[17] ?? null),
            'sunOT_hours'             => $this->num($row[18] ?? null),
            'sunOT_rate'              => $this->num($row[19] ?? null),
            'sunOT_amount'            => $this->num($row[20] ?? null),
            'fixedotbasic'            => $this->num($row[21] ?? null),
            'fixedot'                 => $this->num($row[22] ?? null),
            'total_overtime'          => $this->num($row[23] ?? null),
            'foodbasic'               => $this->num($row[24] ?? null),
            'food'                    => $this->num($row[25] ?? null),
            'airtimebasic'            => $this->num($row[26] ?? null),
            'airtime'                 => $this->num($row[27] ?? null),
            'nightallo'               => $this->num($row[28] ?? null),
            'responsebasic'           => $this->num($row[29] ?? null),
            'responseallo'            => $this->num($row[30] ?? null),
            'transbasic'              => $this->num($row[31] ?? null),
            'transport'               => $this->num($row[32] ?? null),
            'medicalbasic'            => $this->num($row[33] ?? null),
            'medical'                 => $this->num($row[34] ?? null),
            'total_allowance'         => $this->num($row[35] ?? null),
            'gross'                   => $this->num($row[36] ?? null),
            'total_cash_emolument'    => null,
            'total_assessable_income' => null,
            'emp_ssnit_13_percent'    => $this->num($row[37] ?? null),
            'ssnit_5_5_percent'       => $this->num($row[38] ?? null),
            'petra'                   => $this->num($row[39] ?? null),
            'taxable_income'          => $this->num($row[40] ?? null),
            'paye'                    => $this->num($row[41] ?? null),
            'ot_tax'                  => $this->num($row[42] ?? null),
            'total_tax'               => $this->num($row[43] ?? null),
            'absent_without_pay'      => $this->num($row[44] ?? null),
            'salaryloan'              => $this->num($row[45] ?? null),
            'total_deductions'        => $this->num($row[46] ?? null),
            'refunds'                 => $this->num($row[47] ?? null),
            'net_pay'                 => $this->num($row[48] ?? null),
        ];
    }

    /** Coerce a cell to float or null. */
    private function num($value): ?float
    {
        if ($value === null || $value === '') return null;
        $cleaned = str_replace([',', ' ', "\u{00A0}", 'GHS', 'GH₵', '₵'], '', (string) $value);
        return is_numeric($cleaned) ? (float) $cleaned : null;
    }

    // ─────────────────────────────────────────────────────────────────────
    //  DELETE  (unchanged)
    // ─────────────────────────────────────────────────────────────────────
    public function delete(Request $request)
    {
        $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
        $date = Carbon::createFromFormat('Y-m', $selectedMonth);
        $tableName = 'shefer' . strtolower($date->format('F')) . $date->format('y');

        try {
            if (Schema::hasTable($tableName)) {
                DB::table($tableName)->truncate();
                Log::info("SheferPay delete: truncated {$tableName}");
                return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
                    ->with('success', 'Records deleted successfully.');
            }
            return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
                ->with('error', 'No records found for the selected month.');
        } catch (\Exception $e) {
            Log::error('SheferPay delete failed', ['error' => $e->getMessage()]);
            return redirect()->route('sheferpay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to delete records: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    //  TEMPLATE — NOW 51 COLUMNS (legacy 49 + 2 new)
    // ─────────────────────────────────────────────────────────────────────
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID', 'Name', 'Position', 'Department', 'Start Date',
            'Basic Salary', 'Total Weekdays', 'Days Worked', 'Leave Pay', 'Absent Days', 'Basic Earned',
            'Night Days',
            'Weekday OT Hours', 'Weekday OT Rate', 'Weekday OT Amount',
            'Weekend OT Hours', 'Weekend OT Rate', 'Weekend OT Amount',
            'Sunday OT Hours', 'Sunday OT Rate', 'Sunday OT Amount',
            'Fixed OT Basic', 'Fixed OT', 'Total Overtime',
            'Food Basic', 'Food Allowance',
            'Airtime Basic', 'Airtime Allowance',
            'Night Allowance',
            'Response Basic', 'Response Allowance',
            'Transport Basic', 'Transport Allowance',
            'Medical Basic', 'Medical Allowance',
            'Total Allowance',
            'Gross Salary',
            'Total Cash Emolument',        // ★ NEW (col AL)
            'Total Assessable Income',     // ★ NEW (col AM)
            'Employer SSNIT (13%)',
            'Employee SSNIT (5.5%)',
            'PETRA',
            'Taxable Income', 'PAYE', 'OT Tax', 'Total Tax',
            'Absent Without Pay', 'Salary Loan',
            'Total Deductions', 'Refunds', 'Net Pay',
        ];

        $sheet->fromArray($headers, null, 'A1');

        // 51 columns → AY is the last column
        $lastCol = 'AY';
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDDE6');

        $exampleData = [
            'ST00123', 'Akosua Boateng', 'Team Leader', 'Operations', '2023-01-10',
            5800.00, 22, 20.5, 200.00, 1.5, 5270.45,
            8,
            12.5, 55.00, 687.50,
            16.0, 110.00, 1760.00,
            4.0, 165.00, 660.00,
            500.00, 750.00, 3097.50,
            400.00, 400.00,
            300.00, 300.00,
            600.00,
            200.00, 200.00, 200.00,
            400.00, 400.00,
            500.00, 500.00,
            3900.00,      // Total Allowance
            10867.95,     // Gross Salary
            7770.45,      // Total Cash Emolument (NEW) = Gross − OT
            10867.95,     // Total Assessable Income (NEW) = Gross
            1412.83,      // Employer SSNIT 13%
            597.74,       // Employee SSNIT 5.5%
            500.00,       // PETRA
            9270.21,      // Taxable Income
            1245.80,      // PAYE
            309.75,       // OT Tax
            1555.55,      // Total Tax
            341.00,       // Absent Without Pay
            500.00,       // Salary Loan
            2346.55,      // Total Deductions
            150.00,       // Refunds
            8270.90,      // Net Pay
        ];

        $sheet->fromArray($exampleData, null, 'A2');

        // Autosize all columns
        for ($i = 1; $i <= count($headers); $i++) {
            $sheet->getColumnDimension(
                \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i)
            )->setAutoSize(true);
        }

        // Instruction note
        $sheet->setCellValue(
            'A' . ($sheet->getHighestRow() + 3),
            'INSTRUCTIONS: Fill only the required fields. All amounts in GHS. '
            . 'Do NOT add commas, GH¢ or currency symbols to number columns. '
            . 'Dates: DD/MM/YYYY or MM/DD/YYYY. Do NOT edit the header row.'
        );

        $writer   = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'Shefer_Payroll_Template_' . date('M-Y') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }
}