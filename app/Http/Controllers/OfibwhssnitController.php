<?php

namespace App\Http\Controllers;
use App\Models\Ofibwhpay;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfibwhssnitController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = 'olambwh' . strtolower($date->format('F')) . $date->format('y');

        Log::info("OfibwhssnitPay Index: Using table: {$tableName}");

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return Inertia::render('Ofibwhssnit/Index', [
                'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth' => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords' => 0,
                'searchCount' => null,
                'flash' => ['error' => "No data available for {$date->format('F Y')}."],
            ]);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('ssnit as s', 'tp.id', '=', 's.staffid')
            ->select(
                'tp.id as id',
                DB::raw('COALESCE(s.shop, tp.name) as shop'),
                DB::raw('COALESCE(s.lastname, tp.name) as lastname'),
                DB::raw('COALESCE(s.firstname, "") as firstname'),
                DB::raw('COALESCE(s.othername, "") as othername'),
                DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                DB::raw('COALESCE(s.national, "") as gh_card_number'),
                'tp.basic',
                'tp.amt', // Make sure this column exists in your monthly table
                // Smart SSNiT Calculation: Use basic if > 0, otherwise use amt
                DB::raw('
                    CASE
                        WHEN tp.basic > 0 THEN ROUND(tp.basic * 0.135, 2)
                        ELSE ROUND(tp.amt * 0.135, 2)
                    END as ssnit_amount
                '),
                // Also expose the actual base used for clarity (optional)
                DB::raw('
                    CASE
                        WHEN tp.basic > 0 THEN tp.basic
                        ELSE tp.amt
                    END as base_salary_used
                ')
            );

        // Search filter
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

        // Sorting
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'asc');

        $sortColumn = match ($sort) {
            'id' => 'tp.id',
            'shop' => 'shop',
            'lastname' => 'lastname',
            'firstname' => 'firstname',
            'othername' => 'othername',
            'ssnit_number' => 'ssnit_number',
            'gh_card_number' => 'gh_card_number',
            'basic' => 'base_salary_used',
            'ssnit_amount' => 'ssnit_amount',
            default => 'tp.id',
        };

        $query->orderByRaw("ISNULL($sortColumn), $sortColumn $order");

        $perPage = 10;
        $records = $query->paginate($perPage)->appends($request->query());

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        return Inertia::render('Ofibwhssnit/Index', [
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
            $tableName = 'olambwh' . strtolower($date->format('F')) . $date->format('y');

            if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                return response()->json(['error' => "No data for {$date->format('F Y')}"], 404);
            }

            $records = DB::table($tableName . ' as tp')
                ->leftJoin('ssnit as s', 'tp.id', '=', 's.staffid')
                ->select(
                    'tp.id as id',
                    DB::raw('COALESCE(s.shop, tp.name) as shop'),
                    DB::raw('COALESCE(s.lastname, tp.name) as lastname'),
                    DB::raw('COALESCE(s.firstname, "") as firstname'),
                    DB::raw('COALESCE(s.othername, "") as othername'),
                    DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                    DB::raw('COALESCE(s.national, "") as gh_card_number'),
                    DB::raw('
                        CASE
                            WHEN tp.basic > 0 THEN tp.basic
                            ELSE tp.amt
                        END as base_salary_used
                    '),
                    DB::raw('
                        CASE
                            WHEN tp.basic > 0 THEN ROUND(tp.basic * 0.135, 2)
                            ELSE ROUND(tp.amt * 0.135, 2)
                        END as ssnit_amount
                    ')
                )
                ->when($request->search, function ($q) use ($request) {
                    $search = $request->search;
                    $q->where(function ($sq) use ($search) {
                        $sq->where('tp.id', 'like', "%{$search}%")
                           ->orWhere('s.lastname', 'like', "%{$search}%")
                           ->orWhere('s.firstname', 'like', "%{$search}%")
                           ->orWhere('s.ssnit1a', 'like', "%{$search}%");
                    });
                })
                ->orderBy('lastname')
                ->get()
                ->map(function ($record) {
                    return [
                        'id' => $record->id,
                        'shop' => $record->shop,
                        'lastname' => $record->lastname,
                        'firstname' => $record->firstname,
                        'othername' => $record->othername,
                        'ssnit_number' => $record->ssnit_number,
                        'gh_card_number' => $record->gh_card_number,
                        'base_salary_used' => number_format((float)$record->base_salary_used, 2),
                        'ssnit_amount' => number_format((float)$record->ssnit_amount, 2),
                    ];
                });

            return response()->json($records);

        } catch (\Exception $e) {
            Log::error("Ofibwhssnit Export Error: " . $e->getMessage());
            return response()->json(['error' => 'Export failed'], 500);
        }
    }
}
