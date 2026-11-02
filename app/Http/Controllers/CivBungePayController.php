<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class CivBungePayController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    private function buildTableName(string $month): string
    {
        $date = Carbon::createFromFormat('Y-m', $month);
        return 'civ' . strtolower($date->format('F')) . $date->format('y');
    }

    private function createTableIfNotExists(string $tableName): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `id` VARCHAR(255) NOT NULL,
                `1stlevel` VARCHAR(255) DEFAULT NULL,
                `2ndlevel` VARCHAR(255) DEFAULT NULL,
                `city` VARCHAR(255) DEFAULT NULL,
                `jobtitle` VARCHAR(255) DEFAULT NULL,
                `fullname` VARCHAR(255) DEFAULT NULL,
                `reportline` VARCHAR(255) DEFAULT NULL,
                `outsourcing` VARCHAR(255) DEFAULT NULL,
                `entrydate` VARCHAR(255) DEFAULT NULL,
                `payrollstart` VARCHAR(255) DEFAULT NULL,
                `payrollend` VARCHAR(255) DEFAULT NULL,
                `salarymonth` VARCHAR(255) DEFAULT NULL,
                `legalwork` VARCHAR(255) DEFAULT NULL,
                `actualwork` VARCHAR(255) DEFAULT NULL,
                `basicfixed` VARCHAR(255) DEFAULT NULL,
                `basic` VARCHAR(255) DEFAULT NULL,
                `overtime` VARCHAR(255) DEFAULT NULL,
                `overtimeallowance` VARCHAR(255) DEFAULT NULL,
                `transport` VARCHAR(255) DEFAULT NULL,
                `callallowance` VARCHAR(25) DEFAULT NULL,
                `basickpi` VARCHAR(25) DEFAULT NULL,
                `coefficient` VARCHAR(255) DEFAULT NULL,
                `monthlykpi` VARCHAR(25) DEFAULT NULL,
                `leaveallowance` VARCHAR(25) DEFAULT NULL,
                `grosssalary1` VARCHAR(25) DEFAULT NULL,
                `otherdeductions` VARCHAR(25) DEFAULT NULL,
                `grosssalary` VARCHAR(45) DEFAULT NULL,
                `nombre` VARCHAR(255) DEFAULT NULL,
                `importbrut` VARCHAR(255) DEFAULT NULL,
                `ricf` VARCHAR(255) DEFAULT NULL,
                `itsalaries` VARCHAR(255) DEFAULT NULL,
                `itspatron` VARCHAR(255) DEFAULT NULL,
                `cnpssalaries` VARCHAR(255) DEFAULT NULL,
                `cnpspatron` VARCHAR(191) DEFAULT NULL,
                `totaldeduction` VARCHAR(191) DEFAULT NULL,
                `netpay` VARCHAR(191) DEFAULT NULL,
                `fdfp` VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (!Schema::hasColumn($tableName, 'itspatron')) {
            try {
                Schema::table($tableName, function ($table) {
                    $table->string('itspatron', 255)->nullable()->after('itsalaries');
                });
            } catch (\Throwable $e) {
                // Column might have been added concurrently
            }
        }
    }

    private function extractBankRowsFromSheet(array $sheetRows, array &$bankRows): void
    {
        $cleanStr = function($s) {
            $t = trim(preg_replace('/\s+/', ' ', (string)$s));
            return mb_strtolower($t, 'UTF-8');
        };
        $nameCol = -1;
        $bankCol = -1;
        $branchCol = -1;
        $accountCol = -1;

        foreach ($sheetRows as $row) {
            if ($nameCol === -1) {
                foreach ($row as $cIdx => $cell) {
                    $c = $cleanStr($cell);
                    if (str_contains($c, 'beneficiaire') || (str_contains($c, 'nom') && (str_contains($c, 'benef') || str_contains($c, 'agent') || str_contains($c, 'employee')))) {
                        $nameCol = $cIdx;
                    } elseif ($c === 'banque' || $c === 'bank' || str_contains($c, 'nom banque') || str_contains($c, 'bank name')) {
                        $bankCol = $cIdx;
                    } elseif ($c === 'agence' || $c === 'branch' || str_contains($c, 'guichet')) {
                        $branchCol = $cIdx;
                    } elseif (str_contains($c, 'compte') || str_contains($c, 'account') || str_contains($c, 'numero')) {
                        $accountCol = $cIdx;
                    }
                }
                continue;
            }

            $nameVal = trim((string)($row[$nameCol] ?? ''));
            if ($nameVal === '' || str_contains(strtoupper($nameVal), 'TOTAL') || str_contains(strtoupper($nameVal), 'BENEFICIAIRE')) {
                continue;
            }

            $bankRows[] = [
                'name'    => $nameVal,
                'bank'    => $bankCol !== -1 ? trim((string)($row[$bankCol] ?? '')) : '',
                'branch'  => $branchCol !== -1 ? trim((string)($row[$branchCol] ?? '')) : '',
                'account' => $accountCol !== -1 ? trim((string)($row[$accountCol] ?? '')) : '',
            ];
        }
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
        $tableName = $this->buildTableName($month);

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('CivBunge/Index', [
                'records'        => [],
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        $records      = DB::table($tableName)->orderBy('fullname')->get();
        $totalRecords = $records->count();

        return Inertia::render('CivBunge/Index', [
            'records'        => $records,
            'selectedMonth'  => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords'   => $totalRecords,
            'flash'          => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
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
            $tableName = $this->buildTableName($selectedMonth);
            $this->createTableIfNotExists($tableName);

            $file      = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());

            if ($extension === 'xls') {
                $reader = new XlsReader();
            } else {
                $reader = new XlsxReader();
            }

            $spreadsheet = $reader->load($file->getRealPath());

            $norm = function($s) {
                $t = trim(preg_replace('/\s+/', ' ', (string)$s));
                $lower = mb_strtolower($t, 'UTF-8');
                $unacc = str_replace(
                    ['é', 'è', 'ê', 'ë', 'à', 'â', 'ä', 'î', 'ï', 'ô', 'ö', 'ù', 'û', 'ü', 'ç', '’', "\x27"],
                    ['e', 'e', 'e', 'e', 'a', 'a', 'a', 'i', 'i', 'o', 'o', 'u', 'u', 'u', 'c', "'", "'"],
                    $lower
                );
                return [$lower, $unacc];
            };
            $cleanStr = fn($s) => $norm($s)[0];
            $payrollKeywords = [
                'id', 'id number', 'staff id', 'matricule', 'nom et prenoms', 'nom et prenom',
                'fullname', 'full name', 'job title', 'salaire de base', 'basic', 'actual basic',
                'basic fixed', 'net pay', 'salaire net', 'gross salary', 'salaire brute',
                'salaire brut', '1st level department', '1st level', 'city', 'transport', 'fdfp', 'cnps'
            ];

            $bestPayrollSheet   = null;
            $bestScore          = 0;
            $bestHeaderRowIndex = 0;
            $bankRows           = [];

            // 1. Scan all sheets in the workbook
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $sheetTitle = strtolower(trim($sheet->getTitle()));
                $sheetRows  = $sheet->toArray();
                if (empty($sheetRows)) continue;

                if (str_contains($sheetTitle, 'bank') || str_contains($sheetTitle, 'banque')) {
                    $this->extractBankRowsFromSheet($sheetRows, $bankRows);
                    continue;
                }

                $isInvoice = str_contains($sheetTitle, 'invoice') || str_contains($sheetTitle, 'facture');
                foreach (array_slice($sheetRows, 0, 20) as $rIdx => $candidateRow) {
                    $matches = 0;
                    foreach ($candidateRow as $cell) {
                        [$cLower, $cUnacc] = $norm($cell);
                        if (in_array($cLower, $payrollKeywords) || in_array($cUnacc, $payrollKeywords)) {
                            $matches++;
                        }
                    }
                    if ($matches > $bestScore && !$isInvoice) {
                        $bestScore          = $matches;
                        $bestPayrollSheet   = $sheet;
                        $bestHeaderRowIndex = $rIdx;
                    }
                }
            }

            // Fallback to active sheet if no candidate scored well
            if ($bestPayrollSheet === null) {
                $bestPayrollSheet   = $spreadsheet->getActiveSheet();
                $rows               = $bestPayrollSheet->toArray();
                $bestHeaderRowIndex = 0;
                foreach (array_slice($rows, 0, 20) as $rIdx => $candidateRow) {
                    $matches = 0;
                    foreach ($candidateRow as $cell) {
                        [$cLower, $cUnacc] = $norm($cell);
                        if (in_array($cLower, $payrollKeywords) || in_array($cUnacc, $payrollKeywords)) {
                            $matches++;
                        }
                    }
                    if ($matches >= 2) {
                        $bestHeaderRowIndex = $rIdx;
                        break;
                    }
                }
            } else {
                $rows = $bestPayrollSheet->toArray();
            }

            if (empty($rows)) {
                return redirect()->route('civ-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', 'The uploaded file is empty.');
            }

            $headerRowIndex = $bestHeaderRowIndex;

            // Read and map headers
            $headerRow = $rows[$headerRowIndex];
            // Merge previous row headers if present (e.g. FDFP, CNPS category titles)
            if ($headerRowIndex > 0) {
                $prevRow = $rows[$headerRowIndex - 1];
                $maxLen  = max(count($headerRow), count($prevRow));
                for ($ci = 0; $ci < $maxLen; $ci++) {
                    $curr = trim((string)($headerRow[$ci] ?? ''));
                    $prev = trim((string)($prevRow[$ci] ?? ''));
                    if ($curr === '' && $prev !== '') {
                        $headerRow[$ci] = $prev;
                    }
                }
            }
            $dataRows = array_slice($rows, $headerRowIndex + 1);

            $headerMap = [
                'id'                            => 'id',
                'id number'                     => 'id',
                'staff id'                      => 'id',
                'matricule'                     => 'id',
                '1st level'                     => '1stlevel',
                '1st level department'          => '1stlevel',
                '2nd level'                     => '2ndlevel',
                '2nd level department'          => '2ndlevel',
                'city'                          => 'city',
                'ville'                         => 'city',
                'job title'                     => 'jobtitle',
                'poste'                         => 'jobtitle',
                'titre'                         => 'jobtitle',
                'full name'                     => 'fullname',
                'fullname'                      => 'fullname',
                'nom et prenoms'                => 'fullname',
                'nom et prenom'                 => 'fullname',
                'noms et prenoms'               => 'fullname',
                'nom'                           => 'fullname',
                'report line'                   => 'reportline',
                'rapport'                       => 'reportline',
                'outsourcing'                   => 'outsourcing',
                'entreprise'                    => 'outsourcing',
                'societe'                       => 'outsourcing',
                'entry date'                    => 'entrydate',
                'date d\'entré en service'      => 'entrydate',
                'date d\'entree en service'     => 'entrydate',
                'date entree'                   => 'entrydate',
                'payroll start'                 => 'payrollstart',
                'debut des paiements'           => 'payrollstart',
                'debut de paiement'             => 'payrollstart',
                'payroll end'                   => 'payrollend',
                'fin de paiement'               => 'payrollend',
                'fin des paiements'             => 'payrollend',
                'salary month'                  => 'salarymonth',
                'mois de salaire'               => 'salarymonth',
                'legal work'                    => 'legalwork',
                'jours ouvrables legaux'        => 'legalwork',
                'jours ouvrables légaux'        => 'legalwork',
                'actual work'                   => 'actualwork',
                'jours ouvrables réels pour le travail' => 'actualwork',
                'jours ouvrables reels pour le travail' => 'actualwork',
                'basic fixed'                   => 'basicfixed',
                'actual basic'                  => 'basic',
                'basic'                         => 'basic',
                'indemnité de responsabilité'   => 'overtime',
                'indemnite de responsabilite'   => 'overtime',
                'overtime'                      => 'overtime',
                'overtime allowance'            => 'overtimeallowance',
                'transport'                     => 'transport',
                'call allowance'                => 'callallowance',
                'caburan ,internet'             => 'callallowance',
                'caburan, internet'             => 'callallowance',
                'basic kpi'                     => 'basickpi',
                'basic kpi bonus'               => 'monthlykpi',
                'coefficient'                   => 'coefficient',
                'monthly kpi'                   => 'monthlykpi',
                'leave allowance'               => 'leaveallowance',
                'lunch allowance'               => 'lunchallowance',
                'prime d\'ancieneté'            => 'leaveallowance',
                'prime d\'anciennete'           => 'leaveallowance',
                'gross salary 1'                => 'grosssalary1',
                'salaire brute'                 => 'grosssalary1',
                'salaire brut'                  => 'grosssalary1',
                'other deductions'              => 'otherdeductions',
                'the other deductions / refunds'=> 'otherdeductions',
                'autres deductions'             => 'otherdeductions',
                'gross salary'                  => 'grosssalary',
                'salaire brute imposable'       => 'grosssalary',
                'salaire brut imposable'        => 'grosssalary',
                'nombre'                        => 'nombre',
                'nombre de pars'                => 'nombre',
                'nombre de parts'               => 'nombre',
                'import brut'                   => 'importbrut',
                'impôt brut'                    => 'importbrut',
                'impot brut'                    => 'importbrut',
                'ricf'                          => 'ricf',
                'its salaries'                  => 'itsalaries',
                'its salarié'                   => 'itsalaries',
                'its salarie'                   => 'itsalaries',
                'its patron'                    => 'itspatron',
                'its patronal'                  => 'itspatron',
                'its patronale'                 => 'itspatron',
                'cnps salaries'                 => 'cnpssalaries',
                'cnps salarié'                  => 'cnpssalaries',
                'cnps salarie'                  => 'cnpssalaries',
                'cnps patron'                   => 'cnpspatron',
                'cnps patronale'                => 'cnpspatron',
                'total deduction'               => 'totaldeduction',
                'total deductions'              => 'totaldeduction',
                'total salaire deductions'      => 'totaldeduction',
                'total salaire déductions'      => 'totaldeduction',
                'net pay'                       => 'netpay',
                'salaire net'                   => 'netpay',
                'net a payer'                   => 'netpay',
                'fdfp'                          => 'fdfp',
                'ta'                            => 'fdfp',
            ];

            // Map column index → db column
            $colMap = [];
            $seenCols = [];
            foreach ($headerRow as $idx => $hdr) {
                [$cleanHdr, $unaccHdr] = $norm($hdr);
                if ($cleanHdr === 'salaire de base' || $unaccHdr === 'salaire de base') {
                    if (!isset($seenCols['basicfixed'])) {
                        $colMap[$idx] = 'basicfixed';
                        $seenCols['basicfixed'] = true;
                        continue;
                    } else {
                        $colMap[$idx] = 'basic';
                        $seenCols['basic'] = true;
                        continue;
                    }
                }
                if (isset($headerMap[$cleanHdr])) {
                    $target = $headerMap[$cleanHdr];
                    $colMap[$idx] = $target;
                    $seenCols[$target] = true;
                } elseif (isset($headerMap[$unaccHdr])) {
                    $target = $headerMap[$unaccHdr];
                    $colMap[$idx] = $target;
                    $seenCols[$target] = true;
                }
            }

            if (empty($colMap)) {
                return redirect()->route('civ-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', 'No recognisable column headers found in the uploaded file.');
            }

            // Truncate then insert
            DB::table($tableName)->truncate();

            $validRecords  = [];
            $inBankSection = false;
            $errors        = [];
            $autoIdCounter = 1;
            $seenIds       = [];

            $numericCols = [
                'legalwork', 'actualwork', 'basicfixed', 'basic', 'overtime', 'overtimeallowance',
                'transport', 'callallowance', 'basickpi', 'coefficient', 'monthlykpi', 'leaveallowance',
                'grosssalary1', 'otherdeductions', 'grosssalary', 'nombre', 'importbrut', 'ricf',
                'itsalaries', 'itspatron', 'cnpssalaries', 'cnpspatron', 'totaldeduction', 'netpay', 'fdfp'
            ];

            foreach ($dataRows as $index => $row) {
                // Skip completely empty rows
                $allEmpty = true;
                foreach ($row as $cell) {
                    if (trim((string) $cell) !== '') { $allEmpty = false; break; }
                }
                if ($allEmpty) continue;

                $firstCol  = trim((string)($row[0] ?? ''));
                $secondCol = trim((string)($row[1] ?? ''));

                // Detect start of bank details section at bottom of file
                if (str_contains(strtoupper($firstCol), 'NOM DU BENEFICIAIRE') || str_contains(strtoupper($secondCol), 'NOM DU BENEFICIAIRE')) {
                    $inBankSection = true;
                    continue;
                }

                if ($inBankSection) {
                    if (str_contains(strtoupper($firstCol), 'INVOICE') || str_contains(strtoupper($secondCol), 'INVOICE') || str_contains(strtoupper($firstCol), 'STRATEGIC')) {
                        $inBankSection = false;
                        continue;
                    }
                    if (count($row) >= 4 && !empty($firstCol)) {
                        $bankRows[] = [
                            'name'    => $firstCol,
                            'bank'    => trim((string)($row[1] ?? '')),
                            'branch'  => trim((string)($row[2] ?? '')),
                            'account' => trim((string)($row[3] ?? '')),
                        ];
                    }
                    continue;
                }

                $record = [];
                foreach ($colMap as $idx => $dbCol) {
                    $raw = trim((string)($row[$idx] ?? ''));
                    if (in_array($dbCol, $numericCols)) {
                        $clean = str_replace([',', ' ', "\xc2\xa0"], '', $raw);
                        $record[$dbCol] = is_numeric($clean) ? $clean : $raw;
                    } else {
                        $record[$dbCol] = $raw;
                    }
                }

                // Handle lunch allowance: merge into leaveallowance or monthlykpi if not already set
                if (isset($record['lunchallowance'])) {
                    $lunchVal = $record['lunchallowance'];
                    if (!empty($lunchVal) && (float)$lunchVal > 0) {
                        if (empty($record['leaveallowance']) || (float)$record['leaveallowance'] == 0) {
                            $record['leaveallowance'] = $lunchVal;
                        } elseif (empty($record['monthlykpi']) || (float)$record['monthlykpi'] == 0) {
                            $record['monthlykpi'] = $lunchVal;
                        }
                    }
                    unset($record['lunchallowance']);
                }

                $name = trim((string)($record['fullname'] ?? ''));
                $id   = trim((string)($record['id'] ?? ''));

                if (empty($name) && empty($id)) {
                    continue;
                }

                $upperName = strtoupper($name);
                if (
                    str_contains($upperName, 'TOTAL') ||
                    str_contains($upperName, 'BENEFICIAIRE') ||
                    str_contains($upperName, 'INVOICE') ||
                    str_contains($upperName, 'STRATEGIC') ||
                    str_contains($upperName, 'BUNGE') ||
                    str_contains($upperName, 'WEEKEND') ||
                    str_contains($upperName, 'SUNDAY') ||
                    str_contains($upperName, 'HOLIDAY') ||
                    preg_match('/^[A-Z]{2}[0-9]{10,}/', $name)
                ) {
                    continue;
                }

                // A valid employee name must have at least 2 alphabet characters
                $lettersOnly = preg_replace('/[^a-zA-Z]/', '', $name);
                if (strlen($lettersOnly) < 2) {
                    continue;
                }

                if (empty($id)) {
                    // Try looking up employee in newemployeedata or previous CIV month tables
                    $existingId = null;
                    if (Schema::hasTable('newemployeedata')) {
                        $existingId = DB::table('newemployeedata')
                            ->where('employeename', 'LIKE', '%' . $name . '%')
                            ->value('staffid');
                    }
                    if (!$existingId) {
                        try {
                            $allCivTables = DB::select("SHOW TABLES LIKE 'civ%'");
                            foreach (array_reverse($allCivTables) as $t) {
                                $tbl = array_values((array)$t)[0];
                                if (Schema::hasColumn($tbl, 'fullname') && Schema::hasColumn($tbl, 'id')) {
                                    $found = DB::table($tbl)->where('fullname', 'LIKE', '%' . $name . '%')->value('id');
                                    if ($found && !in_array($found, $seenIds)) {
                                        $existingId = $found;
                                        break;
                                    }
                                }
                            }
                        } catch (\Throwable $te) {
                            // ignore
                        }
                    }

                    if ($existingId && !in_array($existingId, $seenIds)) {
                        $id = $existingId;
                    } else {
                        $id = 'CIV-' . str_pad((string)$autoIdCounter++, 3, '0', STR_PAD_LEFT);
                        while (in_array($id, $seenIds)) {
                            $id = 'CIV-' . str_pad((string)$autoIdCounter++, 3, '0', STR_PAD_LEFT);
                        }
                    }
                    $record['id'] = $id;
                }

                if (in_array($record['id'], $seenIds)) {
                    $record['id'] .= '-' . ($index + 1);
                }

                $seenIds[]      = $record['id'];
                $validRecords[] = $record;
            }

            if (!empty($errors)) {
                $msg = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('civ-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', $msg);
            }

            if (!empty($validRecords)) {
                foreach (array_chunk($validRecords, 500) as $chunk) {
                    DB::table($tableName)->insert($chunk);
                }
                Log::info("CivBungePay upload: inserted " . count($validRecords) . " rows into {$tableName}");

                // Update bank details if bank table was found in the file
                if (!empty($bankRows) && Schema::hasTable('bankdetails')) {
                    foreach ($bankRows as $bRow) {
                        $bName = strtolower(preg_replace('/[^a-zA-Z]/', '', $bRow['name']));
                        if (strlen($bName) < 3) continue;

                        $bestEmp = null;
                        $bestScore = 0;

                        foreach ($validRecords as $vEmp) {
                            $vName = strtolower(preg_replace('/[^a-zA-Z]/', '', $vEmp['fullname']));
                            if ($bName === $vName) {
                                $bestEmp = $vEmp;
                                $bestScore = 999;
                                break;
                            }

                            $bTokens = array_unique(array_filter(explode(' ', strtolower(preg_replace('/[^a-zA-Z ]/', '', $bRow['name'])))));
                            $vTokens = array_unique(array_filter(explode(' ', strtolower(preg_replace('/[^a-zA-Z ]/', '', $vEmp['fullname'])))));
                            $common  = array_intersect($bTokens, $vTokens);
                            $score = count($common);

                            if ($score > $bestScore && $score >= 2) {
                                $bestScore = $score;
                                $bestEmp = $vEmp;
                            }
                        }

                        if ($bestEmp) {
                            try {
                                DB::table('bankdetails')->updateOrInsert(
                                    ['employid' => $bestEmp['id']],
                                    [
                                        'bank'    => $bRow['bank'],
                                        'branch'  => $bRow['branch'],
                                        'account' => $bRow['account'],
                                        'name'    => $bestEmp['fullname'],
                                    ]
                                );
                            } catch (\Throwable $be) {
                                Log::warning("Could not update bankdetails for {$bestEmp['id']}: " . $be->getMessage());
                            }
                        }
                    }
                }
            }

            return redirect()->route('civ-bunge.pay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');

        } catch (\Exception $e) {
            Log::error('CivBungePay upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('civ-bunge.pay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // DOWNLOAD TEMPLATE
    // ─────────────────────────────────────────────────────────────────
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('CIV Bunge Payroll');

        $headers = [
            'ID', '1st Level', '2nd Level', 'City', 'Job Title', 'Full Name',
            'Report Line', 'Outsourcing', 'Entry Date', 'Payroll Start', 'Payroll End',
            'Salary Month', 'Legal Work', 'Actual Work', 'Basic Fixed', 'Actual Basic',
            'Overtime', 'Overtime Allowance', 'Transport', 'Call Allowance', 'Basic KPI',
            'Coefficient', 'Monthly KPI', 'Leave Allowance', 'Gross Salary 1',
            'Other Deductions', 'Gross Salary', 'Nombre', 'Import Brut', 'RICF',
            'ITS Salaries', 'ITS Patron', 'CNPS Salaries', 'CNPS Patron',
            'Total Deduction', 'Net Pay', 'FDFP',
        ];

        $totalCols = count($headers);

        // Write headers using column index (safe beyond Z)
        foreach ($headers as $i => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . "1", $header);
        }

        // Style header row — blue background, bold white text
        $lastCol = Coordinate::stringFromColumnIndex($totalCols);
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => [
                'bold'  => true,
                'color' => ['argb' => Color::COLOR_WHITE],
                'size'  => 11,
                'name'  => 'Calibri',
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1e40af'],
            ],
        ]);

        // Freeze header row
        $sheet->freezePane('A2');

        // Auto-size all columns
        for ($i = 1; $i <= $totalCols; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $writer   = new Xlsx($spreadsheet);
        $filename = 'civ_bunge_payroll_template.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store, no-cache',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // EXPORT
    // ─────────────────────────────────────────────────────────────────
    public function export(Request $request)
    {
        try {
            $request->validate(['month' => ['required', 'date_format:Y-m']]);

            $month     = $request->input('month');
            $date      = Carbon::createFromFormat('Y-m', $month);
            $tableName = $this->buildTableName($month);

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
            }

            $records = DB::table($tableName)->orderBy('fullname')->get();

            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('CIV Bunge Payroll');

            $headers = ['ID', 'Full Name', 'Net Pay'];
            foreach ($headers as $i => $header) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . "1", $header);
            }

            $lastCol = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['argb' => Color::COLOR_WHITE],
                    'size'  => 11,
                    'name'  => 'Calibri',
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF1e40af'],
                ],
            ]);

            $row = 2;
            foreach ($records as $r) {
                $sheet->setCellValue("A{$row}", $r->id);
                $sheet->setCellValue("B{$row}", $r->fullname ?? '');
                $sheet->setCellValue("C{$row}", ($r->netpay !== null && $r->netpay !== '') ? number_format((float)$r->netpay, 2, '.', '') : '');
                $row++;
            }

            for ($i = 1; $i <= 3; $i++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }

            $sheet->freezePane('A2');

            $writer   = new Xlsx($spreadsheet);
            $filename = "civ_bunge_payroll_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);

        } catch (\Exception $e) {
            Log::error('CivBungePay export failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed. Please try again.'], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // DELETE MONTH
    // ─────────────────────────────────────────────────────────────────
    public function deleteMonth(Request $request)
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $month     = $request->input('month');
        $tableName = $this->buildTableName($month);

        try {
            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                DB::table($tableName)->truncate();
                return response()->json(['success' => true, 'message' => 'Records deleted successfully.']);
            }
            return response()->json(['success' => false, 'message' => 'No records found for the selected month.'], 404);
        } catch (\Exception $e) {
            Log::error('CivBungePay deleteMonth failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete records: ' . $e->getMessage()], 500);
        }
    }
}
