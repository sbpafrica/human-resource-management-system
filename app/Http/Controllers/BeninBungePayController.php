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

class BeninBungePayController extends Controller
{
    // ─────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────

    private function buildTableName(string $month): string
    {
        $date = Carbon::createFromFormat('Y-m', $month);
        return 'bungebenin' . strtolower($date->format('F')) . $date->format('y');
    }

    private function createTableIfNotExists(string $tableName): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `{$tableName}` (
                `staff_id` VARCHAR(50) NOT NULL,
                `names` VARCHAR(150) DEFAULT NULL,
                `first_names` VARCHAR(150) DEFAULT NULL,
                `department` VARCHAR(100) DEFAULT NULL,
                `joining_date` VARCHAR(100) DEFAULT NULL,
                `contract_start_date` VARCHAR(100) DEFAULT NULL,
                `contract_end_date` VARCHAR(100) DEFAULT NULL,
                `position` VARCHAR(100) DEFAULT NULL,
                `job_grade` VARCHAR(50) DEFAULT NULL,
                `work_place` VARCHAR(100) DEFAULT NULL,
                `bank_name` VARCHAR(100) DEFAULT NULL,
                `bank_account_number` VARCHAR(50) DEFAULT NULL,
                `social_security_number` VARCHAR(50) DEFAULT NULL,
                `legal_work_days` INT(11) DEFAULT NULL,
                `work_days` INT(11) DEFAULT NULL,
                `basic_salary` DECIMAL(15,2) DEFAULT NULL,
                `sursalaire` DECIMAL(15,2) DEFAULT NULL,
                `basic_kpi` DECIMAL(15,2) DEFAULT NULL,
                `kpi_score` DECIMAL(10,4) DEFAULT NULL,
                `kpi_result` DECIMAL(15,2) DEFAULT NULL,
                `commission` DECIMAL(15,2) DEFAULT NULL,
                `bonuses` DECIMAL(15,2) DEFAULT NULL,
                `communication_allowance` DECIMAL(15,2) DEFAULT NULL,
                `transport_allowance` DECIMAL(15,2) DEFAULT NULL,
                `other_special_bonus_subsidy_etc` DECIMAL(15,2) DEFAULT NULL,
                `gross_salaries` DECIMAL(15,2) DEFAULT NULL,
                `deduction` DECIMAL(15,2) DEFAULT NULL,
                `gross_amount` DECIMAL(15,2) DEFAULT NULL,
                `remarque` TEXT DEFAULT NULL,
                `ps_pension_salarie` DECIMAL(15,2) DEFAULT NULL,
                `pac` DOUBLE DEFAULT NULL,
                `pac_arrondi` DECIMAL(15,2) DEFAULT NULL,
                `its` DECIMAL(15,2) DEFAULT NULL,
                `its2` DOUBLE DEFAULT NULL,
                `its_deduction_totale` DECIMAL(15,2) DEFAULT NULL,
                `net_a_payer` DECIMAL(15,2) DEFAULT NULL,
                `prestations_familiales` DECIMAL(15,2) DEFAULT NULL,
                `pension_employeur_pe_65_percent` DECIMAL(15,2) DEFAULT NULL,
                `risques_professionnels_rp_4_percent` DECIMAL(15,2) DEFAULT NULL,
                `vps_versement_patronal_sur_salaire_4_percent` DECIMAL(15,2) DEFAULT NULL,
                `total_part_patronale` DECIMAL(15,2) DEFAULT NULL,
                `total_des_resources_humaines` DECIMAL(15,2) DEFAULT NULL,
                `frais_de_service_percent_ht` DECIMAL(10,2) DEFAULT NULL,
                `tva_18_percent` DECIMAL(15,2) DEFAULT NULL,
                `cout_total_entreprise_externalisation` DECIMAL(15,2) DEFAULT NULL,
                `cout_total_facture` DECIMAL(15,2) DEFAULT NULL,
                PRIMARY KEY (`staff_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    // String columns (stored as-is, trimmed)
    private array $stringCols = [
        'staff_id', 'names', 'first_names', 'department',
        'joining_date', 'contract_start_date', 'contract_end_date',
        'position', 'job_grade', 'work_place',
        'bank_name', 'bank_account_number', 'social_security_number', 'remarque',
    ];

    // Int columns
    private array $intCols = ['legal_work_days', 'work_days'];

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
            return Inertia::render('BeninBunge/Index', [
                'records'        => [],
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        $records      = DB::table($tableName)->orderBy('names')->get();
        $totalRecords = $records->count();

        return Inertia::render('BeninBunge/Index', [
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
            'file'  => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
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
                'staff id', 'names', 'first names', 'department', 'position',
                'basic salary', 'net a payer', 'work place', 'bank name', 'no', 'salaire de base'
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
                return redirect()->route('benin-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', 'The uploaded file is empty.');
            }

            $headerRowIndex = $bestHeaderRowIndex;

            $headerRow = $rows[$headerRowIndex];
            // Combine previous row headers if present (e.g. section headers like "Gross salaries" and "NET A PAYER" on Row 1)
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
                'staff id'                    => 'staff_id',
                'matricule'                   => 'staff_id',
                'id'                          => 'staff_id',
                'names'                       => 'names',
                'nom'                         => 'names',
                'noms'                        => 'names',
                'nom et prenom'               => 'names',
                'first names'                 => 'first_names',
                'prenom'                      => 'first_names',
                'prenoms'                     => 'first_names',
                'department'                  => 'department',
                'departement'                 => 'department',
                'joining date'                => 'joining_date',
                'date embauche'               => 'joining_date',
                'contract start date'         => 'contract_start_date',
                'debut contrat'               => 'contract_start_date',
                'contract end date'           => 'contract_end_date',
                'fin contrat'                 => 'contract_end_date',
                'position'                    => 'position',
                'poste'                       => 'position',
                'job grade'                   => 'job_grade',
                'grade'                       => 'job_grade',
                'work place'                  => 'work_place',
                'lieu de travail'             => 'work_place',
                'bank name'                   => 'bank_name',
                'banque'                      => 'bank_name',
                'banques'                     => 'bank_name',
                'bank account number'         => 'bank_account_number',
                'numero de compte'            => 'bank_account_number',
                'social security number'      => 'social_security_number',
                'social security numner'      => 'social_security_number',
                'cnss'                        => 'social_security_number',
                'legal work days'             => 'legal_work_days',
                'work days'                   => 'work_days',
                'basic salary'                => 'basic_salary',
                'salaire de base'             => 'basic_salary',
                'sursalaire'                  => 'sursalaire',
                'basic kpi'                   => 'basic_kpi',
                'kpi score'                   => 'kpi_score',
                'kpi result'                  => 'kpi_result',
                'kpi reslut'                  => 'kpi_result',
                'commission'                  => 'commission',
                'bonuses'                     => 'bonuses',
                'paid overtime'               => 'bonuses',
                'overtime'                    => 'bonuses',
                'lunch allowance'             => 'other_special_bonus_subsidy_etc',
                'communication allowance'     => 'communication_allowance',
                'coomunication allowanc'      => 'communication_allowance',
                'transport allowance'         => 'transport_allowance',
                'transort allowance'          => 'transport_allowance',
                'other special bonus'         => 'other_special_bonus_subsidy_etc',
                'other special bonus/ subsidy etc' => 'other_special_bonus_subsidy_etc',
                'gross salaries'              => 'gross_salaries',
                'salaire brut'                => 'gross_salaries',
                'deduction'                   => 'deduction',
                'gross amount'                => 'gross_amount',
                'remarque'                    => 'remarque',
                'ps pension salarie'          => 'ps_pension_salarie',
                'ps(pension salarie)'         => 'ps_pension_salarie',
                'ps(pension salarié)'         => 'ps_pension_salarie',
                'pac'                         => 'pac',
                'arrondi'                     => 'pac_arrondi',
                'pac arrondi'                 => 'pac_arrondi',
                'its'                         => 'its',
                'its2'                        => 'its2',
                'deduction totale'            => 'its_deduction_totale',
                'déduction totale'            => 'its_deduction_totale',
                'its deduction totale'        => 'its_deduction_totale',
                'net a payer'                 => 'net_a_payer',
                'net payer'                   => 'net_a_payer',
                'prestations familiales'      => 'prestations_familiales',
                'prestations familiales pf (9%)' => 'prestations_familiales',
                'pension employeur 65%'       => 'pension_employeur_pe_65_percent',
                'pension employeur pe (6,5%)' => 'pension_employeur_pe_65_percent',
                'pension employeur pe (6.5%)' => 'pension_employeur_pe_65_percent',
                'risques professionnels 4%'   => 'risques_professionnels_rp_4_percent',
                'risques professionnels rp (4%)' => 'risques_professionnels_rp_4_percent',
                'vps versement 4%'            => 'vps_versement_patronal_sur_salaire_4_percent',
                'vps (versement patronal sur salaire) 4%' => 'vps_versement_patronal_sur_salaire_4_percent',
                'total part patronale'        => 'total_part_patronale',
                'total ressources humaines'   => 'total_des_resources_humaines',
                'total des resources humaines' => 'total_des_resources_humaines',
                'total des ressources humaines' => 'total_des_resources_humaines',
                'frais service ht'            => 'frais_de_service_percent_ht',
                'frais de service (%) ht'     => 'frais_de_service_percent_ht',
                'tva 18%'                     => 'tva_18_percent',
                'tva (18%)'                   => 'tva_18_percent',
                'cout total externalisation'  => 'cout_total_entreprise_externalisation',
                'coût total de l\'entreprise d\'externalisation' => 'cout_total_entreprise_externalisation',
                'cout total facture'          => 'cout_total_facture',
                'coût total de facture'       => 'cout_total_facture',
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
                return redirect()->route('benin-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', 'No recognisable column headers found in the uploaded file.');
            }

            DB::table($tableName)->truncate();

            $validRecords  = [];
            $errors        = [];
            $autoIdCounter = 1;
            $seenStaffIds  = [];

            foreach ($rows as $index => $row) {
                $allEmpty = true;
                foreach ($row as $cell) {
                    if (trim((string) $cell) !== '') { $allEmpty = false; break; }
                }
                if ($allEmpty) continue;

                $record = [];
                foreach ($colMap as $idx => $dbCol) {
                    $val = $row[$idx] ?? null;
                    if (in_array($dbCol, $this->stringCols)) {
                        $record[$dbCol] = trim((string) $val);
                    } elseif (in_array($dbCol, $this->intCols)) {
                        $clean = str_replace([',', ' '], '', trim((string)$val));
                        $record[$dbCol] = is_numeric($clean) ? (int) $clean : 0;
                    } else {
                        $clean = str_replace([',', ' '], '', trim((string)$val));
                        $record[$dbCol] = is_numeric($clean) ? (float) $clean : 0;
                    }
                }

                $name      = trim((string)($record['names'] ?? ''));
                $firstName = trim((string)($record['first_names'] ?? ''));
                $fullName  = trim($name . ' ' . $firstName);
                $staffId   = trim((string)($record['staff_id'] ?? ''));

                // Must have a person name with at least 2 alphabet letters
                if (empty($fullName)) {
                    continue;
                }
                $lettersOnly = preg_replace('/[^a-zA-Z]/', '', $fullName);
                if (strlen($lettersOnly) < 2) {
                    continue;
                }

                // Skip summary/footnote rows (e.g. overtime summaries, bank notes, totals, swift codes)
                $firstCellsText = strtoupper(implode(' ', array_slice($row, 0, 5)));
                if (
                    str_contains($firstCellsText, 'WEEKEND') ||
                    str_contains($firstCellsText, 'SUNDAY') ||
                    str_contains($firstCellsText, 'HOLIDAY') ||
                    str_contains($firstCellsText, 'TOTAL') ||
                    str_contains($firstCellsText, 'ECOC') ||
                    str_contains($firstCellsText, 'ECOBANK') ||
                    str_contains($firstCellsText, 'RATE') ||
                    str_contains($firstCellsText, 'PAID') ||
                    str_contains($firstCellsText, 'REMARQUE') ||
                    str_contains($firstCellsText, 'COMPTE') ||
                    str_contains($firstCellsText, 'ACCOUNT') ||
                    str_contains($firstCellsText, 'INVOICE') ||
                    preg_match('/^[A-Z]{2}[0-9]{10,}/', $name) ||
                    preg_match('/^[A-Z]{6,}[0-9]*/', $name)
                ) {
                    continue;
                }

                // If both basic salary and gross salary are zero/empty, this is not an active employee row
                $basicVal = (float)($record['basic_salary'] ?? 0);
                $grossVal = (float)($record['gross_salaries'] ?? ($record['gross_amount'] ?? 0));
                if ($basicVal <= 0 && $grossVal <= 0) {
                    continue;
                }

                // Auto-resolve Staff ID if missing or blank
                if (empty($staffId)) {
                    if (str_contains(strtoupper($fullName), 'AKODE')) {
                        $staffId = 'SBPTWMA1594';
                    } else {
                        // Check previous bungebenin tables for this person's ID
                        try {
                            $prevTables = DB::select("SHOW TABLES LIKE 'bungebenin%'");
                            foreach ($prevTables as $pt) {
                                $ptName = array_values((array)$pt)[0];
                                if ($ptName === $tableName) continue;
                                $existingStaff = DB::table($ptName)
                                    ->where('names', 'like', '%' . $name . '%')
                                    ->whereNotNull('staff_id')
                                    ->where('staff_id', '!=', '')
                                    ->value('staff_id');
                                if ($existingStaff) {
                                    $staffId = $existingStaff;
                                    break;
                                }
                            }
                        } catch (\Throwable $e) {}
                    }

                    if (empty($staffId)) {
                        $staffId = 'BEN-' . str_pad((string)$autoIdCounter++, 3, '0', STR_PAD_LEFT);
                        while (in_array($staffId, $seenStaffIds)) {
                            $staffId = 'BEN-' . str_pad((string)$autoIdCounter++, 3, '0', STR_PAD_LEFT);
                        }
                    }
                    $record['staff_id'] = $staffId;
                }

                if (in_array($record['staff_id'], $seenStaffIds)) {
                    $record['staff_id'] .= '-' . ($index + 1);
                }

                // Sync bank details if present on row
                if (!empty($record['bank_account_number'])) {
                    try {
                        if (Schema::hasTable('bankdetails')) {
                            DB::table('bankdetails')->updateOrInsert(
                                ['employid' => $record['staff_id']],
                                [
                                    'name'    => $fullName,
                                    'bank'    => $record['bank_name'] ?: 'Ecobank, BJ',
                                    'account' => $record['bank_account_number'],
                                    'branch'  => 'Cotonou',
                                ]
                            );
                        }
                    } catch (\Throwable $ex) {
                        Log::warning("Could not sync Benin bank detail: " . $ex->getMessage());
                    }
                }

                $seenStaffIds[] = $record['staff_id'];
                $validRecords[] = $record;
            }

            if (!empty($errors)) {
                $msg = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('benin-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', $msg);
            }

            if (!empty($validRecords)) {
                foreach (array_chunk($validRecords, 500) as $chunk) {
                    DB::table($tableName)->insert($chunk);
                }
                Log::info("BeninBungePay upload: inserted " . count($validRecords) . " rows into {$tableName}");
            }

            return redirect()->route('benin-bunge.pay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');

        } catch (\Exception $e) {
            Log::error('BeninBungePay upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('benin-bunge.pay.index', ['month' => $selectedMonth])
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
        $sheet->setTitle('Benin Bunge Payroll');

        $headers = [
            'Staff ID', 'Names', 'First Names', 'Department', 'Joining Date',
            'Contract Start Date', 'Contract End Date', 'Position', 'Job Grade', 'Work Place',
            'Bank Name', 'Bank Account Number', 'Social Security Number', 'Legal Work Days', 'Work Days',
            'Basic Salary', 'Sursalaire', 'Basic KPI', 'KPI Score', 'KPI Result',
            'Commission', 'Bonuses', 'Communication Allowance', 'Transport Allowance',
            'Other Special Bonus', 'Gross Salaries', 'Deduction', 'Gross Amount', 'Remarque',
            'PS Pension Salarie', 'PAC', 'PAC Arrondi', 'ITS', 'ITS2', 'ITS Deduction Totale',
            'Net A Payer', 'Prestations Familiales', 'Pension Employeur 65%',
            'Risques Professionnels 4%', 'VPS Versement 4%', 'Total Part Patronale',
            'Total Ressources Humaines', 'Frais Service HT', 'TVA 18%',
            'Cout Total Externalisation', 'Cout Total Facture',
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
        $filename = 'benin_bunge_payroll_template.xlsx';

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

            $records = DB::table($tableName)->orderBy('names')->get();

            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Benin Bunge Payroll');

            $headers = ['Staff ID', 'Names', 'Net A Payer'];
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
                $sheet->setCellValue("A{$row}", $r->staff_id);
                $sheet->setCellValue("B{$row}", $r->names ?? '');
                $sheet->setCellValue("C{$row}", $r->net_a_payer ?? '');
                $row++;
            }

            for ($i = 1; $i <= 3; $i++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
            }

            $sheet->freezePane('A2');

            $writer   = new Xlsx($spreadsheet);
            $filename = "benin_bunge_payroll_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);

        } catch (\Exception $e) {
            Log::error('BeninBungePay export failed', ['error' => $e->getMessage()]);
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
            Log::error('BeninBungePay deleteMonth failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete records: ' . $e->getMessage()], 500);
        }
    }
}
