<?php

namespace App\Http\Controllers;

use App\Models\Ofihygienepay;
use App\Models\Ssnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfihygienessnitController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        Log::info("OfihygienessnitPay Index: Received month: {$month}");

        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'olamhygiene' . strtolower($date->format('F')) . $date->format('y');
        Log::info("OfihygienessnitPay Index: Generated table name: {$tableName}");

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            Log::warning("OfihygienessnitPay Index: Table {$tableName} does not exist");
            return Inertia::render('Ofihygienessnit/Index', [
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
            ->leftJoin('ssnit as s', 'tp.id', '=', 's.staffid')
            ->select(
                'tp.id as id',
                DB::raw('COALESCE(s.shop, "Ofihygiene") as shop'),
                DB::raw('COALESCE(s.lastname, tp.names) as lastname'),
                DB::raw('COALESCE(s.firstname, "") as firstname'),
                DB::raw('COALESCE(s.othername, "") as othername'),
                DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                DB::raw('COALESCE(s.national, "") as gh_card_number'),
                'tp.total2',
                DB::raw('ROUND(tp.total2 * 0.135, 2) as ssnit_amount')
            );

        // Apply search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.id', 'like', "%{$search}%")
                  ->orWhere('s.lastname', 'like', "%{$search}%")
                  ->orWhere('s.firstname', 'like', "%{$search}%")
                  ->orWhere('s.othername', 'like', "%{$search}%")
                  ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                  ->orWhere('s.national', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'asc');
        Log::info("OfihygienessnitPay Index: Sorting by {$sort} {$order}");
        $sortColumn = match($sort) {
            'id' => 'tp.id', // Use actual column name
            'shop' => 's.shop',
            'lastname' => 's.lastname',
            'firstname' => 's.firstname',
            'othername' => 's.othername',
            'ssnit_number' => 's.ssnit1a',
            'gh_card_number' => 's.national',
            'total2' => 'tp.total2',
            'ssnit_amount' => 'ssnit_amount', // Use alias for calculated column
            default => 'tp.id',
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

        Log::info("OfihygienessnitPay Index: Retrieved records", ['count' => $records->total(), 'sample' => $records->items()[0] ?? null]);

        return Inertia::render('Ofihygienessnit/Index', [
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
            $tableName = 'olamhygiene'. strtolower($date->format('F')) . $date->format('y');
            Log::info("OfihygienessnitPay Export: Generated table name: {$tableName}");

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                Log::warning("OfihygienessnitPay Export: Table {$tableName} does not exist");
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('ssnit as s', 'tp.id', '=', 's.staffid')
                ->select(
                    'tp.id as id',
                    DB::raw('COALESCE(s.shop, "Ofihygiene") as shop'),
                    DB::raw('COALESCE(s.lastname, tp.names) as lastname'),
                    DB::raw('COALESCE(s.firstname, "") as firstname'),
                    DB::raw('COALESCE(s.othername, "") as othername'),
                    DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                    DB::raw('COALESCE(s.national, "") as gh_card_number'),
                    'tp.total2',
                    DB::raw('ROUND(tp.total2 * 0.135, 2) as ssnit_amount')
                );

            // Apply search
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.id', 'like', "%{$search}%")
                      ->orWhere('s.lastname', 'like', "%{$search}%")
                      ->orWhere('s.firstname', 'like', "%{$search}%")
                      ->orWhere('s.othername', 'like', "%{$search}%")
                      ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                      ->orWhere('s.national', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sort = $request->input('sort', 'id');
            $order = $request->input('order', 'asc');
            Log::info("OfihygienessnitPay Export: Sorting by {$sort} {$order}");
            $sortColumn = match($sort) {
                'id' => 'tp.id', // Use actual column name
                'shop' => 's.shop',
                'lastname' => 's.lastname',
                'firstname' => 's.firstname',
                'othername' => 's.othername',
                'ssnit_number' => 's.ssnit1a',
                'gh_card_number' => 's.national',
                'total2' => 'tp.total2',
                'ssnit_amount' => 'ssnit_amount', // Use alias for calculated column
                default => 'tp.id',
            };

            // Use ISNULL to push NULLs to the end for MariaDB compatibility
            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get()->map(function ($record) {
                return [
                    'id' => $record->id,
                    'shop' => $record->shop,
                    'lastname' => $record->lastname,
                    'firstname' => $record->firstname,
                    'othername' => $record->othername,
                    'ssnit_number' => $record->ssnit_number,
                    'gh_card_number' => $record->gh_card_number,
                    'total2' => $record->total2 !== null ? (float) $record->total2 : null,
                    'ssnit_amount' => $record->ssnit_amount !== null ? (float) $record->ssnit_amount : null,
                ];
            });

            Log::info("OfihygienessnitPay Export: Retrieved records", ['count' => count($records), 'sample' => $records->first()]);

            return response()->json($records);
        } catch (\Exception $e) {
            Log::error("OfiohygienessnitPay Export failed", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
