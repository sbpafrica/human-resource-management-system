<?php

namespace App\Http\Controllers;

use App\Models\HygienePayroll;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class HygienePayrollController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', session('selected_month', now()->format('Y-m')));
        session(['selected_month' => $month]);
        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'olamhygienetry' . strtolower($date->format('F')) . $date->format('y');
        Log::info("Index: Generated table name: {$tableName}");

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            Log::warning("Index: Table {$tableName} does not exist");
            return Inertia::render('HygienePayroll/Index', [
                'payrolls' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'filters' => [
                    'search' => $request->input('search'),
                    'sort' => $request->input('sort', 'id'),
                    'order' => $request->input('order', 'asc'),
                    'selectedMonth' => $month,
                ],
                'formattedMonth' => $date->format('F Y'),
                'totalRecords' => 0,
                'searchCount' => null,
                'flash' => ['error' => "No data available for {$date->format('F Y')}. Please upload data."],
            ]);
        }

        $query = DB::table($tableName . ' as hp')
            ->leftJoin('bankdetails as bd', 'hp.id', '=', 'bd.employid')
            ->select(
                'hp.id',
                'hp.names',
                'hp.dept',
                'bd.shop',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'hp.takehome'
            );

        // Apply search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('hp.id', 'like', "%{$search}%")
                  ->orWhere('hp.names', 'like', "%{$search}%")
                  ->orWhere('hp.dept', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'asc');
        Log::info("Index: Sorting by {$sort} {$order}");
        $sortColumn = in_array($sort, ['shop']) ? 'bd.shop' :
            (in_array($sort, ['bank']) ? 'bd.bank' :
            (in_array($sort, ['account']) ? 'bd.account' :
            (in_array($sort, ['code']) ? 'bd.code' :
            (in_array($sort, ['branch']) ? 'bd.branch' :
            (in_array($sort, ['takehome']) ? 'hp.takehome' : "hp.{$sort}")))));
        $query->orderBy($sortColumn, $order);

        // Paginate results
        $perPage = 10;
        $payrolls = $query->paginate($perPage)->appends([
            'month' => $month,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
        ]);

        // Get total records and search count
        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        // Debug STF123
        $stf123 = DB::table($tableName)->where('id', 'STF123')->first();
        Log::info('STF123 Debug', [
            'found' => $stf123 ? true : false,
            'payroll_id' => $stf123 ? $stf123->id : null,
            'hex_id' => $stf123 ? bin2hex($stf123->id) : null,
            'table' => $tableName,
            'bank_detail' => $stf123 ? (array)DB::table('bankdetails')->where('employid', 'STF123')->first() : null,
        ]);

        Log::info("Index: Retrieved records", ['count' => $payrolls->total(), 'sample' => $payrolls->items()[0] ?? null]);

        return Inertia::render('HygienePayroll/Index', [
            'payrolls' => $payrolls,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
                'selectedMonth' => $month,
            ],
            'formattedMonth' => $date->format('F Y'),
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function setMonth(Request $request)
    {
        $request->validate([
            'month' => ['required', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        try {
            $month = $request->input('month');
            Log::info("Setting Month: {$month}");
            session(['selected_month' => $month]);
            return redirect()->route('hygiene-payroll.index', ['month' => $month])
                ->with('success', 'Month updated successfully.');
        } catch (\Exception $e) {
            Log::error('Set month failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('hygiene-payroll.index')
                ->with('error', 'Failed to set month: ' . $e->getMessage());
        }
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:2048'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        try {
            $month = $request->input('month');
            session(['selected_month' => $month]);
            $date = Carbon::createFromFormat('Y-m', $month);
            $tableName = 'olamhygienetry' . strtolower($date->format('F')) . $date->format('y');
            Log::info("Upload: Generated table name: {$tableName}");

            HygienePayroll::createTableIfNotExists($tableName);

            $file = $request->file('file');
            $reader = new XlsxReader();
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);

            // Get headers from the first row
            $fileHeaders = array_map('strtolower', array_map('trim', array_values(array_shift($data) ?? [])));
            $expectedHeaders = ['id', 'names', 'dept', 'noofdays', 'extraldays', 'total1', 'rate', 'overtimehr', 'overtimeweekend', 'transport', 'night', 'crane'];
            Log::info('Uploaded File Headers', ['headers' => $fileHeaders]);

            // Validate headers
            if ($fileHeaders !== array_map('strtolower', $expectedHeaders)) {
                Log::error('Header Mismatch', ['expected' => $expectedHeaders, 'received' => $fileHeaders]);
                return redirect()->route('hygiene-payroll.index', ['month' => $month])
                    ->with('error', 'Excel headers do not match. Expected: ' . implode(', ', $expectedHeaders));
            }

            $existingIds = DB::table($tableName)->pluck('id')->toArray();
            $duplicateIds = [];
            $validRecords = [];
            $errors = [];

            foreach ($data as $index => $row) {
                $rowNumber = $index + 2; // Adjust for header row
                $id = trim($row['A'] ?? '') ?: null;
                $names = trim($row['B'] ?? '') ?: null;
                $dept = trim($row['C'] ?? '') ?: null;
                $noofdays = is_numeric($row['D'] ?? '') ? (float)$row['D'] : 0;
                $extraldays = is_numeric($row['E'] ?? '') ? (float)$row['E'] : 0;
                $total1 = is_numeric($row['F'] ?? '') ? (float)$row['F'] : 0;
                $rate = is_numeric($row['G'] ?? '') ? (float)$row['G'] : 0;
                $overtimehr = is_numeric($row['H'] ?? '') ? (float)$row['H'] : 0;
                $overtimeweekend = is_numeric($row['I'] ?? '') ? (float)$row['I'] : 0;
                $transport = is_numeric($row['J'] ?? '') ? (float)$row['J'] : 0;
                $night = is_numeric($row['K'] ?? '') ? (float)$row['K'] : 0;
                $crane = is_numeric($row['L'] ?? '') ? (float)$row['L'] : 0;

                Log::info("Processing row {$rowNumber}: ", [
                    'id' => $id,
                    'names' => $names,
                    'dept' => $dept,
                    'takehome' => $row['L'] ?? 'N/A',
                ]);

                if (empty($id)) {
                    $errors[] = "Row {$rowNumber}: Staff ID cannot be empty.";
                    continue;
                }
                if (empty($names)) {
                    $errors[] = "Row {$rowNumber}: Name cannot be empty.";
                    continue;
                }
                if (in_array($id, $existingIds) || in_array($id, $duplicateIds)) {
                    $duplicateIds[] = $id;
                    continue;
                }

                $total2 = $id === 'SBPHYGN24' ? 1200 : round($total1 * $rate, 2);
                $overtimetotal = round(($rate / 8) * 1.5 * $overtimehr, 2);
                $overtimetotal2 = round(($rate / 8) * 2 * $overtimeweekend, 2);
                $totalamount = round($total2 + $overtimetotal + $overtimetotal2, 2);
                $servicecharge = round(0.1 * $total2, 2);
                $getfund = round($servicecharge * 0.025, 2);
                $nhil = round($servicecharge * 0.025, 2);
                $covid19 = round($servicecharge * 0.01, 2);
                $vat = round(($servicecharge + $getfund + $nhil + $covid19) * 0.15, 2);
                $ssf = $dept === 'TEMPERING & PACKAGING (FACTORY)-BACKUP' ? 0 : round($total2 <= 490 ? 63.7 : $total2 * 0.13, 2);
                $leave1 = round(1.8374 * $total1, 2);
                $nightallowance = round($night * 11.42, 2);
                $medical = round(0.9187 * $total1, 2);
                $costofolam = round($servicecharge + $getfund + $nhil + $covid19 + $vat + $ssf + $leave1 + $nightallowance + $medical + $totalamount + $transport, 2);
                $ssnitee = $totalamount == 0 ? 0 : round($total2 <= 490 ? 26.95 : $total2 * 0.055, 2);
                $taxableincome = round($totalamount - $ssnitee, 2);
                $totaltaxdeduction = $dept === 'TEMPERING & PACKAGING (FACTORY)-BACKUP' ?
                    $taxableincome * 0.05 :
                    round($taxableincome < 490 ? 0 :
                        ($taxableincome < 600 ? 0.05 * ($taxableincome - 490) :
                        ($taxableincome < 730 ? 0.1 * ($taxableincome - 600) + 5.5 :
                        ($taxableincome < 3896.667 ? 0.175 * ($taxableincome - 730) + 18.5 :
                        ($taxableincome < 19896.67 ? 0.25 * ($taxableincome - 3896.667) + 572.6667 :
                        ($taxableincome < 50416.67 ? 0.3 * ($taxableincome - 19896.67) + 4572.667 :
                        0.35 * ($taxableincome - 50416.67) + 13728.67))))), 2);
                $totaldeduction = round($ssnitee + $totaltaxdeduction, 2);
                $takehome = round($totalamount - $totaldeduction + $nightallowance + $crane + $transport, 2);

                $validRecords[] = [
                    'id' => $id,
                    'names' => $names,
                    'dept' => $dept,
                    'noofdays' => $noofdays,
                    'extraldays' => $extraldays,
                    'total1' => $total1,
                    'rate' => $rate,
                    'overtimehr' => $overtimehr,
                    'overtimeweekend' => $overtimeweekend,
                    'transport' => $transport,
                    'night' => $night,
                    'crane' => $crane,
                    'total2' => $total2,
                    'overtimetotal' => $overtimetotal,
                    'overtimetotal2' => $overtimetotal2,
                    'totalamount' => $totalamount,
                    'servicecharge' => $servicecharge,
                    'getfund' => $getfund,
                    'nhil' => $nhil,
                    'covid19' => $covid19,
                    'vat' => $vat,
                    'ssf' => $ssf,
                    'leave1' => $leave1,
                    'nightallowance' => $nightallowance,
                    'medical' => $medical,
                    'costofolam' => $costofolam,
                    'ssnitee' => $ssnitee,
                    'taxableincome' => $taxableincome,
                    'totaltaxdeduction' => $totaltaxdeduction,
                    'totaldeduction' => $totaldeduction,
                    'takehome' => $takehome,
                ];
                $existingIds[] = $id;
            }

            if (!empty($errors)) {
                $errorMessage = count($errors) > 3
                    ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                    : implode(' | ', $errors);
                return redirect()->route('hygiene-payroll.index', ['month' => $month])
                    ->with('error', $errorMessage);
            }

            if (!empty($duplicateIds)) {
                return redirect()->route('hygiene-payroll.index', ['month' => $month])
                    ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateIds)));
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
            } else {
                Log::warning("Upload: No valid records to insert into {$tableName}");
            }

            return redirect()->route('hygiene-payroll.index', ['month' => $month])
                ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('Upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('hygiene-payroll.index', ['month' => $month])
                ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

    public function download(Request $request)
    {
        try {
            // Clear output buffer
            if (ob_get_length()) {
                ob_clean();
            }

            $month = $request->input('month', session('selected_month', now()->format('Y-m')));
            $date = Carbon::createFromFormat('Y-m', $month);
            $tableName = 'olamhygienetry' . strtolower($date->format('F')) . $date->format('y');
            $formattedMonth = $date->format('F Y');
            $monthFormatted = strtoupper($date->format('F'));
            $yearShort = $date->format('y');

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                Log::warning("Download: Table {$tableName} does not exist");
                return response()->json(['error' => "No data available for {$formattedMonth}"], 404);
            }

            $query = DB::table($tableName);
            $payrolls = $query->get();
            Log::info('Download: Payrolls fetched', ['count' => $payrolls->count()]);

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('INVOICE SCHEDULE');

            // INVOICE SCHEDULE Sheet
            $sheet->setCellValue('C1', 'INVOICE FOR OLAM HYGIENE STAFF')->mergeCells('C1:E1');
            $sheet->getStyle('C1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('000000');
            $sheet->getStyle('C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->setCellValue('C2', 'OLAM HYGIENE_' . $formattedMonth)->mergeCells('C2:E2');
            $sheet->getStyle('C2')->getFont()->setBold(true)->setSize(12)->getColor()->setARGB('000000');
            $sheet->getStyle('C2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $columns = [
                'id', 'names', 'dept', 'noofdays', 'extraldays', 'total1', 'rate', 'total2', 'overtimehr',
                'overtimetotal', 'overtimeweekend', 'overtimetotal2', 'totalamount', 'transport', 'servicecharge',
                'getfund', 'nhil', 'covid19', 'vat', 'ssf', 'leave1', 'night', 'nightallowance', 'medical',
                'costofolam', 'ssnitee', 'taxableincome', 'totaltaxdeduction', 'totaldeduction', 'takehome'
            ];
            $columnLetter = 'A';
            foreach ($columns as $header) {
                $sheet->setCellValue($columnLetter . '4', strtoupper($header));
                $sheet->getColumnDimension($columnLetter)->setWidth(20);
                $sheet->getStyle($columnLetter . '4')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle($columnLetter . '4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                $sheet->getStyle($columnLetter . '4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('61b4fe');
                $sheet->getStyle($columnLetter . '4')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $columnLetter++;
            }
            $sheet->getRowDimension(4)->setRowHeight(60);
            $sheet->freezePane('D5');

            $rowNumber = 5;
            foreach ($payrolls as $payroll) {
                $columnIndex = 'A';
                foreach ($columns as $column) {
                    $value = $payroll->$column;
                    if (in_array($column, ['id', 'names', 'dept'])) {
                        $sheet->setCellValueExplicit($columnIndex . $rowNumber, $value ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    } else {
                        $sheet->setCellValue($columnIndex . $rowNumber, is_numeric($value) ? (float)$value : 0);
                    }
                    $columnIndex++;
                }
                // Set calculations as Excel formulas
                $sheet->setCellValue('H' . $rowNumber, $payroll->id === 'SBPHYGN24' ? 1200 : "=F$rowNumber*G$rowNumber"); // total2
                $sheet->setCellValue('J' . $rowNumber, "=(G$rowNumber/8)*1.5*I$rowNumber"); // overtimetotal
                $sheet->setCellValue('L' . $rowNumber, "=(G$rowNumber/8)*2*K$rowNumber"); // overtimetotal2
                $sheet->setCellValue('M' . $rowNumber, "=H$rowNumber+J$rowNumber+L$rowNumber"); // totalamount
                $sheet->setCellValue('O' . $rowNumber, "=0.1*H$rowNumber"); // servicecharge
                $sheet->setCellValue('P' . $rowNumber, "=O$rowNumber*0.025"); // getfund
                $sheet->setCellValue('Q' . $rowNumber, "=O$rowNumber*0.025"); // nhil
                $sheet->setCellValue('R' . $rowNumber, "=O$rowNumber*0.01"); // covid19
                $sheet->setCellValue('S' . $rowNumber, "=(O$rowNumber+P$rowNumber+Q$rowNumber+R$rowNumber)*0.15"); // vat
                $sheet->setCellValue('T' . $rowNumber, $payroll->dept === 'TEMPERING & PACKAGING (FACTORY)-BACKUP' ? 0 : "=IF(H$rowNumber<=490,63.7,H$rowNumber*0.13)"); // ssf
                $sheet->setCellValue('U' . $rowNumber, "=1.8374*F$rowNumber"); // leave1
                $sheet->setCellValue('W' . $rowNumber, "=V$rowNumber*11.42"); // nightallowance
                $sheet->setCellValue('X' . $rowNumber, "=0.9187*F$rowNumber"); // medical
                $sheet->setCellValue('Y' . $rowNumber, "=O$rowNumber+P$rowNumber+Q$rowNumber+R$rowNumber+S$rowNumber+T$rowNumber+U$rowNumber+W$rowNumber+X$rowNumber+M$rowNumber+N$rowNumber"); // costofolam
                $sheet->setCellValue('Z' . $rowNumber, "=IF(M$rowNumber=0,0,IF(H$rowNumber<=490,26.95,H$rowNumber*0.055))"); // ssnitee
                $sheet->setCellValue('AA' . $rowNumber, "=M$rowNumber-Z$rowNumber"); // taxableincome
                $sheet->setCellValue('AB' . $rowNumber, $payroll->dept === 'TEMPERING & PACKAGING (FACTORY)-BACKUP' ?
                    "=AA$rowNumber*0.05" :
                    "=IF(AA$rowNumber<490,0,IF(AA$rowNumber<600,0.05*(AA$rowNumber-490),IF(AA$rowNumber<730,0.1*(AA$rowNumber-600)+5.5,IF(AA$rowNumber<3896.667,0.175*(AA$rowNumber-730)+18.5,IF(AA$rowNumber<19896.67,0.25*(AA$rowNumber-3896.667)+572.6667,IF(AA$rowNumber<50416.67,0.3*(AA$rowNumber-19896.67)+4572.667,0.35*(AA$rowNumber-50416.67)+13728.67))))))"); // totaltaxdeduction
                $sheet->setCellValue('AC' . $rowNumber, "=Z$rowNumber+AB$rowNumber"); // totaldeduction
                $sheet->setCellValue('AD' . $rowNumber, "=M$rowNumber-AC$rowNumber+W$rowNumber+N$rowNumber+V$rowNumber"); // takehome
                $rowNumber++;
            }

            $lastDataRow = $rowNumber - 1;
            $rowNumber++;
            $sheet->setCellValue("B$rowNumber", 'TOTAL');
            $totalColumns = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD'];
            foreach ($totalColumns as $col) {
                $sheet->setCellValue("$col$rowNumber", "=SUM({$col}5:{$col}$lastDataRow)");
            }
            $sheet->getStyle("C$rowNumber:AD$rowNumber")->getFont()->setSize(14)->setBold(true)->getColor()->setARGB('000000');
            $sheet->getStyle("C$rowNumber:AD$rowNumber")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE0');
            $totalRowNumber = $rowNumber;

            $numericColumns = ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB', 'AC', 'AD'];
            foreach ($numericColumns as $col) {
                $sheet->getStyle("{$col}5:{$col}{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("{$col}{$totalRowNumber}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
            $sheet->getStyle('A4:AD' . $totalRowNumber)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            // HYGN HR COST Sheet
            $invoiceSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, "HYGN $monthFormatted $yearShort HR COST");
            $spreadsheet->addSheet($invoiceSheet);
            $invoiceSheet->getColumnDimension('A')->setWidth(7);
            $invoiceSheet->getRowDimension(2)->setRowHeight(30);
            $invoiceSheet->getColumnDimension('B')->setWidth(15);
            $invoiceSheet->getColumnDimension('C')->setWidth(20);
            $invoiceSheet->getColumnDimension('D')->setWidth(15);
            $invoiceSheet->getColumnDimension('E')->setWidth(15);
            $invoiceSheet->getColumnDimension('F')->setWidth(15);

            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setPath(public_path('images/sbpafricalogo.png'));
            $drawing->setHeight(60)->setCoordinates('A2')->setOffsetX(5)->setOffsetY(5)->setWorksheet($invoiceSheet);
            $invoiceSheet->getRowDimension(1)->setRowHeight(70);

            $invoiceSheet->mergeCells('C3:F3');
            $invoiceSheet->setCellValue('C3', 'STRATEGIC BUSINESS PROCESS SOLUTIONS LTD.');
            $invoiceSheet->getStyle('C3')->getFont()->setBold(true)->setSize(14);
            $invoiceSheet->getStyle('C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $invoiceSheet->mergeCells('B5:F5');
            $invoiceSheet->setCellValue('B5', 'INVOICE (HR COST) - ' . strtoupper($formattedMonth));
            $invoiceSheet->getStyle('B5')->getFont()->setBold(true)->setSize(14);
            $invoiceSheet->getStyle('B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $invoiceSheet->setCellValue('D6', 'Client Account:');
            $invoiceSheet->getStyle('D6')->getFont()->setBold(true)->setSize(8);
            $invoiceSheet->setCellValue('E6', 'SBP-WFM/HND/HRC01');
            $invoiceSheet->getStyle('E6')->getFont()->setSize(8);

            $invoiceSheet->mergeCells('B7:F7');
            $invoiceSheet->setCellValue('B7', 'OLAM COCOA PROCESSING GH LTD.');
            $invoiceSheet->getStyle('B7')->getFont()->setBold(true)->setSize(11);

            $invoiceSheet->setCellValue('B8', '(HYGIENE)');
            $invoiceSheet->getStyle('B8')->getFont()->setBold(true)->setSize(11);

            $invoiceSheet->setCellValue('D8', 'Invoice No.:');
            $invoiceSheet->getStyle('D8')->getFont()->setBold(true)->setSize(8);
            $invoiceSheet->setCellValue('E8', 'SBPSL210125/HRC');
            $invoiceSheet->getStyle('E8')->getFont()->setSize(8);

            $invoiceSheet->setCellValue('B10', 'Date:');
            $invoiceSheet->getStyle('B10')->getFont()->setBold(true)->setSize(8);
            $invoiceSheet->setCellValue('C10', '2025-01-21');
            $invoiceSheet->getStyle('C10')->getFont()->setSize(8);
            $invoiceSheet->getStyle('C10')->getNumberFormat()->setFormatCode('yyyy-mm-dd');

            $invoiceSheet->setCellValue('B11', 'Page:');
            $invoiceSheet->getStyle('B11')->getFont()->setBold(true)->setSize(8);
            $invoiceSheet->setCellValue('C11', 1);
            $invoiceSheet->getStyle('C11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $invoiceSheet->getStyle('C11')->getFont()->setSize(8);

            $invoiceSheet->setCellValue('B12', 'Currency:');
            $invoiceSheet->getStyle('B12')->getFont()->setBold(true)->setSize(8);
            $invoiceSheet->setCellValue('C12', 'GHS');
            $invoiceSheet->getStyle('C12')->getFont()->setSize(8);

            $invoiceSheet->mergeCells('B14:C14');
            $invoiceSheet->setCellValue('B14', 'Description');
            $invoiceSheet->getRowDimension('14')->setRowHeight(20);
            $invoiceSheet->getStyle('B14')->getFont()->setBold(true)->setSize(14);
            $invoiceSheet->setCellValue('D14', 'Unit Cost');
            $invoiceSheet->setCellValue('E14', 'No. of EE');
            $invoiceSheet->setCellValue('F14', 'Sub-Total');
            $invoiceSheet->getStyle('B14:F14')->getFont()->setBold(true)->setSize(14);
            $invoiceSheet->getStyle('B14:F14')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $invoiceSheet->getStyle('B14:F14')->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);

            $invoiceSheet->mergeCells('B16:C16');
            $invoiceSheet->mergeCells('B17:C17');
            $invoiceSheet->mergeCells('B18:C18');
            $invoiceSheet->mergeCells('B19:C19');
            $invoiceSheet->setCellValue('B15', 'Total Basic Salaries');
            $invoiceSheet->setCellValue('E15', "=COUNTA('INVOICE SCHEDULE'!A5:'INVOICE SCHEDULE'!A$lastDataRow)");
            $invoiceSheet->setCellValue('F15', "='INVOICE SCHEDULE'!H$totalRowNumber");
            $invoiceSheet->getStyle('E15')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $invoiceSheet->setCellValue('B16', 'Total Overtime');
            $invoiceSheet->setCellValue('F16', "='INVOICE SCHEDULE'!J$totalRowNumber + 'INVOICE SCHEDULE'!L$totalRowNumber");
            $invoiceSheet->setCellValue('B17', 'Total Other Allowances');
            $invoiceSheet->setCellValue('F17', "='INVOICE SCHEDULE'!N$totalRowNumber + 'INVOICE SCHEDULE'!W$totalRowNumber");
            $invoiceSheet->setCellValue('B18', 'Total Hospital Expense');
            $invoiceSheet->setCellValue('F18', 0);
            $invoiceSheet->setCellValue('B19', 'SSNIT (13%)');
            $invoiceSheet->setCellValue('F19', "='INVOICE SCHEDULE'!T$totalRowNumber");
            $invoiceSheet->setCellValue('E20', 'TOTAL');
            $invoiceSheet->setCellValue('F20', "=SUM(F15:F19)");
            $invoiceSheet->getStyle('D15:F20')->getNumberFormat()->setFormatCode('#,##0.00');
            $invoiceSheet->getStyle('E15')->getNumberFormat()->setFormatCode('#,##0');
            $invoiceSheet->getStyle('B20:F20')->getFont()->setBold(true);
            $invoiceSheet->getStyle('B15:F20')->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            $invoiceSheet->getStyle('A20:D20')->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]]]);
            foreach (range(15, 20) as $row) {
                $invoiceSheet->getStyle("B$row")->getFont()->setSize(12);
                $invoiceSheet->getStyle("F$row")->getFont()->setSize($row == 20 ? 14 : 12);
                $invoiceSheet->getRowDimension($row)->setRowHeight(30);
            }
            $invoiceSheet->getStyle('B15:F20')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $invoiceSheet->mergeCells('B22:C22');
            $invoiceSheet->setCellValue('B22', "First National Bank\nCurrent Accounts: 62000131681\nSort Code: 330106\nBranch: Accra Mall");
            $invoiceSheet->getStyle('B22')->getAlignment()->setWrapText(true);
            $invoiceSheet->getStyle('B22')->getFont()->setSize(8);
            $invoiceSheet->getRowDimension('22')->setRowHeight(35);
            $invoiceSheet->mergeCells('B25:C25');
            $invoiceSheet->setCellValue('B25', "STRATEGIC BUSINESS PROCESS SOLUTIONS LTD.\nP. O. Box CT 7955\nCantonments Accra,\nGhana\nTel: +233 55 755 4599");
            $invoiceSheet->getStyle('B25')->getAlignment()->setWrapText(true);
            $invoiceSheet->getStyle('B25')->getFont()->setSize(8);
            $invoiceSheet->getRowDimension('25')->setRowHeight(60);
            $drawingFooter = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawingFooter->setPath(public_path('images/footer1.png'))->setHeight(50)->setCoordinates('B27')->setOffsetX(5)->setOffsetY(5)->setWorksheet($invoiceSheet);

            // HYGN ADMIN FEES Sheet
            $adminSheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet($spreadsheet, "HYGN $monthFormatted $yearShort ADMIN FEES");
            $spreadsheet->addSheet($adminSheet);
            $adminSheet->getColumnDimension('A')->setWidth(7);
            $adminSheet->getRowDimension(2)->setRowHeight(30);
            $adminSheet->getColumnDimension('B')->setWidth(15);
            $adminSheet->getColumnDimension('C')->setWidth(20);
            $adminSheet->getColumnDimension('D')->setWidth(15);
            $adminSheet->getColumnDimension('E')->setWidth(15);
            $adminSheet->getColumnDimension('F')->setWidth(15);
            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawing->setPath(public_path('images/sbpafricalogo.png'))->setHeight(60)->setCoordinates('A2')->setOffsetX(5)->setOffsetY(5)->setWorksheet($adminSheet);
            $adminSheet->getRowDimension(1)->setRowHeight(70);

            $adminSheet->mergeCells('C3:F3');
            $adminSheet->setCellValue('C3', 'STRATEGIC BUSINESS PROCESS SOLUTIONS LTD.');
            $adminSheet->getStyle('C3')->getFont()->setBold(true)->setSize(14);
            $adminSheet->getStyle('C3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $adminSheet->mergeCells('B5:F5');
            $adminSheet->setCellValue('B5', 'INVOICE (ADMIN FEES) - ' . strtoupper($formattedMonth));
            $adminSheet->getStyle('B5')->getFont()->setBold(true)->setSize(14);
            $adminSheet->getStyle('B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $adminSheet->setCellValue('D6', 'Client Account:');
            $adminSheet->getStyle('D6')->getFont()->setBold(true)->setSize(8);
            $adminSheet->setCellValue('E6', 'SBP-WFM/HND/HRC01');
            $adminSheet->getStyle('E6')->getFont()->setSize(8);

            $adminSheet->mergeCells('B7:F7');
            $adminSheet->setCellValue('B7', 'OLAM COCOA PROCESSING GH LTD.');
            $adminSheet->getStyle('B7')->getFont()->setBold(true)->setSize(11);

            $adminSheet->setCellValue('B8', '(HYGIENE)');
            $adminSheet->getStyle('B8')->getFont()->setBold(true)->setSize(11);

            $adminSheet->setCellValue('D8', 'Invoice No.:');
            $adminSheet->getStyle('D8')->getFont()->setBold(true)->setSize(8);
            $adminSheet->setCellValue('E8', 'SBPSL210125/HRC');
            $adminSheet->getStyle('E8')->getFont()->setSize(8);

            $adminSheet->setCellValue('B10', 'Date:');
            $adminSheet->getStyle('B10')->getFont()->setBold(true)->setSize(8);
            $adminSheet->setCellValue('C10', '2025-01-21');
            $adminSheet->getStyle('C10')->getFont()->setSize(8);
            $adminSheet->getStyle('C10')->getNumberFormat()->setFormatCode('yyyy-mm-dd');

            $adminSheet->setCellValue('B11', 'Page:');
            $adminSheet->getStyle('B11')->getFont()->setBold(true)->setSize(8);
            $adminSheet->setCellValue('C11', '1');
            $adminSheet->getStyle('C11')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $adminSheet->getStyle('C11')->getFont()->setSize(8);

            $adminSheet->setCellValue('B12', 'Currency:');
            $adminSheet->getStyle('B12')->getFont()->setBold(true)->setSize(8);
            $adminSheet->setCellValue('C12', 'GHS');
            $adminSheet->getStyle('C12')->getFont()->setSize(8);

            $adminSheet->mergeCells('B14:C14');
            $adminSheet->setCellValue('B14', 'Description');
            $adminSheet->getRowDimension('14')->setRowHeight(20);
            $adminSheet->getStyle('B14')->getFont()->setBold(true)->setSize(14);
            $adminSheet->setCellValue('D14', 'Unit Cost');
            $adminSheet->setCellValue('E14', 'No. of EE');
            $adminSheet->setCellValue('F14', 'Sub-Total');
            $adminSheet->getStyle('B14:F14')->getFont()->setBold(true)->setSize(14);
            $adminSheet->getStyle('B14:F14')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $adminSheet->getStyle('B14:F14')->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);

            $adminSheet->mergeCells('B16:C16');
            $adminSheet->mergeCells('B17:C17');
            $adminSheet->mergeCells('B18:C18');
            $adminSheet->mergeCells('B19:C19');
            $adminSheet->setCellValue('B15', 'Admin Fees (10% on Total Basic Salaries)');
            $adminSheet->setCellValue('F15', "='INVOICE SCHEDULE'!O$totalRowNumber");
            $adminSheet->setCellValue('B16', 'NHIL (2.5%)');
            $adminSheet->setCellValue('F16', "='INVOICE SCHEDULE'!Q$totalRowNumber");
            $adminSheet->setCellValue('B17', 'GETFUND (2.5%)');
            $adminSheet->setCellValue('F17', "='INVOICE SCHEDULE'!P$totalRowNumber");
            $adminSheet->setCellValue('B18', 'COVID-19 (1%)');
            $adminSheet->setCellValue('F18', "='INVOICE SCHEDULE'!R$totalRowNumber");
            $adminSheet->setCellValue('B19', 'VAT (15%)');
            $adminSheet->setCellValue('F19', "='INVOICE SCHEDULE'!S$totalRowNumber");
            $adminSheet->setCellValue('E20', 'TOTAL');
            $adminSheet->setCellValue('F20', "=SUM(F15:F19)");
            $adminSheet->getStyle('D15:F20')->getNumberFormat()->setFormatCode('#,##0.00');
            $adminSheet->getStyle('B20:F20')->getFont()->setBold(true);
            $adminSheet->getStyle('B15:F20')->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            $adminSheet->getStyle('A20:D20')->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_NONE]]]);
            foreach (range(15, 20) as $row) {
                $adminSheet->getStyle("B$row")->getFont()->setSize(12);
                $adminSheet->getStyle("F$row")->getFont()->setSize($row == 20 ? 14 : 12);
                $adminSheet->getRowDimension($row)->setRowHeight(30);
            }
            $adminSheet->getStyle('B15:F20')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $adminSheet->mergeCells('B22:C22');
            $adminSheet->setCellValue('B22', "First National Bank\nCurrent Accounts: 62000131681\nSort Code: 330106\nBranch: Accra Mall");
            $adminSheet->getStyle('B22')->getAlignment()->setWrapText(true);
            $adminSheet->getStyle('B22')->getFont()->setSize(8);
            $adminSheet->getRowDimension('22')->setRowHeight(35);
            $adminSheet->mergeCells('B25:C25');
            $adminSheet->setCellValue('B25', "STRATEGIC BUSINESS PROCESS SOLUTIONS LTD.\nP. O. Box CT 7955\nCantonments Accra,\nGhana\nTel: +233 55 755 4599");
            $adminSheet->getStyle('B25')->getAlignment()->setWrapText(true);
            $adminSheet->getStyle('B25')->getFont()->setSize(8);
            $adminSheet->getRowDimension('25')->setRowHeight(60);
            $drawingFooter = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
            $drawingFooter->setPath(public_path('images/footer1.png'))->setHeight(50)->setCoordinates('B27')->setOffsetX(5)->setOffsetY(5)->setWorksheet($adminSheet);

            $spreadsheet->setActiveSheetIndexByName("INVOICE SCHEDULE");
            $fileName = "Hygiene_Payroll_" . $date->format("Y_m_d_H_i_s") . ".xlsx";
            $filePath = storage_path("app/public/{$fileName}");
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);
            Log::info('Download: File saved', ['path' => $filePath, 'size' => filesize($filePath)]);

            if (!file_exists($filePath) || !is_readable($filePath)) {
                Log::error('Download: File not found or unreadable', ['path' => $filePath]);
                throw new \Exception('Generated file is not accessible');
            }

            return response()->download($filePath, "Hygiene_Payroll_{$formattedMonth}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Length' => filesize($filePath),
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('General Payroll Download Failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
            ]);
            return response()->json(['error' => 'Failed to generate payroll Excel: ' . $e->getMessage()], 500);
        }
    }

    public function downloadTable(Request $request)
    {
        try {
            $month = $request->input('month', session('selected_month', now()->format('Y-m')));
            $date = Carbon::createFromFormat('Y-m', $month);
            $tableName = 'olamhygienetry' . strtolower($date->format('F')) . $date->format('y');
            $formattedMonth = $date->format('F Y');

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                Log::warning("DownloadTable: Table {$tableName} does not exist");
                return redirect()->route('hygiene-payroll.index', ['month' => $month])
                    ->with('error', "No data available for {$formattedMonth}.");
            }

            $query = DB::table($tableName . ' as hp')
                ->leftJoin('bankdetails as bd', 'hp.id', '=', 'bd.employid')
                ->select(
                    'hp.id',
                    'hp.names',
                    'hp.dept',
                    'bd.shop',
                    'bd.bank',
                    'bd.account',
                    'bd.code',
                    'bd.branch',
                    'hp.takehome'
                );

            // Apply search
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('hp.id', 'like', "%{$search}%")
                      ->orWhere('hp.names', 'like', "%{$search}%")
                      ->orWhere('hp.dept', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sort = $request->input('sort', 'id');
            $order = $request->input('order', 'asc');
            $sortColumn = in_array($sort, ['shop']) ? 'bd.shop' :
                (in_array($sort, ['bank']) ? 'bd.bank' :
                (in_array($sort, ['account']) ? 'bd.account' :
                (in_array($sort, ['code']) ? 'bd.code' :
                (in_array($sort, ['branch']) ? 'bd.branch' :
                (in_array($sort, ['takehome']) ? 'hp.takehome' : "hp.{$sort}")))));
            $query->orderBy($sortColumn, $order);

            $payrolls = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Payroll Table');

            $headers = ['Staff ID', 'Name', 'Department', 'Shop', 'Bank', 'Account', 'Branch Code', 'Branch', 'Net Salary'];
            $columnLetter = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($columnLetter . '1', $header);
                $sheet->getColumnDimension($columnLetter)->setWidth(20);
                $sheet->getStyle($columnLetter . '1')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle($columnLetter . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($columnLetter . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('61b4fe');
                $sheet->getStyle($columnLetter . '1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $columnLetter++;
            }
            $sheet->getRowDimension(1)->setRowHeight(30);

            $rowNumber = 2;
            foreach ($payrolls as $payroll) {
                $sheet->setCellValue("A$rowNumber", $payroll->id);
                $sheet->setCellValue("B$rowNumber", $payroll->names);
                $sheet->setCellValue("C$rowNumber", $payroll->dept);
                $sheet->setCellValue("D$rowNumber", $payroll->shop ?? 'OLAM BWH');
                $sheet->setCellValue("E$rowNumber", $payroll->bank ?? 'N/A');
                $sheet->setCellValue("F$rowNumber", $payroll->account ?? 'N/A');
                $sheet->setCellValue("G$rowNumber", $payroll->code ?? 'N/A');
                $sheet->setCellValue("H$rowNumber", $payroll->branch ?? 'N/A');
                $sheet->setCellValue("I$rowNumber", round($payroll->takehome, 2));
                $rowNumber++;
            }

            $sheet->getStyle("I2:I$rowNumber")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A1:I$rowNumber")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            $fileName = "Payroll_Table_" . $date->format("Y_m_d_H_i_s") . ".xlsx";
            $filePath = storage_path("app/public/{$fileName}");
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            return response()->download($filePath, "Payroll_Table_{$formattedMonth}.xlsx", [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="Payroll_Table_' . $formattedMonth . '.xlsx"',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Payroll Table Download Failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('hygiene-payroll.index', ['month' => $month])
                ->with('error', 'Failed to download table: ' . $e->getMessage());
        }
    }

    public function downloadTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Hygiene Payroll Template');

            $headers = ['ID', 'NAMES', 'DEPT', 'NOOFDAYS', 'EXTRALDAYS', 'TOTAL1', 'RATE', 'OVERTIMEHR', 'OVERTIMEWEEKEND', 'TRANSPORT', 'NIGHT', 'CRANE'];
            $columnLetter = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($columnLetter . '1', $header);
                $sheet->getColumnDimension($columnLetter)->setWidth(20);
                $sheet->getStyle($columnLetter . '1')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle($columnLetter . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($columnLetter . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('61b4fe');
                $sheet->getStyle($columnLetter . '1')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $columnLetter++;
            }
            $sheet->getRowDimension(1)->setRowHeight(30);

            $exampleData = [
                ['STF123', 'John Doe', 'Hygiene', 22, 2, 24, 50, 10, 5, 100, 2, 50],
                ['STF124', 'Akwei', 'Hygiene', 22, 2, 24, 50, 10, 5, 100, 0, 0],
                ['STF125', 'Ama Amponsah', 'Hygiene', 22, 2, 24, 50, 10, 5, 100, 0, 0]
            ];
            $sheet->fromArray($exampleData, null, 'A2');
            $sheet->setCellValue('A5', 'Note: Numeric fields (e.g., TOTAL1, RATE) must be numbers without symbols. All fields are required except NIGHT and CRANE, which can be 0 or empty.');

            $fileName = 'hygiene_payroll_template_' . date('Y_m_d_H_i_s') . '.xlsx';
            $filePath = storage_path("app/public/{$fileName}");
            $writer = new Xlsx($spreadsheet);
            $writer->save($filePath);

            return response()->download($filePath, 'hygiene_payroll_template.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="hygiene_payroll_template.xlsx"',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Template Download Failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('hygiene-payroll.index')
                ->with('error', 'Failed to generate template: ' . $e->getMessage());
        }
    }

    public function delete(Request $request)
    {
        $month = $request->input('month', session('selected_month', now()->format('Y-m')));
        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'olamhygienetry' . strtolower($date->format('F')) . $date->format('y');

        try {
            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                DB::table($tableName)->truncate();
                Log::info("Delete: Truncated table {$tableName}");
                return redirect()->route('hygiene-payroll.index', ['month' => $month])
                    ->with('success', 'Records deleted successfully.');
            } else {
                Log::warning("Delete: Table {$tableName} does not exist");
                return redirect()->route('hygiene-payroll.index', ['month' => $month])
                    ->with('error', 'No records found for the selected month.');
            }
        } catch (\Exception $e) {
            Log::error('Delete failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('hygiene-payroll.index', ['month' => $month])
                ->with('error', 'Failed to delete records: ' . $e->getMessage());
        }
    }
}
