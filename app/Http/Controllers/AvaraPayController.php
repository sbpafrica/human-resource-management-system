<?php

namespace App\Http\Controllers;

use App\Models\Avarapay;
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

class AvaraPayController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    /**
     * Convert any monetary/numeric cell value to a rounded decimal (2dp).
     * Strips currency symbols, commas, spaces. Returns null for empty cells.
     */
    private function toDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        // Remove currency symbols, spaces, commas
        $cleaned = preg_replace('/[^\d.\-]/', '', str_replace(',', '', (string)$value));
        if (!is_numeric($cleaned)) return null;
        return round((float)$cleaned, 2);
    }

    /**
     * Convert integer cells (days/hours counts). Returns null for empty.
     */
    private function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        $cleaned = preg_replace('/[^\d\-]/', '', (string)$value);
        return is_numeric($cleaned) ? (int)$cleaned : null;
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
        $tableName = Avarapay::tableName($month);

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('Avarapay/Index', [
                'records'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'searchCount'    => null,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        $query = DB::table("{$tableName} as tp")
            ->leftJoin('bankdetails as bd', 'tp.STAFFID', '=', 'bd.employid')
            ->select(
                'tp.STAFFID',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.NAME) as NAME'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.SHOP) as SHOP'),
                'bd.bank    as BANK_BD',
                'bd.account as ACCOUNT',
                'bd.code    as CODE',
                'bd.branch  as BRANCH_BD',
                'tp.TAKE_HOME_PAY_GHC'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.STAFFID', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.NAME) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.SHOP) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort  = $request->input('sort', 'STAFFID');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'STAFFID'           => 'tp.STAFFID',
            'NAME'              => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.NAME)'),
            'SHOP'              => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.SHOP)'),
            'BANK'              => 'bd.bank',
            'TAKE_HOME_PAY_GHC' => 'tp.TAKE_HOME_PAY_GHC',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.STAFFID', $order);

        $records      = $query->paginate(30)->appends($request->query());
        $totalRecords = DB::table($tableName)->count();
        $searchCount  = $search ? $query->getCountForPagination() : null;

        return Inertia::render('Avarapay/Index', [
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
            $tableName = Avarapay::tableName($selectedMonth);
            Avarapay::createTableIfNotExists($tableName);

            $reader      = new XlsxReader();
            $spreadsheet = $reader->load($request->file('file')->getRealPath());
            $rows        = $spreadsheet->getActiveSheet()->toArray();
            
            if (empty($rows)) {
                return redirect()->route('avarapay.index', ['month' => $selectedMonth])
                    ->with('error', 'The uploaded file is empty.');
            }

            $rawHeaders = array_shift($rows); // Remove header row

            // Build smart header map: clean header name -> column index
            $headerMap = [];
            foreach ($rawHeaders as $colIdx => $headerVal) {
                if ($headerVal === null) continue;
                $cleanHeader = strtoupper(trim(preg_replace('/[^A-Za-z0-9]/', '', (string)$headerVal)));
                if (!empty($cleanHeader)) {
                    $headerMap[$cleanHeader] = $colIdx;
                }
            }

            $existingIds  = DB::table($tableName)->pluck('STAFFID')->toArray();
            $errors       = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
                // Skip completely empty rows
                $hasContent = false;
                foreach ($row as $cell) {
                    if ($cell !== null && trim((string)$cell) !== '') {
                        $hasContent = true;
                        break;
                    }
                }
                if (!$hasContent) continue;

                $rowNum = $index + 2;

                // Smart cell retriever by alias or fallback index
                $getCell = function(array $aliases, int $defaultIdx) use ($row, $headerMap) {
                    foreach ($aliases as $alias) {
                        $cleanAlias = strtoupper(trim(preg_replace('/[^A-Za-z0-9]/', '', $alias)));
                        if (isset($headerMap[$cleanAlias])) {
                            $val = $row[$headerMap[$cleanAlias]] ?? null;
                            if ($val !== null && trim((string)$val) !== '') {
                                return $val;
                            }
                        }
                    }
                    return $row[$defaultIdx] ?? null;
                };

                $staffid = trim((string)$getCell(['STAFFID', 'STAFF_ID', 'ID', 'EMPLOYEE_ID'], 0));
                $name    = trim((string)$getCell(['NAME', 'EMPLOYEE_NAME', 'FULL_NAME'], 1));

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

                // Parse JOIN_DATE
                $joinDate = null;
                $rawDate  = trim((string)$getCell(['JOIN_DATE', 'START_DATE', 'DATE_JOINED'], 3));
                if (!empty($rawDate)) {
                    try {
                        $joinDate = is_numeric($rawDate)
                            ? \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$rawDate)->format('Y-m-d')
                            : Carbon::parse($rawDate)->format('Y-m-d');
                    } catch (\Exception $e) {
                        // leave null
                    }
                }

                $rawTakeHome = $getCell(['TAKE_HOME_PAY_GHC', 'TAKE_HOME_PAY', 'NET_PAY_GHC', 'NET_PAY', 'TAKE_HOME', 'NET_SALARY'], 37);
                $takeHomePay = $this->toDecimal($rawTakeHome);

                $basicSalary       = $this->toDecimal($getCell(['BASIC_SALARY_GHC', 'BASIC_SALARY', 'BASE_SALARY', 'BASIC'], 5));
                $basicSalaryEarned = $this->toDecimal($getCell(['BASIC_SALARY_EARNED_GHC', 'BASIC_SALARY_EARNED', 'EARNED_BASIC', 'ACTUAL_BASIC'], 11));

                // Fallback Net Pay if null
                if ($takeHomePay === null) {
                    $grossSalary = $this->toDecimal($getCell(['GROSS_SALARY_GHC', 'GROSS_SALARY', 'GROSS_PAY', 'GROSS'], 23));
                    if ($grossSalary !== null && $grossSalary > 0) {
                        $ssnitEe  = $this->toDecimal($getCell(['SSNIT_EE_0_5_PERCENT', 'SSNIT_EE_5_5_PERCENT', 'SSNIT_EE_5_5', 'SSNIT_EE'], 30)) ?? 0;
                        $paye     = $this->toDecimal($getCell(['PAYE_GHC', 'PAYE', 'TAX'], 33)) ?? 0;
                        $petra    = $this->toDecimal($getCell(['PETRA_5_PERCENT', 'PETRA_5', 'PETRA'], 31)) ?? 0;
                        $takeHomePay = max(0, $grossSalary - $ssnitEe - $paye - $petra);
                    }
                }

                if ($takeHomePay === null) {
                    $errors[] = "Row {$rowNum}: Take Home Pay (Net Pay) is required and must be a valid number.";
                    continue;
                }

                $validRecords[] = [
                    'STAFFID'                               => $staffid,
                    'NAME'                                  => $name,
                    'POSITION'                              => trim((string)$getCell(['POSITION', 'TITLE', 'JOB_TITLE', 'DESIGNATION'], 2)) ?: null,
                    'JOIN_DATE'                             => $joinDate,
                    'SHOP'                                  => trim((string)$getCell(['SHOP', 'DEPARTMENT', 'BRANCH', 'LOCATION', 'WORKAREA'], 4)) ?: null,

                    'BASIC_SALARY_GHC'                      => $basicSalary,
                    'LEGAL_WORK_DAYS'                       => $this->toInt($getCell(['LEGAL_WORK_DAYS', 'EXPECTED_DAYS', 'TOTAL_DAYS'], 6)),
                    'NUMBER_OF_DAYS_WORKED'                 => $this->toInt($getCell(['NUMBER_OF_DAYS_WORKED', 'DAYS_WORKED', 'ACTUAL_DAYS', 'WORK_DAYS'], 7)),
                    'LEAVE_DAYS'                            => $this->toInt($getCell(['LEAVE_DAYS', 'LEAVE'], 8)),
                    'ABSENT_DAYS'                           => $this->toInt($getCell(['ABSENT_DAYS', 'ABSENT'], 9)),
                    'ABSENT_DAYS_DEDUCTIONS_GHC'            => $this->toDecimal($getCell(['ABSENT_DAYS_DEDUCTIONS_GHC', 'ABSENT_DEDUCTION', 'ABSENT_DEDUCTIONS'], 10)),
                    'BASIC_SALARY_EARNED_GHC'               => $basicSalaryEarned ?: $basicSalary,
                    'TRANSPORT_GHC'                         => $this->toDecimal($getCell(['TRANSPORT_GHC', 'TRANSPORT', 'ALLOWANCE', 'TRANSPORT_ALLOWANCE'], 12)),
                    'WEEKDAY_OVERTIME_HRS'                  => $this->toDecimal($getCell(['WEEKDAY_OVERTIME_HRS', 'WEEKDAY_OT_HRS', 'NORMAL_OT_HRS'], 13)),
                    'WEEKDAY_OVERTIME_RATE'                 => $this->toDecimal($getCell(['WEEKDAY_OVERTIME_RATE', 'WEEKDAY_OT_RATE'], 14)),
                    'WEEKDAY_OVERTIME_AMOUNT_GHC'           => $this->toDecimal($getCell(['WEEKDAY_OVERTIME_AMOUNT_GHC', 'WEEKDAY_OT_AMOUNT', 'NORMAL_OVERTIME'], 15)),
                    'WEEKEND_OVERTIME_HRS'                  => $this->toDecimal($getCell(['WEEKEND_OVERTIME_HRS', 'WEEKEND_OT_HRS', 'SATURDAY_OT_HRS'], 16)),
                    'WEEKEND_OVERTIME_RATE'                 => $this->toDecimal($getCell(['WEEKEND_OVERTIME_RATE', 'WEEKEND_OT_RATE'], 17)),
                    'WEEKEND_OVERTIME_AMOUNT_GHC'           => $this->toDecimal($getCell(['WEEKEND_OVERTIME_AMOUNT_GHC', 'WEEKEND_OT_AMOUNT', 'SATURDAY_OVERTIME'], 18)),
                    'HOLIDAY_OVERTIME_HRS'                  => $this->toDecimal($getCell(['HOLIDAY_OVERTIME_HRS', 'HOLIDAY_OT_HRS'], 19)),
                    'HOLIDAY_OVERTIME_RATE'                 => $this->toDecimal($getCell(['HOLIDAY_OVERTIME_RATE', 'HOLIDAY_OT_RATE'], 20)),
                    'HOLIDAY_OVERTIME_AMOUNT_GHC'           => $this->toDecimal($getCell(['HOLIDAY_OVERTIME_AMOUNT_GHC', 'HOLIDAY_OT_AMOUNT', 'HOLIDAY_OVERTIME'], 21)),
                    'TOTAL_OVERTIME_AMOUNT_GHC'             => $this->toDecimal($getCell(['TOTAL_OVERTIME_AMOUNT_GHC', 'TOTAL_OVERTIME_AMOUNT', 'OVERTIME'], 22)),
                    'GROSS_SALARY_GHC'                      => $this->toDecimal($getCell(['GROSS_SALARY_GHC', 'GROSS_SALARY', 'GROSS_PAY', 'GROSS'], 23)),
                    'MANAGEMENT_FEE_8_PERCENT'              => $this->toDecimal($getCell(['MANAGEMENT_FEE_8_PERCENT', 'MANAGEMENT_FEE_8', 'MANAGEMENT_FEE'], 24)),
                    'GETFUND_2_5_PERCENT'                   => $this->toDecimal($getCell(['GETFUND_2_5_PERCENT', 'GETFUND_2_5', 'GETFUND'], 25)),
                    'NHIL_2_5_PERCENT'                      => $this->toDecimal($getCell(['NHIL_2_5_PERCENT', 'NHIL_2_5', 'NHIL'], 26)),
                    'VAT_15_PERCENT'                        => $this->toDecimal($getCell(['VAT_15_PERCENT', 'VAT_15', 'VAT'], 27)),
                    'SSF_13_PERCENT'                        => $this->toDecimal($getCell(['SSF_13_PERCENT', 'SSF_13', 'SSNIT_ER_13'], 28)),
                    'COST_TO_AVARA_GHC'                     => $this->toDecimal($getCell(['COST_TO_AVARA_GHC', 'COST_TO_AVARA'], 29)),
                    'SSNIT_EE_0_5_PERCENT'                  => $this->toDecimal($getCell(['SSNIT_EE_0_5_PERCENT', 'SSNIT_EE_5_5_PERCENT', 'SSNIT_EE_5_5', 'SSNIT_EE', 'SSNIT_5_5'], 30)),
                    'PETRA_5_PERCENT'                       => $this->toDecimal($getCell(['PETRA_5_PERCENT', 'PETRA_5', 'PETRA', 'PETRA_FUND'], 31)),
                    'TAXABLE_INCOME_GHC'                    => $this->toDecimal($getCell(['TAXABLE_INCOME_GHC', 'TAXABLE_INCOME', 'TAXABLE'], 32)),
                    'PAYE_GHC'                              => $this->toDecimal($getCell(['PAYE_GHC', 'PAYE', 'TAX'], 33)),
                    'OVERTIME_TAX_GHC'                      => $this->toDecimal($getCell(['OVERTIME_TAX_GHC', 'OVERTIME_TAX'], 34)),
                    'DEDUCTIONS_SURCHARGE_STUDENT_LOAN_GHC' => $this->toDecimal($getCell(['DEDUCTIONS_SURCHARGE_STUDENT_LOAN_GHC', 'STUDENT_LOAN', 'SURCHARGE', 'OTHER_DEDUCTIONS'], 35)),
                    'TOTAL_DEDUCTIONS_GHC'                  => $this->toDecimal($getCell(['TOTAL_DEDUCTIONS_GHC', 'TOTAL_DEDUCTIONS', 'TOTAL_DEDUCTION'], 36)),
                    'TAKE_HOME_PAY_GHC'                     => $takeHomePay,

                    'SSNIT'          => trim((string)$getCell(['SSNIT', 'SSNIT_NO', 'SSNIT_NUMBER'], 38)) ?: null,
                    'GH_CARD'        => trim((string)$getCell(['GH_CARD', 'GHANA_CARD', 'GH_CARD_NUMBER'], 39)) ?: null,
                    'BANK'           => trim((string)$getCell(['BANK', 'BANK_NAME'], 40)) ?: null,
                    'ACCOUNT_NUMBER' => trim((string)$getCell(['ACCOUNT_NUMBER', 'ACCOUNT_NO', 'ACCOUNT'], 41)) ?: null,
                    'BRANCH'         => trim((string)$getCell(['BRANCH', 'BANK_BRANCH'], 42)) ?: null,
                ];

                $existingIds[] = $staffid;
            }

            if (!empty($errors)) {
                $msg = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('avarapay.index', ['month' => $selectedMonth])->with('error', $msg);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("AvaraPay upload: inserted " . count($validRecords) . " rows into {$tableName}");
            }

            return redirect()->route('avarapay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');

        } catch (\Exception $e) {
            Log::error('AvaraPay upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('avarapay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // DELETE
    // ─────────────────────────────────────────────────────────────────
    public function delete(Request $request)
    {
        $selectedMonth = $request->input('month', now()->format('Y-m'));
        $tableName     = Avarapay::tableName($selectedMonth);

        try {
            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                DB::table($tableName)->truncate();
                return redirect()->route('avarapay.index', ['month' => $selectedMonth])
                    ->with('success', 'Records deleted successfully.');
            }
            return redirect()->route('avarapay.index', ['month' => $selectedMonth])
                ->with('error', 'No records found for the selected month.');
        } catch (\Exception $e) {
            Log::error('AvaraPay delete failed', ['error' => $e->getMessage()]);
            return redirect()->route('avarapay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to delete records: ' . $e->getMessage());
        }
    }

   // ─────────────────────────────────────────────────────────────────
// EXPORT  — mirrors the salary summary table exactly
// Columns: No. | Staff ID | Name | Shop/Dept | Bank | Account | Branch Code | Branch | Net Pay
// ─────────────────────────────────────────────────────────────────
public function export(Request $request)
{
    try {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $month     = $request->input('month');
        $date      = Carbon::createFromFormat('Y-m', $month);
        $tableName = Avarapay::tableName($month);

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        // ── Same query as index() ────────────────────────────────────
        $query = DB::table("{$tableName} as tp")
            ->leftJoin('bankdetails as bd', 'tp.STAFFID', '=', 'bd.employid')
            ->select(
                'tp.STAFFID',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.NAME) as NAME'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.SHOP) as SHOP'),
                'bd.bank    as BANK_BD',
                'bd.account as ACCOUNT',
                'bd.code    as CODE',
                'bd.branch  as BRANCH_BD',
                'tp.TAKE_HOME_PAY_GHC'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.STAFFID', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.NAME) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.SHOP) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort    = $request->input('sort', 'STAFFID');
        $order   = $request->input('order', 'asc');
        $sortMap = [
            'STAFFID'           => 'tp.STAFFID',
            'NAME'              => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.NAME)'),
            'SHOP'              => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.SHOP)'),
            'BANK'              => 'bd.bank',
            'TAKE_HOME_PAY_GHC' => 'tp.TAKE_HOME_PAY_GHC',
        ];
        $query->orderBy($sortMap[$sort] ?? 'tp.STAFFID', $order);

        $records = $query->get();

        // ── Build spreadsheet ────────────────────────────────────────
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Salary Summary');

        // Headers — exactly matching the on-screen table
        $headers = [
            'No.',
            'Staff ID',
            'Name',
            'Shop / Dept',
            'Bank',
            'Account',
            'Branch Code',
            'Branch',
            'Net Pay (GHC)',
        ];
        $sheet->fromArray($headers, null, 'A1');

        // Header styling
        $sheet->getStyle('A1:I1')->getFont()->setBold(true)->setSize(11)->setName('Calibri');
        $sheet->getStyle('A1:I1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDEFE');

        // Data rows
        $row = 2;
        foreach ($records as $i => $r) {
            $sheet->setCellValue("A{$row}", $i + 1);                                  // No.
            $sheet->setCellValue("B{$row}", $r->STAFFID);                              // Staff ID
            $sheet->setCellValue("C{$row}", $r->NAME);                                 // Name
            $sheet->setCellValue("D{$row}", $r->SHOP ?? '');                           // Shop / Dept
            $sheet->setCellValue("E{$row}", $r->BANK_BD ?? '');                        // Bank
            $sheet->setCellValueExplicit("F{$row}", $r->ACCOUNT ?? '', DataType::TYPE_STRING);  // Account (keep leading zeros)
            $sheet->setCellValueExplicit("G{$row}", $r->CODE ?? '', DataType::TYPE_STRING);     // Branch Code
            $sheet->setCellValue("H{$row}", $r->BRANCH_BD ?? '');                      // Branch
            $sheet->setCellValue("I{$row}",                                            // Net Pay
                $r->TAKE_HOME_PAY_GHC !== null
                    ? number_format((float)$r->TAKE_HOME_PAY_GHC, 2, '.', '')
                    : ''
            );
            $row++;
        }

        // Add a totals row at the bottom
        $totalNetPay = $records->sum(fn($r) => (float)($r->TAKE_HOME_PAY_GHC ?? 0));
        $sheet->setCellValue("H{$row}", 'TOTAL');
        $sheet->setCellValue("I{$row}", number_format($totalNetPay, 2, '.', ''));
        $sheet->getStyle("H{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal('right');

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(6);   // No.
        $sheet->getColumnDimension('B')->setWidth(14);  // Staff ID
        $sheet->getColumnDimension('C')->setWidth(28);  // Name
        $sheet->getColumnDimension('D')->setWidth(26);  // Shop
        $sheet->getColumnDimension('E')->setWidth(20);  // Bank
        $sheet->getColumnDimension('F')->setWidth(18);  // Account
        $sheet->getColumnDimension('G')->setWidth(14);  // Branch Code
        $sheet->getColumnDimension('H')->setWidth(20);  // Branch
        $sheet->getColumnDimension('I')->setWidth(16);  // Net Pay

        // Right-align the Net Pay column
        $sheet->getStyle("I2:I{$row}")
            ->getAlignment()->setHorizontal('right');

        // Freeze the header row
        $sheet->freezePane('A2');

        $writer   = new Xlsx($spreadsheet);
        $filename = "avara_salary_summary_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error('AvaraPay export failed', ['error' => $e->getMessage()]);
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
            'STAFFID', 'NAME', 'POSITION', 'JOIN_DATE', 'SHOP',
            'BASIC_SALARY_GHC', 'LEGAL_WORK_DAYS', 'NUMBER_OF_DAYS_WORKED',
            'LEAVE_DAYS', 'ABSENT_DAYS', 'ABSENT_DAYS_DEDUCTIONS_GHC',
            'BASIC_SALARY_EARNED_GHC', 'TRANSPORT_GHC',
            'WEEKDAY_OVERTIME_HRS', 'WEEKDAY_OVERTIME_RATE', 'WEEKDAY_OVERTIME_AMOUNT_GHC',
            'WEEKEND_OVERTIME_HRS', 'WEEKEND_OVERTIME_RATE', 'WEEKEND_OVERTIME_AMOUNT_GHC',
            'HOLIDAY_OVERTIME_HRS', 'HOLIDAY_OVERTIME_RATE', 'HOLIDAY_OVERTIME_AMOUNT_GHC',
            'TOTAL_OVERTIME_AMOUNT_GHC', 'GROSS_SALARY_GHC',
            'MANAGEMENT_FEE_8_PERCENT', 'GETFUND_2_5_PERCENT', 'NHIL_2_5_PERCENT',
            'VAT_15_PERCENT', 'SSF_13_PERCENT', 'COST_TO_AVARA_GHC',
            'SSNIT_EE_0_5_PERCENT', 'PETRA_5_PERCENT',
            'TAXABLE_INCOME_GHC', 'PAYE_GHC', 'OVERTIME_TAX_GHC',
            'DEDUCTIONS_SURCHARGE_STUDENT_LOAN_GHC',
            'TOTAL_DEDUCTIONS_GHC', 'NET_PAY_GHC',   // ← labelled Net Pay in template too
            'SSNIT', 'GH_CARD', 'BANK', 'ACCOUNT_NUMBER', 'BRANCH',
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:AQ1')->getFont()->setBold(true);
        $sheet->getStyle('A1:AQ1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDEFE');

        // Sample row
        $sample = [
            'AVR001', 'Ama Serwaa', 'Factory Operator', '2024-01-15', 'Production',
            3500.00, 26, 26, 0, 0, 0.00,
            3500.00, 400.00,
            2.00, 21.63, 43.27,
            0.00, 0.00, 0.00,
            0.00, 0.00, 0.00,
            43.27, 3943.27,
            314.66, 98.58, 98.58, 591.49, 512.63, 5559.21,
            196.66, 197.16,
            3343.27, 350.00, 21.64,
            0.00,
            567.66, 3375.61,
            'C1234567890', 'GHA-000111222-3',
            'GCB Bank', '0012345678', 'Accra Main',
        ];

        $sheet->fromArray($sample, null, 'A2');

        foreach (range('A', 'Z') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        foreach (['AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ'] as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }

        $writer   = new Xlsx($spreadsheet);
        $filename = 'avara_payroll_template_' . date('M-Y') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $filename);
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }
}
