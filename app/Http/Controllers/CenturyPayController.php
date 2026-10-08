<?php

namespace App\Http\Controllers;

use App\Models\CenturyPay;
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

class CenturyPayController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'centurypay' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('Centurypay/Index', [
                'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth' => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords' => 0,
                'searchCount' => null,
                'flash' => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.employ', '=', 'bd.employid')
            ->select(
                'tp.employ',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname) as shop'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.employ', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'employ');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'employ' => 'tp.employ',
            'name'   => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename)'),
            'shop'   => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname)'),
            'bank'   => 'bd.bank',
            'account'=> 'bd.account',
            'code'   => 'bd.code',
            'branch' => 'bd.branch',
            'netpay' => 'tp.netpay',
        ];

        $sortColumn = $sortMap[$sort] ?? 'tp.employ';
        $query->orderBy($sortColumn, $order);

        $perPage = 10;
        $records = $query->paginate($perPage)->appends($request->query());

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        return Inertia::render('Centurypay/Index', [
            'records' => $records,
            'selectedMonth' => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'flash' => ['success' => session('success'), 'error' => session('error')],
        ]);
    }



public function export(Request $request)
{
    try {
        $request->validate([
            'month' => ['required', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month');
        $date  = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'centurypay' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.employ', '=', 'bd.employid')
            ->select(
                'tp.employ as staff_id',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename) as name'),
                DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname) as shop'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay as take_home_pay'
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.employ', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename) LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting – works perfectly on computed columns
        $sort = $request->input('sort', 'employ');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'employ'        => 'tp.employ',
            'staff_id'      => 'tp.employ',
            'name'          => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.empoyeename)'),
            'shop'          => DB::raw('COALESCE(NULLIF(TRIM(bd.shop), ""), tp.shopname)'),
            'bank'          => 'bd.bank',
            'account'       => 'bd.account',
            'code'          => 'bd.code',
            'branch'        => 'bd.branch',
            'netpay'        => 'tp.netpay',
            'take_home_pay' => 'tp.netpay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.employ', $order);

        $records = $query->get();

        // Generate Real Excel File
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = ['Staff ID', 'Name', 'Shop/Branch', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Net Pay'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staff_id);
            $sheet->setCellValue("B{$row}", $r->name);
            $sheet->setCellValue("C{$row}", $r->shop ?? '');
            $sheet->setCellValue("D{$row}", $r->bank ?? '');
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->take_home_pay ? number_format((float)$r->take_home_pay, 2, '.', '') : '');
            $row++;
        }

        // Styling – identical to all your other exports
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headerStyle = $sheet->getStyle('A1:H1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDEFE'); // Light blue header

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "century_pay_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("CenturyPay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Export failed. Please try again.'], 500);
    }
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
            $tableName = 'centurypay' . strtolower($date->format('F')) . $date->format('y');

            CenturyPay::createTableIfNotExists($tableName);

            $file = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray();
            array_shift($rows); // remove header

            $existingStaffIds = DB::table($tableName)->pluck('employ')->toArray();
            $validRecords = [];
            $errors = [];

            foreach ($rows as $index => $row) {
                if (count($row) < 35) {
                    $errors[] = "Row " . ($index + 2) . ": Expected at least 33 columns, found " . count($row);
                    continue;
                }

                $employ           = trim($row[0] ?? '') ?: null;
                $empoyeename      = trim($row[1] ?? '') ?: null;
                $outsourced       = trim($row[2] ?? '') ?: null;
                $desig            = trim($row[3] ?? '') ?: null;
                $joindate         = trim($row[4] ?? '') ?: null;
                $shopname         = trim($row[5] ?? '') ?: null;
                $actualdays       = (int)($row[6] ?? 0);
                $night            = (int)($row[7] ?? 0);
                $halfdays         = (int)($row[8] ?? 0);
                $satoffdays       = (int)($row[9] ?? 0);
                $sunoffdays       = (int)($row[10] ?? 0);
                $leavedays        = (int)($row[11] ?? 0);
                $absentdays       = (int)($row[12] ?? 0);
                $holiday          = (int)($row[13] ?? 0);
                $weekdaysOT       = (int)($row[14] ?? 0);
                $weekendOT        = (int)($row[15] ?? 0);
                $basic            = is_numeric($row[16] ?? '') ? (float)$row[16] : null;
                $absentwithoutpay = (float)($row[17] ?? 0);
                $basicentitlement = (float)($row[18] ?? 0);
                $transport        = (float)($row[19] ?? 0);
                $nightallowance   = (float)($row[20] ?? 0);
                $normalovertime   = (float)($row[21] ?? 0);
                $saturdayovertime = (float)($row[22] ?? 0);
                $gross            = is_numeric($row[23] ?? '') ? (float)$row[23] : null;
                $ssniter            = (float)($row[24] ?? 0);
                $ssnitee            = (float)($row[25] ?? 0);
                $ssnit            = (float)($row[26] ?? 0);
                $taxabled         = (float)($row[27] ?? 0);
                $paye             = (float)($row[28] ?? 0);
                $totalovertime    = (float)($row[29] ?? 0);
                $overtimetax      = (float)($row[30] ?? 0);
                $totaltax         = (float)($row[31] ?? 0);
                $advance          = (float)($row[32] ?? 0);
                $totaldeduction   = (float)($row[33] ?? 0);
                $netpay           = is_numeric($row[34] ?? '') ? (float)$row[34] : null;

                if (empty($employ)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
                    continue;
                }
                if (empty($empoyeename)) {
                    $errors[] = "Row " . ($index + 2) . ": Name is required.";
                    continue;
                }
                if (is_null($basic) || is_null($gross) || is_null($netpay)) {
                    $errors[] = "Row " . ($index + 2) . ": Basic, Gross and Net Pay are required.";
                    continue;
                }
                if (in_array($employ, $existingStaffIds)) {
                    $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: $employ";
                    continue;
                }

                $validRecords[] = [
                    'employ'            => $employ,
                    'empoyeename'       => $empoyeename,
                    'outsourced'        => $outsourced,
                    'desig'             => $desig,
                    'joindate'          => $joindate,
                    'shopname'          => $shopname,
                    'actualdays'        => $actualdays,
                    'night'             => $night,
                    'halfdays'          => $halfdays,
                    'satoffdays'        => $satoffdays,
                    'sunoffdays'        => $sunoffdays,
                    'leavedays'         => $leavedays,
                    'absentdays'        => $absentdays,
                    'holiday'           => $holiday,
                    'weekdaysOT'        => $weekdaysOT,
                    'weekendOT'         => $weekendOT,
                    'basic'             => $basic,
                    'absentwithoutpay'  => $absentwithoutpay,
                    'basicentitlement'  => $basicentitlement,
                    'transport'         => $transport,
                    'nightallowance'    => $nightallowance,
                    'normalovertime'    => $normalovertime,
                    'saturdayovertime'  => $saturdayovertime,
                    'gross'             => $gross,
                    'ssniter'             => $ssniter,
                     'ssnitee'             => $ssnitee,
                      'ssnit'             => $ssnit,
                    'taxabled'          => $taxabled,
                    'paye'              => $paye,
                    'totalovertime'     => $totalovertime,
                    'overtimetax'       => $overtimetax,
                    'totaltax'          => $totaltax,
                    'advance'           => $advance,
                    'totaldeduction'    => $totaldeduction,
                    'netpay'            => $netpay,
                ];

                $existingStaffIds[] = $employ;
            }

            if (!empty($errors)) {
                $errorMessage = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more.'
                    : implode(' | ', $errors);
                return redirect()->route('centurypay.index', ['month' => $selectedMonth])
                    ->with('error', $errorMessage);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("Century: Inserted " . count($validRecords) . " records into {$tableName}");
            }

            return redirect()->route('centurypay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');

        } catch (\Exception $e) {
            Log::error('Century upload failed', ['error' => $e->getMessage()]);
            return redirect()->route('centurypay.index', ['month' => $selectedMonth])
                ->with('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function delete(Request $request)
    {
        $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
        $date = Carbon::createFromFormat('Y-m', $selectedMonth);
        $tableName = 'centurypay' . strtolower($date->format('F')) . $date->format('y');

        if (DB::getSchemaBuilder()->hasTable($tableName)) {
            DB::table($tableName)->truncate();
            return redirect()->route('centurypay.index', ['month' => $selectedMonth])
                ->with('success', 'Records deleted successfully.');
        }

        return redirect()->route('centurypay.index', ['month' => $selectedMonth])
            ->with('error', 'No records found.');
    }

    public function downloadTemplate()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID','Name','Outsourced','Designation','Join Date','Shop Name',
            'Actual Days','Night','Half Days','Sat Off Days','Sun Off Days','Leave Days',
            'Absent Days','Holiday','Weekdays OT','Weekend OT',
            'Basic','Absent Without Pay','Basic Entitlement','Transport','Night Allowance',
            'Normal Overtime','Saturday Overtime','Gross','SSNITER', 'SSNITEE0.5', 'SSNITEE5','Taxable','PAYE',
            'Total Overtime','Overtime Tax','Total Tax','Advance','Total Deduction','Net Pay'
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->getFont()->setBold(true);

        $example = [[
            'CEN001','Ama Serwaa','Yes','Sales Assistant','01/06/2022','Century - Osu',
            26,8,0,0,1,0,0,0,5,3,
            1850.00,0.00,1850.00,200.00,400.00,525.00,450.00,00.00,525.00,450.00,
            4425.00,258.00,4167.00,492.00,975.00,142.00,634.00,100.00,934.00,3491.00
        ]];

        $sheet->fromArray($example, null, 'A2');

        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $fileName = 'Century_Payroll_Template_' . date('M-Y') . '.xlsx';
        $tempFile = tempnam(sys_get_temp_dir(), $fileName);
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }
}
