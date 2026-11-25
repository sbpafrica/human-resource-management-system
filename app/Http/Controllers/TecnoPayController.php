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
            $sheet->setCellValue("H{$row}", ($r->netpay !== null && $r->netpay !== '') ? number_format((float)$r->netpay, 2, '.', '') : '');

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
        \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'transsiontec');

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'csv') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
        } elseif ($ext === 'xls') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        } elseif ($ext === 'ods') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Ods();
        } else {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        }
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, false);

        if (empty($rows)) {
            return redirect()->route('tecnopay.index', ['month' => $selectedMonth])
                ->with('error', 'Uploaded spreadsheet is empty.');
        }

        // Find actual header row dynamically
        $headerRowIndex = null;
        $headerRow = [];
        foreach ($rows as $rIdx => $r) {
            if ($rIdx > 10) break;
            $matchCount = 0;
            foreach ($r as $cellVal) {
                if (!is_string($cellVal)) continue;
                $norm = strtolower(trim(preg_replace('/[\s\xc2\xa0]+/u', ' ', $cellVal)));
                if (preg_match('/^(staff(\s*id)?|name|basic|gross(\s*pay)?|net(\s*pay)?|brand(\s*name)?)/i', $norm)) {
                    $matchCount++;
                }
            }
            if ($matchCount >= 2) {
                $headerRowIndex = $rIdx;
                $headerRow = $r;
                break;
            }
        }

        if ($headerRowIndex === null) {
            $headerRowIndex = 0;
            $headerRow = $rows[0] ?? [];
        }

        $dataRows = array_slice($rows, $headerRowIndex + 1);

        $fieldAliases = [
            'staffId'        => ['staff id', 'staffid', 'employee id', 'staffida'],
            'name'           => ['name', 'employee name', 'fullname', 'staff name'],
            'department'     => ['department', 'dept'],
            'hiredate'       => ['hire date', 'hiredate', 'start date', 'join date'],
            'constart'       => ['contract start', 'constart', 'con start'],
            'conend'         => ['contract end', 'conend', 'con end'],
            'position'       => ['position', 'designation', 'role', 'title'],
            'grade'          => ['grade'],
            'categories'     => ['categories', 'category'],
            'workplace'      => ['workplace', 'work place', 'location'],
            'bankname'       => ['bank name', 'bankname', 'bank'],
            'alcnum'         => ['account number', 'alcnum', 'account no', 'account'],
            'branch'         => ['branch', 'bank branch'],
            'ssnit'          => ['ssnit', 'ssnit number', 'ssnit no'],
            'col12'          => ['col 12', 'col12', 'gh card', 'ghana card'],
            'workdays'       => ['work days', 'workdays', 'days worked'],
            'leavedays'      => ['leave days', 'leavedays'],
            'otworkdays'     => ['ot work days', 'otworkdays', 'overtime work days'],
            'otweekend'      => ['ot weekend', 'otweekend', 'weekend ot'],
            'basic'          => ['basic', 'basic salary', 'basic pay'],
            'performbonus'   => ['performance bonus', 'performbonus', 'perf bonus'],
            'coefficient'    => ['coefficient'],
            'performstand'   => ['performance standard', 'performstand'],
            'allowance'      => ['allowance', 'allowances'],
            'commission'     => ['commission'],
            'otworkingday'   => ['ot working day', 'otworkingday', 'overtime working day'],
            'otholiday'      => ['ot holiday', 'otholiday', 'holiday ot'],
            'other'          => ['other', 'other earnings'],
            'annaulbonus'    => ['annual bonus', 'annaulbonus', '13th month'],
            'leavededuction' => ['leave deduction', 'leavededuction'],
            'otherdeduction' => ['other deduction', 'otherdeduction'],
            'loan'           => ['loan', 'salary loan'],
            'grosspay'       => ['gross pay', 'grosspay', 'gross', 'gross salary'],
            'excess'         => ['excess'],
            'totalemolu'     => ['total emoluments', 'totalemolu', 'total emolument'],
            'accommodation'  => ['accommodation'],
            'chargeincome'   => ['charge income', 'chargeincome'],
            'petra'          => ['petra', 'tier 2', 'petra 5%'],
            'ssnitamt'       => ['ssnit amount', 'ssnitamt', 'ssnit ee'],
            'nssf'           => ['nssf', 'tier 1'],
            'taxbase'        => ['tax base', 'taxbase', 'taxable income'],
            'paye'           => ['paye', 'income tax'],
            'annaultax'      => ['annual tax', 'annaultax', 'bonus tax'],
            'netpay'         => ['net pay', 'netpay', 'take home pay', 'take home'],
            'hrcost'         => ['hr cost', 'hrcost'],
            'brandname'      => ['brand name', 'brandname', 'brand'],
        ];

        $defaultIndexes = [
            'staffId' => 0, 'name' => 1, 'department' => 2, 'hiredate' => 3, 'constart' => 4,
            'conend' => 5, 'position' => 6, 'grade' => 7, 'categories' => 8, 'workplace' => 9,
            'bankname' => 10, 'alcnum' => 11, 'branch' => 12, 'ssnit' => 13, 'col12' => 14,
            'workdays' => 15, 'leavedays' => 16, 'otworkdays' => 17, 'otweekend' => 18,
            'basic' => 19, 'performbonus' => 20, 'coefficient' => 21, 'performstand' => 22,
            'allowance' => 23, 'commission' => 24, 'otworkingday' => 25, 'otholiday' => 26,
            'other' => 27, 'annaulbonus' => 28, 'leavededuction' => 29, 'otherdeduction' => 30,
            'loan' => 31, 'grosspay' => 32, 'excess' => 33, 'totalemolu' => 34,
            'accommodation' => 35, 'chargeincome' => 36, 'petra' => 37, 'ssnitamt' => 38,
            'nssf' => 39, 'taxbase' => 40, 'paye' => 41, 'annaultax' => 42,
            'netpay' => 43, 'hrcost' => 44, 'brandname' => 45,
        ];

        $headerIndexMap = \App\Models\PayrollColumn::buildHeaderIndexMap($headerRow, $fieldAliases, $defaultIndexes);

        // Check for existing staff IDs
        $existingStaffIds = DB::table($tableName)->pluck('staffida')->toArray();
        $duplicateStaffIds = [];
        $validRecords = [];
        $errors = [];

        foreach ($dataRows as $index => $row) {
            // Skip empty rows
            $allEmpty = true;
            foreach ($row as $c) {
                if ($c !== null && trim((string)$c) !== '') {
                    $allEmpty = false;
                    break;
                }
            }
            if ($allEmpty) continue;

            $staffId = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['staffId'], 'str');
            $name    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['name'], 'str');

            if (empty($staffId) && empty($name)) {
                continue;
            }

            $department     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['department'], 'str');
            $hiredate       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['hiredate'], 'str');
            $constart       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['constart'], 'str');
            $conend         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['conend'], 'str');
            $position       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['position'], 'str');
            $grade          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['grade'], 'str');
            $categories     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['categories'], 'str');
            $workplace      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['workplace'], 'str');
            $bankname       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['bankname'], 'str');
            $alcnum         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['alcnum'], 'str');
            $branch         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['branch'], 'str');
            $ssnit          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit'], 'str');
            $col12          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['col12'], 'str');
            $workdays       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['workdays'], 'int', 0);
            $leavedays      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['leavedays'], 'int', 0);
            $otworkdays     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['otworkdays'], 'int', 0);
            $otweekend      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['otweekend'], 'int', 0);
            $basic          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic'], 'req_num');
            $performbonus   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['performbonus'], 'num', 0.0);
            $coefficient    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['coefficient'], 'num', 1.0);
            $performstand   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['performstand'], 'num', 0.0);
            $allowance      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['allowance'], 'num', 0.0);
            $commission     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['commission'], 'num', 0.0);
            $otworkingday   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['otworkingday'], 'num', 0.0);
            $otholiday      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['otholiday'], 'num', 0.0);
            $other          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['other'], 'num', 0.0);
            $annaulbonus    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['annaulbonus'], 'num', 0.0);
            $leavededuction = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['leavededuction'], 'num', 0.0);
            $otherdeduction = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['otherdeduction'], 'num', 0.0);
            $loan           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['loan'], 'num', 0.0);
            $grosspay       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['grosspay'], 'req_num');
            $excess         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['excess'], 'num', 0.0);
            $totalemolu     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['totalemolu'], 'num', 0.0);
            $accommodation  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['accommodation'], 'num', 0.0);
            $chargeincome   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['chargeincome'], 'num', 0.0);
            $petra          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['petra'], 'num', 0.0);
            $ssnitamt       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnitamt'], 'num', 0.0);
            $nssf           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nssf'], 'num', 0.0);
            $taxbase        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['taxbase'], 'num', 0.0);
            $paye           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['paye'], 'num', 0.0);
            $annaultax      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['annaultax'], 'num', 0.0);
            $netpay         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['netpay'], 'req_num');
            $hrcost         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['hrcost'], 'num', 0.0);
            $brandname      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['brandname'], 'str');

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
                $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number.";
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

        // Dynamically merge any registered custom columns from the uploaded header row
        $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'transsiontec');
        if (!empty($customVals)) {
            $data = array_merge($data, $customVals);
        }

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
        // Example data
        $exampleRow = [
            'STF123', 'John Doe', 'Sales', '10/19/2016', '01/01/2025', '12/31/2025', 'SALES REPRESENTATIVE', 'L09',
            'FORMAL STAFF', 'ACCRA', 'Bank A', '1234567890', 'Branch X', 'P0012345678', 'P0031284639', 21, 0, 0, 0,
            1113.75, 1856.25, 1, 1856.25, 742.5, 0, 0, 0, 0, 0, 0, 0, 0, 3712.5, 0, 3712.5, 0, 3712.5, 55.69,
            5.57, 144.79, 3651.24, 529.72, 0, 3121.52, 3857.29, 'TRANSSION'
        ];

        \App\Models\PayrollColumn::augmentTemplate($headers, $exampleRow, 'transsiontec');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([$exampleRow], null, 'A2');

        // Add a note
        $sheet->setCellValue('A4', 'Note: Numeric fields (e.g., Net Pay, Basic) must be numbers without symbols. Dates should be in MM/DD/YYYY format.');

        $writer   = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = 'tecno_template.xlsx';

        return response()->streamDownload(function () use ($writer) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma'              => 'public',
        ]);
 }
}
