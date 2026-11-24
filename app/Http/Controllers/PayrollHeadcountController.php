<?php

namespace App\Http\Controllers;

use App\Services\PayrollHeadcountService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PayrollHeadcountController extends Controller
{
    protected PayrollHeadcountService $service;

    public function __construct(PayrollHeadcountService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = (bool)($user->is_super_admin ?? false);

        if ($isSuperAdmin) {
            $allowedClients = \App\Models\Client::where('is_active', true)->get();
        } else {
            $allowedClients = $user->clients()->where('is_active', true)->get();
        }

        // Exclude foreign branches (Benin, Togo, CIV, Transburkina)
        $allowedClients = $allowedClients->reject(function ($client) {
            $name  = strtolower($client->client_name ?? '');
            $short = strtolower($client->short_name ?? '');
            $db    = strtolower($client->client_dbname ?? '');

            return str_contains($name, 'benin') || str_contains($short, 'benin') || str_contains($db, 'benin')
                || str_contains($name, 'togo')  || str_contains($short, 'togo')  || str_contains($db, 'togo')
                || str_contains($name, 'civ')   || str_contains($short, 'civ')   || str_contains($db, 'civ');
        })->values();

        $selectedYear = $request->filled('year') ? (int)$request->input('year') : null;
        $forceRefresh = $request->boolean('refresh');

        $headcountData = $this->service->getHeadcountData($allowedClients, $selectedYear, $forceRefresh);

        if ($request->wantsJson() || $request->query('json')) {
            return response()->json($headcountData);
        }

        return Inertia::render('Payroll/Headcount/Index', [
            'headcountData' => $headcountData,
            'selectedYear'  => $headcountData['current_year'],
        ]);
    }

    public function getEmployees(Request $request)
    {
        $table  = (string)$request->input('table', '');
        $search = $request->input('search');

        if (!$table || !preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            return response()->json(['employees' => [], 'error' => 'Invalid table name'], 400);
        }

        $employees = $this->service->getEmployeesForTable($table, $search);

        return response()->json([
            'table'     => $table,
            'count'     => count($employees),
            'employees' => $employees,
        ]);
    }

    public function getFinancials(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = (bool) (
            ($user->is_super_admin ?? false) ||
            (method_exists($user, 'hasRole') && ($user->hasRole('Super Admin') || $user->hasRole('super-admin'))) ||
            in_array('Super Admin', (array)($user->getRoleNames() ? $user->getRoleNames()->toArray() : []))
        );
        if (!$isSuperAdmin) {
            return response()->json(['error' => 'Unauthorized. Super Admin access required.'], 403);
        }

        $allowedClients = \App\Models\Client::where('is_active', true)->get();
        $month = $request->input('month');

        $data = $this->service->getFinancialsForMonth($allowedClients, $month);

        return response()->json($data);
    }
}
