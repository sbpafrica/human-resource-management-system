<?php

namespace App\Http\Controllers\SalaryPayment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use App\Models\MelcomSalary;

class MelcomSalaryPaymentController extends Controller
{
    private $shortName = 'Melcom';
    private $color = '#e11d48';

    private function headers(): array
    {
        return [
            'employ', 'empoyeename', 'outsourcename', 'designation', 'joindate', 'shopname', 'category',
            'saturdayot', 'actualdays', 'night', 'halfdays', 'satoffdays', 'sunoffdays', 'leavededuct',
            'absentdays', 'absenthrs', 'holiday', 'weekdaysOT', 'weekendOT', 'holidayot', 'basic',
            'absentwithoutpay', 'basicentitlement', 'transport', 'normalovertime', 'saturdayovertime',
            'holidayotamt', 'gross', 'ssnit', 'taxable', 'paye', 'totalovertime', 'overtimetax',
            'totaltax', 'trust', 'totaldeduction', 'banktransfer'
        ];
    }

  public function index(Request $request)
{
    $month = $request->input('month', now()->format('Y-m'));
    $model = MelcomSalary::forMonth($month);
    $tableName = $model->getTable(); // melcomnovember25

    if (!Schema::hasTable($tableName)) {
        return Inertia::render('Payroll/SalaryPayment/Melcom', [
            'client' => ['short_name' => $this->shortName, 'color' => $this->color],
            'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50),
            'selectedMonth' => $month,
            'formattedMonth' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
            'totalRecords' => 0,
            'searchCount' => null,
            'query' => $request->only(['search', 'sort', 'order']),
        ]);
    }

    // CONSISTENT CACHE KEY — THIS IS THE GOLDEN LINE
    $totalRecords = cache()->remember("melcom_total_{$month}", now()->addDays(1), fn() =>
        DB::table($tableName)->count()
    );

    $query = DB::table("{$tableName} as ms")
        ->leftJoin('bankdetails as bd', 'ms.employ', '=', 'bd.employid')
        ->select(
            'ms.employ as staffida',
            'ms.empoyeename as name',
            'bd.shop',
            'bd.bank',
            'bd.account',
            'bd.code',
            'bd.branch',
            DB::raw('COALESCE(CAST(ms.banktransfer AS DECIMAL(12,2)), 0) as netpay')
        );

    if ($search = $request->input('search')) {
        $like = "%{$search}%";
        $query->where(function ($q) use ($like) {
            $q->where('ms.employ', 'like', $like)
              ->orWhere('ms.empoyeename', 'like', $like)
              ->orWhere('bd.shop', 'like', $like)
              ->orWhere('bd.bank', 'like', $like)
              ->orWhere('bd.account', 'like', $like);
        });
    }

    $sort = $request->input('sort', 'staffida');
    $order = $request->input('order', 'asc') === 'desc' ? 'desc' : 'asc';

    $sortColumn = match ($sort) {
        'staffida' => 'ms.employ',
        'name'     => 'ms.empoyeename',
        'shop'     => 'bd.shop',
        'bank'     => 'bd.bank',
        'netpay'   => 'ms.banktransfer',
        default    => 'ms.employ',
    };

    $query->orderBy($sortColumn, $order);

    $records = $query->paginate(50)->appends($request->query());

    return Inertia::render('Payroll/SalaryPayment/Melcom', [
        'client'         => ['short_name' => $this->shortName, 'color' => $this->color],
        'records'        => $records,
        'selectedMonth'  => $month,
        'formattedMonth' => Carbon::createFromFormat('Y-m', $month)->format('F Y'),
        'totalRecords'   => $totalRecords,
        'searchCount'    => $search ? $records->total() : null,
        'query'          => $request->only(['search', 'sort', 'order']),
    ]);
}

    public function export(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $tableName = MelcomSalary::forMonth($month)->getTable();

        if (!Schema::hasTable($tableName)) {
            return response()->json(['error' => 'No data for this month'], 404);
        }

        $records = DB::table("{$tableName} as ms")
            ->leftJoin('bankdetails as bd', 'ms.employ', '=', 'bd.employid')
            ->select(
                'ms.employ as staffida',
                'ms.empoyeename as name',
                'bd.shop',
                'bd.bank',
                'bd.account',
                'bd.code',
                'bd.branch',
                'ms.banktransfer as netpay'
            )
            ->when($request->search, function ($q, $search) {
                return $q->where('ms.employ', 'like', "%{$search}%")
                         ->orWhere('ms.empoyeename', 'like', "%{$search}%");
            })
            ->orderBy($request->input('sort', 'staffida'), $request->input('order', 'asc') === 'desc' ? 'desc' : 'asc')
            ->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Staff ID', 'Name', 'Department', 'Bank', 'Account', 'Branch Code', 'Branch', 'Net Pay'], null, 'A1');

        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'e11d48']],
        ]);

        foreach ($records as $i => $r) {
            $row = $i + 2;
            $sheet->setCellValue("A{$row}", $r->staffida)
                  ->setCellValue("B{$row}", $r->name)
                  ->setCellValue("C{$row}", $r->shop ?? 'N/A')
                  ->setCellValue("D{$row}", $r->bank ?? 'N/A')
                  ->setCellValueExplicit("E{$row}", $r->account ?? '', DataType::TYPE_STRING)
                  ->setCellValue("F{$row}", $r->code ?? 'N/A')
                  ->setCellValue("G{$row}", $r->branch ?? 'N/A')
                  ->setCellValue("H{$row}", $r->netpay ? number_format((float)$r->netpay, 2, '.', '') : '0.00');
        }

        foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = new Xlsx($spreadsheet);
        $fileName = "Melcom_{$month}_Salary.xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file'  => 'required|file|extensions:xlsx,xls,csv,ods|max:51200',
            'month' => 'required|date_format:Y-m',
        ]);

        $month     = $request->input('month');
        $model     = MelcomSalary::forMonth($month);
        $tableName = $model->getTable(); // → melcomnovember25

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $rows        = $spreadsheet->getActiveSheet()->toArray();

        if (count($rows) <= 1) {
            return back()->with('error', 'Excel file is empty');
        }

        $uploadedHeaders = array_map('trim', array_shift($rows));
        $expectedHeaders = $this->headers();

        if ($uploadedHeaders !== $expectedHeaders) {
            return back()->with('error', 'Wrong template! Download the latest Melcom template.');
        }

        $existingIds = DB::table($tableName)->pluck('employ')->toArray();
        $toInsert    = [];
        $errors      = [];

        foreach ($rows as $i => $row) {
            $rowNum = $i + 2;
            $data   = array_combine($expectedHeaders, $row);

            $employId   = trim($data['employ'] ?? '');
            $name       = trim($data['empoyeename'] ?? '');
            $netpayRaw  = preg_replace('/[^\d.]/', '', $data['banktransfer'] ?? '');
            $netpay     = is_numeric($netpayRaw) ? (float)$netpayRaw : null;

            if (!$employId) $errors[] = "Row {$rowNum}: Staff ID missing";
            if (!$name)     $errors[] = "Row {$rowNum}: Name missing";
            if (is_null($netpay)) $errors[] = "Row {$rowNum}: Invalid banktransfer amount";

            if (in_array($employId, $existingIds) || collect($toInsert)->pluck('employ')->contains($employId)) {
                continue; // skip duplicates
            }

            $data['banktransfer'] = $netpay;
            $toInsert[] = $data;
        }

        if ($errors) {
            $msg = count($errors) > 8 ? implode(' | ', array_slice($errors, 0, 8)) . ' | ...' : implode(' | ', $errors);
            return back()->with('error', $msg);
        }

        if ($toInsert) {
            // CHUNKED INSERT — NO MORE "too many placeholders" ERROR
            collect($toInsert)->chunk(200)->each(function ($chunk) use ($tableName) {
                DB::table($tableName)->insert($chunk->toArray());
            });
        }

        return back()->with('success', "Melcom: Successfully uploaded " . count($toInsert) . " records!");
    }

    public function delete(Request $request)
    {
        $month     = $request->input('month', now()->format('Y-m'));
        $tableName = MelcomSalary::forMonth($month)->getTable();

        if (Schema::hasTable($tableName)) {
            DB::table($tableName)->truncate();
            return back()->with('success', 'Melcom data deleted for ' . Carbon::parse($month)->format('F Y'));
        }

        return back()->with('error', 'No data found for this month.');
    }

    public function downloadTemplate()
    {
        $headers = $this->headers();
        $example = array_fill(0, count($headers), '');
        $example[0] = 'SBP1001';
        $example[1] = 'Kwame Nkrumah';
        $example[array_key_last($example)] = '15234.50';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([$example], null, 'A2');

        $sheet->getStyle('A1:' . $sheet->getHighestColumn() . '1')->applyFromArray([
            'font'  => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'  => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'e11d48']],
        ]);

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = "Melcom_Payroll_Template_" . now()->format('M_Y') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName);
    }
}
