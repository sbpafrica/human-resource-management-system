<?php

namespace App\Http\Controllers;

use App\Models\DriverPay;
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

class DriverPayController extends Controller
{
    private function resolveTableName(Carbon $date): string
    {
        $fullMonth = strtolower($date->format('F')) . $date->format('y');
        $shortMonth = strtolower($date->format('M')) . $date->format('y');

        $candidate1 = 'driverssnit' . $fullMonth;
        $candidate2 = 'driverssnit' . $shortMonth;

        if (DB::getSchemaBuilder()->hasTable($candidate1)) {
            return $candidate1;
        }
        if (DB::getSchemaBuilder()->hasTable($candidate2)) {
            return $candidate2;
        }

        return $candidate1;
    }

    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = $this->resolveTableName($date);

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('Driverpay/Index', [
                'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth' => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords' => 0,
                'searchCount' => null,
                'flash' => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        $query = DB::table($tableName . ' as dp')
            ->leftJoin('bankdetails as bd', 'dp.employ', '=', 'bd.employid')
            ->select(
                'dp.employ',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), dp.name) as name'),
                'dp.report',
                'dp.dept',
                'dp.ssnitnum',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'dp.netpay'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('dp.employ', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), dp.name) LIKE ?', ["%{$search}%"])
                  ->orWhere('dp.report', 'like', "%{$search}%")
                  ->orWhere('dp.ssnitnum', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'employ');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'employ' => 'dp.employ',
            'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), dp.name)'),
            'report' => 'dp.report',
            'dept'   => 'dp.dept',
            'ssnitnum' => 'dp.ssnitnum',
            'bank'   => 'bd.bank',
            'netpay' => 'dp.netpay',
        ];

        $sortColumn = $sortMap[$sort] ?? 'dp.employ';
        $query->orderBy($sortColumn, $order);

        $perPage = 15;
        $records = $query->paginate($perPage)->appends($request->query());

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        return Inertia::render('Driverpay/Index', [
            'records' => $records,
            'selectedMonth' => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        try {
            $selectedMonth = $request->input('month');
            session(['selected_month' => $selectedMonth]);
            $date = Carbon::createFromFormat('Y-m', $selectedMonth);
            $tableName = $this->resolveTableName($date);

            DriverPay::createTableIfNotExists($tableName);

            // Fetch table columns to know what fields exist
            $tableColumns = array_column(DB::select("DESCRIBE `$tableName`"), 'Field');
            $hasSsfyee  = in_array('ssfyee', $tableColumns);
            $hasSsfyeed = in_array('ssfyeed', $tableColumns);
            $hasSsfyer  = in_array('ssfyer', $tableColumns);
            $hasSsfyerd = in_array('ssfyerd', $tableColumns);

            $file = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();
            array_shift($rows); // remove header

            $existingIds = DB::table($tableName)->pluck('employ')->toArray();
            $validRecords = [];
            $errors = [];

            $parseNum = function ($val, $default = 0.0) {
                if (is_null($val) || $val === '') return (float)$default;
                if (is_numeric($val)) return (float)$val;
                $cleaned = preg_replace('/[^\d\.\-]/', '', (string)$val);
                return is_numeric($cleaned) ? (float)$cleaned : (float)$default;
            };

            foreach ($rows as $i => $row) {
                // Auto-pad row to 23 columns so trailing empty cells don't cause errors
                $row = array_pad($row, 23, null);

                // Skip completely empty rows
                $hasContent = false;
                foreach ($row as $cell) {
                    if ($cell !== null && trim((string)$cell) !== '') {
                        $hasContent = true;
                        break;
                    }
                }
                if (!$hasContent) {
                    continue;
                }

                $employ       = trim((string)($row[0] ?? '')) ?: null;
                $name         = trim((string)($row[1] ?? '')) ?: null;
                $report       = trim((string)($row[2] ?? ''));
                $dept         = trim((string)($row[3] ?? ''));
                $dob          = trim((string)($row[4] ?? ''));
                $joindate     = trim((string)($row[5] ?? ''));
                $ssnitnum     = trim((string)($row[6] ?? ''));
                $age          = $parseNum($row[7] ?? 0);
                $remarks      = (int)$parseNum($row[8] ?? 0);
                $salary       = (int)$parseNum($row[9] ?? 0);
                $week         = (int)$parseNum($row[10] ?? 0);
                $basic        = $row[11] !== null && trim((string)$row[11]) !== '' ? $parseNum($row[11]) : null;
                $allowance    = $parseNum($row[12] ?? 0);
                $sat          = $parseNum($row[13] ?? 0);
                $satOT        = $parseNum($row[14] ?? 0);
                $ssfyeed      = $parseNum($row[15] ?? 0);
                $ssfyerd      = $parseNum($row[16] ?? 0);
                $paye         = $parseNum($row[17] ?? 0);
                $ottax        = $parseNum($row[18] ?? 0);
                $advance      = $parseNum($row[19] ?? 0);
                $totaldeduct  = $parseNum($row[20] ?? 0);
                $netpay       = $row[21] !== null && trim((string)$row[21]) !== '' ? $parseNum($row[21]) : null;
                $servicecharge = $parseNum($row[22] ?? 0);

                if (!$employ || !$name || is_null($basic) || is_null($netpay)) {
                    $errors[] = "Row " . ($i + 2) . ": Missing required fields (Employ ID, Name, Basic, or Net Pay)";
                    continue;
                }

                if (in_array($employ, $existingIds)) {
                    $errors[] = "Row " . ($i + 2) . ": Duplicate Employ ID: $employ";
                    continue;
                }

                $record = [
                    'employ'        => $employ,
                    'name'          => $name,
                    'report'        => $report,
                    'dept'          => $dept,
                    'dob'           => $dob,
                    'joindate'      => $joindate,
                    'ssnitnum'      => $ssnitnum,
                    'age'           => $age,
                    'remarks'       => $remarks,
                    'salary'        => $salary,
                    'week'          => $week,
                    'basic'         => $basic,
                    'allowance'     => $allowance,
                    'sat'           => $sat,
                    'satOT'         => $satOT,
                    'paye'          => $paye,
                    'ottax'         => $ottax,
                    'advance'       => $advance,
                    'totaldeduct'   => $totaldeduct,
                    'netpay'        => $netpay,
                    'servicecharge' => $servicecharge,
                ];

                if ($hasSsfyee)  $record['ssfyee']  = $ssfyeed;
                if ($hasSsfyeed) $record['ssfyeed'] = $ssfyeed;
                if ($hasSsfyer)  $record['ssfyer']  = $ssfyerd;
                if ($hasSsfyerd) $record['ssfyerd'] = $ssfyerd;

                $validRecords[] = $record;
                $existingIds[]  = $employ;
            }

            if ($errors) {
                $msg = count($errors) > 3 ? implode(' | ', array_slice($errors, 0, 3)) . " | ...and " . (count($errors) - 3) . " more" : implode(' | ', $errors);
                return redirect()->back()->with('error', $msg);
            }

            if ($validRecords) {
                DB::table($tableName)->insert($validRecords);
            }

            return redirect()->route('driverpay.index', ['month' => $selectedMonth])
                ->with('success', 'Drivers payroll uploaded successfully! ' . count($validRecords) . ' records.');

        } catch (\Exception $e) {
            Log::error('DriverPay upload failed', ['error' => $e->getMessage()]);
            return redirect()->back()->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function delete(Request $request)
    {
        $month = $request->input('month', session('selected_month', now()->format('Y-m')));
        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = $this->resolveTableName($date);

        if (DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::table($tableName)->truncate();
            return redirect()->route('driverpay.index', ['month' => $month])
                ->with('success', 'All driver records deleted successfully.');
        }

        return redirect()->back()->with('error', 'No records found.');
    }

    public function downloadTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Employ','Name','Report','Dept','DOB','Join Date','SSNIT No','Age','Remarks','Salary','Week',
            'Basic','Allowance','Sat','Sat OT','SSF YEE','SSF YER','PAYE','OT Tax','Advance','Total Deduct','Net Pay','Service Charge'
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:W1')->getFont()->setBold(true);

        $example = [[
            'DRV001','Kofi Mensah','Head Office','Transport','15/03/1985','01/06/2018','SS12345678',40,0,3000,4,
            3000,800,200,300,165,181.5,450,90,500,14386.5,13836.5,500
        ]];

        $sheet->fromArray($example, null, 'A2');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'Melcom_Drivers_Payroll_Template_' . date('M-Y') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }



public function export(Request $request)
{
    try {
        $request->validate([
            'month' => ['required', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month');
        $date  = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'driverssnit' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as dp')
            ->leftJoin('bankdetails as bd', 'dp.employ', '=', 'bd.employid')
            ->select(
                'dp.employ as staff_id',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), dp.name) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), dp.dept) as department'),
                'dp.report',
                'dp.ssnitnum',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'dp.netpay as take_home_pay'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('dp.employ', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), dp.name) LIKE ?', ["%{$search}%"])
                  ->orWhere('dp.report', 'like', "%{$search}%")
                  ->orWhere('dp.dept', 'like', "%{$search}%")
                  ->orWhere('dp.ssnitnum', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting – supports computed columns
        $sort = $request->input('sort', 'employ');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'employ'        => 'dp.employ',
            'staff_id'      => 'dp.employ',
            'name'          => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), dp.name)'),
            'department'    => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), dp.dept)'),
            'report'        => 'dp.report',
            'ssnitnum'      => 'dp.ssnitnum',
            'bank'          => 'bd.bank',
            'account'       => 'bd.account',
            'code'          => 'bd.code',
            'branch'        => 'bd.branch',
            'netpay'        => 'dp.netpay',
            'take_home_pay' => 'dp.netpay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'dp.employ', $order);

        $records = $query->get();

        // Generate Real Excel File
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID', 'Name', 'Department', 'Report To', 'SSNIT No.',
            'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Net Pay'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staff_id);
            $sheet->setCellValue("B{$row}", $r->name);
            $sheet->setCellValue("C{$row}", $r->department ?? '');
            $sheet->setCellValue("D{$row}", $r->report ?? '');
            $sheet->setCellValue("E{$row}", $r->ssnitnum ?? '');
            $sheet->setCellValue("F{$row}", $r->bank ?? '');
            $sheet->setCellValueExplicit("G{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("H{$row}", $r->code ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue("I{$row}", $r->branch ?? '');
            $sheet->setCellValue("J{$row}", $r->take_home_pay ? number_format((float)$r->take_home_pay, 2, '.', '') : '');
            $row++;
        }

        // Styling – same as all your other exports
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headerStyle = $sheet->getStyle('A1:J1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDEFE'); // Light blue

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "drivers_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("DriverPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Export failed. Please try again.'], 500);
    }
}
}
