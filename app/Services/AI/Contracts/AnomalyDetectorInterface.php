<?php

namespace App\Services\AI\Contracts;

interface AnomalyDetectorInterface
{
    /**
     * Scans parsed payroll records before final lock and payout.
     *
     * @param array<int, array> $records List of parsed row records
     * @param string $clientSlug e.g. 'jaykay', 'ofitemp'
     * @param string $payrollPeriod e.g. '2026-10'
     * @param array<int, array> $historicalRecords Optional prior 1-3 months records for variance checks
     * @return array [
     *     'total_records' => int,
     *     'passed' => bool,
     *     'summary' => [
     *         'critical_count' => int,
     *         'warning_count' => int,
     *         'info_count' => int,
     *     ],
     *     'anomalies' => array<int, array{
     *         'type' => string,
     *         'severity' => 'critical'|'warning'|'info',
     *         'staff_id' => string|null,
     *         'employee_name' => string|null,
     *         'field' => string|null,
     *         'message' => string,
     *         'suggested_fix' => string|null,
     *         'metadata' => array,
     *     }>,
     * ]
     */
    public function detect(array $records, string $clientSlug, string $payrollPeriod, array $historicalRecords = []): array;
}
