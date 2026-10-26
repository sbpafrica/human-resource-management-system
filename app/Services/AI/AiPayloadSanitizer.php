<?php

namespace App\Services\AI;

class AiPayloadSanitizer
{
    /**
     * Sanitizes spreadsheet headers and sample data for column matching.
     * Only headers and value data types / shapes are passed to Grok, NEVER PII.
     *
     * @param array<int, string|null> $headers
     * @param array<int, array> $sampleRows
     * @return array{headers: array<int, string>, samples: array<int, array>}
     */
    public function sanitizeForColumnMatching(array $headers, array $sampleRows = []): array
    {
        $cleanHeaders = [];
        foreach ($headers as $idx => $header) {
            $cleanHeaders[$idx] = trim((string) $header);
        }

        // Mask sample rows: preserve numeric scale or format patterns, scrub raw text/names
        $cleanSamples = [];
        foreach (array_slice($sampleRows, 0, 3) as $row) {
            $cleanRow = [];
            foreach ($headers as $idx => $header) {
                $val = $row[$idx] ?? null;
                if ($val === null || $val === '') {
                    $cleanRow[$idx] = null;
                } elseif (is_numeric($val)) {
                    // Keep numbers for scale recognition (e.g. daily rate vs monthly salary vs 5% deduction)
                    $cleanRow[$idx] = (float) $val;
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $val)) {
                    $cleanRow[$idx] = 'YYYY-MM-DD';
                } elseif (preg_match('/^GHA-\d{9}-\d$/i', (string) $val)) {
                    $cleanRow[$idx] = 'GHA-*********';
                } else {
                    // Non-numeric text - anonymize to type indicator so names/addresses never leak
                    $cleanRow[$idx] = '[TEXT_STRING]';
                }
            }
            $cleanSamples[] = $cleanRow;
        }

        return [
            'headers' => $cleanHeaders,
            'samples' => $cleanSamples,
        ];
    }

    /**
     * Tokenizes payroll records for AI Anomaly checking.
     * Replaces staff IDs and names with anonymous tokens (e.g. EMP_001).
     *
     * @param array<int, array> $records
     * @return array{sanitized: array<int, array>, token_map: array<string, string>}
     */
    public function sanitizeForAnomalyDetection(array $records): array
    {
        $tokenMap = [];
        $sanitized = [];
        $counter = 1;

        foreach ($records as $record) {
            $staffId = (string) ($record['staff_id'] ?? '');
            $token = 'EMP_' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT);
            $tokenMap[$token] = $staffId;
            $counter++;

            // Include only numerical and structural data needed for variance analysis
            $sanitized[] = [
                'token' => $token,
                'basic_salary' => isset($record['basic_salary']) ? (float) $record['basic_salary'] : null,
                'days_worked' => isset($record['days_worked']) ? (float) $record['days_worked'] : null,
                'overtime_amount' => isset($record['overtime_amount']) ? (float) $record['overtime_amount'] : null,
                'gross_salary' => isset($record['gross_salary']) ? (float) $record['gross_salary'] : null,
                'ssnit_ee' => isset($record['ssnit_ee']) ? (float) $record['ssnit_ee'] : null,
                'petra' => isset($record['petra']) ? (float) $record['petra'] : null,
                'paye' => isset($record['paye']) ? (float) $record['paye'] : null,
                'net_pay' => isset($record['net_pay']) ? (float) $record['net_pay'] : null,
                // Account numbers are hashed/masked for collision/duplicate detection without revealing real details
                'account_hash' => !empty($record['account']) ? substr(hash('sha256', (string) $record['account']), 0, 12) : null,
            ];
        }

        return [
            'sanitized' => $sanitized,
            'token_map' => $tokenMap,
        ];
    }
}
