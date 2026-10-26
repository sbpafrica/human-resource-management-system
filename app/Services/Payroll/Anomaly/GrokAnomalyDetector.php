<?php

namespace App\Services\Payroll\Anomaly;

use App\Services\AI\Contracts\AnomalyDetectorInterface;
use App\Services\AI\GrokAiClient;
use App\Services\AI\AiPayloadSanitizer;
use Illuminate\Support\Facades\Log;
use Throwable;

class GrokAnomalyDetector implements AnomalyDetectorInterface
{
    public function __construct(
        protected GrokAiClient $grokClient,
        protected AiPayloadSanitizer $sanitizer
    ) {}

    public function detect(array $records, string $clientSlug, string $payrollPeriod, array $historicalRecords = []): array
    {
        if (!$this->grokClient->isConfigured()) {
            return [
                'total_records' => count($records),
                'passed' => true,
                'summary' => ['critical_count' => 0, 'warning_count' => 0, 'info_count' => 0],
                'anomalies' => [],
            ];
        }

        try {
            // Anonymize records according to Ghana Data Protection Act Act 843
            $sanitizedData = $this->sanitizer->sanitizeForAnomalyDetection($records);
            $tokenMap = $sanitizedData['token_map']; // [EMP_0001 => staff_id]

            // Reverse map staff_id to employee name for enrichment
            $nameMap = [];
            foreach ($records as $r) {
                if (!empty($r['staff_id'])) {
                    $nameMap[(string) $r['staff_id']] = (string) ($r['name'] ?? '');
                }
            }

            $systemPrompt = <<<PROMPT
You are an expert Forensic Payroll Auditor examining payroll runs in Ghana.
You evaluate anonymized employee records to catch payroll fraud, accidental overpayments, and arithmetic discrepancies.

Your goals:
1. Salary Spikes: Flag any employee whose gross pay deviates by more than 20% from their peers/historical norm without corresponding overtime or additional work days.
2. Irregular Deductions: Flag unusual deductions that distort take-home pay.
3. Outlier Pay Rates: Flag abnormally high daily or overtime rates.

Respond ONLY with a valid JSON object matching this schema:
{
  "anomalies": [
    {
      "token": "EMP_0001",
      "type": "salary_spike",
      "severity": "warning",
      "field": "gross_salary",
      "message": "Gross pay is 45% higher than group baseline without logged overtime hours.",
      "suggested_fix": "Verify if a bonus or promotion was approved for this cycle."
    }
  ]
}
Severity must be "critical", "warning", or "info".
PROMPT;

            // Send batches of up to 100 rows to keep within context limits
            $batches = array_chunk($sanitizedData['sanitized'], 100);
            $allAnomalies = [];

            foreach ($batches as $batchIndex => $batch) {
                $userPayload = [
                    'client' => $clientSlug,
                    'period' => $payrollPeriod,
                    'batch' => $batchIndex + 1,
                    'records' => $batch,
                ];

                $response = $this->grokClient->chatJson($systemPrompt, json_encode($userPayload));

                if ($response && isset($response['anomalies']) && is_array($response['anomalies'])) {
                    foreach ($response['anomalies'] as $item) {
                        $token = (string) ($item['token'] ?? '');
                        $realStaffId = $tokenMap[$token] ?? null;

                        if ($realStaffId) {
                            $allAnomalies[] = [
                                'type' => (string) ($item['type'] ?? 'ai_detected_anomaly'),
                                'severity' => in_array($item['severity'] ?? '', ['critical', 'warning', 'info']) ? $item['severity'] : 'warning',
                                'staff_id' => $realStaffId,
                                'employee_name' => $nameMap[$realStaffId] ?? null,
                                'field' => (string) ($item['field'] ?? 'payroll'),
                                'message' => (string) ($item['message'] ?? 'AI detected potential irregularity.'),
                                'suggested_fix' => (string) ($item['suggested_fix'] ?? 'Review record with department head.'),
                                'metadata' => ['token' => $token, 'source' => 'grok_ai'],
                            ];
                        }
                    }
                }
            }

            $criticalCount = count(array_filter($allAnomalies, fn($a) => $a['severity'] === 'critical'));
            $warningCount = count(array_filter($allAnomalies, fn($a) => $a['severity'] === 'warning'));
            $infoCount = count(array_filter($allAnomalies, fn($a) => $a['severity'] === 'info'));

            return [
                'total_records' => count($records),
                'passed' => $criticalCount === 0,
                'summary' => [
                    'critical_count' => $criticalCount,
                    'warning_count' => $warningCount,
                    'info_count' => $infoCount,
                ],
                'anomalies' => $allAnomalies,
            ];
        } catch (Throwable $e) {
            Log::channel('payroll_ai')->error("GrokAnomalyDetector exception for {$clientSlug}: " . $e->getMessage());
            return [
                'total_records' => count($records),
                'passed' => true,
                'summary' => ['critical_count' => 0, 'warning_count' => 0, 'info_count' => 0],
                'anomalies' => [],
            ];
        }
    }
}
