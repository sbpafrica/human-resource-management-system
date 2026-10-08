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
                `cnpssalaries` VARCHAR(255) DEFAULT NULL,
                `cnpspatron` VARCHAR(191) DEFAULT NULL,
                `totaldeduction` VARCHAR(191) DEFAULT NULL,
                `netpay` VARCHAR(191) DEFAULT NULL,
                `fdfp` VARCHAR(255) DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
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
            'file'  => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
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
            $rows        = $spreadsheet->getActiveSheet()->toArray();

            if (empty($rows)) {
                return redirect()->route('civ-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', 'The uploaded file is empty.');
            }

            // Read and map headers
            $headerRow = array_shift($rows);
            $headerMap = [
                'id'                => 'id',
                '1st level'         => '1stlevel',
                '2nd level'         => '2ndlevel',
                'city'              => 'city',
                'job title'         => 'jobtitle',
                'full name'         => 'fullname',
                'report line'       => 'reportline',
                'outsourcing'       => 'outsourcing',
                'entry date'        => 'entrydate',
                'payroll start'     => 'payrollstart',
                'payroll end'       => 'payrollend',
                'salary month'      => 'salarymonth',
                'legal work'        => 'legalwork',
                'actual work'       => 'actualwork',
                'basic fixed'       => 'basicfixed',
                'basic'      => 'actualbasic',
                'overtime'          => 'overtime',
                'overtime allowance'=> 'overtimeallowance',
                'transport'         => 'transport',
                'call allowance'    => 'callallowance',
                'basic kpi'         => 'basickpi',
                'coefficient'       => 'coefficient',
                'monthly kpi'       => 'monthlykpi',
                'leave allowance'   => 'leaveallowance',
                'gross salary 1'    => 'grosssalary1',
                'other deductions'  => 'otherdeductions',
                'gross salary'      => 'grosssalary',
                'nombre'            => 'nombre',
                'import brut'       => 'importbrut',
                'ricf'              => 'ricf',
                'its salaries'      => 'itsalaries',
                'cnps salaries'     => 'cnpssalaries',
                'cnps patron'       => 'cnpspatron',
                'total deduction'   => 'totaldeduction',
                'net pay'           => 'netpay',
                'fdfp'              => 'fdfp',
            ];

            // Map column index → db column
            $colMap = [];
            foreach ($headerRow as $idx => $hdr) {
                $key = strtolower(trim((string) $hdr));
                if (isset($headerMap[$key])) {
                    $colMap[$idx] = $headerMap[$key];
                }
            }

            if (empty($colMap)) {
                return redirect()->route('civ-bunge.pay.index', ['month' => $selectedMonth])
                    ->with('error', 'No recognisable column headers found in the uploaded file.');
            }

            // Truncate then insert
            DB::table($tableName)->truncate();

            $validRecords = [];
            $errors       = [];

            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;

                // Skip completely empty rows
                $allEmpty = true;
                foreach ($row as $cell) {
                    if (trim((string) $cell) !== '') { $allEmpty = false; break; }
                }
                if ($allEmpty) continue;

                $record = [];
                foreach ($colMap as $idx => $dbCol) {
                    $record[$dbCol] = trim((string) ($row[$idx] ?? ''));
                }

                if (empty($record['id'] ?? '')) {
                    $errors[] = "Row {$rowNum}: ID cannot be empty.";
                    continue;
                }

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
                $sheet->setCellValue("C{$row}", $r->netpay ?? '');
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
