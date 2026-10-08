<?php

namespace App\Http\Controllers;

use App\Models\Melcompay;
use App\Models\Ssnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MelcomssnitController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        Log::info("MelcomssnitPay Index: Received month: {$month}");

        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'melcom' . strtolower($date->format('F')) . $date->format('y');
        Log::info("MelcomssnitPay Index: Generated table name: {$tableName}");

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            Log::warning("MelcomssnitPay Index: Table {$tableName} does not exist");
            return Inertia::render('Melcomssnit/Index', [
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
            ->leftJoin('ssnit as s', 'tp.employ', '=', 's.staffid')
            ->select(
                'tp.employ as employ',
                DB::raw('COALESCE(s.shop, tp.shopname) as shop'),
                DB::raw('COALESCE(s.lastname, tp.empoyeename) as lastname'),
                DB::raw('COALESCE(s.firstname, "") as firstname'),
                DB::raw('COALESCE(s.othername, "") as othername'),
                DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                DB::raw('COALESCE(s.national, "") as gh_card_number'),
                'tp.basicentitlement',
                DB::raw('ROUND(tp.basicentitlement * 0.135, 2) as ssnit_amount')
            );

        // Apply search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.employ', 'like', "%{$search}%")
                  ->orWhere('s.lastname', 'like', "%{$search}%")
                  ->orWhere('s.firstname', 'like', "%{$search}%")
                  ->orWhere('s.othername', 'like', "%{$search}%")
                  ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                  ->orWhere('s.national', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sort = $request->input('sort', 'employ');
        $order = $request->input('order', 'asc');
        Log::info("MelcomssnitPay Index: Sorting by {$sort} {$order}");
        $sortColumn = match($sort) {
            'employ' => 'tp.employ', // Use actual column name
            'shop' => 's.shop',
            'lastname' => 's.lastname',
            'firstname' => 's.firstname',
            'othername' => 's.othername',
            'ssnit_number' => 's.ssnit1a',
            'gh_card_number' => 's.national',
            'basicentitlement' => 'tp.basicentitlement',
            'ssnit_amount' => 'ssnit_amount', // Use alias for calculated column
            default => 'tp.employ',
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

        Log::info("MelcomssnitPay Index: Retrieved records", ['count' => $records->total(), 'sample' => $records->items()[0] ?? null]);

        return Inertia::render('Melcomssnit/Index', [
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
            $tableName = 'melcom'. strtolower($date->format('F')) . $date->format('y');
            Log::info("MelcomssnitPay Export: Generated table name: {$tableName}");

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                Log::warning("MelcomssnitPay Export: Table {$tableName} does not exist");
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('ssnit as s', 'tp.employ', '=', 's.staffid')
                ->select(
                    'tp.employ as employ',
                    DB::raw('COALESCE(s.shop, tp.shopname) as shop'),
                    DB::raw('COALESCE(s.lastname, tp.empoyeename) as lastname'),
                    DB::raw('COALESCE(s.firstname, "") as firstname'),
                    DB::raw('COALESCE(s.othername, "") as othername'),
                    DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                    DB::raw('COALESCE(s.national, "") as gh_card_number'),
                    'tp.basicentitlement',
                    DB::raw('ROUND(tp.basicentitlement * 0.135, 2) as ssnit_amount')
                );

            // Apply search
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.employ', 'like', "%{$search}%")
                      ->orWhere('s.lastname', 'like', "%{$search}%")
                      ->orWhere('s.firstname', 'like', "%{$search}%")
                      ->orWhere('s.othername', 'like', "%{$search}%")
                      ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                      ->orWhere('s.national', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sort = $request->input('sort', 'employ');
            $order = $request->input('order', 'asc');
            Log::info("MelcomssnitPay Export: Sorting by {$sort} {$order}");
            $sortColumn = match($sort) {
                'employ' => 'tp.employ', // Use actual column name
                'shop' => 's.shop',
                'lastname' => 's.lastname',
                'firstname' => 's.firstname',
                'othername' => 's.othername',
                'ssnit_number' => 's.ssnit1a',
                'gh_card_number' => 's.national',
                'basicentitlement' => 'tp.basicentitlement',
                'ssnit_amount' => 'ssnit_amount', // Use alias for calculated column
                default => 'tp.employ',
            };

            // Use ISNULL to push NULLs to the end for MariaDB compatibility
            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get()->map(function ($record) {
                return [
                    'employ' => $record->employ,
                    'shop' => $record->shop,
                    'lastname' => $record->lastname,
                    'firstname' => $record->firstname,
                    'othername' => $record->othername,
                    'ssnit_number' => $record->ssnit_number,
                    'gh_card_number' => $record->gh_card_number,
                    'basicentitlement' => $record->basicentitlement !== null ? (float) $record->basicentitlement : null,
                    'ssnit_amount' => $record->ssnit_amount !== null ? (float) $record->ssnit_amount : null,
                ];
            });

            Log::info("MelcomssnitPay Export: Retrieved records", ['count' => count($records), 'sample' => $records->first()]);

            return response()->json($records);
        } catch (\Exception $e) {
            Log::error("MelcomssnitPay Export failed", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
