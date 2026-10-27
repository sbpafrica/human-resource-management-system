<?php

namespace App\Http\Controllers;

use App\Services\Export\SpreadsheetExporter;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TranssionPetraPayController extends Controller
{
    private function resolveTableName(Carbon $date): ?string
    {
        $fullMonth = strtolower($date->format('F')); // april
        $shortMonth = strtolower($date->format('M')); // apr
        $year = $date->format('y'); // 25

        $candidates = [
            "transsion{$fullMonth}{$year}",
            "transsion{$shortMonth}{$year}",
            "transsiontec{$fullMonth}{$year}",
            "transsiontec{$shortMonth}{$year}",
        ];

        foreach ($candidates as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    private function getLatestAvailableMonth(): ?string
    {
        $tables = DB::select('SHOW TABLES');
        $matching = [];

        foreach ($tables as $t) {
            foreach ((array)$t as $name) {
                if (preg_match('/^transsion([a-z]+)(\d{2})$/', $name, $matches)) {
                    $monthName = $matches[1];
                    $year = $matches[2];
                    try {
                        $parsed = Carbon::parse("1 {$monthName} 20{$year}");
                        $matching[$parsed->format('Y-m')] = $name;
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
            }
        }

        if (empty($matching)) {
            return null;
        }

        krsort($matching);
        return array_key_first($matching);
    }

    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month');

        if (!$month) {
            // Check if current month table exists, else fallback to latest available month
            $now = Carbon::now();
            $tableName = $this->resolveTableName($now);
            if ($tableName) {
                $month = $now->format('Y-m');
            } else {
                $month = $this->getLatestAvailableMonth() ?? $now->format('Y-m');
            }
        }

        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = $this->resolveTableName($date);

        if (!$tableName) {
            Log::warning("TranssionPetraPay Index: Table for {$month} does not exist");
            return Inertia::render('TranssionPetraPay/Index', [
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
                DB::raw('COALESCE(p.ssnit1a, tp.ssnit, "") as petra_id'),
                DB::raw('COALESCE(p.national, "") as ghana_card'),
                'tp.basic',
                DB::raw('COALESCE(tp.petra, ROUND(tp.basic * 0.05, 2)) as petra_amount')
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffida', 'like', "%{$search}%")
                  ->orWhere('tp.name', 'like', "%{$search}%")
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

        return Inertia::render('TranssionPetraPay/Index', [
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

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('petraorg as p', 'tp.staffida', '=', 'p.staffid')
                ->select(
                    'tp.staffida as staff_id',
                    DB::raw('COALESCE(p.shop, "TRANSSION") as shop'),
                    DB::raw('COALESCE(p.lastname, tp.name) as lastname'),
                    DB::raw('COALESCE(p.firstname, "") as firstname'),
                    DB::raw('COALESCE(p.othername, "") as othername'),
                    DB::raw('COALESCE(p.ssnit1a, tp.ssnit, "") as petra_id'),
                    DB::raw('COALESCE(p.national, "") as ghana_card'),
                    'tp.basic',
                    DB::raw('COALESCE(tp.petra, ROUND(tp.basic * 0.05, 2)) as petra_amount')
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.staffida', 'like', "%{$search}%")
                      ->orWhere('tp.name', 'like', "%{$search}%")
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

            $filename = "TranssionPetraPay_" . ($month ?? 'export') . ".xlsx";
            return SpreadsheetExporter::exportPetra($records, $filename);
        } catch (\Exception $e) {
            Log::error("TranssionPetraPay Export failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
