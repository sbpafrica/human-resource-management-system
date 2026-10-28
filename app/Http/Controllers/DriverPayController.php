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
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:2048'],
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
            $hasSsfyee   = in_array('ssfyee', $tableColumns);
            $hasSsfyeed  = in_array('ssfyeed', $tableColumns);
            $hasSsfyer   = in_array('ssfyer', $tableColumns);
            $hasSsfyerd  = in_array('ssfyerd', $tableColumns);
            $hasNewBasic = in_array('new_basic', $tableColumns);
            $hasBasicAll = in_array('basic_all', $tableColumns);
            $hasTaxable  = in_array('taxable', $tableColumns);

            $file = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());

            // Select active sheet or first sheet with data
            $sheet = $spreadsheet->getActiveSheet();
            if ($sheet->getHighestRow() <= 1) {
                foreach ($spreadsheet->getAllSheets() as $s) {
                    if ($s->getHighestRow() > 1) {
                        $sheet = $s;
                        break;
                    }
                }
            }

            $rows = $sheet->toArray(null, true, false, false);

            // Dynamically scan the first 10 rows to locate the real header row
            $headerRowIdx = null;
            foreach ($rows as $rIdx => $r) {
                if ($rIdx > 10) break;
                foreach ($r as $c) {
                    $cleanC = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', (string)$c)));
                    if (in_array($cleanC, ['staffid', 'employ', 'staffida', 'employeeid', 'drivername'])) {
                        $headerRowIdx = $rIdx;
                        break 2;
                    }
                }
            }

            if ($headerRowIdx !== null) {
                $headerRow = $rows[$headerRowIdx];
                $dataRows  = array_slice($rows, $headerRowIdx + 1);
                $startRow  = $headerRowIdx + 2; // Excel row number of the first data row
            } else {
                $headerRow = array_shift($rows) ?? [];
                $dataRows  = $rows;
                $startRow  = 2;
            }

            // Build dynamic header index lookup
            $colIndex = [];
            foreach ($headerRow as $idx => $headerVal) {
                $clean = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', (string)$headerVal)));
                if ($clean !== '') {
                    $colIndex[$clean] = $idx;
                }
            }

            $findCol = function (array $names) use ($colIndex) {
                foreach ($names as $name) {
                    $clean = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $name)));
                    if (isset($colIndex[$clean])) {
                        return $colIndex[$clean];
                    }
                }
                return null;
            };

            $idxEmploy      = $findCol(['staffid', 'employ', 'staffida', 'employeeid']);
            $idxName        = $findCol(['drivername', 'name', 'employeename']);
            $idxReport      = $findCol(['reportingmanager', 'report', 'manager']);
            $idxDept        = $findCol(['dept', 'department']);
            $idxDob         = $findCol(['dateofbirth', 'dob']);
            $idxJoindate    = $findCol(['dateofjoining', 'joindate', 'joiningdate']);
            $idxSsnitnum    = $findCol(['ssnitnumbers', 'ssnitnum', 'ssnit', 'ssnitno']);
            $idxAge         = $findCol(['age']);
            $idxRemarks     = $findCol(['remarks']);
            $idxNewBasic    = $findCol(['newbasic', 'new_basic']);
            $idxSalary      = $findCol(['salary', 'basicsalary']);
            $idxWeek        = $findCol(['weekdays', 'week', 'weekdaysworked', 'workdays']);
            $idxBasic       = $findCol(['basicghc', 'basic', 'basicsalaryghc', 'basicsalary']);
            $idxAllowance   = $findCol(['allowancesghc', 'allowance', 'allowances']);
            $idxSat         = $findCol(['sat4dayswith8hr', 'sat', 'saturday']);
            $idxSatOT       = $findCol(['satovertime', 'satot', 'saturdayovertime']);
            $idxBasicAll    = $findCol(['basicall', 'basicplusall', 'basicallowance']);
            $idxSsfyee      = $findCol(['ssfemployee55', 'ssfemployee', 'ssfyeed', 'ssfyee']);
            $idxSsfyer      = $findCol(['ssfemployer13', 'ssfemployer', 'ssfyerd', 'ssfyer']);
            $idxOttax       = $findCol(['ottax5', 'ottax', 'overtimetax']);
            $idxTaxable     = $findCol(['taxablesalary', 'taxable']);
            $idxPaye        = $findCol(['paye']);
            $idxAdvance     = $findCol(['advance']);
            $idxTotaldeduct = $findCol(['totalded', 'totaldeduct', 'totaldeduction', 'totaldeductions']);
            $idxNetpay      = $findCol(['totalwages', 'netpay', 'netamount', 'takehome', 'wages']);
            $idxService     = $findCol(['servicecharge']);

            $isHeaderMatched = ($idxEmploy !== null);

            $existingIds = DB::table($tableName)->pluck('employ')->toArray();
            $validRecords = [];
            $errors = [];

            $parseNum = function ($val, $default = 0.0) {
                if (is_null($val) || $val === '') return (float)$default;
                if (is_numeric($val)) return (float)$val;
                $cleaned = preg_replace('/[^\d\.\-]/', '', (string)$val);
                return is_numeric($cleaned) ? (float)$cleaned : (float)$default;
            };

            foreach ($dataRows as $i => $row) {
                $excelRowNum = $startRow + $i;
                $row = array_pad($row, 30, null);

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

                $getCell = fn($idx) => ($idx !== null && isset($row[$idx])) ? $row[$idx] : null;

                if ($isHeaderMatched) {
                    $employ        = trim((string)($getCell($idxEmploy) ?? '')) ?: null;
                    $name          = trim((string)($getCell($idxName) ?? '')) ?: null;
                    $report        = trim((string)($getCell($idxReport) ?? ''));
                    $dept          = trim((string)($getCell($idxDept) ?? ''));
                    $dob           = trim((string)($getCell($idxDob) ?? ''));
                    $joindate      = trim((string)($getCell($idxJoindate) ?? ''));
                    $ssnitnum      = trim((string)($getCell($idxSsnitnum) ?? ''));
                    $age           = $parseNum($getCell($idxAge) ?? 0);
                    $remarks       = (int)$parseNum($getCell($idxRemarks) ?? 0);
                    $newBasicRaw   = $getCell($idxNewBasic);
                    $salaryRaw     = $getCell($idxSalary);
                    $newBasic      = $newBasicRaw !== null ? $parseNum($newBasicRaw) : ($salaryRaw !== null ? $parseNum($salaryRaw) : 0);
                    $salary        = $salaryRaw !== null ? (int)$parseNum($salaryRaw) : (int)$newBasic;
                    $week          = (int)$parseNum($getCell($idxWeek) ?? 0);
                    $basicRaw      = $getCell($idxBasic);
                    $basic         = $basicRaw !== null && trim((string)$basicRaw) !== '' ? $parseNum($basicRaw) : null;
                    $allowance     = $parseNum($getCell($idxAllowance) ?? 0);
                    $sat           = $parseNum($getCell($idxSat) ?? 0);
                    $satOT         = $parseNum($getCell($idxSatOT) ?? 0);
                    $basicAllRaw   = $getCell($idxBasicAll);
                    $basicAll      = $basicAllRaw !== null ? $parseNum($basicAllRaw) : ($basic !== null ? $basic + $allowance : 0);
                    $ssfyeed       = $parseNum($getCell($idxSsfyee) ?? 0);
                    $ssfyerd       = $parseNum($getCell($idxSsfyer) ?? 0);
                    $ottax         = $parseNum($getCell($idxOttax) ?? 0);
                    $taxable       = $parseNum($getCell($idxTaxable) ?? 0);
                    $paye          = $parseNum($getCell($idxPaye) ?? 0);
                    $advance       = $parseNum($getCell($idxAdvance) ?? 0);
                    $totaldeduct   = $parseNum($getCell($idxTotaldeduct) ?? 0);
                    $netpayRaw     = $getCell($idxNetpay);
                    $netpay        = $netpayRaw !== null && trim((string)$netpayRaw) !== '' ? $parseNum($netpayRaw) : null;
                    $servicecharge = $parseNum($getCell($idxService) ?? 0);
                } else {
                    // Legacy positional fallback
                    $employ        = trim((string)($row[0] ?? '')) ?: null;
                    $name          = trim((string)($row[1] ?? '')) ?: null;
                    $report        = trim((string)($row[2] ?? ''));
                    $dept          = trim((string)($row[3] ?? ''));
                    $dob           = trim((string)($row[4] ?? ''));
                    $joindate      = trim((string)($row[5] ?? ''));
                    $ssnitnum      = trim((string)($row[6] ?? ''));
                    $age           = $parseNum($row[7] ?? 0);
                    $remarks       = (int)$parseNum($row[8] ?? 0);
                    $salary        = (int)$parseNum($row[9] ?? 0);
                    $newBasic      = (float)$salary;
                    $week          = (int)$parseNum($row[10] ?? 0);
                    $basic         = $row[11] !== null && trim((string)$row[11]) !== '' ? $parseNum($row[11]) : null;
                    $allowance     = $parseNum($row[12] ?? 0);
                    $sat           = $parseNum($row[13] ?? 0);
                    $satOT         = $parseNum($row[14] ?? 0);
                    $basicAll      = $basic !== null ? $basic + $allowance : 0;
                    $ssfyeed       = $parseNum($row[15] ?? 0);
                    $ssfyerd       = $parseNum($row[16] ?? 0);
                    $paye          = $parseNum($row[17] ?? 0);
                    $ottax         = $parseNum($row[18] ?? 0);
                    $taxable       = 0.0;
                    $advance       = $parseNum($row[19] ?? 0);
                    $totaldeduct   = $parseNum($row[20] ?? 0);
                    $netpay        = $row[21] !== null && trim((string)$row[21]) !== '' ? $parseNum($row[21]) : null;
                    $servicecharge = $parseNum($row[22] ?? 0);
                }

                // If Basic was not explicit but New Basic and Week Days are present, calculate basic
                if (is_null($basic)) {
                    if ($newBasic > 0 && $week > 0) {
                        $basic = round(($newBasic / 26) * $week, 2);
                    } elseif ($newBasic > 0) {
                        $basic = $newBasic;
                    }
                }

                // If Net Pay was not explicit, compute from Gross - Total Deductions
                if (is_null($netpay)) {
                    $computedGross = ($basic ?? 0) + $allowance + $satOT;
                    $netpay = round($computedGross - $totaldeduct, 2);
                }

                // Automatically ignore footer / total / note / blank-formatted rows
                if (!$employ && !$name) {
                    continue;
                }

                $cleanEmploy = strtolower(trim((string)$employ));
                $cleanName   = strtolower(trim((string)$name));
                if (
                    str_contains($cleanEmploy, 'total') || str_contains($cleanName, 'total') ||
                    str_contains($cleanEmploy, 'grand') || str_contains($cleanName, 'grand') ||
                    str_contains($cleanEmploy, 'sign') || str_contains($cleanName, 'sign') ||
                    str_contains($cleanEmploy, 'prepared') || str_contains($cleanName, 'prepared') ||
                    str_contains($cleanEmploy, 'approved') || str_contains($cleanName, 'approved') ||
                    str_contains($cleanEmploy, 'receiver') || str_contains($cleanName, 'receiver')
                ) {
                    continue;
                }

                // If row has no Employ ID, it's not an employee record (e.g. summary or note row)
                if (!$employ) {
                    continue;
                }

                $missing = [];
                if (!$name)   $missing[] = 'Name';
                if (is_null($basic)) $missing[] = 'Basic';
                if (is_null($netpay)) $missing[] = 'Net Pay';

                if (!empty($missing)) {
                    $errors[] = "Row {$excelRowNum}: Missing " . implode(', ', $missing);
                    continue;
                }

                if (in_array($employ, $existingIds)) {
                    $errors[] = "Row {$excelRowNum}: Duplicate Employ ID: $employ";
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

                if ($hasNewBasic) $record['new_basic'] = $newBasic;
                if ($hasBasicAll) $record['basic_all'] = $basicAll;
                if ($hasTaxable)  $record['taxable']   = $taxable;
                if ($hasSsfyee)   $record['ssfyee']    = $ssfyeed;
                if ($hasSsfyeed)  $record['ssfyeed']   = $ssfyeed;
                if ($hasSsfyer)   $record['ssfyer']    = $ssfyerd;
                if ($hasSsfyerd)  $record['ssfyerd']   = $ssfyerd;

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
            'Staff ID', 'Driver Name', 'Reporting Manager', 'Dept.', 'Date Of Birth', 'Date of Joining',
            'SSNIT Numbers', 'Age', 'Remarks', 'New Basic', 'Salary', 'Week Days', 'Basic (GHS)',
            'Allowances (GHS)', 'Sat (Days)', 'Sat Overtime', 'Basic + All', 'SSF Employee 5.5%',
            'SSF Employer 13%', 'OT Tax 5%', 'Taxable Salary', 'PAYE', 'Advance', 'Total Deduct',
            'Total Wages', 'Service Charge'
        ];

        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:Z1')->getFont()->setBold(true);

        $example = [[
            'DRV001', 'Kofi Mensah', 'Head Office', 'Transport', '15/03/1985', '01/06/2018',
            'SS12345678', 40, 0, 3000, 3000, 20, 2000,
            800, 4, 300, 2800, 154, 364, 15,
            2646, 250, 0, 419,
            2681, 25
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
