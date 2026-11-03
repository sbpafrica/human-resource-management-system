<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PayrollColumn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PayrollColumnController extends Controller
{
    /**
     * Authorize that the current user is an Admin or HR Manager.
     */
    private function authorizeUser(Request $request): void
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $roles = $user->getRoleNames()->toArray();
        $hasHrRole = in_array('HR Manager', $roles)
            || in_array('Super Admin', $roles)
            || collect($roles)->contains(fn ($r) => str_contains(strtolower($r), 'hr manager'));

        if (!$user->is_super_admin && !$hasHrRole && !$user->can('manage users') && !$user->can('manage payroll columns')) {
            abort(403, 'Unauthorized access to Payroll Columns configuration.');
        }
    }

    /**
     * Display a listing of custom payroll columns per client.
     */
    public function index(Request $request)
    {
        $this->authorizeUser($request);

        $dbClients = Client::select('id', 'client_name', 'short_name', 'client_dbname')
            ->orderBy('client_name')
            ->get();

        $allOption = (object)[
            'id'            => 0,
            'client_name'   => '★ All Clients (Global Across System)',
            'short_name'    => 'All Clients',
            'client_dbname' => 'all',
        ];

        $clients = collect([$allOption])->concat($dbClients);

        $selectedClient = $request->input('client', 'all');

        $columns = PayrollColumn::where('client_dbname', $selectedClient)
            ->orderBy('id', 'asc')
            ->get();

        $existingColumns = $this->getClientTableColumns($selectedClient);

        return Inertia::render('Admin/PayrollColumns/Index', [
            'clients'         => $clients,
            'selectedClient'  => $selectedClient,
            'columns'         => $columns,
            'existingColumns' => $existingColumns,
            'flash'           => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * API to get existing table columns for dropdown positioning.
     */
    public function getColumns(Request $request)
    {
        $this->authorizeUser($request);

        $clientDb = $request->input('client_dbname', 'shefer');
        $columns = $this->getClientTableColumns($clientDb);
        return response()->json($columns);
    }

    /**
     * Store a newly created custom column and apply it to existing tables.
     */
    public function store(Request $request)
    {
        $this->authorizeUser($request);

        $validated = $request->validate([
            'client_dbname'   => ['required', 'string', 'max:64'],
            'display_label'   => ['required', 'string', 'max:128'],
            'column_name'     => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_]+$/'],
            'data_type'       => ['required', 'string', 'max:32'],
            'position_after'  => ['nullable', 'string', 'max:64'],
            'show_in_payslip' => ['boolean'],
            'payslip_section' => ['required', 'string', 'in:earnings,deductions,tax_reference,info'],
        ]);

        $colName = !empty($validated['column_name'])
            ? Str::slug($validated['column_name'], '_')
            : Str::slug($validated['display_label'], '_');

        // Check if column already exists in registry
        $existing = PayrollColumn::where('client_dbname', $validated['client_dbname'])
            ->where('column_name', $colName)
            ->first();

        if ($existing) {
            return redirect()->back()->with('error', "Column '{$colName}' already exists for this client.");
        }

        $client = Client::where('client_dbname', $validated['client_dbname'])->first();

        $column = PayrollColumn::create([
            'client_id'       => $client?->id,
            'client_dbname'   => $validated['client_dbname'],
            'column_name'     => $colName,
            'display_label'   => $validated['display_label'],
            'data_type'       => $validated['data_type'],
            'position_after'  => $validated['position_after'] ?: null,
            'show_in_payslip' => $request->boolean('show_in_payslip', false),
            'payslip_section' => $validated['payslip_section'] ?? 'earnings',
            'is_active'       => true,
        ]);

        // Auto-apply column to all existing client tables in database
        $appliedCount = PayrollColumn::applyToAllClientTables($column);

        return redirect()->route('admin.payroll-columns.index', ['client' => $validated['client_dbname']])
            ->with('success', "Column '{$column->display_label}' ({$colName}) created and added to {$appliedCount} database table(s).");
    }

    /**
     * Remove the specified custom column from registry.
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeUser($request);

        $column = PayrollColumn::findOrFail($id);
        $clientDb = $column->client_dbname;
        $name = $column->display_label;

        $column->delete();

        return redirect()->route('admin.payroll-columns.index', ['client' => $clientDb])
            ->with('success', "Column '{$name}' removed from custom payroll columns registry.");
    }

    /**
     * Toggle show in payslip flag.
     */
    public function togglePayslip(Request $request, $id)
    {
        $this->authorizeUser($request);

        $column = PayrollColumn::findOrFail($id);
        $column->show_in_payslip = !$column->show_in_payslip;
        $column->save();

        return redirect()->back()->with('success', "Payslip visibility for '{$column->display_label}' updated.");
    }

    /**
     * Helper to find columns of a client's latest/base table.
     */
    private function getClientTableColumns(string $clientDb): array
    {
        $prefix = strtolower(trim($clientDb));

        $prefixes = ($prefix === 'all')
            ? ['shefer', 'pz', 'melcom', 'nyonkopa']
            : PayrollColumn::getTablePrefixesForClient($prefix);

        $likeClauses = [];
        $bindings = [];
        foreach ($prefixes as $p) {
            $likeClauses[] = "TABLE_NAME = ? OR TABLE_NAME LIKE ?";
            $bindings[] = $p;
            $bindings[] = "{$p}%";
        }
        $whereLikes = implode(' OR ', $likeClauses);

        // Find candidate tables
        $tables = DB::select("
            SELECT TABLE_NAME 
            FROM INFORMATION_SCHEMA.TABLES 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND ({$whereLikes})
              AND TABLE_NAME NOT LIKE '%_sign'
              AND TABLE_NAME NOT LIKE '%_contracts%'
              AND TABLE_NAME NOT LIKE '%attendance%'
              AND TABLE_NAME NOT LIKE '%sms%'
              AND TABLE_NAME NOT LIKE '%data'
              AND TABLE_NAME NOT LIKE '%con'
            ORDER BY TABLE_NAME DESC
            LIMIT 10
        ", $bindings);

        foreach ($tables as $t) {
            $tableName = $t->TABLE_NAME;
            if (Schema::hasTable($tableName)) {
                $cols = Schema::getColumnListing($tableName);
                if (!empty($cols)) {
                    return $cols;
                }
            }
        }

        return ['staff_id', 'name', 'position', 'department', 'basic', 'gross', 'net_pay'];
    }
}

