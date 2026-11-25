<?php

namespace App\Http\Controllers;

use App\Models\Olamfoodpay;
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

class OlamfoodPayController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month     = $request->input('month', now()->format('Y-m'));
        $date      = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'olamfood' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('Olamfoodpay/Index', [
                'records'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'searchCount'    => null,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
            ->select(
                'tp.staffid',
                DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.basic',
                'tp.gross',
                'tp.netpay'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffid', 'like', "%{$search}%")
                  ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort  = $request->input('sort', 'staffid');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'staffid' => 'tp.staffid',
            'name'    => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
            'bank'    => 'bd.bank',
            'account' => 'bd.account',
            'netpay'  => 'tp.netpay',
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.staffid', $order);

        $records      = $query->paginate(30)->appends($request->query());
        $totalRecords = DB::table($tableName)->count();
        $searchCount  = $search ? $query->getCountForPagination() : null;

        return Inertia::render('Olamfoodpay/Index', [
            'records'        => $records,
            'selectedMonth'  => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords'   => $totalRecords,
            'searchCount'    => $searchCount,
            'flash'          => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file'  => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:2048'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $selectedMonth = $request->input('month');

        try {
            $date      = Carbon::createFromFormat('Y-m', $selectedMonth);
            $tableName = 'olamfood' . strtolower($date->format('F')) . $date->format('y');

            Olamfoodpay::createTableIfNotExists($tableName);
            \App\Models\PayrollColumn::ensureColumnsForTable($tableName, 'olamfood');

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
            $rows        = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);

            if (empty($rows)) {
                return redirect()->route('olamfoodpay.index', ['month' => $selectedMonth])
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
                    if (preg_match('/^(staff(\s*id)?|name|basic|gross|net(\s*pay)?)/i', $norm)) {
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
                'staffid'          => ['staff id', 'staffid', 'employee id'],
                'name'             => ['name', 'staff name', 'employee name'],
                'basic'            => ['basic', 'basic salary'],
                'gross'            => ['gross', 'gross salary'],
                'service_charge_7' => ['service charge 7%', 'service charge'],
                'getfund_2_5'      => ['getfund 2.5%', 'getfund'],
                'nhil_2_5'         => ['nhil 2.5%', 'nhil'],
                'vat_15'           => ['vat 15%', 'vat'],
                'ssf_13'           => ['ssf 13%', 'ssf'],
                'cost_to_ofi'      => ['cost to ofi', 'cost to company', 'cost to olam'],
                'ssnit_ee_5_5'     => ['ssnit ee 5.5%', 'ssnit ee'],
                'petra'            => ['petra', 'tier 2', 'petra 5%'],
                'taxable_income'   => ['taxable income', 'taxable'],
                'paye'             => ['paye', 'income tax'],
                'total_deduction'  => ['total deduction', 'total deductions'],
                'netpay'           => ['net pay', 'netpay', 'take home pay', 'take home', 'bank transfer'],
            ];

            $defaultIndexes = [
                'staffid' => 0, 'name' => 1, 'basic' => 2, 'gross' => 3,
                'service_charge_7' => 4, 'getfund_2_5' => 5, 'nhil_2_5' => 6,
                'vat_15' => 7, 'ssf_13' => 8, 'cost_to_ofi' => 9, 'ssnit_ee_5_5' => 10,
                'petra' => 11, 'taxable_income' => 12, 'paye' => 13, 'total_deduction' => 14,
                'netpay' => 15,
            ];

            $headerIndexMap = \App\Models\PayrollColumn::buildHeaderIndexMap($headerRow, $fieldAliases, $defaultIndexes);

            $existingIds  = DB::table($tableName)->pluck('staffid')->toArray();
            $errors       = [];
            $validRecords = [];

            foreach ($dataRows as $index => $row) {
                // Skip completely blank rows
                $allEmpty = true;
                foreach ($row as $c) {
                    if ($c !== null && trim((string)$c) !== '') {
                        $allEmpty = false;
                        break;
                    }
                }
                if ($allEmpty) continue;

                $staffid = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['staffid'], 'str');
                $name    = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['name'], 'str');

                if (empty($staffid) && empty($name)) continue;

                if (empty($staffid)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID is required.";
                    continue;
                }
                if (empty($name)) {
                    $errors[] = "Row " . ($index + 2) . ": Name is required.";
                    continue;
                }
                if (in_array($staffid, $existingIds)) {
                    $errors[] = "Row " . ($index + 2) . ": Duplicate Staff ID: {$staffid}";
                    continue;
                }

                $basic            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['basic'], 'req_num');
                $gross            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['gross'], 'req_num');
                $service_charge_7 = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['service_charge_7'], 'num');
                $getfund_2_5      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['getfund_2_5'], 'num');
                $nhil_2_5         = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['nhil_2_5'], 'num');
                $vat_15           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['vat_15'], 'num');
                $ssf_13           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssf_13'], 'num');
                $cost_to_ofi      = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['cost_to_ofi'], 'num');
                $ssnit_ee_5_5     = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['ssnit_ee_5_5'], 'num');
                $petra            = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['petra'], 'num', 0.0);
                $taxable_income   = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['taxable_income'], 'num');
                $paye             = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['paye'], 'num');
                $total_deduction  = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['total_deduction'], 'num');
                $netpay           = \App\Models\PayrollColumn::extractValue($row, $headerIndexMap['netpay'], 'req_num');

                if (is_null($basic) || is_null($gross)) {
                    $errors[] = "Row " . ($index + 2) . ": Basic and Gross are required.";
                    continue;
                }
                if (is_null($netpay)) {
                    $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number.";
                    continue;
                }

                $record = [
                    'staffid'           => $staffid,
                    'name'              => $name,
                    'basic'             => $basic,
                    'gross'             => $gross,
                    'service_charge_7'  => $service_charge_7,
                    'getfund_2_5'       => $getfund_2_5,
                    'nhil_2_5'          => $nhil_2_5,
                    'vat_15'            => $vat_15,
                    'ssf_13'            => $ssf_13,
                    'cost_to_ofi'       => $cost_to_ofi,
                    'ssnit_ee_5_5'      => $ssnit_ee_5_5,
                    'petra'             => $petra,
                    'taxable_income'    => $taxable_income,
                    'paye'              => $paye,
                    'total_deduction'   => $total_deduction,
                    'netpay'            => $netpay,
                ];

                // Dynamic custom columns from payroll_columns
                $customVals = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headerRow, 'olamfood');
                if (!empty($customVals)) {
                    $record = array_merge($record, $customVals);
                }

                $validRecords[] = $record;
                $existingIds[] = $staffid;
            }

            if (!empty($errors)) {
                $msg = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('olamfoodpay.index', ['month' => $selectedMonth])->with('error', $msg);
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
            }

            return redirect()->route('olamfoodpay.index', ['month' => $selectedMonth])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');

        } catch (\Exception $e) {
            Log::error('OlamfoodPay upload failed', ['error' => $e->getMessage()]);
            return redirect()->route('olamfoodpay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

    public function delete(Request $request)
    {
        $selectedMonth = $request->input('month', now()->format('Y-m'));
        $date          = Carbon::createFromFormat('Y-m', $selectedMonth);
        $tableName     = 'olamfood' . strtolower($date->format('F')) . $date->format('y');

        try {
            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                DB::table($tableName)->truncate();
                return redirect()->route('olamfoodpay.index', ['month' => $selectedMonth])
                    ->with('success', 'Records deleted successfully.');
            }
            return redirect()->route('olamfoodpay.index', ['month' => $selectedMonth])
                ->with('error', 'No records found for the selected month.');
        } catch (\Exception $e) {
            Log::error('OlamfoodPay delete failed', ['error' => $e->getMessage()]);
            return redirect()->route('olamfoodpay.index', ['month' => $selectedMonth])
                ->with('error', 'Failed to delete records: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        try {
            $request->validate(['month' => ['required', 'date_format:Y-m']]);

            $month     = $request->input('month');
            $date      = Carbon::createFromFormat('Y-m', $month);
            $tableName = 'olamfood' . strtolower($date->format('F')) . $date->format('y');

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('bankdetails as bd', 'tp.staffid', '=', 'bd.employid')
                ->select(
                    'tp.staffid',
                    DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) as name'),
                    'bd.bank',
                    'bd.account',
                    'bd.code',
                    'bd.branch',
                    'tp.basic',
                    'tp.gross',
                    'tp.service_charge_7',
                    'tp.getfund_2_5',
                    'tp.nhil_2_5',
                    'tp.vat_15',
                    'tp.ssf_13',
                    'tp.cost_to_ofi',
                    'tp.ssnit_ee_5_5',
                    'tp.petra',
                    'tp.taxable_income',
                    'tp.paye',
                    'tp.total_deduction',
                    'tp.netpay'
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.staffid', 'like', "%{$search}%")
                      ->orWhereRaw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name) LIKE ?', ["%{$search}%"])
                      ->orWhere('bd.bank', 'like', "%{$search}%");
                });
            }

            $sort  = $request->input('sort', 'staffid');
            $order = $request->input('order', 'asc');
            $sortMap = [
                'staffid' => 'tp.staffid',
                'name'    => DB::raw('COALESCE(NULLIF(TRIM(bd.name), ""), tp.name)'),
                'bank'    => 'bd.bank',
                'netpay'  => 'tp.netpay',
            ];
            $query->orderBy($sortMap[$sort] ?? 'tp.staffid', $order);

            $records = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();

            $headers = [
                'Staff ID', 'Name', 'Bank', 'Account', 'Branch Code', 'Branch',
                'Basic', 'Gross', 'Service Charge 7%', 'GETFund 2.5%', 'NHIL 2.5%',
                'VAT 15%', 'SSF 13%', 'Cost to OFI', 'SSNIT EE 5.5%', 'PETRA',
                'Taxable Income', 'PAYE', 'Total Deduction', 'Net Pay',
            ];
        $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            foreach ($records as $r) {
                $sheet->setCellValue("A{$row}", $r->staffid);
                $sheet->setCellValue("B{$row}", $r->name);
                $sheet->setCellValue("C{$row}", $r->bank ?? '');
                $sheet->setCellValueExplicit("D{$row}", $r->account ?? '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("E{$row}", $r->code ?? '', DataType::TYPE_STRING);
                $sheet->setCellValue("F{$row}", $r->branch ?? '');
                $sheet->setCellValue("G{$row}", $r->basic ?? '');
                $sheet->setCellValue("H{$row}", $r->gross ?? '');
                $sheet->setCellValue("I{$row}", $r->service_charge_7 ?? '');
                $sheet->setCellValue("J{$row}", $r->getfund_2_5 ?? '');
                $sheet->setCellValue("K{$row}", $r->nhil_2_5 ?? '');
                $sheet->setCellValue("L{$row}", $r->vat_15 ?? '');
                $sheet->setCellValue("M{$row}", $r->ssf_13 ?? '');
                $sheet->setCellValue("N{$row}", $r->cost_to_ofi ?? '');
                $sheet->setCellValue("O{$row}", $r->ssnit_ee_5_5 ?? '');
                $sheet->setCellValue("P{$row}", $r->petra ?? '');
                $sheet->setCellValue("Q{$row}", $r->taxable_income ?? '');
                $sheet->setCellValue("R{$row}", $r->paye ?? '');
                $sheet->setCellValue("S{$row}", $r->total_deduction ?? '');
                $sheet->setCellValue("T{$row}", ($r->netpay !== null && $r->netpay !== '') ? number_format((float)$r->netpay, 2, '.', '') : '');
                $row++;
            }

            $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
            $sheet->getStyle('A1:T1')->getFont()->setBold(true);
            $sheet->getStyle('A1:T1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('BBDEFE');

            foreach (range('A', 'T') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer   = new Xlsx($spreadsheet);
            $filename = "olamfood_pay_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);

        } catch (\Exception $e) {
            Log::error('OlamfoodPay export failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed. Please try again.'], 500);
        }
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID', 'Name', 'Basic', 'Gross',
            'Service Charge 7%', 'GETFund 2.5%', 'NHIL 2.5%', 'VAT 15%',
            'SSF 13%', 'Cost to OFI', 'SSNIT EE 5.5%', 'PETRA',
            'Taxable Income', 'PAYE', 'Total Deduction', 'Net Pay',
        ];

        $nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'olamfood');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);

        // Sample row
        $sheet->fromArray([
            'OFI001', 'Kwame Boateng', 3000.00, 3000.00,
            210.00, 75.00, 75.00, 450.00,
            390.00, 4200.00, 165.00, 0,
            2625.00, 180.00, 345.00, 2655.00,
        ], null, 'A2');

        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer   = new Xlsx($spreadsheet);
        $filename = 'olamfood_payroll_template_' . date('M-Y') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'max-age=0, no-cache, no-store, must-revalidate',
            'Pragma'              => 'public',
        ]);
    }
}
