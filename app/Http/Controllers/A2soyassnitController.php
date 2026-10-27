<?php

namespace App\Http\Controllers;

use App\Services\Export\SpreadsheetExporter;

use App\Models\A2soyapay;
use App\Models\Ssnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class A2soyassnitController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'a2soya' . strtolower($date->format('F')) . $date->format('y');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('A2soyassnit/Index', [
                'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth' => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords' => 0,
                'searchCount' => null,
                'flash' => ['error' => "No data available for {$date->format('F Y')}."],
            ]);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('ssnit as s', 'tp.staff_id', '=', 's.staffid')
            ->select(
                'tp.staff_id as staff_id',
                DB::raw('COALESCE(s.shop, "A2soya") as shop'),
                DB::raw('COALESCE(s.lastname, tp.name) as lastname'),
                DB::raw('COALESCE(s.firstname, "") as firstname'),
                DB::raw('COALESCE(s.othername, "") as othername'),
                DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                DB::raw('COALESCE(s.national, "") as gh_card_number'),
                'tp.basic_salary_earned as basic_salary',
                DB::raw('ROUND(tp.basic_salary_earned * 0.135, 2) as ssnit_amount')
            );

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

        $sort = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');
        $sortColumn = match($sort) {
            'staff_id'       => 'tp.staff_id',
            'shop'           => 's.shop',
            'lastname'       => 's.lastname',
            'firstname'      => 's.firstname',
            'othername'      => 's.othername',
            'ssnit_number'   => 's.ssnit1a',
            'gh_card_number' => 's.national',
            'basic_salary'   => 'tp.basic_salary_earned',
            'ssnit_amount'   => 'ssnit_amount',
            default          => 'tp.staff_id',
        };

        $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

        $perPage = 10;
        $records = $query->paginate($perPage)->appends([
            'month' => $month, 'search' => $search, 'sort' => $sort, 'order' => $order,
        ]);

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        return Inertia::render('A2soyassnit/Index', [
            'records'        => $records,
            'selectedMonth'  => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords'   => $totalRecords,
            'searchCount'    => $searchCount,
            'flash'          => ['success' => session('success'), 'error' => session('error')],
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
            $tableName = 'a2soya' . strtolower($date->format('F')) . $date->format('y');

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('ssnit as s', 'tp.staff_id', '=', 's.staffid')
                ->select(
                    'tp.staff_id as staff_id',
                    DB::raw('COALESCE(s.shop, "A2soya") as shop'),
                    DB::raw('COALESCE(s.lastname, tp.name) as lastname'),
                    DB::raw('COALESCE(s.firstname, "") as firstname'),
                    DB::raw('COALESCE(s.othername, "") as othername'),
                    DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                    DB::raw('COALESCE(s.national, "") as gh_card_number'),
                    'tp.basic_salary_earned as basic_salary',
                    DB::raw('ROUND(tp.basic_salary_earned * 0.135, 2) as ssnit_amount')
                );

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

            $sort = $request->input('sort', 'staff_id');
            $order = $request->input('order', 'asc');
            $sortColumn = match($sort) {
                'staff_id'       => 'tp.staff_id',
                'shop'           => 's.shop',
                'lastname'       => 's.lastname',
                'firstname'      => 's.firstname',
                'othername'      => 's.othername',
                'ssnit_number'   => 's.ssnit1a',
                'gh_card_number' => 's.national',
                'basic_salary'   => 'tp.basic_salary_earned',
                'ssnit_amount'   => 'ssnit_amount',
                default          => 'tp.staff_id',
            };

            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get()->map(function ($record) {
                return [
                    'staff_id'       => $record->staff_id,
                    'shop'           => $record->shop,
                    'lastname'       => $record->lastname,
                    'firstname'      => $record->firstname,
                    'othername'      => $record->othername,
                    'ssnit_number'   => $record->ssnit_number,
                    'gh_card_number' => $record->gh_card_number,
                    'basic_salary'   => $record->basic_salary !== null ? (float) $record->basic_salary : null,
                    'ssnit_amount'   => $record->ssnit_amount !== null ? (float) $record->ssnit_amount : null,
                ];
            });

            $filename = "A2soyassnit_" . ($month ?? 'export') . ".xlsx";
            return SpreadsheetExporter::exportSsnit($records, $filename);
        } catch (\Exception $e) {
            Log::error("A2soyassnitPay Export failed", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
