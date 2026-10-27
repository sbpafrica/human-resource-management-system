<?php

namespace App\Http\Controllers;

use App\Models\Tecnopay;
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

class TecnoPayController extends Controller
{
 public function index(Request $request)
 {
 $request->validate([
 'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
 ]);

 $month = $request->input('month', now()->format('Y-m'));
 Log::info("Index: Received month: {$month}");

 $date = Carbon::createFromFormat('Y-m', $month);
 $tableName = 'transsiontec' . strtolower($date->format('F')) . $date->format('y');
 Log::info("Index: Generated table name: {$tableName}");

 if (!DB::getSchemaBuilder()->hasTable($tableName)) {
 Log::warning("Index: Table {$tableName} does not exist");
 return Inertia::render('Tecnopay/Index', [
 'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
 'selectedMonth' => $month,
 'formattedMonth' => $date->format('F Y'),
 'totalRecords' => 0,
 'searchCount' => null,
 'flash' => [
 'error' => "No data available for {$date->format('F Y')}. Please upload data.",
 ],
 ]);
 }

 $query = DB::table($tableName . ' as tp')
 ->leftJoin('bankdetails as bd', 'tp.staffida', '=', 'bd.employid')
 ->select(
 'tp.staffida',
 'tp.name',
 'bd.shop',
 'bd.bank',
 'bd.account',
 'bd.code',
 'bd.branch',
 'tp.netpay'
 );

 // Apply search
 if ($search = $request->input('search')) {
 $query->where(function ($q) use ($search, $tableName) {
 $q->where("tp.staffida", 'like', "%{$search}%")
 ->orWhere("tp.name", 'like', "%{$search}%")
 ->orWhere('bd.bank', 'like', "%{$search}%");
 });
 }

 // Apply sorting
 $sort = $request->input('sort', 'staffida');
 $order = $request->input('order', 'asc');
 Log::info("Index: Sorting by {$sort} {$order}");
 $sortColumn = in_array($sort, ['shop', 'department']) ? 'bd.shop' :
 (in_array($sort, ['bank', 'bankname']) ? 'bd.bank' :
 (in_array($sort, ['account', 'alcnum']) ? 'bd.account' :
 (in_array($sort, ['code']) ? 'bd.code' :
 (in_array($sort, ['branch']) ? 'bd.branch' : "tp.{$sort}"))));
 $query->orderBy($sortColumn, $order);

 // Paginate results
 $perPage = 10;
 $records = $query->paginate($perPage)->appends([
 'month' => $month,
 'search' => $search,
 'sort' => $sort,
 'order' => $order,
 ]);

 // Get total records and search count
 $totalRecords = DB::table($tableName)->count();
 $searchCount = $search ? $query->count() : null;

 Log::info("Index: Retrieved records", ['count' => $records->total(), 'sample' => $records->items()[0] ?? null]);

 return Inertia::render('Tecnopay/Index', [
 'records' => $records,
 'selectedMonth' => $month,
 'formattedMonth' => $date->format('F Y'),
 'totalRecords' => $totalRecords,
 'searchCount' => $searchCount,
 'flash' => [
 'success' => session('success'),
 'error' => session('error'),
 ],
 ]);
 }



public function export(Request $request)
{
    try {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);

        $month = $request->input('month');
        $date  = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'transsiontec' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staffida', '=', 'bd.employid')
            ->select(
                'tp.staffida',
                'tp.name',
                'bd.shop',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffida', 'like', "%{$search}%")
                  ->orWhere('tp.name', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort  = $request->input('sort', 'staffida');
        $order = $request->input('order', 'asc');

        $sortColumn = match ($sort) {
            'shop'      => 'bd.shop',
            'bank'      => 'bd.bank',
            'account'   => 'bd.account',
            'code'      => 'bd.code',
            'branch'    => 'bd.branch',
            default     => "tp.{$sort}",
        };

        $records = $query->orderBy($sortColumn, $order)->get();

        // ==================== EXCEL GENERATION ====================
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Name', 'Department', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Net Pay'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staffida);
            $sheet->setCellValue("B{$row}", $r->name);
            $sheet->setCellValue("C{$row}", $r->shop ?? 'TECNO');
            $sheet->setCellValue("D{$row}", $r->bank ?? '');

            // 100% GUARANTEED: Account & Code as TEXT — no scientific, no apostrophe
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '',    DataType::TYPE_STRING);

            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->netpay ? (float)$r->netpay : '');

            $row++;
        }

        // Same professional styling as all other exports
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('bbdcfe');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "tecno_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

    } catch (\Exception $e) {
        Log::error("TecnoPay export failed: " . $e->getMessage());
        return response()->json(['error' => 'Export failed'], 500);
    }
}

 public function upload(Request $request)
 {
 $request->validate([
 'file' => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:2048'],
 'month' => ['required', 'date_format:Y-m'],
 ]);

 try {
 $selectedMonth = $request->input('month');
 session(['selected_month' => $selectedMonth]);
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'transsiontec' . strtolower($date->format('F')) . $date->format('y');

 // Create table if it doesn't exist
 TecnoPay::createTableIfNotExists($tableName);

 $file = $request->file('file');
 $reader = new XlsxReader();
 $spreadsheet = $reader->load($file->getRealPath());
 $sheet = $spreadsheet->getActiveSheet();
 $rows = $sheet->toArray();
 array_shift($rows); // Remove header row

 // Check for existing staff IDs
 $existingStaffIds = DB::table($tableName)->pluck('staffida')->toArray();
 $duplicateStaffIds = [];
 $validRecords = [];
 $errors = [];

 foreach ($rows as $index => $row) {
 if (count($row) < 46) {
 $errors[] = "Row " . ($index + 2) . ": Expected 46 columns, found " . count($row);
 continue;
 }

 // Map all columns from Excel
 $staffId = trim($row[0] ?? '') ?: null;
 $name = trim($row[1] ?? '') ?: null;
 $department = trim($row[2] ?? '') ?: null;
 $hiredate = trim($row[3] ?? '') ?: null;
 $constart = trim($row[4] ?? '') ?: null;
 $conend = trim($row[5] ?? '') ?: null;
 $position = trim($row[6] ?? '') ?: null;
 $grade = trim($row[7] ?? '') ?: null;
 $categories = trim($row[8] ?? '') ?: null;
 $workplace = trim($row[9] ?? '') ?: null;
 $bankname = trim($row[10] ?? '') ?: null;
 $alcnum = trim($row[11] ?? '') ?: null;
 $branch = trim($row[12] ?? '') ?: null;
 $ssnit = trim($row[13] ?? '') ?: null;
 $col12 = trim($row[14] ?? '') ?: null;
 $workdays = is_numeric($row[15] ?? '') ? (int) $row[15] : null;
 $leavedays = is_numeric($row[16] ?? '') ? (int) $row[16] : null;
 $otworkdays = is_numeric($row[17] ?? '') ? (int) $row[17] : null;
 $otweekend = is_numeric($row[18] ?? '') ? (int) $row[18] : null;
 $basic = is_numeric($row[19] ?? '') ? (float) $row[19] : null;
 $performbonus = is_numeric($row[20] ?? '') ? (float) $row[20] : null;
 $coefficient = is_numeric($row[21] ?? '') ? (float) $row[21] : null;
 $performstand = is_numeric($row[22] ?? '') ? (float) $row[22] : null;
 $allowance = is_numeric($row[23] ?? '') ? (float) $row[23] : null;
 $commission = is_numeric($row[24] ?? '') ? (float) $row[24] : null;
 $otworkingday = is_numeric($row[25] ?? '') ? (float) $row[25] : null;
 $otholiday = is_numeric($row[26] ?? '') ? (float) $row[26] : null;
 $other = is_numeric($row[27] ?? '') ? (float) $row[27] : null;
 $annaulbonus = is_numeric($row[28] ?? '') ? (float) $row[28] : 0;
 $leavededuction = is_numeric($row[29] ?? '') ? (float) $row[29] : null;
 $otherdeduction = is_numeric($row[30] ?? '') ? (float) $row[30] : null;
 $loan = is_numeric($row[31] ?? '') ? (float) $row[31] : null;
 $grosspay = is_numeric($row[32] ?? '') ? (float) $row[32] : null;
 $excess = is_numeric($row[33] ?? '') ? (float) $row[33] : 0;
 $totalemolu = is_numeric($row[34] ?? '') ? (float) $row[34] : 0;
 $accommodation = is_numeric($row[35] ?? '') ? (float) $row[35] : 0;
 $chargeincome = is_numeric($row[36] ?? '') ? (float) $row[36] : 0;
 $petra = is_numeric($row[37] ?? '') ? (float) $row[37] : 0;
 $ssnitamt = is_numeric($row[38] ?? '') ? (float) $row[38] : 0;
 $nssf = is_numeric($row[39] ?? '') ? (float) $row[39] : 0;
 $taxbase = is_numeric($row[40] ?? '') ? (float) $row[40] : 0;
 $paye = is_numeric($row[41] ?? '') ? (float) $row[41] : 0;
 $annaultax = is_numeric($row[42] ?? '') ? (float) $row[42] : 0;
 $rawNetPay = trim($row[43] ?? '');
 $cleanNetPay = preg_replace('/[^0-9.]/', '', $rawNetPay);
 $decimalCount = substr_count($cleanNetPay, '.');
 $netpay = ($decimalCount <= 1 && is_numeric($cleanNetPay)) ? (float) $cleanNetPay : null;
 $hrcost = is_numeric($row[44] ?? '') ? (float) $row[44] : 0;
 $brandname = trim($row[45] ?? '') ?: null;

 // Log row data for debugging
 Log::info("Processing row " . ($index + 2) . ": ", [
 'staffId' => $staffId,
 'name' => $name,
 'department' => $department,
 'hiredate' => $hiredate,
 'constart' => $constart,
 'conend' => $conend,
 'position' => $position,
 'grade' => $grade,
 'bankname' => $bankname,
 'alcnum' => $alcnum,
 'branch' => $branch,
 'netpay' => $rawNetPay,
 'cleanNetPay' => $cleanNetPay,
 ]);

 // Validation
 if (empty($staffId)) {
 $errors[] = "Row " . ($index + 2) . ": Staff ID cannot be empty.";
 continue;
 }
 if (empty($name)) {
 $errors[] = "Row " . ($index + 2) . ": Name cannot be empty.";
 continue;
 }
 if (is_null($netpay)) {
 $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number (found: '$rawNetPay').";
 continue;
 }

 if (in_array($staffId, $existingStaffIds) || in_array($staffId, $duplicateStaffIds)) {
 $duplicateStaffIds[] = $staffId;
 continue;
 }

 $data = [
 'staffida' => $staffId,
 'name' => $name,
 'department' => $department,
 'hiredate' => $hiredate,
 'constart' => $constart,
 'conend' => $conend,
 'position' => $position,
 'grade' => $grade,
 'categories' => $categories,
 'workplace' => $workplace,
 'bankname' => $bankname,
 'alcnum' => $alcnum,
 'branch' => $branch,
 'ssnit' => $ssnit,
 'COL 12' => $col12,
 'workdays' => $workdays,
 'leavedays' => $leavedays,
 'otworkdays' => $otworkdays,
 'otweekend' => $otweekend,
 'basic' => $basic,
 'performbonus' => $performbonus,
 'coefficient' => $coefficient,
 'performstand' => $performstand,
 'allowance' => $allowance,
 'commission' => $commission,
 'otworkingday' => $otworkingday,
 'otholiday' => $otholiday,
 'other' => $other,
 'annaulbonus' => $annaulbonus,
 'leavededuction' => $leavededuction,
 'otherdeduction' => $otherdeduction,
 'loan' => $loan,
 'grosspay' => $grosspay,
 'excess' => $excess,
 'totalemolu' => $totalemolu,
 'accommodation' => $accommodation,
 'chargeincome' => $chargeincome,
 'petra' => $petra,
 'ssnitamt' => $ssnitamt,
 'nssf' => $nssf,
 'taxbase' => $taxbase,
 'paye' => $paye,
 'annaultax' => $annaultax,
 'netpay' => $netpay,
 'hrcost' => $hrcost,
 'brandname' => $brandname,
 ];

 $validRecords[] = $data;
 $existingStaffIds[] = $staffId;
 }

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('tecnopay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('tecnopay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('tecnopay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('tecnopay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'transsiontec' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('tecnopay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('tecnopay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('tecnopay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 public function downloadTemplate()
 {
 $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

 // Set headers
 $headers = [
 'Staff ID', 'Name', 'Department', 'Hire Date', 'Contract Start', 'Contract End', 'Position', 'Grade',
 'Categories', 'Workplace', 'Bank Name', 'Account Number', 'Branch', 'SSNIT', 'COL 12', 'Work Days',
 'Leave Days', 'OT Work Days', 'OT Weekend', 'Basic', 'Performance Bonus', 'Coefficient', 'Performance Standard',
 'Allowance', 'Commission', 'OT Working Day', 'OT Holiday', 'Other', 'Annual Bonus', 'Leave Deduction',
 'Other Deduction', 'Loan', 'Gross Pay', 'Excess', 'Total Emoluments', 'Accommodation', 'Charge Income',
 'Petra', 'SSNIT Amount', 'NSSF', 'Tax Base', 'PAYE', 'Annual Tax', 'Net Pay', 'HR Cost', 'Brand Name'
 ];
 $sheet->fromArray($headers, null, 'A1');

 // Example data
 $exampleData = [
 [
 'STF123', 'John Doe', 'Sales', '10/19/2016', '01/01/2025', '12/31/2025', 'SALES REPRESENTATIVE', 'L09',
 'FORMAL STAFF', 'ACCRA', 'Bank A', '1234567890', 'Branch X', 'P0012345678', 'P0031284639', 21, 0, 0, 0,
 1113.75, 1856.25, 1, 1856.25, 742.5, 0, 0, 0, 0, 0, 0, 0, 0, 3712.5, 0, 3712.5, 0, 3712.5, 55.69,
 5.57, 144.79, 3651.24, 529.72, 0, 3121.52, 3857.29, 'TRANSSION'
 ]
 ];
 $sheet->fromArray($exampleData, null, 'A2');

 // Add a note
 $sheet->setCellValue('A4', 'Note: Numeric fields (e.g., Net Pay, Basic) must be numbers without symbols. Dates should be in MM/DD/YYYY format.');

 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'tecno_template.xlsx';
 $tempFile = tempnam(sys_get_temp_dir(), $fileName);
 $writer->save($tempFile);

 return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
 }
}
