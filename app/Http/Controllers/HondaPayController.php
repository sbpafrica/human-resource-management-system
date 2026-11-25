<?php

namespace App\Http\Controllers;

use App\Models\Hondapay;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;           // ← CRITICAL
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;     // ← Also needed

class HondaPayController extends Controller
{
 public function index(Request $request)
 {
         $request->validate([
         'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
         ]);

         $month = $request->input('month', now()->format('Y-m'));
         Log::info("Index: Received month: {$month}");

         $date = Carbon::createFromFormat('Y-m', $month);
         $tableName = 'honda' . strtolower($date->format('F')) . $date->format('y');
         Log::info("Index: Generated table name: {$tableName}");

         if (!DB::getSchemaBuilder()->hasTable($tableName)) {
         Log::warning("Index: Table {$tableName} does not exist");
         return Inertia::render('Hondapay/Index', [
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

         $query = DB::table($tableName . ' as tp')->leftJoin('bankdetails as bd', 'tp.STAFFID', '=', 'bd.employid')
             ->select(
             'tp.STAFFID',
             'tp.NAME',
             'bd.shop',
             'bd.bank',
             'bd.account',
             'bd.code',
             'bd.branch',
             'tp.TAKEHOMEPAY_GHC'
             );

 // Apply search
 if ($search = $request->input('search')) {
 $query->where(function ($q) use ($search, $tableName) {
 $q->where("tp.STAFFID", 'like', "%{$search}%")
 ->orWhere("tp.NAME", 'like', "%{$search}%")
 ->orWhere('bd.bank', 'like', "%{$search}%");
 });
 }

 // Apply sorting
 $sort = $request->input('sort', 'STAFFID');
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

 return Inertia::render('Hondapay/Index', [
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

        $month     = $request->input('month');
        $date      = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'honda' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.STAFFID', '=', 'bd.employid')
            ->select('tp.STAFFID', 'tp.NAME', 'bd.shop', 'bd.bank', 'bd.account', 'bd.code', 'bd.branch', 'tp.TAKEHOMEPAY_GHC');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.STAFFID', 'like', "%{$search}%")
                  ->orWhere('tp.NAME', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort  = $request->input('sort', 'STAFFID');
        $order = $request->input('order', 'asc');

        $sortColumn = match ($sort) {
            'shop'    => 'bd.shop',
            'bank'    => 'bd.bank',
            'account' => 'bd.account',
            'code'    => 'bd.code',
            'branch'  => 'bd.branch',
            default   => "tp.{$sort}",
        };

        $records = $query->orderBy($sortColumn, $order)->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Headers
        $headers = ['Staff ID', 'Name', 'Shop', 'Bank', 'Account No.', 'Code', 'Branch', 'Take Home Pay (GHC)'];
        $sheet->fromArray($headers, null, 'A1');

        // Write data row by row — this is the magic
        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->STAFFID);
            $sheet->setCellValue("B{$row}", $r->NAME);
            $sheet->setCellValue("C{$row}", $r->shop ?? '');
            $sheet->setCellValue("D{$row}", $r->bank ?? '');

            // FORCE Account No. and Code as TEXT — no apostrophe, no scientific
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '',    DataType::TYPE_STRING);

            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", ($r->TAKEHOMEPAY_GHC !== null && $r->TAKEHOMEPAY_GHC !== '') ? number_format((float)$r->TAKEHOMEPAY_GHC, 2, '.', '') : '');

            $row++;
        }

        // Styling — exactly like DriversPetraPay
        $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('bbdcfe');

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "honda_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

    } catch (\Exception $e) {
        Log::error("Honda export failed: " . $e->getMessage());
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
            $tableName = 'honda' . strtolower($date->format('F')) . $date->format('y');

            HondaPay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'honda');

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
                return redirect()->route('hondapay.index', ['month' => $selectedMonth])
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
                    if (preg_match('/^(staff(\s*id)?|name|position|basic|gross|take\s*home|net(\s*pay)?)/i', $norm)) {
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
                'STAFFID'                  => ['staff id', 'staffid', 'employee id'],
                'NAME'                     => ['name', 'staff name', 'employee name'],
                'DATEOFEMPLOYMENT'         => ['date of employment', 'employment date', 'join date'],
                'POSITION'                 => ['position', 'role', 'designation'],
                'BASICSALARY_GHC'          => ['basic salary (ghc)', 'basic salary', 'basic', 'basic pay'],
                'LEGALWORKDAYS'            => ['legal work days', 'work days', 'legal workdays'],
                'NUMBEROFDAYSWORKED'       => ['days worked', 'number of days worked'],
                'LEAVEDAYS'                => ['leave days'],
                'LEAVEAMOUNT'              => ['leave amount'],
                'BASICSALARYEARNED_GHC'    => ['basic salary earned (ghc)', 'basic salary earned', 'basic earned'],
                'PERFORMANCEBONUSSTANDARD' => ['performance bonus standard', 'perf bonus standard'],
                'COEFFICIENT'              => ['coefficient'],
                'PERFORMANCEBONUS'         => ['performance bonus', 'perf bonus'],
                'TRANSPORTALLOWANCE'       => ['transport allowance', 'transport'],
                'foodallowance'            => ['food allowance', 'food'],
                'holiovertime'             => ['holiday overtime', 'holi overtime'],
                'holiovertimeamt'          => ['holiday overtimeamt', 'holiday overtime amount', 'holiovertimeamt'],
                'medicalinsurance'         => ['medical insurance', 'medical'],
                'GROSSSALARY_GHC'          => ['gross salary (ghc)', 'gross salary', 'gross', 'gross pay'],
                'MANAGEMENTFEE_8'          => ['management fee 8%', 'management fee'],
                'GETFUND_2_5'              => ['getfund 2.5%', 'getfund'],
                'NHIL_2_5'                 => ['nhil 2.5%', 'nhil'],
                'VAT_15'                   => ['vat 15%', 'vat'],
                'SSF_13'                   => ['ssf 13%', 'ssf'],
                'COSTTOHONDA_GHC'          => ['cost to honda (ghc)', 'cost to honda'],
                'SSNITEE_0_5'              => ['ssnit ee 0.5%', 'ssnit ee', 'ssnit 5.5%'],
                'PETRA_5'                  => ['petra 5%', 'petra'],
                'TAXABLEINCOME_GHC'        => ['taxable income (ghc)', 'taxable income', 'taxable'],
                'PAYE_GHC'                 => ['paye (ghc)', 'paye', 'income tax'],
                'TOTALDEDUCTIONS_GHC'      => ['total deductions (ghc)', 'total deductions', 'total deduction'],
                'TAKEHOMEPAY_GHC'          => ['take home pay (ghc)', 'take home pay', 'take home', 'net pay', 'netpay', 'bank transfer'],
                'BANK'                     => ['bank', 'bank name'],
                'ACCOUNTNUMBER'            => ['account number', 'account no'],
                'BRANCH'                   => ['branch'],
                'SSNITNUMBER'              => ['ssnit number', 'ssnit no', 'ssnit'],
                'GHCARDNUMBER'             => ['ghana card number', 'ghana card', 'gh card'],
            ];

            $defaultIndexes = [
                'STAFFID' => 0, 'NAME' => 1, 'DATEOFEMPLOYMENT' => 2, 'POSITION' => 3,
                'BASICSALARY_GHC' => 4, 'LEGALWORKDAYS' => 5, 'NUMBEROFDAYSWORKED' => 6,
                'LEAVEDAYS' => 7, 'LEAVEAMOUNT' => 8, 'BASICSALARYEARNED_GHC' => 9,
                'PERFORMANCEBONUSSTANDARD' => 10, 'COEFFICIENT' => 11, 'PERFORMANCEBONUS' => 12,
                'TRANSPORTALLOWANCE' => 13, 'foodallowance' => 14, 'holiovertime' => 15,
                'holiovertimeamt' => 16, 'medicalinsurance' => 17, 'GROSSSALARY_GHC' => 18,
                'MANAGEMENTFEE_8' => 19, 'GETFUND_2_5' => 20, 'NHIL_2_5' => 21,
                'VAT_15' => 22, 'SSF_13' => 23, 'COSTTOHONDA_GHC' => 24,
                'SSNITEE_0_5' => 25, 'PETRA_5' => 26, 'TAXABLEINCOME_GHC' => 27,
                'PAYE_GHC' => 28, 'TOTALDEDUCTIONS_GHC' => 29, 'TAKEHOMEPAY_GHC' => 30,
                'BANK' => 31, 'ACCOUNTNUMBER' => 32, 'BRANCH' => 33, 'SSNITNUMBER' => 34,
                'GHCARDNUMBER' => 35,
            ];

            $headerIndexMap = \App\Models\PayrollColumn::buildHeaderIndexMap($headerRow, $fieldAliases, $defaultIndexes);

            // Check for existing staff IDs
            $existingStaffIds = DB::table($tableName)->pluck('STAFFID')->toArray();
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

                $STAFFID = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['STAFFID'], 'str');
                $NAME    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['NAME'], 'str');

                if (empty($STAFFID) && empty($NAME)) {
                    continue;
                }

                $DATEOFEMPLOYMENT         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['DATEOFEMPLOYMENT'], 'str');
                $POSITION                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['POSITION'], 'str');
                $BASICSALARY_GHC          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['BASICSALARY_GHC'], 'req_num');
                $LEGALWORKDAYS            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['LEGALWORKDAYS'], 'int');
                $NUMBEROFDAYSWORKED       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['NUMBEROFDAYSWORKED'], 'num');
                $LEAVEDAYS                = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['LEAVEDAYS'], 'num');
                $LEAVEAMOUNT              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['LEAVEAMOUNT'], 'num');
                $BASICSALARYEARNED_GHC    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['BASICSALARYEARNED_GHC'], 'num');
                $PERFORMANCEBONUSSTANDARD = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['PERFORMANCEBONUSSTANDARD'], 'num');
                $COEFFICIENT              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['COEFFICIENT'], 'num', 1.0);
                $PERFORMANCEBONUS         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['PERFORMANCEBONUS'], 'num');
                $TRANSPORTALLOWANCE       = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['TRANSPORTALLOWANCE'], 'num');
                $foodallowance            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['foodallowance'], 'num');
                $holiovertime             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['holiovertime'], 'num');
                $holiovertimeamt          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['holiovertimeamt'], 'num');
                $medicalinsurance         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['medicalinsurance'], 'num');
                $GROSSSALARY_GHC          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['GROSSSALARY_GHC'], 'req_num');
                $MANAGEMENTFEE_8          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['MANAGEMENTFEE_8'], 'num');
                $GETFUND_2_5              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['GETFUND_2_5'], 'num');
                $NHIL_2_5                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['NHIL_2_5'], 'num');
                $VAT_15                   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['VAT_15'], 'num');
                $SSF_13                   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['SSF_13'], 'num');
                $COSTTOHONDA_GHC          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['COSTTOHONDA_GHC'], 'num');
                $SSNITEE_0_5              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['SSNITEE_0_5'], 'num');
                $PETRA_5                  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['PETRA_5'], 'num');
                $TAXABLEINCOME_GHC        = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['TAXABLEINCOME_GHC'], 'num');
                $PAYE_GHC                 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['PAYE_GHC'], 'num');
                $TOTALDEDUCTIONS_GHC      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['TOTALDEDUCTIONS_GHC'], 'num');
                $TAKEHOMEPAY_GHC          = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['TAKEHOMEPAY_GHC'], 'req_num');
                $BANK                     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['BANK'], 'str');
                $ACCOUNTNUMBER            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ACCOUNTNUMBER'], 'str');
                $BRANCH                   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['BRANCH'], 'str');
                $SSNITNUMBER              = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['SSNITNUMBER'], 'str');
                $GHCARDNUMBER             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['GHCARDNUMBER'], 'str');

                // Validation
                if (empty($STAFFID)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID cannot be empty.";
                    continue;
                }
                if (empty($NAME)) {
                    $errors[] = "Row " . ($index + 2) . ": Name cannot be empty.";
                    continue;
                }
                if (is_null($TAKEHOMEPAY_GHC)) {
                    $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number.";
                    continue;
                }

                if (in_array($STAFFID, $existingStaffIds) || in_array($STAFFID, $duplicateStaffIds)) {
                    $duplicateStaffIds[] = $STAFFID;
                    continue;
                }

                $data = [
                    'STAFFID'                  => $STAFFID,
                    'NAME'                     => $NAME,
                    'DATEOFEMPLOYMENT'         => $DATEOFEMPLOYMENT,
                    'POSITION'                 => $POSITION,
                    'BASICSALARY_GHC'          => $BASICSALARY_GHC,
                    'LEGALWORKDAYS'            => $LEGALWORKDAYS,
                    'NUMBEROFDAYSWORKED'       => $NUMBEROFDAYSWORKED,
                    'LEAVEDAYS'                => $LEAVEDAYS,
                    'LEAVEAMOUNT'              => $LEAVEAMOUNT,
                    'BASICSALARYEARNED_GHC'    => $BASICSALARYEARNED_GHC,
                    'PERFORMANCEBONUSSTANDARD' => $PERFORMANCEBONUSSTANDARD,
                    'COEFFICIENT'              => $COEFFICIENT,
                    'PERFORMANCEBONUS'         => $PERFORMANCEBONUS,
                    'TRANSPORTALLOWANCE'       => $TRANSPORTALLOWANCE,
                    'foodallowance'            => $foodallowance,
                    'holiovertime'             => $holiovertime,
                    'holiovertimeamt'          => $holiovertimeamt,
                    'medicalinsurance'         => $medicalinsurance,
                    'GROSSSALARY_GHC'          => $GROSSSALARY_GHC,
                    'MANAGEMENTFEE_8'          => $MANAGEMENTFEE_8,
                    'GETFUND_2_5'              => $GETFUND_2_5,
                    'NHIL_2_5'                 => $NHIL_2_5,
                    'VAT_15'                   => $VAT_15,
                    'SSF_13'                   => $SSF_13,
                    'COSTTOHONDA_GHC'          => $COSTTOHONDA_GHC,
                    'SSNITEE_0_5'              => $SSNITEE_0_5,
                    'PETRA_5'                  => $PETRA_5,
                    'TAXABLEINCOME_GHC'        => $TAXABLEINCOME_GHC,
                    'PAYE_GHC'                 => $PAYE_GHC,
                    'TOTALDEDUCTIONS_GHC'      => $TOTALDEDUCTIONS_GHC,
                    'TAKEHOMEPAY_GHC'          => $TAKEHOMEPAY_GHC,
                    'BANK'                     => $BANK,
                    'ACCOUNTNUMBER'            => $ACCOUNTNUMBER,
                    'BRANCH'                   => $BRANCH,
                    'SSNITNUMBER'              => $SSNITNUMBER,
                    'GHCARDNUMBER'             => $GHCARDNUMBER,
                ];

                // Dynamic custom columns
                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'honda');
                if (!empty($customVals)) {
                    $data = array_merge($data, $customVals);
                }

                $validRecords[] = $data;
                $existingStaffIds[] = $STAFFID;
            }

            if (!empty($errors)) {
                Log::error("Upload errors: " . implode(' | ', $errors));
                $errorMessage = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('hondapay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
            } else {
                Log::warning("Upload: No valid records to insert into {$tableName}");
            }

            return redirect()->route('hondapay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('hondapay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'honda' . strtolower($date->format('F')) . $date->format('y');

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('hondapay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('hondapay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('hondapay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 public function downloadTemplate()
 {
 $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

 $headers = [
    'STAFF ID','NAME','DATE OF EMPLOYMENT','POSITION',
    'BASIC SALARY (GHC)','LEGAL WORK DAYS','DAYS WORKED','LEAVE DAYS','LEAVE AMOUNT',
    'BASIC SALARY EARNED (GHC)','PERFORMANCE BONUS STANDARD','COEFFICIENT','PERFORMANCE BONUS',
    'TRANSPORT ALLOWANCE','FOOD ALLOWANCE','HOLIDAY OVERTIME','HOLIDAY OVERTIMEAMT','MEDICAL INSURANCE',
    'GROSS SALARY (GHC)',
    'MANAGEMENT FEE 8%','GETFUND 2.5%','NHIL 2.5%','VAT 15%','SSF 13%','COST TO HONDA (GHC)',
    'SSNIT EE 0.5%','PETRA 5%','TAXABLE INCOME (GHC)','PAYE (GHC)','TOTAL DEDUCTIONS (GHC)',
    'TAKE HOME PAY (GHC)',
    'BANK','ACCOUNT NUMBER','BRANCH','SSNIT NUMBER','GHANA CARD NUMBER'
];

$nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'honda');
        $sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:AI1')->getFont()->setBold(true);




 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'honda_template.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

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
