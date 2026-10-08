<?php

namespace App\Http\Controllers\SalaryPayment;

use App\Http\Controllers\Controller;
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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class TranssionSalaryPaymentController extends Controller
{
    private $clientDbname = 'transsion';
    private $shortName = 'Transsion';
    private $color = '#1e40af'; // Transsion blue

    // TRANSSION EXACT TEMPLATE HEADERS (FROM YOUR ORIGINAL)
    private function getTranssionHeaders(): array
    {
        return [
            'Staff ID', 'Name', 'Department', 'Hire Date', 'Contract Start', 'Contract End', 'Position', 'Grade',
            'Categories', 'Workplace', 'Bank Name', 'Account Number', 'Branch', 'SSNIT', 'COL 12', 'Work Days',
            'Leave Days', 'OT Work Days', 'OT Weekend', 'Basic', 'Performance Bonus', 'Coefficient', 'Performance Standard',
            'Allowance', 'Commission', 'OT Working Day', 'OT Holiday', 'Other', 'Annual Bonus', 'Leave Deduction',
            'Other Deduction', 'Loan', 'Gross Pay', 'Excess', 'Total Emoluments', 'Accommodation', 'Charge Income',
            'Petra', 'SSNIT Amount', 'NSSF', 'Tax Base', 'PAYE', 'Annual Tax', 'Net Pay', 'HR Cost', 'Brand Name'
        ];
    }

    // DB COLUMN MAPPING FOR TRANSSION
    private function getHeaderToDbMap(): array
    {
        $headers = $this->getTranssionHeaders();
        $dbColumns = [
            'staffida','name','department','hiredate','constart','conend','position','grade',
            'categories','workplace','bankname','alcnum','branch','ssnit','col12','workdays',
            'leavedays','otworkdays','otweekend','basic','performbonus','coefficient','performstand',
            'allowance','commission','otworkingday','otholiday','other','annaulbonus','leavededuction',
            'otherdeduction','loan','grosspay','excess','totalemolu','accommodation','chargeincome',
            'petra','ssnitamt','nssf','taxbase','paye','annaultax','netpay','hrcost','brandname'
        ];
        return array_combine($headers, $dbColumns);
    }

    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $formattedMonth = $date->format('F Y');
        $tableName = \App\Models\MonthlySalary::generateTableName($this->clientDbname, $month);

        if (!Schema::hasTable($tableName)) {
    return Inertia::render('Payroll/SalaryPayment/Transsion', [
        'client' => [
            'short_name' => $this->shortName,
            'client_dbname' => $this->clientDbname,
            'color' => $this->color
        ],
        'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25),
        'selectedMonth' => $month,
        'formattedMonth' => $formattedMonth,
        'totalRecords' => 0,
        'searchCount' => null,
        'flash' => ['error' => "No data for {$formattedMonth}. Upload using template."]
        // ← NO TRAILING COMMA HERE
    ]);
}

        $query = DB::table("{$tableName} as ms")
            ->leftJoin('bankdetails as bd', 'ms.staffida', '=', 'bd.employid')
            ->select(
                'ms.staffida',
                'ms.name',
                'bd.shop',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'ms.netpay'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ms.staffida', 'like', "%{$search}%")
                  ->orWhere('ms.name', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'staffida');
        $order = $request->input('order', 'asc');
        $sortColumn = in_array($sort, ['shop', 'bank', 'account', 'branch', 'code'])
            ? "bd.{$sort}"
            : "ms.{$sort}";
        $query->orderBy($sortColumn, $order);

        $perPage = 25;
        $records = $query->paginate($perPage)->appends($request->query());

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        return Inertia::render('Payroll/SalaryPayment/Transsion', [
            'client' => ['short_name' => $this->shortName, 'client_dbname' => $this->clientDbname, 'color' => $this->color],
            'records' => $records,
            'selectedMonth' => $month,
            'formattedMonth' => $formattedMonth,
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'flash' => session('success') ? ['success' => session('success')] : (session('error') ? ['error' => session('error')] : null),
        ]);
    }

    public function export(Request $request)
{
    try {
        Log::info('Transsion Export Started', $request->all());

        // === Step 1: Extract Request Params ===
        $month  = $request->input('month', now()->format('Y-m'));
        $search = $request->input('search');
        $sort   = $request->input('sort', 'staffida');
        $order  = $request->input('order', 'asc');

        // === Step 2: Determine Table ===
        $tableName = \App\Models\MonthlySalary::generateTableName($this->clientDbname, $month);
        if (!Schema::hasTable($tableName)) {
            return response()->json(['error' => "No data for {$month}"], 404);
        }

        // === Step 3: Query Data ===
        $query = DB::table("{$tableName} as ms")
            ->leftJoin('bankdetails as bd', 'ms.staffida', '=', 'bd.employid')
            ->select([
                'ms.staffida',
                'ms.name',
                'bd.shop as shop',
                'bd.bank as bank',
                'bd.account as account',
                'bd.code as code',
                'bd.branch as branch',
                'ms.netpay',
            ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('ms.staffida', 'like', "%{$search}%")
                  ->orWhere('ms.name', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%")
                  ->orWhere('bd.shop', 'like', "%{$search}%")
                  ->orWhere('bd.account', 'like', "%{$search}%");
            });
        }

        $sortColumn = in_array($sort, ['shop', 'bank', 'account', 'code', 'branch'])
            ? "bd.{$sort}"
            : "ms.{$sort}";

        $records = $query->orderBy($sortColumn, $order === 'desc' ? 'desc' : 'asc')->get();

        // === Step 4: Create Spreadsheet ===
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // --- Header Row ---
        $headers = ['Staff ID', 'Name', 'Department', 'Bank', 'Account', 'Branch Code', 'Branch', 'Net Pay'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(chr(65 + $i) . '1', $h);
        }

        // --- Header Style ---
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1e40af'],
            ],
        ]);

        // --- Account Column: Force Text ---
        $sheet->getStyle('E:E')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

        // === Step 5: Populate Rows ===
        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staffida);
            $sheet->setCellValue("B{$row}", $r->name);
            $sheet->setCellValue("C{$row}", $r->shop ?? 'N/A');
            $sheet->setCellValue("D{$row}", $r->bank ?? 'N/A');

            // Explicitly set as text to prevent truncating leading zeros
            $account = $r->account ?? 'N/A';
            $sheet->setCellValueExplicit("E{$row}", $account, DataType::TYPE_STRING);

            $sheet->setCellValue("F{$row}", $r->code ?? 'N/A');
            $sheet->setCellValue("G{$row}", $r->branch ?? 'N/A');
            $sheet->setCellValue("H{$row}", $r->netpay ? number_format((float)$r->netpay, 2, '.', '') : '0.00');
            $row++;
        }

        // === Step 6: Adjust Layout ===
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // === Step 7: Stream Download ===
        $writer = new Xlsx($spreadsheet);
        $fileName = "Transsion_{$month}_Salary.xlsx";

        // Proper buffer handling (prevents corrupted downloads)
        if (ob_get_length()) {
            ob_end_clean();
        }

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);

    } catch (\Throwable $e) {
        Log::error('Transsion Export Failed', [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
            'request' => $request->all(),
        ]);

        return response()->json([
            'error' => 'Export failed: ' . $e->getMessage()
        ], 500);
    }
}

    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        try {
            $month = $request->input('month');
            $tableName = \App\Models\MonthlySalary::generateTableName($this->clientDbname, $month);
            \App\Models\MonthlySalary::forClient($this->clientDbname, $month); // Auto-create table

            $file = $request->file('file');
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (empty($rows)) {
                return redirect()->back()->with('error', 'Empty file');
            }

            $uploadedHeaders = array_map('trim', array_shift($rows));
            $expectedHeaders = $this->getTranssionHeaders();
            $headerToDb = $this->getHeaderToDbMap();

            $missing = array_diff($expectedHeaders, $uploadedHeaders);
            if (!empty($missing)) {
                return redirect()->back()->with('error', 'Invalid template. Missing: ' . implode(', ', array_slice($missing, 0, 5)));
            }

            $existingIds = DB::table($tableName)->pluck('staffida')->toArray();
            $validRecords = [];
            $errors = [];
            $duplicateIds = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                $record = [];
                $staffId = null;
                $name = null;
                $netpay = null;

                foreach ($expectedHeaders as $idx => $header) {
                    $value = trim($row[$idx] ?? '');

                    if (!isset($headerToDb[$header])) continue;

                    $dbCol = $headerToDb[$header];

                    if ($dbCol === 'netpay') {
                        $clean = preg_replace('/[^0-9.]/', '', $value);
                        $value = is_numeric($clean) ? (float)$clean : null;
                        $netpay = $value;
                    }

                    if ($dbCol === 'staffida') $staffId = $value;
                    if ($dbCol === 'name') $name = $value;

                    $record[$dbCol] = $value ?? null;
                }

                if (!$staffId) {
                    $errors[] = "Row {$rowNumber}: Staff ID missing";
                    continue;
                }
                if (!$name) {
                    $errors[] = "Row {$rowNumber}: Name missing";
                    continue;
                }
                if (is_null($netpay)) {
                    $errors[] = "Row {$rowNumber}: Invalid Net Pay";
                    continue;
                }

                if (in_array($staffId, $existingIds) || in_array($staffId, array_column($validRecords, 'staffida'))) {
                    $duplicateIds[] = $staffId;
                    continue;
                }

                $validRecords[] = $record;
            }

            if (!empty($errors)) {
                $msg = count($errors) > 5 ? implode(' | ', array_slice($errors, 0, 5)) . ' | ...' : implode(' | ', $errors);
                return redirect()->back()->with('error', $msg);
            }

            if (!empty($duplicateIds)) {
                return redirect()->back()->with('error', 'Duplicates: ' . implode(', ', array_unique($duplicateIds)));
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
            }

            return redirect()->back()->with('success', 'Transsion: Uploaded ' . count($validRecords) . ' records!');

        } catch (\Exception $e) {
            Log::error('Transsion Upload Failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function delete(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $tableName = \App\Models\MonthlySalary::generateTableName($this->clientDbname, $month);

        if (Schema::hasTable($tableName)) {
            DB::table($tableName)->truncate();
            return redirect()->back()->with('success', 'Transsion records deleted for ' . Carbon::createFromFormat('Y-m', $month)->format('F Y'));
        }

        return redirect()->back()->with('error', 'No records found.');
    }

    public function downloadTemplate()
    {
        $headers = $this->getTranssionHeaders();
        $exampleRow = array_fill(0, count($headers), '');
        $exampleRow[0] = 'TRS001';
        $exampleRow[1] = 'Kwame Mensah';
        $exampleRow[array_key_last($exampleRow)] = '8500.00';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([$exampleRow], null, 'A2');

        // Style header
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e40af']],
        ];
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray($headerStyle);
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $writer = new Xlsx($spreadsheet);
        $fileName = "Transsion_Payroll_Template_" . now()->format('M_Y') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
