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
