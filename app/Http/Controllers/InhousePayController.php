<?php

namespace App\Http\Controllers;

use App\Models\Inhousepay;
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

class InhousePayController extends Controller
{
 public function index(Request $request)
 {
 $request->validate([
 'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
 ]);

 $month = $request->input('month', now()->format('Y-m'));
 Log::info("Index: Received month: {$month}");

 $date = Carbon::createFromFormat('Y-m', $month);
 $tableName = 'inhouse' . strtolower($date->format('F')) . $date->format('y') . 'pay';
 Log::info("Index: Generated table name: {$tableName}");

 if (!DB::getSchemaBuilder()->hasTable($tableName)) {
 Log::warning("Index: Table {$tableName} does not exist");
 return Inertia::render('Inhousepay/Index', [
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
 ->leftJoin('bankdetails as bd', 'tp.id', '=', 'bd.employid')
 ->select(
 'tp.id',
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
 $q->where("tp.id", 'like', "%{$search}%")
 ->orWhere("tp.name", 'like', "%{$search}%")
 ->orWhere('bd.bank', 'like', "%{$search}%");
 });
 }

 // Apply sorting
 $sort = $request->input('sort', 'id');
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

 return Inertia::render('Inhousepay/Index', [
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
        $tableName = 'inhouse' . strtolower($date->format('F')) . $date->format('y') . 'pay';

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
        }

        $tableColumns = DB::getSchemaBuilder()->getColumnListing($tableName);
        $commEarnedExpr = in_array('commission_earned', $tableColumns) ? 'tp.commission_earned' : (in_array('bonus', $tableColumns) ? 'tp.bonus' : 'NULL');
        $commTaxExpr    = in_array('commission_tax', $tableColumns) ? 'tp.commission_tax' : (in_array('bonustax', $tableColumns) ? 'tp.bonustax' : 'NULL');

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('bankdetails as bd', 'tp.id', '=', 'bd.employid')
            ->select(
                'tp.id as staff_id',
                'tp.name as employee_name',
                'bd.shop',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'tp.netpay as net_pay',
                DB::raw("{$commEarnedExpr} as commission_earned"),
                DB::raw("{$commTaxExpr} as commission_tax")
            );

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.id', 'like', "%{$search}%")
                  ->orWhere('tp.name', 'like', "%{$search}%")
                  ->orWhere('bd.shop', 'like', "%{$search}%")
                  ->orWhere('bd.bank', 'like', "%{$search}%");
            });
        }

        // Sorting – fully compatible with frontend
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'id'                => 'tp.id',
            'staff_id'          => 'tp.id',
            'name'              => 'tp.name',
            'employee_name'     => 'tp.name',
            'shop'              => 'bd.shop',
            'bank'              => 'bd.bank',
            'account'           => 'bd.account',
            'code'              => 'bd.code',
            'branch'            => 'bd.branch',
            'netpay'            => 'tp.netpay',
            'net_pay'           => 'tp.netpay',
            'commission_earned' => DB::raw($commEarnedExpr),
            'commission_tax'    => DB::raw($commTaxExpr),
        ];

        $query->orderBy($sortMap[$sort] ?? 'tp.id', $order);

        $records = $query->get();

        // Generate Real Excel File
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'Staff ID',
            'Employee Name',
            'Department/Shop',
            'Bank Name',
            'Account Number',
            'Branch Code',
            'Branch',
            'Final Net Pay (GHS)',
            'Commission Earned',
            'Commission Tax'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($records as $r) {
            $sheet->setCellValue("A{$row}", $r->staff_id);
            $sheet->setCellValue("B{$row}", $r->employee_name);
            $sheet->setCellValue("C{$row}", $r->shop ?? '');
            $sheet->setCellValue("D{$row}", $r->bank ?? '');
            $sheet->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("F{$row}", $r->code ?? '', DataType::TYPE_STRING);
            $sheet->setCellValue("G{$row}", $r->branch ?? '');
            $sheet->setCellValue("H{$row}", $r->net_pay ? number_format((float)$r->net_pay, 2, '.', '') : '');
            $sheet->setCellValue("I{$row}", $r->commission_earned ? number_format((float)$r->commission_earned, 2, '.', '') : '');
            $sheet->setCellValue("J{$row}", $r->commission_tax ? number_format((float)$r->commission_tax, 2, '.', '') : '');
            $row++;
        }

        // Professional Styling – same as ALL your exports
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $headerStyle = $sheet->getStyle('A1:J1');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('BBDEFE'); // Light blue header

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = "inhouse_payroll_{$month}.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);

    } catch (\Exception $e) {
        Log::error("InhousePay export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
        return response()->json(['error' => 'Export failed. Please try again.'], 500);
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
 $tableName = 'inhouse' . strtolower($date->format('F')) . $date->format('y') . 'pay';

        // Create table if it doesn't exist
        InhousePay::createTableIfNotExists($tableName);

        $file = $request->file('file');
        $reader = new XlsxReader();
        $spreadsheet = $reader->load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        $headerRow = array_shift($rows); // Remove header row

        // Check for existing staff IDs
        $existingStaffIds = DB::table($tableName)->pluck('id')->toArray();
        $duplicateStaffIds = [];
        $validRecords = [];
        $errors = [];

        // Build dynamic column map from header row
        $norm = function ($text) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', (string)$text)));
        };

        $colMap = [];
        if (!empty($headerRow)) {
            foreach ($headerRow as $cIdx => $val) {
                $h = $norm($val);
                if (empty($h)) continue;
                if (in_array($h, ['emid', 'staffid', 'id']) && !isset($colMap['id'])) $colMap['id'] = $cIdx;
                elseif (in_array($h, ['name', 'staffname', 'fullname', 'employeename']) && !isset($colMap['name'])) $colMap['name'] = $cIdx;
                elseif (in_array($h, ['position', 'designation', 'jobtitle']) && !isset($colMap['position'])) $colMap['position'] = $cIdx;
                elseif (in_array($h, ['ghcardno', 'ghanacard', 'ghcard', 'ghcardnumber']) && !isset($colMap['ghcard'])) $colMap['ghcard'] = $cIdx;
                elseif (in_array($h, ['ssnitnumber', 'ssnitno', 'ssnit']) && !isset($colMap['ssnit'])) $colMap['ssnit'] = $cIdx;
                elseif (in_array($h, ['datejoined', 'datejoin', 'doj']) && !isset($colMap['datejoin'])) $colMap['datejoin'] = $cIdx;
                elseif (in_array($h, ['acnumber', 'accountnumber', 'alcnum', 'accno', 'accnum', 'accountno']) && !isset($colMap['alcnum'])) $colMap['alcnum'] = $cIdx;
                elseif (in_array($h, ['bankname', 'bank']) && !isset($colMap['bankname'])) $colMap['bankname'] = $cIdx;
                elseif (in_array($h, ['branch']) && !isset($colMap['branch'])) $colMap['branch'] = $cIdx;
                elseif (in_array($h, ['legalworkingdays', 'legalworkdays', 'legalwork']) && !isset($colMap['legalwork'])) $colMap['legalwork'] = $cIdx;
                elseif (in_array($h, ['actualworkingdaysforworkattendance', 'actualworkingdays', 'actualdaysworked', 'actualwork']) && !isset($colMap['actualwork'])) $colMap['actualwork'] = $cIdx;
                elseif (in_array($h, ['basicsalary', 'basic']) && !isset($colMap['basic'])) $colMap['basic'] = $cIdx;
                elseif (in_array($h, ['actualbasicsalary', 'actualbasic']) && !isset($colMap['actualbasic'])) $colMap['actualbasic'] = $cIdx;
                elseif (in_array($h, ['basickpi', 'kpitarget', 'kpi']) && !isset($colMap['kpi'])) $colMap['kpi'] = $cIdx;
                elseif (in_array($h, ['coefficient']) && !isset($colMap['coefficient'])) $colMap['coefficient'] = $cIdx;
                elseif (in_array($h, ['monthlykpibonus', 'monthlykpi']) && !isset($colMap['monthlykpi'])) $colMap['monthlykpi'] = $cIdx;
                elseif (in_array($h, ['overtimeallowance', 'overtime']) && !isset($colMap['overtime'])) $colMap['overtime'] = $cIdx;
                elseif (in_array($h, ['transportsubsidy', 'transport']) && !isset($colMap['transport'])) $colMap['transport'] = $cIdx;
                elseif (in_array($h, ['leave', 'leavepay', 'leave1']) && !isset($colMap['leave1'])) $colMap['leave1'] = $cIdx;
                elseif (in_array($h, ['grosssalary', 'grossearnings', 'total', 'gross']) && !isset($colMap['total'])) $colMap['total'] = $cIdx;
                elseif (in_array($h, ['ssniter']) && !isset($colMap['ssniter'])) $colMap['ssniter'] = $cIdx;
                elseif (in_array($h, ['ssnitee', 'ssnitpay', 'ssnit5']) && !isset($colMap['ssnitpay'])) $colMap['ssnitpay'] = $cIdx;
                elseif (in_array($h, ['petra']) && !isset($colMap['petra'])) $colMap['petra'] = $cIdx;
                elseif (in_array($h, ['taxableincome', 'taxincome']) && !isset($colMap['taxincome'])) $colMap['taxincome'] = $cIdx;
                elseif (in_array($h, ['paye']) && !isset($colMap['paye'])) $colMap['paye'] = $cIdx;
                elseif (in_array($h, ['overtimetax']) && !isset($colMap['overtimetax'])) $colMap['overtimetax'] = $cIdx;
                elseif (in_array($h, ['totaltaxation', 'totaltax']) && !isset($colMap['totaltax'])) $colMap['totaltax'] = $cIdx;
                elseif (in_array($h, ['totaldeductions', 'totaldeduction']) && !isset($colMap['totaldeduction'])) $colMap['totaldeduction'] = $cIdx;
                elseif (in_array($h, ['netsalary', 'netpaybefore13th', 'netpay1']) && !isset($colMap['netpay1'])) $colMap['netpay1'] = $cIdx;
                elseif (in_array($h, ['commissionbonusearned', 'commissionearned', 'bonusearned', 'commissionbonus', 'bonus']) && !isset($colMap['commission_earned'])) $colMap['commission_earned'] = $cIdx;
                elseif (in_array($h, ['commissionbonustax', 'commissiontax', 'bonustax']) && !isset($colMap['commission_tax'])) $colMap['commission_tax'] = $cIdx;
                elseif (in_array($h, ['commissionbonusfinal', 'commissionfinal', 'commissiionbonusfinal', 'bonusfinal']) && !isset($colMap['commission_final'])) $colMap['commission_final'] = $cIdx;
                elseif (in_array($h, ['noofmonthsworked', '13thmonthdays', 'numdays']) && !isset($colMap['numdays'])) $colMap['numdays'] = $cIdx;
                elseif (in_array($h, ['13thmonth', '13thmonthpay', 'endyearmonth']) && !isset($colMap['endyearmonth'])) $colMap['endyearmonth'] = $cIdx;
                elseif (in_array($h, ['13thtax', '13thmonthtax', 'endyeartax']) && !isset($colMap['endyeartax'])) $colMap['endyeartax'] = $cIdx;
                elseif (in_array($h, ['13thmonthpayable', '13thmonthnet', 'endyearpay']) && !isset($colMap['endyearpay'])) $colMap['endyearpay'] = $cIdx;
                elseif (in_array($h, ['loans', 'loan']) && !isset($colMap['loans'])) $colMap['loans'] = $cIdx;
                elseif (in_array($h, ['actualnet', 'finalnetpay', 'netpay']) && !isset($colMap['netpay'])) $colMap['netpay'] = $cIdx;
            }
        }

        // Determine positional fallback if header mapping is incomplete
        $firstHeaderNorm = !empty($headerRow) ? $norm($headerRow[0] ?? '') : '';
        $is39Format = ($firstHeaderNorm === 'sn' || (isset($headerRow) && count($headerRow) >= 39));

        $getVal = function ($row, $field, $fallbackIdx39, $fallbackIdx37) use ($colMap, $is39Format) {
            $idx = $colMap[$field] ?? ($is39Format ? $fallbackIdx39 : $fallbackIdx37);
            return $row[$idx] ?? null;
        };

        $tableColumns = DB::getSchemaBuilder()->getColumnListing($tableName);

        foreach ($rows as $index => $row) {
            if (empty(array_filter($row, fn($cell) => $cell !== null && trim((string)$cell) !== ''))) {
                continue; // Skip blank rows
            }

            if (count($row) < 34) {
                $errors[] = "Row " . ($index + 2) . ": Expected at least 34 columns, found " . count($row);
                continue;
            }

            $id         = trim((string)$getVal($row, 'id', 1, 0)) ?: null;
            $name       = trim((string)$getVal($row, 'name', 2, 1)) ?: null;
            $position   = trim((string)$getVal($row, 'position', 3, 2)) ?: null;
            $datejoin   = trim((string)$getVal($row, 'datejoin', 4, 5)) ?: null;
            $ssnit      = trim((string)$getVal($row, 'ssnit', 5, 4)) ?: null;
            $ghcard     = trim((string)$getVal($row, 'ghcard', 6, 3)) ?: null;
            $bankname   = trim((string)$getVal($row, 'bankname', 7, 7)) ?: null;
            $alcnum     = trim((string)$getVal($row, 'alcnum', 8, 6)) ?: null;
            $branch     = trim((string)$getVal($row, 'branch', 9, 8)) ?: null;

            $legalworkRaw  = $getVal($row, 'legalwork', 10, 9);
            $actualworkRaw = $getVal($row, 'actualwork', 11, 10);
            $legalwork     = is_numeric($legalworkRaw) ? (int)$legalworkRaw : null;
            $actualwork    = is_numeric($actualworkRaw) ? (float)$actualworkRaw : null;

            $basicRaw       = $getVal($row, 'basic', 12, 11);
            $actualbasicRaw = $getVal($row, 'actualbasic', 13, 12);
            $kpiRaw         = $getVal($row, 'kpi', 14, 13);
            $coefficientRaw = $getVal($row, 'coefficient', 15, 14);
            $monthlykpiRaw  = $getVal($row, 'monthlykpi', 16, 15);
            $overtimeRaw    = $getVal($row, 'overtime', 17, 16);
            $transportRaw   = $getVal($row, 'transport', 18, 17);
            $leave1Raw      = $getVal($row, 'leave1', 19, 18);
            $totalRaw       = $getVal($row, 'total', 20, 19);

            $basic          = is_numeric($basicRaw) ? (float)$basicRaw : null;
            $actualbasic    = is_numeric($actualbasicRaw) ? (float)$actualbasicRaw : null;
            $kpi            = is_numeric($kpiRaw) ? (float)$kpiRaw : null;
            $coefficient    = is_numeric($coefficientRaw) ? (float)$coefficientRaw : null;
            $monthlykpi     = is_numeric($monthlykpiRaw) ? (float)$monthlykpiRaw : null;
            $overtime       = is_numeric($overtimeRaw) ? (float)$overtimeRaw : null;
            $transport      = is_numeric($transportRaw) ? (float)$transportRaw : null;
            $leave1         = is_numeric($leave1Raw) ? (float)$leave1Raw : null;
            $total          = is_numeric($totalRaw) ? (float)$totalRaw : null;

            $ssniterRaw     = $getVal($row, 'ssniter', 21, 20);
            $ssnitpayRaw    = $getVal($row, 'ssnitpay', 22, 21);
            $petraRaw       = $getVal($row, 'petra', 23, null);
            $taxincomeRaw   = $getVal($row, 'taxincome', 24, 22);
            $payeRaw        = $getVal($row, 'paye', 25, 23);
            $overtimetaxRaw = $getVal($row, 'overtimetax', 26, 24);
            $totaltaxRaw    = $getVal($row, 'totaltax', 27, 25);
            $totaldeductRaw = $getVal($row, 'totaldeduction', 28, 26);
            $netpay1Raw     = $getVal($row, 'netpay1', 29, 27);

            $ssniter        = is_numeric($ssniterRaw) ? (float)$ssniterRaw : null;
            $ssnitpay       = is_numeric($ssnitpayRaw) ? (float)$ssnitpayRaw : null;
            $petra          = is_numeric($petraRaw) ? (float)$petraRaw : null;
            $taxincome      = is_numeric($taxincomeRaw) ? (float)$taxincomeRaw : null;
            $paye           = is_numeric($payeRaw) ? (float)$payeRaw : null;
            $overtimetax    = is_numeric($overtimetaxRaw) ? (float)$overtimetaxRaw : null;
            $totaltax       = is_numeric($totaltaxRaw) ? (float)$totaltaxRaw : null;
            $totaldeduction = is_numeric($totaldeductRaw) ? (float)$totaldeductRaw : null;
            $netpay1        = is_numeric($netpay1Raw) ? (float)$netpay1Raw : null;

            // Commission / Bonus fields
            $commEarnedRaw  = $getVal($row, 'commission_earned', 30, 28);
            $commTaxRaw     = $getVal($row, 'commission_tax', 31, 29);
            $commFinalRaw   = $getVal($row, 'commission_final', 32, 30);
            $commEarned     = is_numeric($commEarnedRaw) ? (float)$commEarnedRaw : null;
            $commTax        = is_numeric($commTaxRaw) ? (float)$commTaxRaw : null;
            $commFinal      = is_numeric($commFinalRaw) ? (float)$commFinalRaw : null;

            $numdaysRaw     = $getVal($row, 'numdays', 33, 31);
            $thmonthRaw     = $getVal($row, 'endyearmonth', 34, 32);
            $thtaxRaw       = $getVal($row, 'endyeartax', 35, 33);
            $thpayRaw       = $getVal($row, 'endyearpay', 36, 34);
            $loansRaw       = $getVal($row, 'loans', 37, 35);

            $numdays        = is_numeric($numdaysRaw) ? (int)$numdaysRaw : null;
            $thmonth        = is_numeric($thmonthRaw) ? (float)$thmonthRaw : null;
            $thtax          = is_numeric($thtaxRaw) ? (float)$thtaxRaw : null;
            $thpay          = is_numeric($thpayRaw) ? (float)$thpayRaw : null;
            $loans          = is_numeric($loansRaw) ? (float)$loansRaw : null;

            // Final Net Pay
            $rawNet         = trim((string)$getVal($row, 'netpay', 38, 36));
            $cleanNet       = preg_replace('/[^0-9.-]/', '', $rawNet);
            $netpay         = is_numeric($cleanNet) ? (float)$cleanNet : null;

            // Validation
            if (empty($id)) {
                $errors[] = "Row " . ($index + 2) . ": Staff ID cannot be empty.";
                continue;
            }
            if (empty($name)) {
                $errors[] = "Row " . ($index + 2) . ": Name cannot be empty.";
                continue;
            }
            if (is_null($netpay)) {
                $errors[] = "Row " . ($index + 2) . ": Net Pay must be a valid number (found: '{$rawNet}').";
                continue;
            }

            if (in_array($id, $existingStaffIds) || in_array($id, $duplicateStaffIds)) {
                $duplicateStaffIds[] = $id;
                continue;
            }

            $record = [
                'id'                => $id,
                'name'              => $name,
                'position'          => $position,
                'ghcard'            => $ghcard,
                'ssnit'             => $ssnit,
                'datejoin'          => $datejoin,
                'alcnum'            => $alcnum,
                'bankname'          => $bankname,
                'branch'            => $branch,
                'legalwork'         => $legalwork,
                'actualwork'        => $actualwork,
                'basic'             => $basic,
                'actualbasic'       => $actualbasic,
                'kpi'               => $kpi,
                'coefficient'       => $coefficient,
                'monthlykpi'        => $monthlykpi,
                'overtime'          => $overtime,
                'transport'         => $transport,
                'leave1'            => $leave1,
                'total'             => $total,
                'ssniter'           => $ssniter,
                'ssnitpay'          => $ssnitpay,
                'petra'             => $petra,
                'taxincome'         => $taxincome,
                'paye'              => $paye,
                'overtimetax'       => $overtimetax,
                'totaltax'          => $totaltax,
                'totaldeduction'    => $totaldeduction,
                'netpay1'           => $netpay1,
                'bonus'             => $commEarned,
                'bonustax'          => $commTax,
                'bonusfinal'        => $commFinal,
                'commission_earned' => $commEarned,
                'commission_tax'    => $commTax,
                'commission_final'  => $commFinal,
                'numdays'           => $numdays,
                'endyearmonth'      => $thmonth,
                'endyeartax'        => $thtax,
                'endyearpay'        => $thpay,
                'loans'             => $loans,
                'netpay'            => $netpay,
            ];

            // Filter columns against database schema to avoid unknown column errors
            $validRecords[] = array_intersect_key($record, array_flip($tableColumns));
            $existingStaffIds[] = $id;
        }

 if (!empty($errors)) {
 Log::error("Upload errors: " . implode(' | ', $errors));
 $errorMessage = count($errors) > 3
 ? implode(' | ', array_slice($errors, 0, 3)) . ' | ... and ' . (count($errors) - 3) . ' more errors.'
 : implode(' | ', $errors);
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('error', $errorMessage);
 }

 if (!empty($duplicateStaffIds)) {
 Log::error("Duplicate Staff IDs: " . implode(', ', array_unique($duplicateStaffIds)));
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('error', 'Duplicate Staff IDs found: ' . implode(', ', array_unique($duplicateStaffIds)));
 }

 if (!empty($validRecords)) {
 DB::table($tableName)->insert($validRecords);
 Log::info("Upload: Inserted " . count($validRecords) . " records into {$tableName}");
 } else {
 Log::warning("Upload: No valid records to insert into {$tableName}");
 }

 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('success', 'Successfully uploaded ' . count($validRecords) . ' record(s).');
 } catch (\Exception $e) {
 Log::error('File upload failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to process the uploaded file: ' . $e->getMessage());
 }
 }

 public function delete(Request $request)
 {
 $selectedMonth = $request->input('month', session('selected_month', now()->format('Y-m')));
 $date = Carbon::createFromFormat('Y-m', $selectedMonth);
 $tableName = 'inhouse' . strtolower($date->format('F')) . $date->format('y') . 'pay';

 try {
 if (DB::getSchemaBuilder()->hasTable($tableName)) {
 DB::table($tableName)->truncate();
 Log::info("Delete: Truncated table {$tableName}");
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('success', 'Records deleted successfully.');
 } else {
 Log::warning("Delete: Table {$tableName} does not exist");
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('error', 'No records found for the selected month.');
 }
 } catch (\Exception $e) {
 Log::error('Delete records failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
 return redirect()->route('inhousepay.index', ['month' => $selectedMonth])
 ->with('error', 'Failed to delete records: ' . $e->getMessage());
 }
 }

 public function downloadTemplate()
 {
 $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
 $sheet = $spreadsheet->getActiveSheet();

        // Set headers matching official inhouse template
        $headers = [
            'S/N',
            'EM ID',
            'NAME',
            'POSITION',
            'DATE JOINED',
            'SSNIT NUMBER',
            'GH-CARD NO',
            'BANK NAME',
            'A/C NUMBER',
            'BRANCH',
            'Legal Working Days',
            'Actual working days for work (Attendance)',
            'Basic Salary',
            'Actual Basic Salary',
            'Basic KPI',
            'Coefficient',
            'Monthly KPI Bonus',
            'Overtime Allowance',
            'Transport Subsidy',
            'LEAVE',
            'Gross Salary',
            'SSNIT ER',
            'SSNIT EE',
            'PETRA',
            'TAXABLE INCOME',
            'PAYE',
            'OVERTIME TAX',
            'TOTAL TAXATION',
            'TOTAL DEDUCTIONS',
            'NET SALARY',
            'COMMISSION/ BONUS EARNED',
            'COMMISSION/ BONUS TAX',
            'COMMISSION/ BONUS FINAL',
            'NO OF MONTHS WORKED',
            '13TH MONTH',
            '13TH TAX',
            '13TH MONTH PAYABLE',
            'LOANS',
            'ACTUAL NET',
        ];

        $nullRow = null;
        \App\Models\PayrollColumn::augmentTemplate($headers, $nullRow, 'inhouse');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:AM1')->getFont()->setBold(true);





 $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
 $fileName = 'inhouse_template.xlsx';
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
