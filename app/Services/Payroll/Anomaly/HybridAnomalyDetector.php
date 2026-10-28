<?php

namespace App\Services\Payroll\Anomaly;

use App\Services\AI\Contracts\AnomalyDetectorInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class HybridAnomalyDetector implements AnomalyDetectorInterface
{
    public function __construct(
        protected RuleBasedAnomalyDetector $ruleDetector,
        protected GrokAnomalyDetector $grokDetector
    ) {}

    /**
     * Executes unified pre-flight audit:
     * Deterministic PHP Rule Engine + Grok AI Predictive Engine.
     */
    public function detect(array $records, string $clientSlug, string $payrollPeriod, array $historicalRecords = []): array
    {
        Log::channel('payroll_ai')->info("Starting pre-flight audit for {$clientSlug} ({$payrollPeriod}) on " . count($records) . " records.");

        // 1. Always execute Tier 2 Deterministic PHP Rules first (Zero Cost, 100% Reliable)
        $ruleResult = $this->ruleDetector->detect($records, $clientSlug, $payrollPeriod, $historicalRecords);
        $mergedAnomalies = $ruleResult['anomalies'];

        // 2. Check if AI Anomaly Detection is enabled for this client
        $aiEnabled = (bool) config('ai.enabled', true)
            && (bool) config('ai.features.anomaly_detection', true)
            && (bool) config("ai.clients.{$clientSlug}.enabled", true);

        if ($aiEnabled) {
            try {
                Log::channel('payroll_ai')->info("Running Tier 1 Grok AI anomaly checks for {$clientSlug}");
                $aiResult = $this->grokDetector->detect($records, $clientSlug, $payrollPeriod, $historicalRecords);

                // Merge AI anomalies, preventing duplicates on same staff_id and type
                $existingKeys = [];
                foreach ($mergedAnomalies as $anomaly) {
                    $key = ($anomaly['staff_id'] ?? '') . ':' . ($anomaly['type'] ?? '');
                    $existingKeys[$key] = true;
                }

                foreach ($aiResult['anomalies'] as $aiAnomaly) {
                    $key = ($aiAnomaly['staff_id'] ?? '') . ':' . ($aiAnomaly['type'] ?? '');
                    if (!isset($existingKeys[$key])) {
                        $mergedAnomalies[] = $aiAnomaly;
                        $existingKeys[$key] = true;
                    }
                }
            } catch (Throwable $e) {
                Log::channel('payroll_ai')->error("Grok AI anomaly detection failed for {$clientSlug}: {$e->getMessage()}. Continuing with PHP rule results.");
            }
        }

        // 3. Compute final audit summary
        $criticalCount = count(array_filter($mergedAnomalies, fn($a) => ($a['severity'] ?? '') === 'critical'));
        $warningCount = count(array_filter($mergedAnomalies, fn($a) => ($a['severity'] ?? '') === 'warning'));
        $infoCount = count(array_filter($mergedAnomalies, fn($a) => ($a['severity'] ?? '') === 'info'));

        $auditScore = 100;
        $auditScore -= ($criticalCount * 25);
        $auditScore -= ($warningCount * 5);
        $auditScore -= ($infoCount * 1);
        $auditScore = max(0, min(100, $auditScore));

        return [
            'client_slug' => $clientSlug,
            'payroll_period' => $payrollPeriod,
            'total_records' => count($records),
            'audit_score' => $auditScore,
            'ready_for_approval' => $criticalCount === 0,
            'summary' => [
                'critical_count' => $criticalCount,
                'warning_count' => $warningCount,
                'info_count' => $infoCount,
            ],
            'anomalies' => $mergedAnomalies,
        ];
    }
}
