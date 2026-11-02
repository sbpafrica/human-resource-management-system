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

class TogoBungePayController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    private function buildTableName(string $month): string
    {
        $date = Carbon::createFromFormat('Y-m', $month);
        return 'togobunge' . strtolower($date->format('F')) . $date->format('y');
    }

    private function createTableIfNotExists(string $tableName): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `staffid` VARCHAR(15) NOT NULL,
                `nom` VARCHAR(255) DEFAULT NULL,
                `prenom` VARCHAR(255) DEFAULT NULL,
                `department` VARCHAR(255) DEFAULT NULL,
                `joindate` VARCHAR(255) DEFAULT NULL,
                `constart` VARCHAR(255) DEFAULT NULL,
                `conend` VARCHAR(255) DEFAULT NULL,
                `position` VARCHAR(255) DEFAULT NULL,
                `grade` VARCHAR(255) DEFAULT NULL,
                `travail` VARCHAR(255) DEFAULT NULL,
                `banques` VARCHAR(255) DEFAULT NULL,
                `numero` VARCHAR(255) DEFAULT NULL,
                `ssnitno` VARCHAR(255) DEFAULT NULL,
                `legale` INT(11) DEFAULT NULL,
                `reelle` INT(11) DEFAULT NULL,
                `salarybase` DOUBLE DEFAULT NULL,
                `sursalaire` DOUBLE DEFAULT NULL,
                `performstand` DOUBLE DEFAULT NULL,
                `coefficient` DOUBLE DEFAULT NULL,
                `performprimes` DOUBLE DEFAULT NULL,
                `commission` DOUBLE DEFAULT NULL,
                `indconges` DOUBLE DEFAULT NULL,
                `indcom` DOUBLE DEFAULT NULL,
                `indtransport` DOUBLE DEFAULT NULL,
                `autressbonus` DOUBLE DEFAULT NULL,
                `salairebrut` DOUBLE DEFAULT NULL,
                `deduction` DOUBLE DEFAULT NULL,
                `brutglobal` DOUBLE DEFAULT NULL,
                `remark` INT(11) DEFAULT NULL,
                `cnss` DOUBLE DEFAULT NULL,
                `amu` DOUBLE DEFAULT NULL,
                `r1cnss` DOUBLE DEFAULT NULL,
                `abt28` DOUBLE DEFAULT NULL,
                `pac` DOUBLE DEFAULT NULL,
                `netimpo` DOUBLE DEFAULT NULL,
                `arrondi` DOUBLE DEFAULT NULL,
                `irpprch` DOUBLE DEFAULT NULL,
                `irppdu` DOUBLE DEFAULT NULL,
                `irpp` DOUBLE DEFAULT NULL,
                `totaldeduction` DOUBLE DEFAULT NULL,
                `netpayer` DOUBLE DEFAULT NULL,
                `prestation` DOUBLE DEFAULT NULL,
                `pension` DOUBLE DEFAULT NULL,
                `risques` DOUBLE DEFAULT NULL,
                `amutax` DOUBLE DEFAULT NULL,
                `totalcnss` DOUBLE DEFAULT NULL,
                `client` VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (`staffid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // String columns for Togo Bunge
    private array $stringCols = [
        'staffid', 'nom', 'prenom', 'department', 'joindate', 'constart',
        'conend', 'position', 'grade', 'travail', 'banques', 'numero', 'ssnitno', 'client',
    ];

    // Int columns
    private array $intCols = ['legale', 'reelle', 'remark'];

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
            return Inertia::render('TogoBunge/Index', [
                'records'        => [],
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        $records      = DB::table($tableName)->orderBy('nom')->get();
        $totalRecords = $records->count();

        return Inertia::render('TogoBunge/Index', [
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

            $reader = $extension === 'xls' ? new XlsReader() : new XlsxReader();

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
            $keywords = [
                'staff id', 'staffid', 'nom', 'prenom', 'department', 'position',
                'salary base', 'net payer', 'travail', 'banques', 'id du personnel', 'salaire de base', 'net a payer'
            ];

            $bestSheet          = null;
            $bestScore          = 0;
            $bestHeaderRowIndex = 0;

            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $sheetTitle = strtolower(trim($sheet->getTitle()));
                $sheetRows  = $sheet->toArray();
                if (empty($sheetRows)) continue;

                $isInvoice = str_contains($sheetTitle, 'invoice') || str_contains($sheetTitle, 'facture');
                foreach (array_slice($sheetRows, 0, 15) as $rIdx => $candidateRow) {
                    $matches = 0;
                    foreach ($candidateRow as $cell) {
                        [$cLower, $cUnacc] = $norm($cell);
                        if (in_array($cLower, $keywords) || in_array($cUnacc, $keywords)) {
                            $matches++;
                        }
                    }
                    if ($matches > $bestScore && !$isInvoice) {
                        $bestScore          = $matches;
                        $bestSheet          = $sheet;
                        $bestHeaderRowIndex = $rIdx;
                    }
                }
            }

            if ($bestSheet === null) {
                $bestSheet          = $spreadsheet->getActiveSheet();
                $rows               = $bestSheet->toArray();
                $bestHeaderRowIndex = 0;
                foreach (array_slice($rows, 0, 15) as $rIdx => $candidateRow) {
                    $matches = 0;
                    foreach ($candidateRow as $cell) {
                        [$cLower, $cUnacc] = $norm($cell);
                        if (in_array($cLower, $keywords) || in_array($cUnacc, $keywords)) {
                            $matches++;
                        }
                    }
                    if ($matches >= 2) {
                        $bestHeaderRowIndex = $rIdx;
                        break;
                    }
                }
            } else {
                $rows = $bestSheet->toArray();
            }

            if (empty($rows)) {
                return redirect()->route('togo-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', 'The uploaded file is empty.');
            }

            $headerRowIndex = $bestHeaderRowIndex;

            $headerRow = $rows[$headerRowIndex];
            // Combine previous row headers if present (e.g. section headers on Row 1)
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
            $rows = array_slice($rows, $headerRowIndex + 1);

            $headerMap = [
                'staff id'                  => 'staffid',
                'id du personnel'           => 'staffid',
                'matricule'                 => 'staffid',
                'id'                        => 'staffid',
                'nom'                       => 'nom',
                'names'                     => 'nom',
                'prenoms'                   => 'prenom',
                'prenom'                    => 'prenom',
                'first names'               => 'prenom',
                'département'               => 'department',
                'departement'               => 'department',
                'join date'                 => 'joindate',
                'date de debut'             => 'joindate',
                'date de début de paie'     => 'joindate',
                'contract start'            => 'constart',
                'contract end'              => 'conend',
                'date de fin du contrat'    => 'conend',
                'position'                  => 'position',
                'grade'                     => 'grade',
                'grade d\'emploi'           => 'grade',
                'travail'                   => 'travail',
                'lieu de travail'           => 'travail',
                'banques'                   => 'banques',
                'nom de banque'             => 'banques',
                'numero'                    => 'numero',
                'numéro de compte'          => 'numero',
                'numero de compte'          => 'numero',
                'ssnit no'                  => 'ssnitno',
                'cnss no'                   => 'ssnitno',
                'legale'                    => 'legale',
                'journée de travail légale' => 'legale',
                'journee de travail legale' => 'legale',
                'reelle'                    => 'reelle',
                'journée de travail réelle' => 'reelle',
                'journee de travail reelle' => 'reelle',
                'salary base'               => 'salarybase',
                'salaire de base'           => 'salarybase',
                'salaire base'              => 'salarybase',
                'sursalaire'                => 'sursalaire',
                'rappel de salaire'         => 'sursalaire',
                'perform stand'             => 'performstand',
                'coefficient'               => 'coefficient',
                'perform primes'            => 'performprimes',
                'performance primes'        => 'performprimes',
                'commission'                => 'commission',
                'ind. conges'               => 'indconges',
                'ind conges'                => 'indconges',
                'lunch subsidy'             => 'indconges',
                'ind. com'                  => 'indcom',
                'ind com'                   => 'indcom',
                'communication'             => 'indcom',
                'ind. transport'            => 'indtransport',
                'ind transport'             => 'indtransport',
                'autres bonus'              => 'autressbonus',
                'autres bonus/ allocations' => 'autressbonus',
                'autres bonus / allocations'=> 'autressbonus',
                'salaire brut'              => 'salairebrut',
                'deduction'                 => 'deduction',
                'déduction'                 => 'deduction',
                'brut global'               => 'brutglobal',
                'salaire brut final'        => 'brutglobal',
                'remark'                    => 'remark',
                'cnss'                      => 'cnss',
                'amu'                       => 'amu',
                'amu (5%)'                  => 'amu',
                'r1 cnss'                   => 'r1cnss',
                'r1cnss'                    => 'r1cnss',
                'abt28'                     => 'abt28',
                'pac'                       => 'pac',
                'net impo'                  => 'netimpo',
                'arrondi'                   => 'arrondi',
                'irpp rch'                  => 'irpprch',
                'irpp du'                   => 'irppdu',
                'irpp'                      => 'irpp',
                'total deduction'           => 'totaldeduction',
                'déduction totale'          => 'totaldeduction',
                'deduction totale'          => 'totaldeduction',
                'net payer'                 => 'netpayer',
                'net a payer'               => 'netpayer',
                'prestation'                => 'prestation',
                'prestations familiales'    => 'prestation',
                'pension'                   => 'pension',
                'pension vieillesse'        => 'pension',
                'risques'                   => 'risques',
                'risques professionnels'    => 'risques',
                'amu tax'                   => 'amutax',
                'amu patronat'              => 'amutax',
                'total cnss'                => 'totalcnss',
                'total cnss part patronale' => 'totalcnss',
                'client'                    => 'client',
            ];

            $colMap = [];
            foreach ($headerRow as $idx => $hdr) {
                [$cleanHdr, $unaccHdr] = $norm($hdr);
                if (isset($headerMap[$cleanHdr])) {
                    $colMap[$idx] = $headerMap[$cleanHdr];
                } elseif (isset($headerMap[$unaccHdr])) {
                    $colMap[$idx] = $headerMap[$unaccHdr];
                }
            }

            if (empty($colMap)) {
                return redirect()->route('togo-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', 'No recognisable column headers found in the uploaded file.');
            }

            DB::table($tableName)->truncate();

            $validRecords  = [];
            $errors        = [];
            $autoIdCounter = 1;
            $seenStaffIds  = [];

            // Scan rows for employee lines (stop before totals/bank metadata)
            $bankRows = [];
            $inBankSection = false;

            foreach ($rows as $index => $row) {
                $allEmpty = true;
                foreach ($row as $cell) {
                    if (trim((string) $cell) !== '') { $allEmpty = false; break; }
                }
                if ($allEmpty) {
                    continue;
                }

                $firstCellsText = strtoupper(implode(' ', array_slice($row, 0, 5)));

                // Check if we reached the Bank details block at the bottom
                if (str_contains($firstCellsText, 'NAME') && str_contains($firstCellsText, 'ACCOUNT')) {
                    $inBankSection = true;
                    continue;
                }

                if ($inBankSection) {
                    // Collect bank details: [NAME, ACCOUNT, AMOUNT, ...]
                    $nonEmpty = array_values(array_filter(array_map('trim', array_slice($row, 0, 5)), fn($x) => $x !== ''));
                    $bankNameCell = $nonEmpty[0] ?? '';
                    $bankAccCell  = $nonEmpty[1] ?? '';

                    if (!empty($bankNameCell) && !empty($bankAccCell) && !str_contains(strtoupper($bankNameCell), 'TOTAL')) {
                        $bankRows[] = [
                            'name'    => $bankNameCell,
                            'account' => $bankAccCell,
                        ];
                    }
                    continue;
                }

                if (
                    str_contains($firstCellsText, 'TOTAL') ||
                    str_contains($firstCellsText, 'INVOICE') ||
                    str_contains($firstCellsText, 'STRATEGIC') ||
                    str_contains($firstCellsText, 'BAREME')
                ) {
                    continue;
                }

                $record = [];
                foreach ($colMap as $idx => $dbCol) {
                    $val = $row[$idx] ?? null;
                    if (in_array($dbCol, $this->stringCols)) {
                        $record[$dbCol] = trim((string) $val);
                    } elseif (in_array($dbCol, $this->intCols)) {
                        $clean = str_replace([',', ' ', "\xc2\xa0"], '', trim((string)$val));
                        $record[$dbCol] = is_numeric($clean) ? (int) $clean : 0;
                    } else {
                        $clean = str_replace([',', ' ', "\xc2\xa0"], '', trim((string)$val));
                        $record[$dbCol] = is_numeric($clean) ? (float) $clean : 0;
                    }
                }

                $nom      = trim((string)($record['nom'] ?? ''));
                $prenom   = trim((string)($record['prenom'] ?? ''));
                $fullName = trim($nom . ' ' . $prenom);
                $staffId  = trim((string)($record['staffid'] ?? ''));

                if (empty($fullName)) {
                    continue;
                }
                $lettersOnly = preg_replace('/[^a-zA-Z]/', '', $fullName);
                if (strlen($lettersOnly) < 2) {
                    continue;
                }

                $upperName = strtoupper($fullName);
                if (
                    str_contains($upperName, 'ACCOUNT') ||
                    str_contains($upperName, 'COMPTE') ||
                    str_contains($upperName, 'TOTAL') ||
                    str_contains($upperName, 'BANQUE') ||
                    str_contains($upperName, 'BANK') ||
                    str_contains($upperName, 'BENEFICIAIRE') ||
                    str_contains($upperName, 'INVOICE') ||
                    str_contains($upperName, 'STRATEGIC')
                ) {
                    continue;
                }

                if (empty($staffId)) {
                    $staffId = 'TG-' . str_pad((string)$autoIdCounter++, 3, '0', STR_PAD_LEFT);
                    while (in_array($staffId, $seenStaffIds)) {
                        $staffId = 'TG-' . str_pad((string)$autoIdCounter++, 3, '0', STR_PAD_LEFT);
                    }
                    $record['staffid'] = $staffId;
                }

                if (in_array($record['staffid'], $seenStaffIds)) {
                    $record['staffid'] .= '-' . ($index + 1);
                }

                $record['client'] = 'BUNGE';
                $seenStaffIds[] = $record['staffid'];
                $validRecords[] = $record;
            }

            // Sync bank details if found in bank block
            foreach ($bankRows as $b) {
                $bNameUpper = strtoupper($b['name']);
                $bAcc       = $b['account'];
                $bankName   = str_starts_with($bAcc, 'TG016') ? 'Orabank Togo' : 'Ecobank Togo';

                $bTokens = array_filter(explode(' ', strtoupper(preg_replace('/[^a-zA-Z ]/', '', $b['name']))));

                foreach ($validRecords as &$rec) {
                    $eTokens = array_filter(explode(' ', strtoupper(preg_replace('/[^a-zA-Z ]/', '', ($rec['nom'] ?? '') . ' ' . ($rec['prenom'] ?? '')))));
                    $common = array_intersect($bTokens, $eTokens);
                    $matched = count($common) >= 1;

                    if ($matched) {
                        $rec['banques'] = $bankName;
                        $rec['numero']  = $bAcc;

                        // Also save into bankdetails table for permanence
                        try {
                            if (Schema::hasTable('bankdetails')) {
                                DB::table('bankdetails')->updateOrInsert(
                                    ['employid' => $rec['staffid']],
                                    [
                                        'name'    => trim(($rec['nom'] ?? '') . ' ' . ($rec['prenom'] ?? '')),
                                        'bank'    => $bankName,
                                        'account' => $bAcc,
                                        'branch'  => 'Lomé',
                                    ]
                                );
                            }
                        } catch (\Throwable $ex) {
                            Log::warning("Could not sync Togo bank detail: " . $ex->getMessage());
                        }
                    }
                }
                unset($rec);
            }

            if (!empty($errors)) {
                $msg = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('togo-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', $msg);
            }

            if (!empty($validRecords)) {
                foreach (array_chunk($validRecords, 500) as $chunk) {
                    DB::table($tableName)->insert($chunk);
                }
                Log::info("TogoBungePay upload: inserted " . count($validRecords) . " rows into {$tableName}");
            }

            return redirect()->route('togo-bunge.pay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');

        } catch (\Exception $e) {
            Log::error('TogoBungePay upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('togo-bunge.pay.index', ['month' => $selectedMonth])
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
        $sheet->setTitle('Togo Bunge Payroll');

        $headers = [
            'Staff ID', 'Nom', 'Prenom', 'Department', 'Join Date', 'Contract Start',
            'Contract End', 'Position', 'Grade', 'Travail', 'Banques', 'Numero',
            'SSNIT No', 'Legale', 'Reelle', 'Salary Base', 'Sursalaire', 'Perform Stand',
            'Coefficient', 'Perform Primes', 'Commission', 'Ind. Conges', 'Ind. Com',
            'Ind. Transport', 'Autres Bonus', 'Salaire Brut', 'Deduction', 'Brut Global',
            'Remark', 'CNSS', 'AMU', 'R1 CNSS', 'ABT28', 'PAC', 'Net Impo', 'Arrondi',
            'IRPP RCH', 'IRPP DU', 'IRPP', 'Total Deduction', 'Net Payer', 'Prestation',
            'Pension', 'Risques', 'AMU Tax', 'Total CNSS', 'Client',
        ];

        $totalCols = count($headers);

        foreach ($headers as $i => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . "1", $header);
        }

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

        $sheet->freezePane('A2');

        for ($i = 1; $i <= $totalCols; $i++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        $writer   = new Xlsx($spreadsheet);
        $filename = 'togo_bunge_payroll_template.xlsx';

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

            $records = DB::table($tableName)->orderBy('nom')->get();

            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Togo Bunge Payroll');

            $headers = ['Staff ID', 'Nom', 'Net Payer'];
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
                $sheet->setCellValue("A{$row}", $r->staffid);
                $sheet->setCellValue("B{$row}", $r->nom ?? '');
                $sheet->setCellValue("C{$row}", ($r->netpayer !== null && $r->netpayer !== '') ? number_format((float)$r->netpayer, 2, '.', '') : '');
                $row++;
            }

            for ($i = 1; $i <= 3; $i++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }

            $sheet->freezePane('A2');

            $writer   = new Xlsx($spreadsheet);
            $filename = "togo_bunge_payroll_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);

        } catch (\Exception $e) {
            Log::error('TogoBungePay export failed', ['error' => $e->getMessage()]);
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
            Log::error('TogoBungePay deleteMonth failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete records: ' . $e->getMessage()], 500);
        }
    }
}
