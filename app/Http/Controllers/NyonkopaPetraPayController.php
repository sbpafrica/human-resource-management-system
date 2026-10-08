<?php

namespace App\Http\Controllers;

use App\Models\Petra;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class NyonkopaPetraPayController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        Log::info("NyonkopaPetraPay Index: Received month: {$month}");

        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'nyonkopa' . strtolower($date->format('F')) . $date->format('y');
        Log::info("NyonkopaPetraPay Index: Generated table name: {$tableName}");

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            Log::warning("NyonkopaPetraPay Index: Table {$tableName} does not exist");
            return Inertia::render('NyonkopaPetraPay/Index', [
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
            ->leftJoin('petraorg as p', 'tp.staff_id', '=', 'p.staffid')
            ->select(
                'tp.staff_id as staff_id',
                DB::raw('COALESCE(p.shop, "NYONKOPA") as shop'),
                DB::raw('COALESCE(p.lastname, tp.employee_name) as lastname'),
                DB::raw('COALESCE(p.firstname, "") as firstname'),
                DB::raw('COALESCE(p.othername, "") as othername'),
                DB::raw('COALESCE(p.ssnit1a, "") as petra_id'),
                DB::raw('COALESCE(p.national, "") as ghana_card'),
                'tp.base_salary',
                DB::raw('ROUND(tp.base_salary * 0.05, 2) as petra_amount')
            );

        // Apply search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staff_id', 'like', "%{$search}%")
                  ->orWhere('p.lastname', 'like', "%{$search}%")
                  ->orWhere('p.firstname', 'like', "%{$search}%")
                  ->orWhere('p.othername', 'like', "%{$search}%")
                  ->orWhere('p.ssnit1a', 'like', "%{$search}%")
                  ->orWhere('p.national', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sort = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');
        Log::info("NyonkopaPetraPay Index: Sorting by {$sort} {$order}");

        $sortColumn = match($sort) {
            'staff_id'     => 'tp.staff_id',
            'shop'         => 'p.shop',
            'lastname'     => 'p.lastname',
            'firstname'    => 'p.firstname',
            'othername'    => 'p.othername',
            'petra_id'     => 'p.ssnit1a',
            'ghana_card'   => 'p.national',
            'base_salary'  => 'tp.base_salary',
            'petra_amount' => 'petra_amount',
            default        => 'tp.staff_id',
        };

        $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

        // Paginate
        $perPage = 10;
        $records = $query->paginate($perPage)->appends([
            'month' => $month,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
        ]);

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        Log::info("NyonkopaPetraPay Index: Retrieved records", [
            'count' => $records->total(),
            'sample' => $records->items()[0] ?? null
        ]);

        return Inertia::render('NyonkopaPetraPay/Index', [
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
            $tableName = 'nyonkopa' . strtolower($date->format('F')) . $date->format('y');

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('petraorg as p', 'tp.staff_id', '=', 'p.staffid')
                ->select(
                    'tp.staff_id as staff_id',
                    DB::raw('COALESCE(p.shop, "NYONKOPA") as shop'),
                    DB::raw('COALESCE(p.lastname, tp.employee_name) as lastname'),
                    DB::raw('COALESCE(p.firstname, "") as firstname'),
                    DB::raw('COALESCE(p.othername, "") as othername'),
                    DB::raw('COALESCE(p.ssnit1a, "") as petra_id'),
                    DB::raw('COALESCE(p.national, "") as ghana_card'),
                    'tp.base_salary',
                    DB::raw('ROUND(tp.base_salary * 0.05, 2) as petra_amount')
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.staff_id', 'like', "%{$search}%")
                      ->orWhere('p.lastname', 'like', "%{$search}%")
                      ->orWhere('p.firstname', 'like', "%{$search}%")
                      ->orWhere('p.othername', 'like', "%{$search}%")
                      ->orWhere('p.ssnit1a', 'like', "%{$search}%")
                      ->orWhere('p.national', 'like', "%{$search}%");
                });
            }

            $sort = $request->input('sort', 'staff_id');
            $order = $request->input('order', 'asc');
            $sortColumn = match($sort) {
                'staff_id'     => 'tp.staff_id',
                'shop'         => 'p.shop',
                'lastname'     => 'p.lastname',
                'firstname'    => 'p.firstname',
                'othername'    => 'p.othername',
                'petra_id'     => 'p.ssnit1a',
                'ghana_card'   => 'p.national',
                'base_salary'  => 'tp.base_salary',
                'petra_amount' => 'petra_amount',
                default        => 'tp.staff_id',
            };

            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");
            $records = $query->get();

            // Create spreadsheet
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Define headers
            $headers = [
                'Staff ID',
                'Shop',
                'Last Name',
                'First Name',
                'Other Name',
                'PETRA ID',
                'Ghana Card',
                'Base Salary',
                'PETRA Amount'
            ];

            // Put headers in row 1
            $sheet->fromArray($headers, null, 'A1');

            // Put data starting from row 2
            $dataRows = $records->map(function ($r) {
                return [
                    $r->staff_id,
                    $r->shop,
                    $r->lastname,
                    $r->firstname,
                    $r->othername,
                    $r->petra_id,
                    $r->ghana_card,
                    $r->base_salary != null ? (float)$r->base_salary : 'N/A',
                    $r->petra_amount != null ? (float)$r->petra_amount : 'N/A',
                ];
            })->toArray();

            if (!empty($dataRows)) {
                $sheet->fromArray($dataRows, null, 'A2');
            }

            // Styling
            $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
            $sheet->getStyle('A1:I1')->getFont()->setBold(true);
            $sheet->getStyle('A1:I1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('bbdcfe');

            // Auto-size columns
            foreach (range('A', 'I') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Extra width for comfort
            $sheet->getColumnDimension('B')->setWidth(20);
            $sheet->getColumnDimension('C')->setWidth(18);
            $sheet->getColumnDimension('G')->setWidth(18);

            $writer = new Xlsx($spreadsheet);
            $filename = "nyonkopapetrapay_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (\Exception $e) {
            Log::error("NyonkopaPetraPay Export failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
