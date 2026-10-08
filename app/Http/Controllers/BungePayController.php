<?php

namespace App\Http\Controllers;

use App\Models\Bungepay;
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

class BungePayController extends Controller
{
    private function resolveTableName(Carbon $date): string
    {
        $fullMonth = strtolower($date->format('F')) . $date->format('y');
        $shortMonth = strtolower($date->format('M')) . $date->format('y');

        $candidate1 = 'bungegh' . $fullMonth;
        $candidate2 = 'bungegh' . $shortMonth;

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
        Log::info("Index: Received month: {$month}");

        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = $this->resolveTableName($date);
        Log::info("Index: Generated table name: {$tableName}");

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            Log::warning("Index: Table {$tableName} does not exist");
            return Inertia::render('Bungepay/Index', [
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
            ->leftJoin('bankdetails as bd', 'tp.STAFF_ID', '=', 'bd.employid')
            ->select(
                'tp.STAFF_ID',
                'tp.NAME',
                'bd.shop',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.TAKE_HOME_PAY_GHC'
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where("tp.STAFF_ID", 'like', "%{$search}%")
                  ->orWhere("tp.NAME", 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'STAFF_ID');
        $order = $request->input('order', 'asc');
        Log::info("Index: Sorting by {$sort} {$order}");
        $sortColumn = in_array($sort, ['shop', 'department']) ? 'bd.shop' :
        (in_array($sort, ['bank', 'bankname']) ? 'bd.bank' :
        (in_array($sort, ['account', 'alcnum']) ? 'bd.account' :
        (in_array($sort, ['code']) ? 'bd.code' :
        (in_array($sort, ['branch']) ? 'bd.branch' : "tp.{$sort}"))));
        $query->orderBy($sortColumn, $order);

        $perPage = 10;
        $records = $query->paginate($perPage)->appends([
            'month' => $month,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
        ]);

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        return Inertia::render('Bungepay/Index', [
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
            $request->validate([
                'month' => ['required', 'date_format:Y-m', 'after_or_equal:2020-01'],
            ]);

            $month = $request->input('month');
            $date  = Carbon::createFromFormat('Y-m', $month);
            $tableName = $this->resolveTableName($date);

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('bankdetails as bd', 'tp.STAFF_ID', '=', 'bd.employid')
                ->select(
                    'tp.STAFF_ID as staff_id',
                    'tp.NAME as name',
                    'bd.shop',
                    'bd.bank',
                    'bd.account',
                    'bd.code',
                    'bd.branch',
                    'tp.TAKE_HOME_PAY_GHC as net_pay'
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.STAFF_ID', 'like', "%{$search}%")
                      ->orWhere('tp.NAME', 'like', "%{$search}%")
                      ->orWhere('bd.shop', 'like', "%{$search}%")
                      ->orWhere('bd.bank', 'like', "%{$search}%");
                });
            }

            $sort = $request->input('sort', 'STAFF_ID');
            $order = $request->input('order', 'asc');

            $sortMap = [
                'STAFF_ID'     => 'tp.STAFF_ID',
                'staff_id'     => 'tp.STAFF_ID',
                'NAME'         => 'tp.NAME',
                'name'         => 'tp.NAME',
                'shop'         => 'bd.shop',
                'bank'         => 'bd.bank',
                'account'      => 'bd.account',
                'code'         => 'bd.code',
                'branch'       => 'bd.branch',
                'TAKE_HOME_PAY_GHC' => 'tp.TAKE_HOME_PAY_GHC',
                'net_pay'      => 'tp.TAKE_HOME_PAY_GHC',
            ];

            $query->orderBy($sortMap[$sort] ?? 'tp.STAFF_ID', $order);

            $records = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = ['Staff ID', 'Employee Name', 'Shop/Location', 'Bank Name', 'Account No.', 'Branch Code', 'Branch', 'Take Home Pay (GHC)'];
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
                $sheet->setCellValue("H{$row}", $r->net_pay ? number_format((float)$r->net_pay, 2, '.', '') : '');
                $row++;
            }

            $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
            $headerStyle = $sheet->getStyle('A1:H1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('BBDEFE');

            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = "bunge_ghana_payroll_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (\Exception $e) {
            Log::error("BungePay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
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
            $tableName = $this->resolveTableName($date);

            BungePay::createTableIfNotExists($tableName);

            $file = $request->file('file');
            $reader = new XlsxReader();
            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            array_shift($rows); // Remove header row

            $existingStaffIds = DB::table($tableName)->pluck('STAFF_ID')->toArray();
            $duplicateStaffIds = [];
            $validRecords = [];
            $errors = [];

            $parseNum = function ($val, $default = 0.0) {
                if (is_null($val) || $val === '') return (float)$default;
                if (is_numeric($val)) return (float)$val;
                $cleaned = preg_replace('/[^\d\.\-]/', '', (string)$val);
                return is_numeric($cleaned) ? (float)$cleaned : (float)$default;
            };

            foreach ($rows as $index => $row) {
                // Auto-pad row to 31 columns so trailing empty cells don't cause errors
                $row = array_pad($row, 31, null);

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

                $STAFF_ID                     = trim((string)($row[0] ?? '')) ?: null;
                $NAME                         = trim((string)($row[1] ?? '')) ?: null;
                $POSITION                     = trim((string)($row[2] ?? '')) ?: null;

                $BASE_SALARY_GHC              = $parseNum($row[3] ?? 0);
                $BACKPAY                      = $parseNum($row[4] ?? 0);
                $TRANSPORT_ALLOWANCE_GHC      = $parseNum($row[5] ?? 0);
                $ADDITIONAL_ALLOWANCE_GHC     = $parseNum($row[6] ?? 0);
                $Rent                         = $parseNum($row[7] ?? 0);
                $tmporary_ALLOWANCE_GHC       = $parseNum($row[8] ?? 0);
                $OVERTIME_GHC                 = $parseNum($row[9] ?? 0);
                $BONUS_EARNINGS_ALLOWANCE     = $parseNum($row[10] ?? 0);
                $GROSS_SALARY_GHC             = $parseNum($row[11] ?? 0);

                $MANAGEMENT_FEE_5_PERCENT     = $parseNum($row[12] ?? 0);
                $SSNIT_ER_13_PERCENT          = $parseNum($row[13] ?? 0);
                $GETFUND_2_5_PERCENT          = $parseNum($row[14] ?? 0);
                $NHIL_2_5_PERCENT             = $parseNum($row[15] ?? 0);
                $VAT_15_PERCENT               = $parseNum($row[16] ?? 0);
                $COST_TO_BUNGE_GHC            = $parseNum($row[17] ?? 0);

                $gross2                       = $parseNum($row[18] ?? 0);

                $SSNIT_5_5_PERCENT            = $parseNum($row[19] ?? 0);
                $PETRA                        = $parseNum($row[20] ?? 0);
                $TAXABLE_INCOME_GHC           = $parseNum($row[21] ?? 0);
                $PAYE_GHC                     = $parseNum($row[22] ?? 0);
                $BONUS_TAX                    = $parseNum($row[23] ?? 0);
                $TOTAL_TAXATION_GHC           = $parseNum($row[24] ?? 0);

                $rawTakehome                  = trim((string)($row[25] ?? ''));
                $TAKE_HOME_PAY_GHC            = $parseNum($rawTakehome, null);

                // Fallback Net Pay calculation if empty
                if (is_null($TAKE_HOME_PAY_GHC) && $GROSS_SALARY_GHC > 0) {
                    $TAKE_HOME_PAY_GHC = $GROSS_SALARY_GHC - $SSNIT_5_5_PERCENT - $TOTAL_TAXATION_GHC - $PETRA;
                }

                $BANK                         = trim((string)($row[26] ?? '')) ?: null;
                $ACCOUNT_NUMBER               = trim((string)($row[27] ?? '')) ?: null;
                $BRANCH                       = trim((string)($row[28] ?? '')) ?: null;
                $SSNIT                        = trim((string)($row[29] ?? '')) ?: null;
                $GH_CARD_NUMBER               = trim((string)($row[30] ?? '')) ?: null;

                if (empty($STAFF_ID)) {
                    $errors[] = "Row " . ($index + 2) . ": Staff ID cannot be empty.";
                    continue;
                }
                if (empty($NAME)) {
                    $errors[] = "Row " . ($index + 2) . ": Name cannot be empty.";
                    continue;
                }
                if (is_null($TAKE_HOME_PAY_GHC)) {
                    $errors[] = "Row " . ($index + 2) . ": Take Home Pay must be a valid number.";
                    continue;
                }

                if (in_array($STAFF_ID, $existingStaffIds) || in_array($STAFF_ID, $duplicateStaffIds)) {
                    $duplicateStaffIds[] = $STAFF_ID;
                    continue;
                }

                $data = [
                    'STAFF_ID'                  => $STAFF_ID,
                    'NAME'                      => $NAME,
                    'POSITION'                  => $POSITION,
                    'BASE_SALARY_GHC'           => $BASE_SALARY_GHC,
                    'BACKPAY'                   => $BACKPAY,
                    'TRANSPORT_ALLOWANCE_GHC'   => $TRANSPORT_ALLOWANCE_GHC,
                    'ADDITIONAL_ALLOWANCE_GHC'  => $ADDITIONAL_ALLOWANCE_GHC,
                    'Rent'                      => $Rent,
                    'tmporary_ALLOWANCE_GHC'    => $tmporary_ALLOWANCE_GHC,
                    'OVERTIME_GHC'              => $OVERTIME_GHC,
                    'BONUS_EARNINGS_ALLOWANCE'  => $BONUS_EARNINGS_ALLOWANCE,
                    'GROSS_SALARY_GHC'          => $GROSS_SALARY_GHC,
                    'MANAGEMENT_FEE_5_PERCENT'  => $MANAGEMENT_FEE_5_PERCENT,
                    'SSNIT_ER_13_PERCENT'       => $SSNIT_ER_13_PERCENT,
                    'GETFUND_2_5_PERCENT'       => $GETFUND_2_5_PERCENT,
                    'NHIL_2_5_PERCENT'          => $NHIL_2_5_PERCENT,
                    'VAT_15_PERCENT'            => $VAT_15_PERCENT,
                    'COST_TO_BUNGE_GHC'         => $COST_TO_BUNGE_GHC,
                    'gross2'                    => $gross2,
                    'SSNIT_5_5_PERCENT'         => $SSNIT_5_5_PERCENT,
                    'PETRA'                     => $PETRA,
                    'TAXABLE_INCOME_GHC'        => $TAXABLE_INCOME_GHC,
                    'PAYE_GHC'                  => $PAYE_GHC,
                    'BONUS_TAX'                 => $BONUS_TAX,
                    'TOTAL_TAXATION_GHC'        => $TOTAL_TAXATION_GHC,
                    'TAKE_HOME_PAY_GHC'         => $TAKE_HOME_PAY_GHC,
                    'BANK'                      => $BANK,
                    'ACCOUNT_NUMBER'            => $ACCOUNT_NUMBER,
                    'BRANCH'                    => $BRANCH,
                    'SSNIT'                     => $SSNIT,
                    'GH_CARD_NUMBER'            => $GH_CARD_NUMBER,
                ];

                $validRecords[] = $data;
                $existingStaffIds[] = $STAFF_ID;
            }

            if (!empty($errors)) {
                Log::error("Upload errors: " . implode(' | ', $errors));
                $errorMessage = count($errors) > 3
                ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
                : implode(' | ', $errors);
                return redirect()->route('bungepay.index', ['month' => $selectedMonth])
                ->with('error', $errorMessage);
            }

            if (!empty($duplicateStaffIds)) {
                Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
                return redirect()->route('bungepay.index', ['month' => $selectedMonth])
                ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
            }

            if (!empty($validRecords)) {
                DB::table($tableName)->insert($validRecords);
                Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
            } else {
                Log::warning("Upload: No valid records to insert into {$tableName}");
            }

            return redirect()->route('bungepay.index', ['month' => $selectedMonth])
            ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
        } catch (\Exception $e) {
            Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('bungepay.index', ['month' => $selectedMonth])
            ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
        }
    }

    public function delete(Request $request)
    {
        $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
        $date = Carbon::createFromFormat('Y-m', $selectedMonth);
        $tableName = $this->resolveTableName($date);

        try {
            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                DB::table($tableName)->truncate();
                Log::info("Delete: Truncated table {$tableName}");
                return redirect()->route('bungepay.index', ['month' => $selectedMonth])
                ->with('success', 'Records deleted successfully.');
            } else {
                Log::warning("Delete: Table {$tableName} does not exist");
                return redirect()->route('bungepay.index', ['month' => $selectedMonth])
                ->with('error', 'No records found for the selected month.');
            }
        } catch (\Exception $e) {
            Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return redirect()->route('bungepay.index', ['month' => $selectedMonth])
            ->with('error', 'Failed to delete records: ' . $e->getMessage());
        }
    }

 public function downloadTemplate()
 {
 $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

 $headers = [
    'STAFF ID','NAME','POSITION',
    'BASE SALARY (GHC)',
    'BACKPAY','TRANSPORT ALLOWANCE (GHC)','ADDITIONAL ALLOWANCE (GHC)','Rent','Temporary ALLOWANCE (GHC)',
    'OVERTIME (GHC)','BONUS/EARNINGS ALLOWANCE',
    'GROSS SALARY (GHC)',
    'MANAGEMENT FEE 5%','SSNIT ER 13%','GETFUND 2.5%','NHIL 2.5%','VAT 15%','COST TO BUNGE (GHC)',
    'GROSS (REPEAT)',
    'SSNIT EE 5.5%','PETRA','TAXABLE INCOME (GHC)','PAYE (GHC)','BONUS TAX','TOTAL TAXATION (GHC)',
    'TAKE HOME PAY (GHC)',
    'BANK','ACCOUNT NUMBER','BRANCH','SSNIT','GH CARD NUMBER'
];

$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:AB1')->getFont()->setBold(true);  // 28 columns = A to AB



 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'bunge_template.xlsx';
 $tempFile = tempnam(sys_get_temp_dir(), $fileName);
 $writer->save($tempFile);

 return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
 }
}
