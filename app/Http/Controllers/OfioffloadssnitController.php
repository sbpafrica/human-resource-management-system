<?php

namespace App\Http\Controllers;

use App\Services\Export\SpreadsheetExporter;

use App\Models\Ofioffloadpay;
use App\Models\Ssnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfioffloadssnitController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        Log::info("OfioffloadssnitPay Index: Received month: {$month}");

        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'offload' . strtolower($date->format('F')) . $date->format('y');
        Log::info("OfioffloadssnitPay Index: Generated table name: {$tableName}");

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            Log::warning("OfioffloadssnitPay Index: Table {$tableName} does not exist");
            return Inertia::render('Ofioffloadssnit/Index', [
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
            ->leftJoin('ssnit as s', 'tp.StaffID', '=', 's.staffid')
            ->select(
                'tp.StaffID as StaffID',
                DB::raw('COALESCE(s.shop, "Ofioffload") as shop'),
                DB::raw('COALESCE(s.lastname, tp.employeename) as lastname'),
                DB::raw('COALESCE(s.firstname, "") as firstname'),
                DB::raw('COALESCE(s.othername, "") as othername'),
                DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                DB::raw('COALESCE(s.national, "") as gh_card_number'),
                'tp.basicsalary',
                DB::raw('ROUND(tp.basicsalary * 0.135, 2) as ssnit_amount')
            );

        // Apply search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.StaffID', 'like', "%{$search}%")
                  ->orWhere('s.lastname', 'like', "%{$search}%")
                  ->orWhere('s.firstname', 'like', "%{$search}%")
                  ->orWhere('s.othername', 'like', "%{$search}%")
                  ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                  ->orWhere('s.national', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sort = $request->input('sort', 'StaffID');
        $order = $request->input('order', 'asc');
        Log::info("OfioffloadssnitPay Index: Sorting by {$sort} {$order}");
        $sortColumn = match($sort) {
            'StaffID' => 'tp.StaffID', // Use actual column name
            'shop' => 's.shop',
            'lastname' => 's.lastname',
            'firstname' => 's.firstname',
            'othername' => 's.othername',
            'ssnit_number' => 's.ssnit1a',
            'gh_card_number' => 's.national',
            'basicsalary' => 'tp.basicsalary',
            'ssnit_amount' => 'ssnit_amount', // Use alias for calculated column
            default => 'tp.StaffID',
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

        Log::info("OfioffloadssnitPay Index: Retrieved records", ['count' => $records->total(), 'sample' => $records->items()[0] ?? null]);

        return Inertia::render('Ofioffloadssnit/Index', [
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
            $tableName = 'offload'. strtolower($date->format('F')) . $date->format('y');
            Log::info("OfioffloadssnitPay Export: Generated table name: {$tableName}");

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                Log::warning("OfioffloadssnitPay Export: Table {$tableName} does not exist");
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('ssnit as s', 'tp.StaffID', '=', 's.staffid')
                ->select(
                    'tp.StaffID as StaffID',
                    DB::raw('COALESCE(s.shop, "Ofioffload") as shop'),
                    DB::raw('COALESCE(s.lastname, tp.employeename) as lastname'),
                    DB::raw('COALESCE(s.firstname, "") as firstname'),
                    DB::raw('COALESCE(s.othername, "") as othername'),
                    DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                    DB::raw('COALESCE(s.national, "") as gh_card_number'),
                    'tp.basicsalary',
                    DB::raw('ROUND(tp.basicsalary * 0.135, 2) as ssnit_amount')
                );

            // Apply search
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.StaffID', 'like', "%{$search}%")
                      ->orWhere('s.lastname', 'like', "%{$search}%")
                      ->orWhere('s.firstname', 'like', "%{$search}%")
                      ->orWhere('s.othername', 'like', "%{$search}%")
                      ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                      ->orWhere('s.national', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            $sort = $request->input('sort', 'StaffID');
            $order = $request->input('order', 'asc');
            Log::info("OfioffloadssnitPay Export: Sorting by {$sort} {$order}");
            $sortColumn = match($sort) {
                'StaffID' => 'tp.StaffID', // Use actual column name
                'shop' => 's.shop',
                'lastname' => 's.lastname',
                'firstname' => 's.firstname',
                'othername' => 's.othername',
                'ssnit_number' => 's.ssnit1a',
                'gh_card_number' => 's.national',
                'basicsalary' => 'tp.basicsalary',
                'ssnit_amount' => 'ssnit_amount', // Use alias for calculated column
                default => 'tp.StaffID',
            };

            // Use ISNULL to push NULLs to the end for MariaDB compatibility
            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get()->map(function ($record) {
                return [
                    'StaffID' => $record->StaffID,
                    'shop' => $record->shop,
                    'lastname' => $record->lastname,
                    'firstname' => $record->firstname,
                    'othername' => $record->othername,
                    'ssnit_number' => $record->ssnit_number,
                    'gh_card_number' => $record->gh_card_number,
                    'basicsalary' => $record->basicsalary !== null ? (float) $record->basicsalary : null,
                    'ssnit_amount' => $record->ssnit_amount !== null ? (float) $record->ssnit_amount : null,
                ];
            });

            Log::info("OfioffloadssnitPay Export: Retrieved records", ['count' => count($records), 'sample' => $records->first()]);

            $filename = "Ofioffloadssnit_" . ($month ?? 'export') . ".xlsx";
            return SpreadsheetExporter::exportSsnit($records, $filename);
        } catch (\Exception $e) {
            Log::error("OfioffloadssnitPay Export failed", ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
