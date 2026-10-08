<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TranssionBfaPetraPayController extends Controller
{
    private function resolveTableName(Carbon $date): ?string
    {
        $fullMonth = strtolower($date->format('F'));
        $shortMonth = strtolower($date->format('M'));
        $year = $date->format('y');

        $candidates = [
            "bfatranssion{$shortMonth}{$year}",
            "bfatranssion{$fullMonth}{$year}",
            "transsionbfa{$shortMonth}{$year}",
            "transsionbfa{$fullMonth}{$year}",
        ];

        foreach ($candidates as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = $this->resolveTableName($date);

        if (!$tableName) {
            return Inertia::render('TranssionBfaPetraPay/Index', [
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
                DB::raw('COALESCE(p.shop, "TRANSSION BFA") as shop'),
                DB::raw('COALESCE(p.lastname, tp.name) as lastname'),
                DB::raw('COALESCE(p.firstname, "") as firstname'),
                DB::raw('COALESCE(p.othername, "") as othername'),
                DB::raw('COALESCE(p.ssnit1a, "") as petra_id'),
                DB::raw('COALESCE(p.national, "") as ghana_card'),
                'tp.basic',
                DB::raw('COALESCE(tp.petra, ROUND(tp.basic * 0.05, 2)) as petra_amount')
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffida', 'like', "%{$search}%")
                  ->orWhere('tp.name', 'like', "%{$search}%")
                  ->orWhere('p.lastname', 'like', "%{$search}%")
                  ->orWhere('p.firstname', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');
        $sortColumn = match($sort) {
            'staff_id'     => 'tp.staffida',
            'shop'         => 'shop',
            'lastname'     => 'lastname',
            'firstname'    => 'firstname',
            'othername'    => 'othername',
            'petra_id'     => 'petra_id',
            'ghana_card'   => 'ghana_card',
            'basic'        => 'tp.basic',
            'petra_amount' => 'petra_amount',
            default        => 'tp.staffida',
        };

        $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

        $records = $query->paginate(10)->appends([
            'month'  => $month,
            'search' => $search,
            'sort'   => $sort,
            'order'  => $order,
        ]);

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? (clone $query)->count() : null;

        return Inertia::render('TranssionBfaPetraPay/Index', [
            'records'        => $records,
            'selectedMonth'  => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords'   => $totalRecords,
            'searchCount'    => $searchCount,
            'flash'          => [
                'success' => session('success'),
                'error'   => session('error'),
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
            $tableName = $this->resolveTableName($date);

            if (!$tableName) {
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table("{$tableName} as tp")
                ->leftJoin('petraorg as p', 'tp.staffida', '=', 'p.staffid')
                ->select(
                    'tp.staffida as staff_id',
                    DB::raw('COALESCE(p.shop, "TRANSSION BFA") as shop'),
                    DB::raw('COALESCE(p.lastname, tp.name) as lastname'),
                    DB::raw('COALESCE(p.firstname, "") as firstname'),
                    DB::raw('COALESCE(p.othername, "") as othername'),
                    DB::raw('COALESCE(p.ssnit1a, "") as petra_id'),
                    DB::raw('COALESCE(p.national, "") as ghana_card'),
                    'tp.basic',
                    DB::raw('COALESCE(tp.petra, ROUND(tp.basic * 0.05, 2)) as petra_amount')
                );

            $sort = $request->input('sort', 'staff_id');
            $order = $request->input('order', 'asc');
            $sortColumn = match($sort) {
                'staff_id'     => 'tp.staffida',
                'shop'         => 'shop',
                'lastname'     => 'lastname',
                'firstname'    => 'firstname',
                'othername'    => 'othername',
                'petra_id'     => 'petra_id',
                'ghana_card'   => 'ghana_card',
                'basic'        => 'tp.basic',
                'petra_amount' => 'petra_amount',
                default        => 'tp.staffida',
            };

            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get()->map(function ($record) {
                return [
                    'staff_id'     => $record->staff_id,
                    'shop'         => $record->shop,
                    'lastname'     => $record->lastname,
                    'firstname'    => $record->firstname,
                    'othername'    => $record->othername,
                    'petra_id'     => $record->petra_id,
                    'ghana_card'   => $record->ghana_card,
                    'basic'        => $record->basic !== null ? (float) $record->basic : null,
                    'petra_amount' => $record->petra_amount !== null ? (float) $record->petra_amount : null,
                ];
            });

            return response()->json($records);
        } catch (\Exception $e) {
            Log::error("TranssionBfaPetraPay Export failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
