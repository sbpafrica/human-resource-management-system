<?php

namespace App\Services\Payroll\ColumnMatcher;

use App\Services\AI\Contracts\ColumnMatcherInterface;

class LegacyColumnMatcher implements ColumnMatcherInterface
{
    /**
     * Tier 3 Fallback: Returns the exact hardcoded array offsets
     * currently running in production controllers.
     */
    public function match(array $headers, string $clientSlug, array $sampleRows = []): array
    {
        $normalizedSlug = strtolower(preg_replace('/[^a-z0-9]/', '', $clientSlug));

        $mapping = match ($normalizedSlug) {
            'jaykay', 'jkay' => [
                'staff_id'            => 0,
                'name'                => 1,
                'position'            => 2,
                'join_date'           => 3,
                'basic_salary'        => 4,
                'legalworkdays'       => 5,
                'daily_rate'          => 6,
                'days_worked'         => 7,
                'absentee_days'       => 8,
                'absent_deductions'   => 9,
                'earned_basic_salary' => 10,
                'saturdays_worked'    => 11,
                'saturday_rate'       => 12,
                'saturday_amount'     => 13,
                'total_days_amount'   => 14,
                'overtime_hours'      => 15,
                'overtime_rate'       => 16,
                'overtime_amount'     => 17,
                'night_allowance'     => 18,
                'totalovertime'       => 19,
                'gross_salary'        => 20,
                'management_fee'      => 21,
                'getfund'             => 22,
                'nhil'                => 23,
                'vat'                 => 24,
                'ssf'                 => 25,
                'cost_to_jkay'        => 26,
                'ssnit_ee'            => 27,
                'petra'               => 28,
                'taxable_income'      => 29,
                'paye'                => 30,
                'overtime_tax'        => 31,
                'total_deductions'    => 33,
                'net_pay'             => 34,
            ],

            'ofitemp', 'ofibwh', 'ofi' => [
                'staff_id'     => 0,
                'name'         => 1,
                'daily_rate'   => 2,
                'days_worked'  => 3,
                'gross_salary' => 4,
                'ssnit_ee'     => 5,
                'petra'        => 6,
                'paye'         => 7,
                'net_pay'      => 8,
            ],

            'melcom', 'melcomtry' => [
                'staff_id'     => 0,
                'name'         => 1,
                'position'     => 2,
                'basic_salary' => 3,
                'days_worked'  => 4,
                'gross_salary' => 12,
                'ssnit_ee'     => 15,
                'petra'        => 16,
                'paye'         => 17,
                'net_pay'      => 20,
            ],

            'transsion' => [
                'staff_id'     => 0,
                'name'         => 1,
                'position'     => 2,
                'basic_salary' => 3,
                'days_worked'  => 4,
                'gross_salary' => 10,
                'ssnit_ee'     => 12,
                'petra'        => 13,
                'paye'         => 14,
                'net_pay'      => 16,
            ],

            default => [
                'staff_id'     => 0,
                'name'         => 1,
                'basic_salary' => 2,
                'days_worked'  => 3,
                'gross_salary' => 4,
                'ssnit_ee'     => 5,
                'petra'        => 6,
                'paye'         => 7,
                'net_pay'      => 8,
            ],
        };

        return [
            'mapping' => $mapping,
            'confidence' => 0.70,
            'tier' => 'legacy',
            'unmatched' => [],
        ];
    }
}
