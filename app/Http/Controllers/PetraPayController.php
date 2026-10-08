<?php
namespace App\Http\Controllers;

use App\Models\Petra;
use App\Models\TranssionPay;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PetraPayController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        Log::info("PetraPay Index: Received month: {$month}");

        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'transsion' . strtolower($date->format('F')) . $date->format('y');
        Log::info("PetraPay Index: Generated table name: {$tableName}");

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            Log::warning("PetraPay Index: Table {$tableName} does not exist");
            return Inertia::render('PetraPay/Index', [
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
            ->leftJoin('petraorg as p', 'tp.staffida', '=', 'p.staffid')
            ->select(
                'tp.staffida as staff_id',
                DB::raw('COALESCE(p.shop, "TRANSSION") as shop'),
                DB::raw('COALESCE(p.lastname, tp.name) as lastname'),
                DB::raw('COALESCE(p.firstname, "") as firstname'),
                DB::raw('COALESCE(p.othername, "") as othername'),
                DB::raw('COALESCE(p.ssnit1a, "") as petra_id'),
                DB::raw('COALESCE(p.national, "") as ghana_card'),
                'tp.basic',
                DB::raw('ROUND(tp.basic * 0.05, 2) as petra_amount')
            );

        // Apply search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffida', 'like', "%{$search}%")
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
        Log::info("PetraPay Index: Sorting by {$sort} {$order}");
        $sortColumn = match($sort) {
            'staff_id' => 'tp.staffida',
            'shop' => 'p.shop',
            'lastname' => 'p.lastname',
            'firstname' => 'p.firstname',
            'othername' => 'p.othername',
            'petra_id' => 'p.ssnit1a',
            'ghana_card' => 'p.national',
            'basic' => 'tp.basic',
            'petra_amount' => 'petra_amount',
            default => 'tp.staffida',
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

        Log::info("PetraPay Index: Retrieved records", ['count' => $records->total(), 'sample' => $records->items()[0] ?? null]);

        return Inertia::render('PetraPay/Index', [
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
            $tableName = 'transsion' . strtolower($date->format('F')) . $date->format('y');
            Log::info("PetraPay Export: Generated table name: {$tableName}");

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                Log::warning("PetraPay Export: Table {$tableName} does not exist");
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('petraorg as p', 'tp.staffida', '=', 'p.staffid')
                ->select(
                    'tp.staffida as staff_id',
                    DB::raw('COALESCE(p.shop, "TRANSSION") as shop'),
                    DB::raw('COALESCE(p.lastname, tp.name) as lastname'),
                    DB::raw('COALESCE(p.firstname, "") as firstname'),
                    DB::raw('COALESCE(p.othername, "") as othername'),
                    DB::raw('COALESCE(p.ssnit1a, "") as petra_id'),
                    DB::raw('COALESCE(p.national, "") as ghana_card'),
                    'tp.basic',
                    DB::raw('ROUND(tp.basic * 0.05, 2) as petra_amount')
                );

            // Apply search
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.staffida', 'like', "%{$search}%")
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
            Log::info("PetraPay Export: Sorting by {$sort} {$order}");
            $sortColumn = match($sort) {
                'staff_id' => 'tp.staffida',
                'shop' => 'p.shop',
                'lastname' => 'p.lastname',
                'firstname' => 'p.firstname',
                'othername' => 'p.othername',
                'petra_id' => 'p.ssnit1a',
                'ghana_card' => 'p.national',
                'basic' => 'tp.basic',
                'petra_amount' => 'petra_amount',
                default => 'tp.staffida',
            };

            // Use ISNULL to push NULLs to the end for MariaDB compatibility
            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get()->map(function ($record) {
                return [
                    'staff_id' => $record->staff_id,
                    'shop' => $record->shop,
                    'lastname' => $record->lastname,
                    'firstname' => $record->firstname,
                    'othername' => $record->othername,
                    'petra_id' => $record->petra_id,
                    'ghana_card' => $record->ghana_card,
                    'basic' => $record->basic !== null ? (float) $record->basic : null,
                    'petra_amount' => $record->petra_amount !== null ? (float) $record->petra_amount : null,
                ];
            });

            Log::info("PetraPay Export: Retrieved records", ['count' => count($records), 'sample' => $records->first()]);

            return response()->json($records);
        } catch (\Exception $e) {
            Log::error("PetraPay Export failed", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
