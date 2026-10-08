<?php

namespace App\Http\Controllers;

use App\Models\Xiaomipay;
use App\Models\Petra;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XiaomiPetraPayController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month     = $request->input('month', now()->format('Y-m'));
        $date      = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'xiaomi' . strtolower($date->format('F')) . $date->format('y');

        Log::info("XiaomiPetraPay Index: table={$tableName}");

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('XiaomiPetraPay/Index', [
                'records'        => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth'  => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords'   => 0,
                'searchCount'    => null,
                'flash'          => ['error' => "No data available for {$date->format('F Y')}."],
            ]);
        }

        $query = DB::table("{$tableName} as tp")
            ->leftJoin('petraorg as p', 'tp.staffida', '=', 'p.staffid')
            ->select(
                'tp.staffida as staff_id',
                DB::raw('COALESCE(p.shop, "Xiaomi") as shop'),
                DB::raw('COALESCE(p.lastname,  tp.name) as lastname'),
                DB::raw('COALESCE(p.firstname, "")      as firstname'),
                DB::raw('COALESCE(p.othername, "")      as othername'),
                DB::raw('COALESCE(p.ssnit1a,   "")      as petra_id'),
                DB::raw('COALESCE(p.national,  "")      as ghana_card'),
                'tp.basic',
                DB::raw('ROUND(tp.basic * 0.05, 2) as petra_amount')
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffida',  'like', "%{$search}%")
                  ->orWhere('p.lastname',  'like', "%{$search}%")
                  ->orWhere('p.firstname', 'like', "%{$search}%")
                  ->orWhere('p.othername', 'like', "%{$search}%")
                  ->orWhere('p.ssnit1a',   'like', "%{$search}%")
                  ->orWhere('p.national',  'like', "%{$search}%")
                  ->orWhere('tp.name',     'like', "%{$search}%");
            });
        }

        $sort  = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');

        $sortColumn = match($sort) {
            'staff_id'     => 'tp.staffida',
            'shop'         => 'p.shop',
            'lastname'     => 'p.lastname',
            'firstname'    => 'p.firstname',
            'othername'    => 'p.othername',
            'petra_id'     => 'p.ssnit1a',
            'ghana_card'   => 'p.national',
            'basic'        => 'tp.basic',
            'petra_amount' => 'petra_amount',
            default        => 'tp.staffida',
        };

        $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

        $records      = $query->paginate(10)->appends($request->query());
        $totalRecords = DB::table($tableName)->count();
        $searchCount  = $search ? $query->getCountForPagination() : null;

        return Inertia::render('XiaomiPetraPay/Index', [
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
            $tableName = 'xiaomi' . strtolower($date->format('F')) . $date->format('y');

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table("{$tableName} as tp")
                ->leftJoin('petraorg as p', 'tp.staffida', '=', 'p.staffid')
                ->select(
                    'tp.staffida as staff_id',
                    DB::raw('COALESCE(p.shop, "Xiaomi") as shop'),
                    DB::raw('COALESCE(p.lastname,  tp.name) as lastname'),
                    DB::raw('COALESCE(p.firstname, "")      as firstname'),
                    DB::raw('COALESCE(p.othername, "")      as othername'),
                    DB::raw('COALESCE(p.ssnit1a,   "")      as petra_id'),
                    DB::raw('COALESCE(p.national,  "")      as ghana_card'),
                    'tp.basic',
                    DB::raw('ROUND(tp.basic * 0.05, 2) as petra_amount')
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.staffida',  'like', "%{$search}%")
                      ->orWhere('p.lastname',  'like', "%{$search}%")
                      ->orWhere('p.firstname', 'like', "%{$search}%")
                      ->orWhere('p.othername', 'like', "%{$search}%")
                      ->orWhere('p.ssnit1a',   'like', "%{$search}%")
                      ->orWhere('p.national',  'like', "%{$search}%")
                      ->orWhere('tp.name',     'like', "%{$search}%");
                });
            }

            $sort  = $request->input('sort', 'staff_id');
            $order = $request->input('order', 'asc');

            $sortColumn = match($sort) {
                'staff_id'     => 'tp.staffida',
                'shop'         => 'p.shop',
                'lastname'     => 'p.lastname',
                'firstname'    => 'p.firstname',
                'othername'    => 'p.othername',
                'petra_id'     => 'p.ssnit1a',
                'ghana_card'   => 'p.national',
                'basic'        => 'tp.basic',
                'petra_amount' => 'petra_amount',
                default        => 'tp.staffida',
            };

            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get()->map(fn($r) => [
                'staff_id'     => $r->staff_id,
                'shop'         => $r->shop,
                'lastname'     => $r->lastname,
                'firstname'    => $r->firstname,
                'othername'    => $r->othername,
                'petra_id'     => $r->petra_id,
                'ghana_card'   => $r->ghana_card,
                'basic'        => $r->basic !== null ? (float) $r->basic : null,
                'petra_amount' => $r->petra_amount !== null ? (float) $r->petra_amount : null,
            ]);

            return response()->json($records);

        } catch (\Exception $e) {
            Log::error("XiaomiPetraPay Export failed", ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
