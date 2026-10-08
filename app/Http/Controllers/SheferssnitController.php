<?php

namespace App\Http\Controllers;

use App\Models\sheferpay;
use App\Models\Ssnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SheferssnitController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        Log::info("SheferssnitPay Index: Received month: {$month}");

        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'shefer' . strtolower($date->format('F')) . $date->format('y');
        Log::info("SheferssnitPay Index: Generated table name: {$tableName}");

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            Log::warning("SheferssnitPay Index: Table {$tableName} does not exist");
            return Inertia::render('Sheferssnit/Index', [
                'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth' => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords' => 0,
                'searchCount' => null,
                'flash' => [
                    'error' => "No data available for {$date->format('F Y')}.",
                ],
            ]);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('ssnit as s', 'tp.staff_id', '=', 's.staffid')
            ->select(
                'tp.staff_id as staff_id',
                DB::raw('COALESCE(s.shop, "Shefer") as shop'),
                DB::raw('COALESCE(s.lastname, tp.name1) as lastname'),
                DB::raw('COALESCE(s.firstname, "") as firstname'),
                DB::raw('COALESCE(s.othername, "") as othername'),
                DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                DB::raw('COALESCE(s.national, "") as gh_card_number'),
                'tp.basic_earned',
                DB::raw('ROUND(tp.basic_earned * 0.135, 2) as ssnit_amount')
            );

        // Apply search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staff_id', 'like', "%{$search}%")
                  ->orWhere('s.lastname', 'like', "%{$search}%")
                  ->orWhere('s.firstname', 'like', "%{$search}%")
                  ->orWhere('s.othername', 'like', "%{$search}%")
                  ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                  ->orWhere('s.national', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sort = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');
        Log::info("SheferssnitPay Index: Sorting by {$sort} {$order}");
        $sortColumn = match($sort) {
            'staff_id' => 'tp.staff_id', // Use actual column name
            'shop' => 's.shop',
            'lastname' => 's.lastname',
            'firstname' => 's.firstname',
            'othername' => 's.othername',
            'ssnit_number' => 's.ssnit1a',
            'gh_card_number' => 's.national',
            'basic_earned' => 'tp.basic_earned',
            'ssnit_amount' => 'ssnit_amount', // Use alias for calculated column
            default => 'tp.staff_id',
        };

        // Use ISNULL to push NULLs to the end for MariaDB compatibility
        $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

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

        Log::info("SheferssnitPay Index: Retrieved records", ['count' => $records->total(), 'sample' => $records->items()[0] ?? null]);

        return Inertia::render('Sheferssnit/Index', [
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
            $date = Carbon::createFromFormat('Y-m', $month);
            $tableName = 'shefer'. strtolower($date->format('F')) . $date->format('y');
            Log::info("SheferssnitPay Export: Generated table name: {$tableName}");

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                Log::warning("SheferssnitPay Export: Table {$tableName} does not exist");
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('ssnit as s', 'tp.staff_id', '=', 's.staffid')
                ->select(
                    'tp.staff_id as staff_id',
                    DB::raw('COALESCE(s.shop, "Shefer") as shop'),
                    DB::raw('COALESCE(s.lastname, tp.name1) as lastname'),
                    DB::raw('COALESCE(s.firstname, "") as firstname'),
                    DB::raw('COALESCE(s.othername, "") as othername'),
                    DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                    DB::raw('COALESCE(s.national, "") as gh_card_number'),
                    'tp.basic_earned',
                    DB::raw('ROUND(tp.basic_earned * 0.135, 2) as ssnit_amount')
                );

            // Apply search
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.staff_id', 'like', "%{$search}%")
                      ->orWhere('s.lastname', 'like', "%{$search}%")
                      ->orWhere('s.firstname', 'like', "%{$search}%")
                      ->orWhere('s.othername', 'like', "%{$search}%")
                      ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                      ->orWhere('s.national', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sort = $request->input('sort', 'staff_id');
            $order = $request->input('order', 'asc');
            Log::info("SheferssnitPay Export: Sorting by {$sort} {$order}");
            $sortColumn = match($sort) {
                'staff_id' => 'tp.staff_id', // Use actual column name
                'shop' => 's.shop',
                'lastname' => 's.lastname',
                'firstname' => 's.firstname',
                'othername' => 's.othername',
                'ssnit_number' => 's.ssnit1a',
                'gh_card_number' => 's.national',
                'basic_earned' => 'tp.basic_earned',
                'ssnit_amount' => 'ssnit_amount', // Use alias for calculated column
                default => 'tp.staff_id',
            };

            // Use ISNULL to push NULLs to the end for MariaDB compatibility
            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $headers = ['Staff ID', 'Shop', 'Last Name', 'First Name', 'Other Name', 'SSNIT Number', 'Ghana Card', 'Basic Salary', 'SSNIT (13.5%)'];
            $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            $totalBasic = 0;
            $totalSsnit = 0;

            foreach ($records as $r) {
                $basicVal = (float)($r->basic_earned ?? 0);
                $ssnitVal = (float)($r->ssnit_amount ?? 0);
                $totalBasic += $basicVal;
                $totalSsnit += $ssnitVal;

                $sheet->setCellValueExplicit("A{$row}", $r->staff_id ?? '', DataType::TYPE_STRING);
                $sheet->setCellValue("B{$row}", $r->shop ?? '');
                $sheet->setCellValue("C{$row}", $r->lastname ?? '');
                $sheet->setCellValue("D{$row}", $r->firstname ?? '');
                $sheet->setCellValue("E{$row}", $r->othername ?? '');
                $sheet->setCellValueExplicit("F{$row}", $r->ssnit_number ?? '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("G{$row}", $r->gh_card_number ?? '', DataType::TYPE_STRING);
                $sheet->setCellValue("H{$row}", number_format($basicVal, 2, '.', ''));
                $sheet->setCellValue("I{$row}", number_format($ssnitVal, 2, '.', ''));
                $row++;
            }

            // Total row
            $sheet->setCellValue("A{$row}", "TOTAL");
            $sheet->setCellValue("H{$row}", number_format($totalBasic, 2, '.', ''));
            $sheet->setCellValue("I{$row}", number_format($totalSsnit, 2, '.', ''));

            $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
            $headerStyle = $sheet->getStyle('A1:I1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9E1F2');

            $totalStyle = $sheet->getStyle("A{$row}:I{$row}");
            $totalStyle->getFont()->setBold(true);

            foreach (range('A', 'I') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = "shefer_ssnit_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Exception $e) {
            Log::error("SheferssnitPay Export failed", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
