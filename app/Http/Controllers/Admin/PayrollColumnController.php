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
            || collect($roles)->contains(fn ($r) => str_contains(strtolower($r), 'hr') || str_contains(strtolower($r), 'payroll'));

        if (!$user->is_super_admin && !$hasHrRole && !$user->can('manage users') && !$user->can('manage payroll columns') && !$user->can('view payroll')) {
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
            ->where('is_custom', true)
            ->orderBy('id', 'asc')
            ->get();

        $standardColumns = PayrollColumn::getStandardColumnsWithOverrides($selectedClient);
        $existingColumns = $this->getClientTableColumns($selectedClient);

        return Inertia::render('Admin/PayrollColumns/Index', [
            'clients'         => $clients,
            'selectedClient'  => $selectedClient,
            'columns'         => $columns,
            'standardColumns' => $standardColumns,
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
     * Update an existing custom column.
     * Preserves physical MySQL column_name to prevent database errors and historical data loss.
     * Updates display_label, header_aliases, position_after, show_in_payslip, payslip_section.
     * Automatically appends any previous display label to header_aliases for backward compatibility.
     */
    public function update(Request $request, $id)
    {
        $this->authorizeUser($request);

        $column = PayrollColumn::findOrFail($id);

        $validated = $request->validate([
            'display_label'   => ['required', 'string', 'max:128'],
            'header_aliases'  => ['nullable', 'string', 'max:500'],
            'position_after'  => ['nullable', 'string', 'max:64'],
            'show_in_payslip' => ['boolean'],
            'payslip_section' => ['required', 'string', 'in:earnings,deductions,tax_reference,info'],
        ]);

        $oldLabel = $column->display_label;
        $newLabel = trim($validated['display_label']);

        // Collect existing and new aliases
        $aliasesArray = array_filter(array_map('trim', preg_split('/[,;|\n\r]+/', (string)($validated['header_aliases'] ?? ''))));

        // If display label changed, ensure old display label is preserved as an alias
        if ($oldLabel !== $newLabel && !in_array($oldLabel, $aliasesArray, true)) {
            array_unshift($aliasesArray, $oldLabel);
        }

        // Also ensure column_name is preserved as an alias
        if (!in_array($column->column_name, $aliasesArray, true)) {
            $aliasesArray[] = $column->column_name;
        }

        $column->display_label   = $newLabel;
        $column->header_aliases  = implode(', ', array_unique($aliasesArray));
        if (array_key_exists('position_after', $validated)) {
            $column->position_after = ($validated['position_after'] === 'END' || empty($validated['position_after'])) ? null : $validated['position_after'];
        }
        $column->show_in_payslip = $request->boolean('show_in_payslip', false);
        $column->payslip_section = $validated['payslip_section'] ?? 'earnings';

        // NOTE: $column->column_name is strictly NEVER altered in MySQL tables.
        // This ensures existing payroll tables (e.g. sheferoctober26) and queries remain 100% stable.
        $column->save();

        return redirect()->route('admin.payroll-columns.index', ['client' => $column->client_dbname])
            ->with('success', "Column '{$column->display_label}' updated. Excel uploads will now recognize new names and preserve old formats securely.");
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
     * Update or create an override for a standard base payroll column.
     * Core identifier (staff_id) is strictly locked and cannot be renamed.
     */
    public function updateStandard(Request $request)
    {
        $this->authorizeUser($request);

        $validated = $request->validate([
            'client_dbname'   => ['required', 'string', 'max:64'],
            'column_name'     => ['required', 'string', 'max:64'],
            'display_label'   => ['required', 'string', 'max:128'],
            'header_aliases'  => ['nullable', 'string', 'max:500'],
            'show_in_payslip' => ['boolean'],
            'payslip_section' => ['required', 'string', 'in:earnings,deductions,tax_reference,info'],
        ]);

        $colName = strtolower(trim($validated['column_name']));

        // Enforce user requirement: "no changing of id"
        if (in_array($colName, ['staff_id', 'staffid', 'id', 'employid'], true)) {
            return redirect()->back()->with('error', 'Staff ID is a protected core identifier and cannot be renamed.');
        }

        $canonical = PayrollColumn::getCanonicalStandardColumns();
        if (!isset($canonical[$colName])) {
            return redirect()->back()->with('error', "Unrecognized standard column: '{$colName}'.");
        }

        $client = Client::where('client_dbname', $validated['client_dbname'])->first();

        $record = PayrollColumn::where('client_dbname', $validated['client_dbname'])
            ->where('column_name', $colName)
            ->first();

        $aliasesArray = array_filter(array_map('trim', preg_split('/[,;|\n\r]+/', (string)($validated['header_aliases'] ?? ''))));
        if (!in_array($canonical[$colName]['display_label'], $aliasesArray, true)) {
            $aliasesArray[] = $canonical[$colName]['display_label'];
        }
        if (!in_array($colName, $aliasesArray, true)) {
            $aliasesArray[] = $colName;
        }

        if ($record) {
            $record->display_label   = trim($validated['display_label']);
            $record->header_aliases  = implode(', ', array_unique($aliasesArray));
            $record->show_in_payslip = $request->boolean('show_in_payslip', true);
            $record->payslip_section = $validated['payslip_section'];
            $record->is_custom       = false;
            $record->save();
        } else {
            PayrollColumn::create([
                'client_id'       => $client?->id,
                'client_dbname'   => $validated['client_dbname'],
                'is_custom'       => false,
                'column_name'     => $colName,
                'display_label'   => trim($validated['display_label']),
                'header_aliases'  => implode(', ', array_unique($aliasesArray)),
                'data_type'       => $canonical[$colName]['data_type'],
                'position_after'  => null,
                'show_in_payslip' => $request->boolean('show_in_payslip', true),
                'payslip_section' => $validated['payslip_section'],
                'is_active'       => true,
            ]);
        }

        return redirect()->route('admin.payroll-columns.index', ['client' => $validated['client_dbname']])
            ->with('success', "Standard column '{$colName}' updated. New label '{$validated['display_label']}' will reflect in payslips, templates, and map changed Excel headers securely.");
    }

    /**
     * Reset a standard column override back to system default.
     */
    public function resetStandard(Request $request)
    {
        $this->authorizeUser($request);

        $validated = $request->validate([
            'client_dbname' => ['required', 'string', 'max:64'],
            'column_name'   => ['required', 'string', 'max:64'],
        ]);

        PayrollColumn::where('client_dbname', $validated['client_dbname'])
            ->where('column_name', $validated['column_name'])
            ->where('is_custom', false)
            ->delete();

        return redirect()->route('admin.payroll-columns.index', ['client' => $validated['client_dbname']])
            ->with('success', "Standard column '{$validated['column_name']}' reset to system default.");
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
