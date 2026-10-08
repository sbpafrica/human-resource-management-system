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
            'file'  => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $selectedMonth = $request->input('month');

        try {
            $date      = Carbon::createFromFormat('Y-m', $selectedMonth);
            $tableName = 'olamfood' . strtolower($date->format('F')) . $date->format('y');

            Olamfoodpay::createTableIfNotExists($tableName);

            $reader      = new XlsxReader();
            $spreadsheet = $reader->load($request->file('file')->getRealPath());
            $rows        = $spreadsheet->getActiveSheet()->toArray();
            array_shift($rows); // remove header

            $existingIds  = DB::table($tableName)->pluck('staffid')->toArray();
            $errors       = [];
            $validRecords = [];

            foreach ($rows as $index => $row) {
                // skip completely blank rows
                if (empty(trim($row[0] ?? ''))) continue;

                $staffid = trim($row[0] ?? '');
                $name    = trim($row[1] ?? '');

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

                $n = fn($v) => is_numeric($v) ? (float)$v : null;

                $validRecords[] = [
                    'staffid'           => $staffid,
                    'name'              => $name,
                    'basic'             => $n($row[2]  ?? null),
                    'gross'             => $n($row[3]  ?? null),
                    'service_charge_7'  => $n($row[4]  ?? null),
                    'getfund_2_5'       => $n($row[5]  ?? null),
                    'nhil_2_5'          => $n($row[6]  ?? null),
                    'vat_15'            => $n($row[7]  ?? null),
                    'ssf_13'            => $n($row[8]  ?? null),
                    'cost_to_ofi'       => $n($row[9]  ?? null),
                    'ssnit_ee_5_5'      => $n($row[10] ?? null),
                    'petra'             => $n($row[11] ?? null) ?? 0,
                    'taxable_income'    => $n($row[12] ?? null),
                    'paye'              => $n($row[13] ?? null),
                    'total_deduction'   => $n($row[14] ?? null),
                    'netpay'            => $n($row[15] ?? null),
                ];

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
                $sheet->setCellValue("T{$row}", $r->netpay ?? '');
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
        $tempFile = tempnam(sys_get_temp_dir(), $filename);
        $writer->save($tempFile);

        return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    }
}
