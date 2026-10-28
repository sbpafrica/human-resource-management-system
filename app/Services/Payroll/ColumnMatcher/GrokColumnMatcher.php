<?php

namespace App\Services\Payroll\ColumnMatcher;

use App\Services\AI\Contracts\ColumnMatcherInterface;
use App\Services\AI\GrokAiClient;
use App\Services\AI\AiPayloadSanitizer;
use Illuminate\Support\Facades\Log;
use Throwable;

class GrokColumnMatcher implements ColumnMatcherInterface
{
    public function __construct(
        protected GrokAiClient $grokClient,
        protected AiPayloadSanitizer $sanitizer
    ) {}

    /**
     * Tier 1: Uses Grok AI to match complex, messy, or renamed spreadsheet headers.
     */
    public function match(array $headers, string $clientSlug, array $sampleRows = []): array
    {
        if (!$this->grokClient->isConfigured()) {
            return [
                'mapping' => [],
                'confidence' => 0.0,
                'tier' => 'ai',
                'unmatched' => $headers,
            ];
        }

        try {
            // Anonymize and sanitize inputs according to Ghana Act 843
            $sanitized = $this->sanitizer->sanitizeForColumnMatching($headers, $sampleRows);

            $systemPrompt = <<<PROMPT
You are a Ghanaian Payroll & HR Data Engineering specialist for HRS (Human Resource Management System).
Your task is to analyze spreadsheet column headers from client payroll files and map them to standard HRS database fields.

The standard internal fields are:
- "staff_id": Unique identifier for the employee (e.g. Staff ID, Emp ID, Matricule, ID No)
- "name": Full name of the employee
- "position": Job title, designation, department, or shop
- "join_date": Date of employment
- "basic_salary": Fixed monthly base salary or basic pay
- "legalworkdays": Standard or statutory work days in the month
- "daily_rate": Daily pay rate or daily tonnage rate
- "days_worked": Actual days worked in the month (e.g. Total1, Actual Days, Days)
- "absentee_days": Number of days absent
- "absent_deductions": Deduction amount for unexcused absence
- "earned_basic_salary": Basic salary earned based on actual days worked
- "saturdays_worked": Days or count of Saturday work
- "saturday_rate": Saturday pay rate
- "saturday_amount": Total earnings for Saturday work
- "total_days_amount": Total base earnings from days worked
- "overtime_hours": Overtime hours worked
- "overtime_rate": Hourly overtime rate
- "overtime_amount": Total overtime pay
- "night_allowance": Night shift allowance
- "totalovertime": Cumulative overtime earnings
- "gross_salary": Gross earnings before statutory deductions (Total Gross, Gross Salary, Total Earn)
- "ssnit_ee": Social Security Tier 1 Employee 5.5% deduction (SSNIT EE, SSF EE)
- "petra": Petra Tier 2 Employee 5.0% provident fund / pension deduction
- "taxable_income": Net taxable earnings for GRA PAYE calculation
- "paye": GRA Personal Income Tax (P.A.Y.E.)
- "overtime_tax": Tax withheld specifically on overtime earnings
- "total_deductions": Sum of all statutory and non-statutory deductions
- "net_pay": Final take-home pay transferred to employee bank account / MoMo
- "bank": Bank name
- "account": Bank account number or mobile money number

Respond ONLY with a JSON object matching this schema:
{
  "confidence": 0.95,
  "mapping": {
    "staff_id": 0,
    "name": 1,
    "basic_salary": 4,
    "net_pay": 34
  },
  "unmatched": ["Extra Column 1"]
}
Confidence must be a float between 0.0 and 1.0. The mapped values must be integer column indices (0-indexed).
PROMPT;

            $userPrompt = json_encode([
                'client_slug' => $clientSlug,
                'headers_with_indices' => $sanitized['headers'],
                'sample_data_types' => $sanitized['samples'],
            ], JSON_PRETTY_PRINT);

            $response = $this->grokClient->chatJson($systemPrompt, (string) $userPrompt);

            if (!$response || !isset($response['mapping']) || !is_array($response['mapping'])) {
                Log::channel('payroll_ai')->warning("Grok AI column matching returned invalid format for {$clientSlug}.");
                return [
                    'mapping' => [],
                    'confidence' => 0.0,
                    'tier' => 'ai',
                    'unmatched' => $headers,
                ];
            }

            // Validate that mapped indices are valid integers within bounds
            $validMapping = [];
            $headerCount = count($headers);

            foreach ($response['mapping'] as $field => $colIndex) {
                if (is_numeric($colIndex) && $colIndex >= 0 && $colIndex < $headerCount) {
                    $validMapping[(string) $field] = (int) $colIndex;
                }
            }

            $confidence = isset($response['confidence']) && is_numeric($response['confidence'])
                ? (float) $response['confidence']
                : 0.85;

            return [
                'mapping' => $validMapping,
                'confidence' => $confidence,
                'tier' => 'ai',
                'unmatched' => $response['unmatched'] ?? [],
            ];
        } catch (Throwable $e) {
            Log::channel('payroll_ai')->error("GrokColumnMatcher exception for {$clientSlug}: " . $e->getMessage());
            return [
                'mapping' => [],
                'confidence' => 0.0,
                'tier' => 'ai',
                'unmatched' => $headers,
            ];
        }
    }
}
