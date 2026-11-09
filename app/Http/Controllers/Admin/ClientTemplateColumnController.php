<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientTemplateColumn;
use App\Services\Payroll\TemplateBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientTemplateColumnController extends Controller
{
    protected TemplateBuilderService $templateBuilder;

    public function __construct(TemplateBuilderService $templateBuilder)
    {
        $this->templateBuilder = $templateBuilder;
    }

    const MODULES = ['payroll', 'ssnit', 'petra', 'bank', 'gra', 'contract'];

    /**
     * Get all column configs for a client across all modules.
     */
    public function index(Client $client): JsonResponse
    {
        $modules = self::MODULES;
        $columnsByModule = [];

        foreach ($modules as $module) {
            $cols = ClientTemplateColumn::forClientAndModule($client->client_dbname, $module)->get();

            if ($cols->isEmpty()) {
                // If not yet saved in database, seed defaults so they exist persistently
                ClientTemplateColumn::seedDefaultsForClient($client->id, $client->client_dbname, $module);
                $cols = ClientTemplateColumn::forClientAndModule($client->client_dbname, $module)->get();
            }

            $columnsByModule[$module] = $cols;
        }

        $otherClients = Client::select('id', 'client_name', 'client_dbname')
            ->where('id', '!=', $client->id)
            ->where('is_active', true)
            ->orderBy('client_name')
            ->get();

        return response()->json([
            'client'        => $client,
            'modules'       => $columnsByModule,
            'other_clients' => $otherClients,
        ]);
    }

    /**
     * Save/update column settings for a specific module of a client.
     */
    public function update(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'module'                  => ['required', 'string', 'in:payroll,ssnit,petra,bank,gra,contract'],
            'columns'                 => ['required', 'array', 'min:1'],
            'columns.*.column_key'    => ['required', 'string', 'max:100'],
            'columns.*.display_label' => ['required', 'string', 'max:150'],
            'columns.*.data_type'     => ['nullable', 'string', 'in:string,decimal,integer,date,boolean,text'],
            'columns.*.is_active'     => ['required', 'boolean'],
            'columns.*.is_required'   => ['nullable', 'boolean'],
            'columns.*.is_custom'     => ['nullable', 'boolean'],
            'columns.*.sort_order'    => ['nullable', 'integer'],
            'columns.*.sample_value'  => ['nullable', 'string', 'max:255'],
            'columns.*.header_aliases'=> ['nullable', 'string', 'max:255'],
        ]);

        $module = $validated['module'];
        $submittedColumns = $validated['columns'];

        try {
            DB::beginTransaction();

            // Clear existing rows for this client & module
            ClientTemplateColumn::where('client_dbname', $client->client_dbname)
                ->where('module', $module)
                ->delete();

            $insertRows = [];
            foreach ($submittedColumns as $idx => $col) {
                $columnKey = Str::slug($col['column_key'], '_');
                $insertRows[] = [
                    'client_id'      => $client->id,
                    'client_dbname'  => $client->client_dbname,
                    'module'         => $module,
                    'column_key'     => $columnKey,
                    'display_label'  => trim($col['display_label']),
                    'header_aliases' => $col['header_aliases'] ?? null,
                    'data_type'      => $col['data_type'] ?? 'string',
                    'is_active'      => (bool) ($col['is_active'] ?? true),
                    'is_required'    => (bool) ($col['is_required'] ?? false),
                    'is_custom'      => (bool) ($col['is_custom'] ?? false),
                    'sort_order'     => $col['sort_order'] ?? $idx,
                    'sample_value'   => $col['sample_value'] ?? null,
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }

            ClientTemplateColumn::insert($insertRows);

            DB::commit();

            $updatedColumns = ClientTemplateColumn::forClientAndModule($client->client_dbname, $module)->get();

            return response()->json([
                'success' => true,
                'message' => 'Template columns updated successfully for ' . ucfirst($module) . '!',
                'columns' => $updatedColumns,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update template columns: ' . $e->getMessage(), [
                'client_id' => $client->id,
                'module'    => $module,
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save template columns: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a single custom column to a module.
     */
    public function addCustomColumn(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'module'        => ['required', 'string', 'in:payroll,ssnit,petra,bank,gra,contract'],
            'display_label' => ['required', 'string', 'max:150'],
            'data_type'     => ['required', 'string', 'in:string,decimal,integer,date'],
            'sample_value'  => ['nullable', 'string', 'max:255'],
        ]);

        $module = $validated['module'];
        $label = trim($validated['display_label']);
        $columnKey = 'custom_' . Str::slug($label, '_');

        // Check if key already exists for this client and module
        $exists = ClientTemplateColumn::where('client_dbname', $client->client_dbname)
            ->where('module', $module)
            ->where('column_key', $columnKey)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'A column with this name already exists.',
            ], 422);
        }

        $maxOrder = ClientTemplateColumn::where('client_dbname', $client->client_dbname)
            ->where('module', $module)
            ->max('sort_order') ?? 0;

        $created = ClientTemplateColumn::create([
            'client_id'      => $client->id,
            'client_dbname'  => $client->client_dbname,
            'module'         => $module,
            'column_key'     => $columnKey,
            'display_label'  => $label,
            'header_aliases' => $columnKey . ',' . Str::slug($label, '_'),
            'data_type'      => $validated['data_type'],
            'is_active'      => true,
            'is_required'    => false,
            'is_custom'      => true,
            'sort_order'     => $maxOrder + 1,
            'sample_value'   => $validated['sample_value'] ?? ($validated['data_type'] === 'decimal' ? '0.00' : 'Sample'),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Custom column '{$label}' added successfully!",
            'column'  => $created,
        ]);
    }

    /**
     * Clone template column configuration from another client.
     */
    public function cloneFrom(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'source_client_dbname' => ['required', 'string', 'exists:client,client_dbname'],
        ]);

        $sourceDb = $validated['source_client_dbname'];

        try {
            DB::beginTransaction();
            ClientTemplateColumn::cloneFromClient($client->client_dbname, $client->id, $sourceDb);
            DB::commit();

            // Fetch newly cloned columns for all modules
            $modules = self::MODULES;
            $columnsByModule = [];
            foreach ($modules as $m) {
                $columnsByModule[$m] = ClientTemplateColumn::forClientAndModule($client->client_dbname, $m)->get();
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully cloned template columns from {$sourceDb}!",
                'modules' => $columnsByModule,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Clone failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to clone template columns: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset columns for a client module back to canonical standard.
     */
    public function resetToStandard(Request $request, Client $client): JsonResponse
    {
        $module = $request->input('module');

        try {
            DB::beginTransaction();

            if ($module && in_array($module, self::MODULES, true)) {
                ClientTemplateColumn::where('client_dbname', $client->client_dbname)
                    ->where('module', $module)
                    ->delete();
                ClientTemplateColumn::seedDefaultsForClient($client->id, $client->client_dbname, $module);
            } else {
                ClientTemplateColumn::where('client_dbname', $client->client_dbname)->delete();
                ClientTemplateColumn::seedAllModulesForClient($client->id, $client->client_dbname);
            }

            DB::commit();

            $modules = self::MODULES;
            $columnsByModule = [];
            foreach ($modules as $m) {
                $columnsByModule[$m] = ClientTemplateColumn::forClientAndModule($client->client_dbname, $m)->get();
            }

            return response()->json([
                'success' => true,
                'message' => 'Columns reset to standard successfully!',
                'modules' => $columnsByModule,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Reset failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset columns: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download the live Excel template for a client and module.
     */
    public function download(string $client_dbname, string $module): StreamedResponse
    {
        $allowed = self::MODULES;
        if (!in_array(strtolower($module), $allowed, true)) {
            abort(404, 'Invalid template module.');
        }

        return $this->templateBuilder->downloadResponse($client_dbname, strtolower($module));
    }
}
