<?php

namespace App\Http\Controllers;

use App\Services\Export\SpreadsheetExporter;

use App\Models\Olamfoodpay;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OlamfoodssnitController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month     = $request->input('month', now()->format('Y-m'));
        $date      = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'olamfood' . strtolower($date->format('F')) . $date->format('y');

        Log::info("OlamfoodSsnitPay Index: table={$tableName}");

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('OlamfoodSsnit/Index', [
                'records'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'searchCount'    => null,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}."],
            ]);
        }

        $query = DB::table("{$tableName} as tp")
            ->leftJoin('ssnit as s', 'tp.staffid', '=', 's.staffid')
            ->select(
                'tp.staffid as staff_id',
                DB::raw('COALESCE(s.shop, "Olam Food") as shop'),
                DB::raw('COALESCE(s.lastname,  tp.name) as lastname'),
                DB::raw('COALESCE(s.firstname, "")      as firstname'),
                DB::raw('COALESCE(s.othername, "")      as othername'),
                DB::raw('COALESCE(s.ssnit1a,   "")      as ssnit_number'),
                DB::raw('COALESCE(s.national,  "")      as gh_card_number'),
                'tp.basic',
                DB::raw('ROUND(tp.basic * 0.135, 2) as ssnit_amount')
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffid',   'like', "%{$search}%")
                  ->orWhere('s.lastname',  'like', "%{$search}%")
                  ->orWhere('s.firstname', 'like', "%{$search}%")
                  ->orWhere('s.othername', 'like', "%{$search}%")
                  ->orWhere('s.ssnit1a',   'like', "%{$search}%")
                  ->orWhere('s.national',  'like', "%{$search}%")
                  ->orWhere('tp.name',     'like', "%{$search}%");
            });
        }

        $sort  = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');

        $sortColumn = match($sort) {
            'staff_id'       => 'tp.staffid',
            'shop'           => 's.shop',
            'lastname'       => 's.lastname',
            'firstname'      => 's.firstname',
            'othername'      => 's.othername',
            'ssnit_number'   => 's.ssnit1a',
            'gh_card_number' => 's.national',
            'basic'          => 'tp.basic',
            'ssnit_amount'   => 'ssnit_amount',
            default          => 'tp.staffid',
        };

        $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

        $records      = $query->paginate(10)->appends($request->query());
        $totalRecords = DB::table($tableName)->count();
        $searchCount  = $search ? $query->getCountForPagination() : null;

        return Inertia::render('OlamfoodSsnit/Index', [
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

            $month     = $request->input('month');
            $date      = Carbon::createFromFormat('Y-m', $month);
            $tableName = 'olamfood' . strtolower($date->format('F')) . $date->format('y');

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table("{$tableName} as tp")
                ->leftJoin('ssnit as s', 'tp.staffid', '=', 's.staffid')
                ->select(
                    'tp.staffid as staff_id',
                    DB::raw('COALESCE(s.shop, "Olam Food") as shop'),
                    DB::raw('COALESCE(s.lastname,  tp.name) as lastname'),
                    DB::raw('COALESCE(s.firstname, "")      as firstname'),
                    DB::raw('COALESCE(s.othername, "")      as othername'),
                    DB::raw('COALESCE(s.ssnit1a,   "")      as ssnit_number'),
                    DB::raw('COALESCE(s.national,  "")      as gh_card_number'),
                    'tp.basic',
                    DB::raw('ROUND(tp.basic * 0.135, 2) as ssnit_amount')
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.staffid',   'like', "%{$search}%")
                      ->orWhere('s.lastname',  'like', "%{$search}%")
                      ->orWhere('s.firstname', 'like', "%{$search}%")
                      ->orWhere('s.othername', 'like', "%{$search}%")
                      ->orWhere('s.ssnit1a',   'like', "%{$search}%")
                      ->orWhere('s.national',  'like', "%{$search}%")
                      ->orWhere('tp.name',     'like', "%{$search}%");
                });
            }

            $sort  = $request->input('sort', 'staff_id');
            $order = $request->input('order', 'asc');

            $sortColumn = match($sort) {
                'staff_id'       => 'tp.staffid',
                'shop'           => 's.shop',
                'lastname'       => 's.lastname',
                'firstname'      => 's.firstname',
                'othername'      => 's.othername',
                'ssnit_number'   => 's.ssnit1a',
                'gh_card_number' => 's.national',
                'basic'          => 'tp.basic',
                'ssnit_amount'   => 'ssnit_amount',
                default          => 'tp.staffid',
            };

            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get()->map(fn($r) => [
                'staff_id'       => $r->staff_id,
                'shop'           => $r->shop,
                'lastname'       => $r->lastname,
                'firstname'      => $r->firstname,
                'othername'      => $r->othername,
                'ssnit_number'   => $r->ssnit_number,
                'gh_card_number' => $r->gh_card_number,
                'basic'          => $r->basic !== null ? (float) $r->basic : null,
                'ssnit_amount'   => $r->ssnit_amount !== null ? (float) $r->ssnit_amount : null,
            ]);

            $filename = "Olamfoodssnit_" . ($month ?? 'export') . ".xlsx";
            return SpreadsheetExporter::exportSsnit($records, $filename);

        } catch (\Exception $e) {
            Log::error("OlamfoodSsnitPay Export failed", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}