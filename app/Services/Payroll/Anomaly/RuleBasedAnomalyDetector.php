<?php

namespace App\Services\Payroll\Anomaly;

use App\Services\AI\Contracts\AnomalyDetectorInterface;

class RuleBasedAnomalyDetector implements AnomalyDetectorInterface
{
    /**
     * National Daily Minimum Wage in Ghana (GHS).
     */
    protected float $dailyMinimumWage = 18.15;

    public function detect(array $records, string $clientSlug, string $payrollPeriod, array $historicalRecords = []): array
    {
        $anomalies = [];
        $accountMap = []; // [account_no => [staff_id1, staff_id2]]

        foreach ($records as $index => $row) {
            $staffId = trim((string) ($row['staff_id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));
            $rowNum = $index + 2; // Assuming row 1 was header

            // 1. Missing Staff ID or Name
            if (empty($staffId)) {
                $anomalies[] = [
                    'type' => 'missing_staff_id',
                    'severity' => 'critical',
                    'staff_id' => null,
                    'employee_name' => $name ?: null,
                    'field' => 'staff_id',
                    'message' => "Row {$rowNum}: Missing Staff ID.",
                    'suggested_fix' => "Assign a valid employee ID before locking payroll.",
                    'metadata' => ['row' => $rowNum],
                ];
                continue;
            }

            $gross = isset($row['gross_salary']) && is_numeric($row['gross_salary']) ? (float) $row['gross_salary'] : 0.0;
            $net = isset($row['net_pay']) && is_numeric($row['net_pay']) ? (float) $row['net_pay'] : 0.0;
            $basic = isset($row['basic_salary']) && is_numeric($row['basic_salary']) ? (float) $row['basic_salary'] : 0.0;
            $ssnit = isset($row['ssnit_ee']) && is_numeric($row['ssnit_ee']) ? (float) $row['ssnit_ee'] : null;
            $petra = isset($row['petra']) && is_numeric($row['petra']) ? (float) $row['petra'] : null;
            $dailyRate = isset($row['daily_rate']) && is_numeric($row['daily_rate']) ? (float) $row['daily_rate'] : null;
            $account = trim((string) ($row['account'] ?? ''));

            // 2. Duplicate Bank Account / MoMo Number Collision (Ghost Worker Risk)
            if (!empty($account) && strlen($account) >= 6) {
                if (isset($accountMap[$account])) {
                    $conflictStaff = $accountMap[$account];
                    $anomalies[] = [
                        'type' => 'duplicate_bank_account',
                        'severity' => 'critical',
                        'staff_id' => $staffId,
                        'employee_name' => $name,
                        'field' => 'account',
                        'message' => "Duplicate payment account detected! Account/MoMo ({$account}) is shared with Staff ID: {$conflictStaff}.",
                        'suggested_fix' => "Verify employee bank details to prevent double or erroneous disbursement.",
                        'metadata' => ['account' => $account, 'conflicting_staff_id' => $conflictStaff],
                    ];
                } else {
                    $accountMap[$account] = $staffId;
                }
            }

            // 3. Negative or Zero Net Pay with Positive Gross
            if ($gross > 0 && $net <= 0) {
                $anomalies[] = [
                    'type' => 'negative_net_pay',
                    'severity' => 'critical',
                    'staff_id' => $staffId,
                    'employee_name' => $name,
                    'field' => 'net_pay',
                    'message' => "Employee has gross earnings (GHS " . number_format($gross, 2) . ") but non-positive Net Pay (GHS " . number_format($net, 2) . ").",
                    'suggested_fix' => "Review deductions or tax offsets causing negative/zero take-home.",
                    'metadata' => ['gross' => $gross, 'net' => $net],
                ];
            }

            // 4. Net Pay Exceeds Gross Pay
            if ($gross > 0 && $net > ($gross + 0.05)) {
                $anomalies[] = [
                    'type' => 'net_exceeds_gross',
                    'severity' => 'critical',
                    'staff_id' => $staffId,
                    'employee_name' => $name,
                    'field' => 'net_pay',
                    'message' => "Net Pay (GHS " . number_format($net, 2) . ") is higher than Gross Salary (GHS " . number_format($gross, 2) . ").",
                    'suggested_fix' => "Recheck formula; net pay cannot exceed gross compensation.",
                    'metadata' => ['gross' => $gross, 'net' => $net],
                ];
            }

            // 5. Statutory SSNIT Tier 1 Check (0.5% standard in HRS, or statutory 5.5%)
            $salaryBase = $basic > 0 ? $basic : $gross;
            if ($salaryBase > 0) {
                $expectedSsnit05 = round($salaryBase * 0.005, 2);
                $expectedSsnit55 = round($salaryBase * 0.055, 2);
                $isMatchingRate = (abs($ssnit - $expectedSsnit05) <= 2.00) || (abs($ssnit - $expectedSsnit55) <= 5.00);

                if ($ssnit === null || ($ssnit == 0 && $salaryBase > 200)) {
                    $anomalies[] = [
                        'type' => 'missing_ssnit_deduction',
                        'severity' => 'warning',
                        'staff_id' => $staffId,
                        'employee_name' => $name,
                        'field' => 'ssnit_ee',
                        'message' => "Missing SSNIT deduction. Expected ~GHS " . number_format($expectedSsnit05, 2) . " (0.5%).",
                        'suggested_fix' => "Confirm if employee is exempt or rectify statutory deduction.",
                        'metadata' => ['expected_ssnit' => $expectedSsnit05, 'actual_ssnit' => $ssnit],
                    ];
                } elseif (!$isMatchingRate && $ssnit > 0) {
                    $anomalies[] = [
                        'type' => 'ssnit_variance',
                        'severity' => 'info',
                        'staff_id' => $staffId,
                        'employee_name' => $name,
                        'field' => 'ssnit_ee',
                        'message' => "SSNIT deduction (GHS " . number_format($ssnit, 2) . ") deviates from standard 0.5% (GHS " . number_format($expectedSsnit05, 2) . ").",
                        'suggested_fix' => "Verify if calculation includes allowances or special pension base.",
                        'metadata' => ['expected_ssnit' => $expectedSsnit05, 'actual_ssnit' => $ssnit],
                    ];
                }

                // 6. Statutory Petra Tier 2 Check (5.0% of base/gross)
                $expectedPetra = round($salaryBase * 0.05, 2);
                if ($petra === null || ($petra == 0 && $salaryBase > 200)) {
                    $anomalies[] = [
                        'type' => 'missing_petra_deduction',
                        'severity' => 'warning',
                        'staff_id' => $staffId,
                        'employee_name' => $name,
                        'field' => 'petra',
                        'message' => "Missing Petra Tier 2 deduction. Expected ~GHS " . number_format($expectedPetra, 2) . " (5.0%).",
                        'suggested_fix' => "Ensure Tier 2 provident fund contributions are applied.",
                        'metadata' => ['expected_petra' => $expectedPetra, 'actual_petra' => $petra],
                    ];
                }
            }

            // 7. Daily Minimum Wage Violation
            if ($dailyRate !== null && $dailyRate > 0 && $dailyRate < $this->dailyMinimumWage) {
                $anomalies[] = [
                    'type' => 'minimum_wage_violation',
                    'severity' => 'warning',
                    'staff_id' => $staffId,
                    'employee_name' => $name,
                    'field' => 'daily_rate',
                    'message' => "Daily rate (GHS " . number_format($dailyRate, 2) . ") is below the national statutory minimum wage (GHS " . number_format($this->dailyMinimumWage, 2) . ").",
                    'suggested_fix' => "Adjust rate to comply with Ghanaian labor standards.",
                    'metadata' => ['rate' => $dailyRate, 'statutory_minimum' => $this->dailyMinimumWage],
                ];
            }
        }

        $criticalCount = count(array_filter($anomalies, fn($a) => $a['severity'] === 'critical'));
        $warningCount = count(array_filter($anomalies, fn($a) => $a['severity'] === 'warning'));
        $infoCount = count(array_filter($anomalies, fn($a) => $a['severity'] === 'info'));

        return [
            'total_records' => count($records),
            'passed' => $criticalCount === 0,
            'summary' => [
                'critical_count' => $criticalCount,
                'warning_count' => $warningCount,
                'info_count' => $infoCount,
            ],
            'anomalies' => $anomalies,
        ];
    }
}
