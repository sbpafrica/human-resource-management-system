<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
            $rows        = $spreadsheet->getActiveSheet()->toArray();

            if (empty($rows)) {
                return redirect()->route('benin-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', 'The uploaded file is empty.');
            }

            $headerRow = array_shift($rows);
            $headerMap = [
                'staff id'                    => 'staff_id',
                'names'                       => 'names',
                'first names'                 => 'first_names',
                'department'                  => 'department',
                'joining date'                => 'joining_date',
                'contract start date'         => 'contract_start_date',
                'contract end date'           => 'contract_end_date',
                'position'                    => 'position',
                'job grade'                   => 'job_grade',
                'work place'                  => 'work_place',
                'bank name'                   => 'bank_name',
                'bank account number'         => 'bank_account_number',
                'social security number'      => 'social_security_number',
                'legal work days'             => 'legal_work_days',
                'work days'                   => 'work_days',
                'basic salary'                => 'basic_salary',
                'sursalaire'                  => 'sursalaire',
                'basic kpi'                   => 'basic_kpi',
                'kpi score'                   => 'kpi_score',
                'kpi result'                  => 'kpi_result',
                'commission'                  => 'commission',
                'bonuses'                     => 'bonuses',
                'communication allowance'     => 'communication_allowance',
                'transport allowance'         => 'transport_allowance',
                'other special bonus'         => 'other_special_bonus_subsidy_etc',
                'gross salaries'              => 'gross_salaries',
                'deduction'                   => 'deduction',
                'gross amount'                => 'gross_amount',
                'remarque'                    => 'remarque',
                'ps pension salarie'          => 'ps_pension_salarie',
                'pac'                         => 'pac',
                'pac arrondi'                 => 'pac_arrondi',
                'its'                         => 'its',
                'its2'                        => 'its2',
                'its deduction totale'        => 'its_deduction_totale',
                'net a payer'                 => 'net_a_payer',
                'prestations familiales'      => 'prestations_familiales',
                'pension employeur 65%'       => 'pension_employeur_pe_65_percent',
                'risques professionnels 4%'   => 'risques_professionnels_rp_4_percent',
                'vps versement 4%'            => 'vps_versement_patronal_sur_salaire_4_percent',
                'total part patronale'        => 'total_part_patronale',
                'total ressources humaines'   => 'total_des_resources_humaines',
                'frais service ht'            => 'frais_de_service_percent_ht',
                'tva 18%'                     => 'tva_18_percent',
                'cout total externalisation'  => 'cout_total_entreprise_externalisation',
                'cout total facture'          => 'cout_total_facture',
            ];

            $colMap = [];
            foreach ($headerRow as $idx => $hdr) {
                $key = strtolower(trim((string) $hdr));
                if (isset($headerMap[$key])) {
                    $colMap[$idx] = $headerMap[$key];
                }
            }

            if (empty($colMap)) {
                return redirect()->route('benin-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', 'No recognisable column headers found in the uploaded file.');
            }

            DB::table($tableName)->truncate();

            $validRecords = [];
            $errors       = [];

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;

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
                        $record[$dbCol] = is_numeric($val) ? (int) $val : 0;
                    } else {
                        $record[$dbCol] = is_numeric($val) ? (float) $val : 0;
                    }
                }

                if (empty($record['staff_id'] ?? '')) {
                    $errors[] = "Row {$rowNum}: Staff ID cannot be empty.";
                    continue;
                }

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
