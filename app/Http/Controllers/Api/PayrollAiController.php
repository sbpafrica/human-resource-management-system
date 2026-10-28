<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Payroll\DynamicIngestionService;
use App\Services\Payroll\ColumnMatcher\HybridColumnMatcher;
use App\Services\Payroll\Anomaly\HybridAnomalyDetector;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Carbon\Carbon;
use Throwable;

class PayrollAiController extends Controller
{
    public function __construct(
        protected DynamicIngestionService $ingestionService,
        protected HybridColumnMatcher $columnMatcher,
        protected HybridAnomalyDetector $anomalyDetector
    ) {}

    /**
     * Renders the Pre-Flight Ingestion & Anomaly Audit UI page.
     */
    public function index(Request $request): InertiaResponse
    {
        try {
            $clients = Client::where('is_active', 1)
                ->orderBy('display_order')
                ->orderBy('client_name')
                ->get(['client_name', 'slug']);
        } catch (Throwable) {
            $clients = collect([]);
        }

        if ($clients->isEmpty()) {
            $clients = collect([
                ['client_name' => 'Bunge Benin', 'slug' => 'beninbunge'],
                ['client_name' => 'Bunge Togo', 'slug' => 'togobunge'],
                ['client_name' => 'Bunge Côte d\'Ivoire (CIV)', 'slug' => 'civ'],
                ['client_name' => 'Bunge Ghana', 'slug' => 'bunge'],
                ['client_name' => 'Jay Kay Global', 'slug' => 'jaykay'],
                ['client_name' => 'Melcom Distribution', 'slug' => 'melcom'],
                ['client_name' => 'Twellium Industrial (Kumasi)', 'slug' => 'twellium'],
                ['client_name' => 'Twellium Accra', 'slug' => 'twelliumaccra'],
                ['client_name' => 'Transsion / Good Ease', 'slug' => 'transsion'],
                ['client_name' => 'OFI Bean Warehouse', 'slug' => 'ofibwh'],
            ]);
        }

        return Inertia::render('PayrollAi/Inspect', [
            'clients' => $clients,
            'defaultMonth' => Carbon::now()->format('Y-m'),
        ]);
    }

    /**
     * Pre-flight spreadsheet inspection:
     * Reads spreadsheet via PhpSpreadsheet, resolves columns (Grok AI -> Rules -> Legacy),
     * and performs fraud & anomaly audits BEFORE committing to database.
     */
    public function inspectSpreadsheet(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv,ods', 'max:5120'],
            'client_slug' => ['required', 'string'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $file = $request->file('file');
        $clientSlug = strtolower(trim($request->input('client_slug')));
        $month = $request->input('month');

        $result = $this->ingestionService->process($file, $clientSlug, $month);

        return response()->json($result);
    }

    /**
     * Audits an already imported monthly client table.
     */
    public function auditExistingMonth(Request $request): JsonResponse
    {
        $request->validate([
            'table_name' => ['required', 'string'],
            'client_slug' => ['required', 'string'],
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $tableName = $request->input('table_name');
        $clientSlug = strtolower(trim($request->input('client_slug')));
        $month = $request->input('month');

        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json([
                'success' => false,
                'error' => "Table {$tableName} does not exist.",
            ], 404);
        }

        $records = DB::table($tableName)->get()->map(fn($row) => (array) $row)->toArray();

        $auditReport = $this->anomalyDetector->detect($records, $clientSlug, $month);

        return response()->json([
            'success' => true,
            'table_name' => $tableName,
            'client_slug' => $clientSlug,
            'month' => $month,
            'audit' => $auditReport,
        ]);
    }

    /**
     * Resolves raw spreadsheet headers to standard fields.
     */
    public function matchHeaders(Request $request): JsonResponse
    {
        $request->validate([
            'headers' => ['required', 'array'],
            'client_slug' => ['required', 'string'],
            'sample_rows' => ['nullable', 'array'],
        ]);

        $headers = $request->input('headers');
        $clientSlug = strtolower(trim($request->input('client_slug')));
        $sampleRows = $request->input('sample_rows', []);

        $result = $this->columnMatcher->match($headers, $clientSlug, $sampleRows);

        return response()->json($result);
    }
}
