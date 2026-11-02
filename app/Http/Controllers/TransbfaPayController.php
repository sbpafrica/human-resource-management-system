<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;                 // ← FIX #1 (auto-detect xlsx/xls/csv/ods)
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class TransbfaPayController extends Controller
{
    /** Build the monthly table name. E.g. April 2026 → transburkinaapril26 */
    private function buildTableName(string $month): string
    {
        $date = Carbon::createFromFormat('Y-m', $month);
        return 'transburkina' . strtolower($date->format('F')) . $date->format('y');
    }

    /** Safely parse a Y-m string, falling back to the current month. */
    private function parseMonthOrNow(?string $month): array
    {
        try {
            $date = Carbon::createFromFormat('Y-m', (string) $month);
            // Guard against overflows like "2026-13"
            if ($date->format('Y-m') !== (string) $month) {
                throw new \InvalidArgumentException('Invalid month');
            }
            return [$month, $date];
        } catch (\Throwable $e) {
            $now = now();
            return [$now->format('Y-m'), $now];
        }
    }

    private function createTableIfNotExists(string $tableName): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `staffid`        VARCHAR(255) NOT NULL,
                `firstname`      VARCHAR(255) DEFAULT NULL,
                `lastname`       VARCHAR(255) DEFAULT NULL,
                `fullname`       VARCHAR(255) DEFAULT NULL,
                `department`     VARCHAR(255) DEFAULT NULL,
                `dept2`          VARCHAR(255) DEFAULT NULL,
                `cost`           VARCHAR(255) DEFAULT NULL,
                `center`         VARCHAR(255) DEFAULT NULL,
                `hiredate`       VARCHAR(255) DEFAULT NULL,
                `departure`      VARCHAR(255) DEFAULT NULL,
                `constart`       VARCHAR(255) DEFAULT NULL,
                `conend`         VARCHAR(255) DEFAULT NULL,
                `corporatecode`  VARCHAR(255) DEFAULT NULL,
                `position`       VARCHAR(255) DEFAULT NULL,
                `grade`          VARCHAR(255) DEFAULT NULL,
                `workplace`      VARCHAR(255) DEFAULT NULL,
                `bank`           VARCHAR(255) DEFAULT NULL,
                `alcnum`         VARCHAR(255) DEFAULT NULL,
                `cnssno`         VARCHAR(255) DEFAULT NULL,
                `month`          DOUBLE DEFAULT NULL,
                `lwork`          DOUBLE DEFAULT NULL,
                `acwork`         DOUBLE DEFAULT NULL,
                `basic`          DOUBLE DEFAULT NULL,
                `transport`      DOUBLE DEFAULT NULL,
                `housing`        DOUBLE DEFAULT NULL,
                `work`           DOUBLE DEFAULT NULL,
                `kpistandard`    DOUBLE DEFAULT NULL,
                `kpiindex`       DOUBLE DEFAULT NULL,
                `kpirank`        VARCHAR(13) DEFAULT NULL,
                `kpiactual`      DOUBLE DEFAULT NULL,
                `overtime`       DOUBLE DEFAULT NULL,
                `salescomm`      DOUBLE DEFAULT NULL,
                `progra`         DOUBLE DEFAULT NULL,
                `grossnew`       DOUBLE DEFAULT NULL,
                `lateness`       DOUBLE DEFAULT NULL,
                `casual`         DOUBLE DEFAULT NULL,
                `otherdeduct`    DOUBLE DEFAULT NULL,
                `gross`          DOUBLE DEFAULT NULL,
                `cnssexonere`    DOUBLE DEFAULT NULL,
                `salaireimpos`   DOUBLE DEFAULT NULL,
                `transexonere`   DOUBLE DEFAULT NULL,
                `logemexoneree`  DOUBLE DEFAULT NULL,
                `fonctexonere`   DOUBLE DEFAULT NULL,
                `abattem`        DOUBLE DEFAULT NULL,
                `iutsimpos`      DOUBLE DEFAULT NULL,
                `iutsarrondi`    DOUBLE DEFAULT NULL,
                `iutssalaire`    DOUBLE DEFAULT NULL,
                `enfant`         VARCHAR(100) DEFAULT NULL,
                `iutsnet`        DOUBLE DEFAULT NULL,
                `cnsssalaire`    DOUBLE DEFAULT NULL,
                `ecarts`         DOUBLE DEFAULT NULL,
                `nap`            DOUBLE DEFAULT NULL,
                `nom`            VARCHAR(255) DEFAULT NULL,
                `tpa`            DOUBLE DEFAULT NULL,
                `av1`            DOUBLE DEFAULT NULL,
                `rp1`            DOUBLE DEFAULT NULL,
                `pf1`            DOUBLE DEFAULT NULL,
                `total`          DOUBLE DEFAULT NULL,
                `couttotal`      DOUBLE DEFAULT NULL,
                `frais`          DOUBLE DEFAULT NULL,
                `tva`            DOUBLE DEFAULT NULL,
                `fact`           DOUBLE DEFAULT NULL,
                `wht`            DOUBLE DEFAULT NULL,
                `finalnetpay`    DOUBLE DEFAULT NULL,
                `brand`          VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (`staffid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * FIX #10 — central whitelist used by both index() and export() so the two
     * never drift apart.
     */
    private function resolveSortColumn(string $sort, string $tableAlias = 't', string $bankAlias = 'b'): ?string
    {
        return match ($sort) {
            'staffid'     => "{$tableAlias}.staffid",
            'fullname'    => "{$tableAlias}.fullname",
            'finalnetpay' => "{$tableAlias}.finalnetpay",
            'bankname'    => "{$bankAlias}.bankname",   // handled specially below
            default       => "{$tableAlias}.fullname",
        };
    }

    private function applySearch($query, string $search, string $tableAlias = 't', string $bankAlias = 'b'): void
    {
        if ($search === '') {
            return;
        }
        $query->where(function ($q) use ($search, $tableAlias, $bankAlias) {
            $q->where("{$tableAlias}.staffid",   'like', "%{$search}%")
              ->orWhere("{$tableAlias}.fullname", 'like', "%{$search}%")
              ->orWhere("{$bankAlias}.bankname",  'like', "%{$search}%")
              ->orWhere("{$bankAlias}.accountnum",'like', "%{$search}%");
        });
    }

    /**
     * FIX #3 + #4 — NULL-safe sort direction for bankname, plus a stable
     * secondary sort on staffid so pagination doesn't shuffle rows.
     */
    private function applySort($query, string $sort, string $order, string $tableAlias = 't', string $bankAlias = 'b'): void
    {
        $direction = $order === 'desc' ? 'desc' : 'asc';

        if ($sort === 'bankname') {
            // Non-null banks first, then null banks last, regardless of direction
            $query->orderByRaw("{$bankAlias}.bankname IS NULL, {$bankAlias}.bankname {$direction}");
        } else {
            $column = $this->resolveSortColumn($sort, $tableAlias, $bankAlias);
            $query->orderBy($column, $direction);
        }

        // Stable tiebreaker
        $query->orderBy("{$tableAlias}.staffid", 'asc');
    }

    public function index(Request $request)
    {
        [$month, $date] = $this->parseMonthOrNow($request->input('month'));

        $search = (string) $request->input('search', '');
        $sort   = (string) $request->input('sort', 'fullname');
        $order  = (string) $request->input('order', 'asc');

        $tableName = $this->buildTableName($month);

        if (!Schema::hasTable($tableName)) {
            return Inertia::render('TranssionBfa/Pay/Index', [
                'records'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15),
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'hasBank'        => 0,
                'noBank'         => 0,
                'searchCount'    => null,
                'search'         => $search,
                'sort'           => $sort,
                'order'          => $order,
                'flash'          => [
                    'error' => "No data available for {$date->format('F Y')}. Please upload data.",
                ],
            ]);
        }

        $query = DB::table("{$tableName} as t")
            ->leftJoin('bfabank as b', 't.staffid', '=', 'b.staffida')
            ->select(
                't.staffid',
                't.fullname',
                't.finalnetpay',
                'b.bankname',
                'b.accountnum',
                'b.employname'
            );

        $this->applySearch($query, $search);
        $this->applySort($query, $sort, $order);

        $records = $query->paginate(15)->appends([
            'month'  => $month,
            'search' => $search,
            'sort'   => $sort,
            'order'  => $order,
        ]);

        $totalRecords = DB::table($tableName)->count();
        $searchCount  = $search !== '' ? $records->total() : null;

        // FIX #5 — COUNT(DISTINCT) so duplicate bfabank rows don't inflate the metric
        $hasBank = (int) DB::table("{$tableName} as t")
            ->leftJoin('bfabank as b', 't.staffid', '=', 'b.staffida')
            ->whereNotNull('b.bankname')
            ->selectRaw('COUNT(DISTINCT t.staffid) as c')
            ->value('c');

        $noBank = max(0, $totalRecords - $hasBank);

        return Inertia::render('TranssionBfa/Pay/Index', [
            'records'        => $records,
            'selectedMonth'  => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords'   => $totalRecords,
            'hasBank'        => $hasBank,
            'noBank'         => $noBank,
            'searchCount'    => $searchCount,
            'search'         => $search,
            'sort'           => $sort,
            'order'          => $order,
            'flash'          => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file'  => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:10240'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $month     = $request->input('month');
        $tableName = $this->buildTableName($month);

        try {
            $this->createTableIfNotExists($tableName);

            $file = $request->file('file');

            // FIX #1 — auto-detect reader from file extension
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet       = $spreadsheet->getActiveSheet();

            // FIX #2 — formatData = false → raw floats/ints instead of "1,234.56"
            $rows = $sheet->toArray(null, true, false, false);

            if (empty($rows)) {
                return redirect()->back()->with('error', 'Uploaded file is empty.');
            }

            // ── Detect header row ──────────────────────────────────────────
            $headerRowIdx = -1;
            foreach ($rows as $rIdx => $row) {
                foreach ($row as $cell) {
                    $cleanCell = strtolower(trim((string) $cell));
                    if (str_contains($cleanCell, 'staff.no')
                        || str_contains($cleanCell, 'staff no')
                        || str_contains($cleanCell, 'staff id')
                        || str_contains($cleanCell, 'full name')
                        || str_contains($cleanCell, 'nom et prenoms')
                        || str_contains($cleanCell, 'nom et prenom')) {
                        $headerRowIdx = $rIdx;
                        break 2;
                    }
                }
            }
            if ($headerRowIdx === -1) {
                $headerRowIdx = 0;
            }

            // ── Merge headers from headerRowIdx downward ────────────────────
            $mergedHeaders = [];
            for ($r = max(0, $headerRowIdx); $r < min($headerRowIdx + 3, count($rows)); $r++) {
                foreach ($rows[$r] as $idx => $cell) {
                    $clean = strtolower(preg_replace('/\s+/', ' ', trim((string) $cell)));
                    if ($clean !== '' && !isset($mergedHeaders[$idx])) {
                        $mergedHeaders[$idx] = $clean;
                    }
                }
            }

            $aliasMap = [
                'staffid'        => ['staff.no', 'staff no', 'staff id', 'staff_id', 'staff_no', 'no.'],
                'firstname'      => ['first name', 'fisrt name', 'first_name', 'prenom'],
                'lastname'       => ['last name', 'last_name', 'nom de famille'],
                'fullname'       => ['full name', 'full_name', 'nom et prenoms', 'nom et prenom'],
                'department'     => ['1st dept.', '1st dept', 'department', '1st department'],
                'dept2'          => ['2nd dept.', '2nd dept', 'dept2', '2nd department'],
                'cost'           => ['cost center no', 'cost center_no', 'cost'],
                'center'         => ['cost center name', 'cost_center_name', 'center'],
                'hiredate'       => ['hire date', 'hire_date', 'hiredate'],
                'departure'      => ['departure date', 'departure', 'departure_date'],
                'constart'       => ['contract start date', 'contract start', 'constart'],
                'conend'         => ['contract end date', 'contract end', 'conend'],
                'corporatecode'  => ['connected corporate code', 'corporate code', 'corporate_code'],
                'position'       => ['position'],
                'grade'          => ['job grade', 'grade', 'job_grade'],
                'workplace'      => ['work place', 'workplace', 'work_place'],
                'bank'           => ['bank name', 'bank', 'bank_name'],
                'alcnum'         => ['account number', 'account no', 'account_number', 'account_no', 'alcnum'],
                'cnssno'         => ['cnss no', 'cnss_no', 'cnss number', 'cnss num'],
                'month'          => ['salary month', 'month', 'salary_month'],
                'lwork'          => ['legal work days', 'l.work', 'legal work day'],
                'acwork'         => ['actual work days', 'ac.work', 'actual work day'],
                'basic'          => ['basic salary', 'basic', 'basic_salary'],
                'transport'      => ['transport'],
                'housing'        => ['housing'],
                'work'           => ['work'],
                'kpistandard'    => ['kpi standard', 'kpi_standard'],
                'kpiindex'       => ['kpi index', 'kpi index (0-1.5)', 'kpi_index'],
                'kpirank'        => ['kpi ranking', 'kpi rank', 'kpi_rank'],
                'kpiactual'      => ['kpi actual', 'kpiactual', 'kpi_actual'],
                'overtime'       => ['overtime', 'overtimes'],
                'salescomm'      => ['sales commission', 'sales comm', 'sales_commission'],
                'progra'         => ['other bonus', 'progra', 'other_bonus'],
                'grossnew'       => ['gross salary', 'gross new', 'gross_salary'],
                'lateness'       => ['lateness'],
                'casual'         => ['casual leave deduction', 'casual', 'casual_leave_deduction'],
                'otherdeduct'    => ['other deduction', 'other deduct', 'other_deduction'],
                'gross'          => ['final gross salary', 'gross', 'final_gross_salary'],
                'cnssexonere'    => ['cnss exonere', 'cnss_exonere', 'cnss exonérée'],
                'salaireimpos'   => ['salaire impos', 'salaire brut imposable', 'salaire_impos'],
                'transexonere'   => ['trans exonere', 'transp exonere', 'trans_exonere'],
                'logemexoneree'  => ['logem exoneree', 'logem_exoneree'],
                'fonctexonere'   => ['fonct exonere', 'fonct_exonere'],
                'abattem'        => ['abattem', 'abattem, frais/charge profes.'],
                'iutsimpos'      => ['iuts impos', 'iuts_impos', 'salaire iuts imposable'],
                'iutsarrondi'    => ['iuts arrondi', 'iuts_arrondi', 'base iuts arrondi'],
                'iutssalaire'    => ['iuts salaire', 'iuts salariale'],
                'enfant'         => ['enfant', 'enfants'],
                'iutsnet'        => ['iuts net', 'iuts_net', 'iuts retenu', 'retenue iuts', 'impot iuts', 'impôt iuts', 'i.u.t.s.', 'i.u.t.s', 'iuts'],
                'cnsssalaire'    => ['cnss salaire', 'cnss_salaire', 'cnss (5.5%)', 'cnss 5.5%', 'cnss 5,5%', 'cnss (5,5%)', 'cnss employe', 'cnss employé', 'retenue cnss', 'cotisation cnss', 'part ouvriere cnss', 'cnss'],
                'ecarts'         => ['ecarts'],
                'nap'            => ['nap', 'net pay', 'net_pay'],
                'nom'            => ['nom'],
                'tpa'            => ['tpa', 't,p,a,'],
                'av1'            => ['av1', 'a,v,'],
                'rp1'            => ['rp1', 'r,p,'],
                'pf1'            => ['pf1', 'p,f,'],
                'total'          => ['total'],
                'couttotal'      => ['cout total', 'cout_total', 'coût rh total'],
                'frais'          => ['frais', 'frais de service total 8%/4%'],
                'tva'            => ['tva', 'tva (18%)'],
                'fact'           => ['fact', 'facturation (ttc)'],
                'wht'            => ['wht', '1% wht on net salary', 'fsp', 'f.s.p.', 'f.s.p', '1% fsp', 'fsp (1%)', 'fonds de soutien patriotique', 'soutien patriotique', '1% f.s.p', 'retenue fsp'],
                'finalnetpay'    => ['final net salary', 'final net pay', 'final_net_salary', 'final_net_pay'],
                'brand'          => ['brand'],
            ];

            $dbColToIdx = [];
            foreach ($aliasMap as $dbColumn => $aliases) {
                foreach ($mergedHeaders as $idx => $hText) {
                    foreach ($aliases as $alias) {
                        if ($hText === $alias || str_contains($hText, $alias)) {
                            $dbColToIdx[$dbColumn] = $idx;
                            break 2;
                        }
                    }
                }
            }

            // FIX #10 — bail loudly instead of silently inserting 0 records
            if (!isset($dbColToIdx['staffid'])) {
                return redirect()->back()->with(
                    'error',
                    'Could not detect a "Staff ID" / "Staff No" column in the uploaded file. Please use the provided template.'
                );
            }

            $stringColumns = [
                'staffid', 'firstname', 'lastname', 'fullname', 'department', 'dept2', 'cost', 'center',
                'hiredate', 'departure', 'constart', 'conend', 'corporatecode', 'position', 'grade', 'workplace',
                'bank', 'alcnum', 'cnssno', 'kpirank', 'enfant', 'nom', 'brand',
            ];

            // FIX #9 — expanded to cover every header we might still hit
            $skipHeaderWords = [
                'no', 'no.', 'n°', 'nº', 's/n', 'sn', 'number', 'num',
                'staff.no', 'staff no', 'staff id', 'staff_id', 'staff_no',
                'legal', 'actual', 'basic', 'allowance',
                'kpi', 'overtime', 'salaires', 'transp', 'logem', 'fonct', 'abattem', 'cnss',
                'iuts', 'base', 'total', 'subtotal', 'grand total',
                'exonérations & base imposable', "employees' contributions",
                "employers' contributions", 'coût rh total',
                'nom et prenoms', 'nom et prenom', 'full name', 'name', 'names',
                'first name', 'last name', 'prenom',
            ];

            $staffidIdx = $dbColToIdx['staffid'];
            $validRecords = [];

            foreach ($rows as $row) {
                $staffid = trim((string) ($row[$staffidIdx] ?? ''));
                $cleanStaff = strtolower($staffid);

                if ($staffid === '' || in_array($cleanStaff, $skipHeaderWords, true)) {
                    continue;
                }

                $record = ['staffid' => $staffid];
                foreach ($aliasMap as $dbColumn => $aliases) {
                    if ($dbColumn === 'staffid') {
                        continue;
                    }
                    $idx = $dbColToIdx[$dbColumn] ?? null;
                    $val = $idx !== null ? ($row[$idx] ?? null) : null;

                    if (in_array($dbColumn, $stringColumns, true)) {
                        if ($val === null) {
                            $record[$dbColumn] = null;
                        } elseif (is_float($val) && $val == floor($val) && abs($val) < PHP_INT_MAX) {
                            // Large integer cell (e.g. account number) — render without scientific notation
                            $record[$dbColumn] = (string) (int) $val;
                        } else {
                            $record[$dbColumn] = trim((string) $val);
                        }
                    } else {
                        $strVal  = trim((string) ($val ?? ''));
                        $cleaned = str_replace([',', ' ', "\u{00A0}"], '', $strVal);
                        $record[$dbColumn] = is_numeric($cleaned) ? (float) $cleaned : 0;
                    }
                }

                $validRecords[] = $record;
            }

            // FIX #6 — dedupe by staffid; the DB primary key would otherwise
            // make the chunked insert fail hard on the second duplicate.
            $deduped = [];
            foreach ($validRecords as $rec) {
                $deduped[$rec['staffid']] = $rec;
            }
            $validRecords = array_values($deduped);

            // FIX #7 — atomic replace so a failure mid-insert doesn't wipe the month
            DB::transaction(function () use ($tableName, $validRecords) {
                DB::table($tableName)->truncate();
                foreach (array_chunk($validRecords, 200) as $chunk) {
                    DB::table($tableName)->insertOrIgnore($chunk);
                }
            });

            return redirect()->back()->with(
                'success',
                'Successfully uploaded ' . count($validRecords)
                . ' record(s) for ' . Carbon::createFromFormat('Y-m', $month)->format('F Y') . '.'
            );

        } catch (\Exception $e) {
            Log::error('TransbfaPay upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'Failed to process file: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID', 'First Name', 'Last Name', 'Full Name', 'Department', 'Dept2', 'Cost', 'Center',
            'Hire Date', 'Departure', 'Contract Start', 'Contract End', 'Corporate Code', 'Position', 'Grade', 'Workplace',
            'Bank', 'Account No', 'CNSS No', 'Month', 'L.Work', 'Ac.Work', 'Basic', 'Transport', 'Housing',
            'Work', 'KPI Standard', 'KPI Index', 'KPI Rank', 'KPI Actual', 'Overtime', 'Sales Comm', 'Progra',
            'Gross New', 'Lateness', 'Casual', 'Other Deduct', 'Gross', 'CNSS Exonere', 'Salaire Impos',
            'Trans Exonere', 'Logem Exoneree', 'Fonct Exonere', 'Abattem', 'IUTS Impos', 'IUTS Arrondi',
            'IUTS Salaire', 'Enfant', 'IUTS Net', 'CNSS Salaire', 'Ecarts', 'NAP', 'Nom', 'TPA', 'AV1',
            'RP1', 'PF1', 'Total', 'Cout Total', 'Frais', 'TVA', 'Fact', 'WHT', 'Final Net Pay', 'Brand',
        ];

        foreach ($headers as $i => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . '1', $header);
        }

        $totalCols   = count($headers);
        $lastCol     = Coordinate::stringFromColumnIndex($totalCols);
        $headerRange = 'A1:' . $lastCol . '1';

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e40af']],
        ]);

        for ($i = 1; $i <= $totalCols; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        $writer   = new Xlsx($spreadsheet);
        $fileName = 'transsion_bfa_payroll_template.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }

    /**
     * FIX #11 — respects the search/sort/order that the user currently has applied,
     * so the exported file matches what they see on screen.
     */
    public function export(Request $request)
    {
        [$month, $date] = $this->parseMonthOrNow($request->input('month'));

        $search = (string) $request->input('search', '');
        $sort   = (string) $request->input('sort', 'fullname');
        $order  = (string) $request->input('order', 'asc');

        $tableName = $this->buildTableName($month);

        if (!Schema::hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        try {
            $query = DB::table("{$tableName} as t")
                ->leftJoin('bfabank as b', 't.staffid', '=', 'b.staffida')
                ->select('t.staffid', 't.fullname', 'b.bankname', 'b.accountnum', 't.finalnetpay');

            $this->applySearch($query, $search);
            $this->applySort($query, $sort, $order);

            $records = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();

            $sheet->setCellValue('A1', 'Staff ID');
            $sheet->setCellValue('B1', 'Full Name');
            $sheet->setCellValue('C1', 'Bank Name');
            $sheet->setCellValue('D1', 'Account Number');
            $sheet->setCellValue('E1', 'Net Pay');

            // Force account numbers to stay as text
            $sheet->getStyle('D:D')->getNumberFormat()
                ->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

            $row      = 2;
            $totalPay = 0;
            foreach ($records as $r) {
                $sheet->setCellValue('A' . $row, $r->staffid ?? '');
                $sheet->setCellValue('B' . $row, $r->fullname ?? '');
                $sheet->setCellValue('C' . $row, $r->bankname ?? '');
                $sheet->setCellValueExplicit('D' . $row, (string) ($r->accountnum ?? ''), DataType::TYPE_STRING);
                $netPay = (float) ($r->finalnetpay ?? 0);
                $sheet->setCellValue('E' . $row, number_format($netPay, 2, '.', ''));
                $totalPay += $netPay;
                $row++;
            }

            $sheet->setCellValue('A' . $row, 'TOTAL');
            $sheet->setCellValue('E' . $row, number_format($totalPay, 2, '.', ''));
            $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);

            $sheet->getStyle('A1:E1')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e40af']],
            ]);

            foreach (range('A', 'E') as $c) {
                $sheet->getColumnDimension($c)->setAutoSize(true);
            }

            $writer   = new Xlsx($spreadsheet);
            $fileName = "transsion_bfa_salary_summary_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $fileName, [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache',
            ]);

        } catch (\Exception $e) {
            Log::error('TransbfaPay export failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }

    public function deleteMonth(Request $request)
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        [$month, $date] = $this->parseMonthOrNow($request->input('month'));
        $tableName      = $this->buildTableName($month);

        try {
            if (Schema::hasTable($tableName)) {
                DB::table($tableName)->truncate();
                Log::info("TransbfaPay: Truncated {$tableName}");
                return redirect()->back()->with('success', "All payroll records for {$date->format('F Y')} deleted successfully.");
            }

            return redirect()->back()->with('error', "No data found for {$date->format('F Y')}.");

        } catch (\Exception $e) {
            Log::error('TransbfaPay deleteMonth failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to delete: ' . $e->getMessage());
        }
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'ids'   => ['required', 'array'],
            'ids.*' => ['string'],
        ]);

        [$month, $date] = $this->parseMonthOrNow($request->input('month'));
        $ids            = $request->input('ids');
        $tableName      = $this->buildTableName($month);

        try {
            if (Schema::hasTable($tableName)) {
                $count = DB::table($tableName)->whereIn('staffid', $ids)->delete();
                Log::info("TransbfaPay: Deleted {$count} records from {$tableName}");
                return redirect()->back()->with('success', "Successfully deleted {$count} record(s).");
            }

            return redirect()->back()->with('error', "No data found for {$date->format('F Y')}.");

        } catch (\Exception $e) {
            Log::error('TransbfaPay bulkDelete failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Failed to delete records: ' . $e->getMessage());
        }
    }
}