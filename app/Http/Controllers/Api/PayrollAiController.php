<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payroll\DynamicIngestionService;
use App\Services\Payroll\ColumnMatcher\HybridColumnMatcher;
use App\Services\Payroll\Anomaly\HybridAnomalyDetector;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
     * Pre-flight spreadsheet inspection:
     * Reads spreadsheet via PhpSpreadsheet, resolves columns (Grok AI -> Rules -> Legacy),
     * and performs fraud & anomaly audits BEFORE committing to database.
     */
    public function inspectSpreadsheet(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
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
