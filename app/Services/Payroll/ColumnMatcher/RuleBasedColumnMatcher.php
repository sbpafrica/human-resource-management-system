<?php

namespace App\Services\Payroll\ColumnMatcher;

use App\Services\AI\Contracts\ColumnMatcherInterface;

class RuleBasedColumnMatcher implements ColumnMatcherInterface
{
    /**
     * Dictionary mapping standard internal keys to regex patterns
     * commonly found across Ghanaian client Excel payroll files.
     */
    protected array $dictionary = [
        'staff_id' => [
            '/^(staff|emp(loyee)?|worker)\s*[-_ ]*id$/i',
            '/^id\s*[-_ ]*(no|num|number)?$/i',
            '/^badge(\s*no)?$/i',
            '/^matricule$/i',
        ],
        'name' => [
            '/^(full\s*)?name$/i',
            '/^employee\s*name$/i',
            '/^staff\s*name$/i',
            '/^nom(\s*et\s*prenom)?$/i',
        ],
        'position' => [
            '/^position$/i',
            '/^designation$/i',
            '/^job\s*title$/i',
            '/^department$/i',
            '/^shop$/i',
            '/^role$/i',
        ],
        'join_date' => [
            '/^(join|start|hire|employment)\s*date$/i',
            '/^date\s*of\s*(joining|employment)$/i',
        ],
        'basic_salary' => [
            '/^basic(\s*sal(ary)?)?$/i',
            '/^base\s*(pay|salary)$/i',
            '/^monthly\s*(rate|basic|salary)$/i',
            '/^salaire\s*de\s*base$/i',
        ],
        'legalworkdays' => [
            '/^legal\s*work\s*days$/i',
            '/^standard\s*days$/i',
            '/^work\s*days\s*in\s*month$/i',
        ],
        'daily_rate' => [
            '/^daily\s*rate$/i',
            '/^rate\s*(per|\/)\s*day$/i',
            '/^rate\s*amount$/i',
            '/^tonnage\s*rate$/i',
            '/^taux\s*journalier$/i',
        ],
        'days_worked' => [
            '/^days?\s*worked$/i',
            '/^actual\s*days$/i',
            '/^total\s*days$/i',
            '/^work(ing)?\s*days$/i',
            '/^total1$/i',
            '/^jours?\s*travaill[eé]s?$/i',
        ],
        'absentee_days' => [
            '/^absent(ee)?\s*days?$/i',
            '/^days?\s*absent$/i',
        ],
        'absent_deductions' => [
            '/^absent(ee(ism)?)?\s*ded(uction)?s?$/i',
            '/^absence\s*deduction$/i',
        ],
        'earned_basic_salary' => [
            '/^earned\s*basic(\s*salary)?$/i',
            '/^basic\s*earned$/i',
        ],
        'saturdays_worked' => [
            '/^sat(urdays?)?\s*worked$/i',
            '/^sat\s*days$/i',
        ],
        'saturday_rate' => [
            '/^sat(urday)?\s*rate$/i',
        ],
        'saturday_amount' => [
            '/^sat(urday)?\s*(amount|pay)$/i',
        ],
        'total_days_amount' => [
            '/^total\s*days\s*amount$/i',
            '/^basic\s*days$/i',
        ],
        'weekday_hours' => [
            '/^(weekday|week\s*day|normal)\s*(ot\s*)?hours?$/i',
            '/^weekday\s*hrs?$/i',
            '/^normal\s*ot\s*hrs?$/i',
            '/^normal\s*ot\s*hours?$/i',
            '/^weekday\s*overtime\s*hours?$/i',
        ],
        'weekday_overtime_amt' => [
            '/^(weekday|week\s*day|normal)\s*(ot|overtime)\s*(amt|amount|total|pay)?$/i',
            '/^weekday(s)?\s*overtime$/i',
            '/^normal\s*overtime$/i',
            '/^normal\s*ot\s*(amt|amount)?$/i',
            '/^weekday\s*overtime\s*amount$/i',
        ],
        'saturday_hours' => [
            '/^sat(urday)?\s*(ot\s*)?hours?$/i',
            '/^sat(urday)?\s*hrs?$/i',
            '/^num(ber)?\s*(of\s*)?saturdays?$/i',
            '/^saturday(s)?\s*worked$/i',
        ],
        'saturday_overtime_amt' => [
            '/^sat(urday)?\s*(ot|overtime)\s*(amt|amount|total|pay)?$/i',
            '/^saturday(s)?\s*overtime$/i',
            '/^weekend\s*overtime(\s*amount)?$/i',
        ],
        'num_sundays_holidays' => [
            '/^num(ber)?\s*(of\s*)?(sundays?|holidays?)(\s*(and|\/)?\s*holidays?)?$/i',
            '/^(sunday|holiday|offday)\s*(ot\s*)?hours?$/i',
            '/^sunday\s*holiday\s*hours?$/i',
            '/^sundays?\s*holiday\s*overtime$/i',
            '/^offday\s*holiday\s*overtime\s*hours?$/i',
            '/^special\s*day\s*overtime\s*hours?$/i',
        ],
        'sunday_holiday_overtime_amt' => [
            '/^(sunday(s)?(\s*(and|\/)?\s*holiday(s)?)?|holiday|offday)\s*(ot|overtime)\s*(amt|amount|total|pay)?$/i',
            '/^sunday(s)?\s*holiday(s)?\s*overtime(\s*(amt|amount|total|pay))?$/i',
            '/^sundays?\s*(and|\/)\s*holidays?\s*overtime(\s*(amt|amount|total|pay))?$/i',
            '/^offday\s*holiday\s*overtime\s*(total|amount)?$/i',
            '/^special\s*day\s*overtime(\s*amount)?$/i',
        ],
        'overtimerefund' => [
            '/^overtime\s*refund$/i',
            '/^ot\s*refund$/i',
            '/^overtimerefund$/i',
        ],
        'total_overtime_amt' => [
            '/^total\s*(overtime|ot)\s*(amt|amount)?$/i',
            '/^totalovertimeamt$/i',
            '/^total_overtime_amt$/i',
            '/^total\s*overtime\s*amount$/i',
        ],
        'overtime_hours' => [
            '/^o(\.)?t(\.)?\s*hours?$/i',
            '/^overtime\s*hours?$/i',
            '/^ot\s*hrs?$/i',
        ],
        'overtime_rate' => [
            '/^o(\.)?t(\.)?\s*rate$/i',
            '/^overtime\s*rate$/i',
        ],
        'overtime_amount' => [
            '/^o(\.)?t(\.)?\s*(amount|pay)$/i',
            '/^overtime\s*(amount|pay)$/i',
            '/^total\s*overtime$/i',
            '/^totalovertime$/i',
            '/^overtime$/i',
        ],
        'night_allowance' => [
            '/^night\s*(allowance|shift)$/i',
        ],
        'gross_salary' => [
            '/^gross(\s*(salary|pay|earnings?))?$/i',
            '/^total\s*(earn(ings?)?|gross)$/i',
            '/^totalearn$/i',
            '/^salaire\s*brut$/i',
        ],
        'ssnit_ee' => [
            '/^ssnit(\s*(5\.5%|ee|emp(loyee)?))?$/i',
            '/^ssf(\s*ee)?$/i',
            '/^tier\s*1(\s*ee)?$/i',
            '/^ssnitee$/i',
            '/^cnss(\s*ouvrier)?$/i',
        ],
        'petra' => [
            '/^petra(\s*(5%|tier\s*2|ee))?$/i',
            '/^tier\s*2(\s*(ee|petra))?$/i',
            '/^provident\s*fund$/i',
            '/^pf(\s*ee)?$/i',
        ],
        'taxable_income' => [
            '/^taxable\s*(income|pay|salary)$/i',
        ],
        'paye' => [
            '/^p\.?a\.?y\.?e\.?$/i',
            '/^income\s*tax$/i',
            '/^gra\s*tax$/i',
            '/^tax$/i',
            '/^its$/i',
        ],
        'overtime_tax' => [
            '/^(ot|overtime)\s*tax$/i',
        ],
        'total_deductions' => [
            '/^total\s*ded(uctions?)?$/i',
        ],
        'net_pay' => [
            '/^net(\s*(salary|pay))?$/i',
            '/^take\s*home(\s*pay)?$/i',
            '/^final\s*pay$/i',
            '/^amount\s*payable$/i',
            '/^salaire\s*net$/i',
        ],
        'bank' => [
            '/^bank(\s*name)?$/i',
            '/^banque$/i',
        ],
        'account' => [
            '/^acc(ount)?\s*(no|num|number)?$/i',
            '/^bank\s*acc(ount)?$/i',
            '/^momo(\s*(no|number))?$/i',
        ],
    ];

    /**
     * Matches raw headers to standardized keys using regex patterns.
     */
    public function match(array $headers, string $clientSlug, array $sampleRows = []): array
    {
        $mapping = [];
        $unmatched = [];

        foreach ($headers as $colIndex => $rawHeader) {
            $headerStr = trim((string) $rawHeader);
            if ($headerStr === '') {
                continue;
            }

            $matchedField = null;

            foreach ($this->dictionary as $field => $patterns) {
                if (isset($mapping[$field])) {
                    // Already mapped this field to an earlier column
                    continue;
                }

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $headerStr)) {
                        $matchedField = $field;
                        break 2;
                    }
                }
            }

            if ($matchedField) {
                $mapping[$matchedField] = $colIndex;
            } else {
                $unmatched[] = $headerStr;
            }
        }

        // Keep overtime_amount and total_overtime_amt in sync for downstream consumers
        if (isset($mapping['total_overtime_amt']) && !isset($mapping['overtime_amount'])) {
            $mapping['overtime_amount'] = $mapping['total_overtime_amt'];
        } elseif (isset($mapping['overtime_amount']) && !isset($mapping['total_overtime_amt'])) {
            $mapping['total_overtime_amt'] = $mapping['overtime_amount'];
        }

        // Calculate confidence score based on essential payroll field matches
        $essentialFields = ['staff_id', 'name', 'gross_salary', 'net_pay'];
        $essentialCount = 0;
        foreach ($essentialFields as $field) {
            if (isset($mapping[$field])) {
                $essentialCount++;
            }
        }

        $confidence = count($mapping) > 0 ? round($essentialCount / count($essentialFields), 2) : 0.0;

        return [
            'mapping' => $mapping,
            'confidence' => $confidence,
            'tier' => 'rule',
            'unmatched' => $unmatched,
        ];
    }
}
