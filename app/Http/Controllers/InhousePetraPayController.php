<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InhousePetraPayController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'inhouse' . strtolower($date->format('F')) . $date->format('y') . 'pay';

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('InhousePetraPay/Index', [
                'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth' => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords' => 0,
                'searchCount' => null,
                'flash' => ['error' => "No data available for {$date->format('F Y')}."],
            ]);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('petraorg as p', 'tp.id', '=', 'p.staffid')
            ->select(
                'tp.id as staff_id',
                DB::raw('COALESCE(p.shop, "Inhouse") as shop'),
                DB::raw('COALESCE(p.lastname, tp.name) as lastname'),
                DB::raw('COALESCE(p.firstname, "") as firstname'),
                DB::raw('COALESCE(p.othername, "") as othername'),
                DB::raw('COALESCE(p.ssnit1a, "") as petra_id'),
                DB::raw('COALESCE(p.national, "") as ghana_card'),
                'tp.actualbasic as basic_salary',
                DB::raw('ROUND(tp.actualbasic * 0.05, 2) as petra_amount')
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.id', 'like', "%{$search}%")
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
            'staff_id'     => 'tp.id',
            'shop'         => 'shop',
            'lastname'     => 'lastname',
            'firstname'    => 'firstname',
            'othername'    => 'othername',
            'petra_id'     => 'petra_id',
            'ghana_card'   => 'ghana_card',
            'basic_salary' => 'tp.actualbasic',
            'petra_amount' => 'petra_amount',
            default        => 'tp.id',
        };

        $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

        $perPage = 10;
        $records = $query->paginate($perPage)->appends($request->query());

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        return Inertia::render('InhousePetraPay/Index', [
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
            $request->validate(['month' => ['required', 'date_format:Y-m']]);

            $month = $request->input('month');
            $date = Carbon::createFromFormat('Y-m', $month);
            $tableName = 'inhouse' . strtolower($date->format('F')) . $date->format('y') . 'pay';

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('petraorg as p', 'tp.id', '=', 'p.staffid')
                ->select(
                    'tp.id as staff_id',
                    DB::raw('COALESCE(p.shop, "Inhouse") as shop'),
                    DB::raw('COALESCE(p.lastname, tp.name) as lastname'),
                    DB::raw('COALESCE(p.firstname, "") as firstname'),
                    DB::raw('COALESCE(p.othername, "") as othername'),
                    DB::raw('COALESCE(p.ssnit1a, "") as petra_id'),
                    DB::raw('COALESCE(p.national, "") as ghana_card'),
                    'tp.actualbasic as basic_salary',
                    DB::raw('ROUND(tp.actualbasic * 0.05, 2) as petra_amount')
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.id', 'like', "%{$search}%")
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
                'staff_id'     => 'tp.id',
                'shop'         => 'shop',
                'lastname'     => 'lastname',
                'firstname'    => 'firstname',
                'othername'    => 'othername',
                'petra_id'     => 'petra_id',
                'ghana_card'   => 'ghana_card',
                'basic_salary' => 'tp.actualbasic',
                'petra_amount' => 'petra_amount',
                default        => 'tp.id',
            };

            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");
            $records = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $headers = ['Staff ID', 'Shop', 'Last Name', 'First Name', 'Other Name', 'PETRA ID', 'Ghana Card', 'Basic Salary', 'PETRA Amount'];
            $sheet->fromArray($headers, null, 'A1');

            $data = $records->map(fn($r) => [
                $r->staff_id,
                $r->shop,
                $r->lastname,
                $r->firstname,
                $r->othername,
                $r->petra_id,
                $r->ghana_card,
                $r->basic_salary ? (float)$r->basic_salary : 'N/A',
                $r->petra_amount ? (float)$r->petra_amount : 'N/A',
            ])->toArray();

            if ($data) $sheet->fromArray($data, null, 'A2');

            // Beautiful styling
            $sheet->getParent()->getDefaultStyle()->getFont()->setSize(11)->setName('Calibri');
            $sheet->getStyle('A1:I1')->getFont()->setBold(true);
            $sheet->getStyle('A1:I1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE6E6E6');
            foreach (range('A', 'I') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

            $writer = new Xlsx($spreadsheet);
            $filename = "inhouse_petra_{$month}.xlsx";

            return response()->streamDownload(fn() => $writer->save('php://output'), $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        } catch (\Exception $e) {
            Log::error("InhousePetraPay Export failed", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed: ' . $e->getMessage()], 500);
        }
    }
}
