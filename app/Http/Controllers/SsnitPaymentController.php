<?php

namespace App\Http\Controllers;

use App\Services\Export\SpreadsheetExporter;

use App\Models\MonthlySalary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class SsnitPaymentController extends Controller
{
    public function index()
    {
        $clients = DB::table('client')
            ->select('client_dbname', 'name', 'short_name', 'color', 'is_active', 'display_order')
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return Inertia::render('SsnitPayment/Index', ['clients' => $clients]);
    }

    public function showClient(Request $request, $client_dbname)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $formattedMonth = $date->format('F Y');
        $tableName = MonthlySalary::generateTableName($client_dbname, $month);

        $client = DB::table('client')->where('client_dbname', $client_dbname)->firstOrFail();

        if (!Schema::hasTable($tableName)) {
            return Inertia::render('SsnitPayment/Client', [
                'client' => $client,
                'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25),
                'selectedMonth' => $month,
                'formattedMonth' => $formattedMonth,
                'totalRecords' => 0,
                'missingSsnitCount' => 0,
                'searchCount' => null,
                'flash' => ['error' => "No payroll data for {$formattedMonth}."],
            ]);
        }

        $basicColumn = $this->detectColumn($tableName, ['basic', 'BASICSALARY_GHC', 'base_salary', 'basic_salary', 'basic_earned', 'salary', 'BASICSALARYEARNED_GHC'], 'basic');
        $shopColumn = $this->detectColumn($tableName, ['shopname', 'workplace', 'department', 'shop', 'work_location', 'workarea', 'dept']);

        $shopExpr = $shopColumn ? "COALESCE(s.shop, p.{$shopColumn})" : "COALESCE(s.shop, '')";
        $basicExpr = $basicColumn ? "COALESCE(p.{$basicColumn}, 0)" : "0";

        $query = DB::table("{$tableName} as p")
            ->leftJoin('ssnit as s', 'p.staffida', '=', 's.staffid')
            ->selectRaw("
                p.staffida as staff_id,
                {$shopExpr} as shop,
                s.lastname as ssnit_lastname,
                s.firstname as ssnit_firstname,
                s.othername as ssnit_othername,
                p.name as salary_name,
                COALESCE(NULLIF(TRIM(s.ssnit1a), ''), '') as ssnit_number,
                COALESCE(NULLIF(TRIM(s.national), ''), '') as gh_card_number,
                {$basicExpr} as basic,
                ROUND({$basicExpr} * 0.135, 2) as ssnit_amount,
                CASE WHEN s.ssnit1a IS NULL OR TRIM(s.ssnit1a) = '' THEN 1 ELSE 0 END as missing_ssnit
            ");

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('p.staffida', 'like', "%{$search}%")
                  ->orWhere('p.name', 'like', "%{$search}%")
                  ->orWhereRaw("CONCAT(s.firstname, ' ', s.lastname, ' ', s.othername) LIKE ?", ["%{$search}%"])
                  ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                  ->orWhere('s.national', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');

        $sortMap = [
            'staff_id' => 'p.staffida',
            'shop' => $shopExpr,
            'full_name' => 'p.name',
            'lastname' => 's.lastname',
            'firstname' => 's.firstname',
            'ssnit_number' => 's.ssnit1a',
            'gh_card_number' => 's.national',
            'basic' => $basicExpr,
            'ssnit_amount' => 'ssnit_amount',
        ];

        $sortColumn = $sortMap[$sort] ?? 'p.staffida';
        $query->orderByRaw("ISNULL($sortColumn), $sortColumn {$order}");

        $perPage = 25;
        $records = $query->paginate($perPage)->appends($request->query())->through(function ($r) {
            $salaryName = trim($r->salary_name ?? '');
            $lastName = trim($r->ssnit_lastname ?? '');
            $firstName = trim($r->ssnit_firstname ?? '');
            $otherName = trim($r->ssnit_othername ?? '');

            if ($lastName === '' && $firstName === '' && $salaryName !== '') {
                $parts = preg_split('/\s+/', $salaryName);
                if (count($parts) === 1) {
                    $firstName = $parts[0];
                    $lastName = $parts[0];
                } elseif (count($parts) === 2) {
                    $lastName = $parts[0];
                    $firstName = $parts[1];
                } else {
                    $lastName = array_shift($parts);
                    $firstName = array_shift($parts);
                    $otherName = implode(' ', $parts);
                }
            }

            $fullName = trim("{$lastName} {$firstName} {$otherName}") ?: $salaryName;

            return [
                'staff_id' => $r->staff_id,
                'shop' => $r->shop ?? '',
                'lastname' => $lastName,
                'firstname' => $firstName,
                'othername' => $otherName,
                'full_name' => $fullName,
                'ssnit_number' => $r->ssnit_number ?? '',
                'gh_card_number' => $r->gh_card_number ?? '',
                'basic' => (float)$r->basic,
                'ssnit_amount' => (float)$r->ssnit_amount,
                'missing_ssnit' => (int)$r->missing_ssnit,
            ];
        });

        $totalRecords = DB::table($tableName)->count();
        $missingSsnitCount = DB::table("{$tableName} as p")
            ->leftJoin('ssnit as s', 'p.staffida', '=', 's.staffid')
            ->where(function ($q) {
                $q->whereNull('s.ssnit1a')->orWhere('s.ssnit1a', '=', '');
            })
            ->count();

        $searchCount = $search ? $records->total() : null;

        return Inertia::render('SsnitPayment/Client', [
            'client' => $client,
            'records' => $records,
            'selectedMonth' => $month,
            'formattedMonth' => $formattedMonth,
            'totalRecords' => $totalRecords,
            'missingSsnitCount' => $missingSsnitCount,
            'searchCount' => $searchCount,
            'flash' => session('flash'),
        ]);
    }

    public function export(Request $request, $client_dbname)
    {
        try {
            $month = $request->query('month');
            $search = $request->query('search');
            $sort = $request->query('sort', 'staff_id');
            $order = $request->query('order', 'asc');

            $tableName = MonthlySalary::generateTableName($client_dbname, $month);

            if (!Schema::hasTable($tableName)) {
                return response()->json(['error' => 'No payroll data for this month'], 404);
            }

            $basicColumn = $this->detectColumn($tableName, ['basic', 'BASICSALARY_GHC', 'base_salary', 'basic_salary', 'basic_earned', 'salary'], 'basic');
            $shopColumn = $this->detectColumn($tableName, ['shopname', 'workplace', 'department', 'shop', 'work_location', 'workarea', 'dept']);

            $shopExpr = $shopColumn ? "COALESCE(s.shop, p.{$shopColumn})" : "COALESCE(s.shop, '')";
            $basicExpr = $basicColumn ? "COALESCE(p.{$basicColumn}, 0)" : "0";

            $query = DB::table("{$tableName} as p")
                ->leftJoin('ssnit as s', 'p.staffida', '=', 's.staffid')
                ->selectRaw("
                    p.staffida as staff_id,
                    {$shopExpr} as shop,
                    s.lastname as ssnit_lastname,
                    s.firstname as ssnit_firstname,
                    s.othername as ssnit_othername,
                    p.name as salary_name,
                    COALESCE(NULLIF(TRIM(s.ssnit1a), ''), '') as ssnit_number,
                    COALESCE(NULLIF(TRIM(s.national), ''), '') as gh_card_number,
                    {$basicExpr} as basic,
                    ROUND({$basicExpr} * 0.135, 2) as ssnit_amount
                ");

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('p.staffida', 'like', "%{$search}%")
                      ->orWhere('p.name', 'like', "%{$search}%")
                      ->orWhereRaw("CONCAT(s.firstname, ' ', s.lastname) LIKE ?", ["%{$search}%"])
                      ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                      ->orWhere('s.national', 'like', "%{$search}%");
                });
            }

            $sortMap = [
                'staff_id' => 'p.staffida',
                'shop' => $shopExpr,
                'full_name' => 'p.name',
                'lastname' => 's.lastname',
                'firstname' => 's.firstname',
                'ssnit_number' => 's.ssnit1a',
                'gh_card_number' => 's.national',
                'basic' => $basicExpr,
                'ssnit_amount' => 'ssnit_amount',
            ];

            $sortColumn = $sortMap[$sort] ?? 'p.staffida';
            $query->orderByRaw("ISNULL($sortColumn), $sortColumn {$order}");

            $records = $query->get()->map(function ($r) {
                $salaryName = trim($r->salary_name ?? '');
                $lastName = trim($r->ssnit_lastname ?? '');
                $firstName = trim($r->ssnit_firstname ?? '');
                $otherName = trim($r->ssnit_othername ?? '');

                if ($lastName === '' && $firstName === '' && $salaryName !== '') {
                    $parts = preg_split('/\s+/', $salaryName);
                    if (count($parts) === 1) {
                        $firstName = $parts[0];
                        $lastName = $parts[0];
                    } elseif (count($parts) === 2) {
                        $lastName = $parts[0];
                        $firstName = $parts[1];
                    } else {
                        $lastName = array_shift($parts);
                        $firstName = array_shift($parts);
                        $otherName = implode(' ', $parts);
                    }
                }

                $fullName = trim("{$lastName} {$firstName} {$otherName}") ?: $salaryName;

                return [
                    'staff_id' => $r->staff_id,
                    'staffid' => $r->staff_id,
                    'shop' => $r->shop ?? '',
                    'lastname' => $lastName,
                    'firstname' => $firstName,
                    'othername' => $otherName,
                    'full_name' => $fullName,
                    'name' => $fullName,
                    'ssnit_number' => $r->ssnit_number ?? '',
                    'gh_card_number' => $r->gh_card_number ?? '',
                    'basic' => number_format((float)$r->basic, 2, '.', ''),
                    'ssnit_amount' => number_format((float)$r->ssnit_amount, 2, '.', ''),
                ];
            });

            // Return JSON if requested via API/Fetch or download Excel binary stream
            if ($request->wantsJson() && !$request->has('download')) {
                return response()->json($records);
            }

            $filename = "SsnitPayment_" . ($month ?? 'export') . ".xlsx";
            return SpreadsheetExporter::exportSsnit($records, $filename);

        } catch (\Exception $e) {
            Log::error('SSNIT Export failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Export failed. Please try again.'], 500);
        }
    }

    private function detectColumn($tableName, array $possibleColumns, ?string $fallback = null): ?string
    {
        $allColumns = array_map('strtolower', Schema::getColumnListing($tableName));

        foreach ($possibleColumns as $col) {
            $key = array_search(strtolower($col), $allColumns, true);
            if ($key !== false) {
                return Schema::getColumnListing($tableName)[$key];
            }
        }

        // If fallback requested (e.g. searching for basic salary), check for partial match
        if ($fallback !== null) {
            foreach ($allColumns as $i => $col) {
                if (str_contains($col, 'basic') || str_contains($col, 'salary')) {
                    return Schema::getColumnListing($tableName)[$i];
                }
            }
            if (in_array(strtolower($fallback), $allColumns, true)) {
                return $fallback;
            }
        }

        return null;
    }
}
