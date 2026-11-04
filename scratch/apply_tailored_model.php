<?php
/**
 * Update app/Models/PayrollColumn.php with tailored getStandardColumnsWithOverrides
 * and static template helpers.
 */
$modelPath = __DIR__ . '/../app/Models/PayrollColumn.php';
$content = file_get_contents($modelPath);

// 1. Add 'name' right after 'staff_id' if not already present
$staffIdTarget = "            'staff_id' => [
                'display_label'   => 'Staff ID',
                'header_aliases'  => 'Staff ID, StaffID, Staff No, Employee ID, EmployID, ID Number',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(32)',
                'description'     => 'Primary Employee Identification (Immutable Core System Key - Locked).',
                'locked'          => true,
            ],";

$nameCode = "            'name' => [
                'display_label'   => 'Employee Name',
                'header_aliases'  => 'Name, Employee Name, Staff Name, Full Name, Emp Name, Names, Fullname',
                'payslip_section' => 'info',
                'data_type'       => 'varchar(150)',
                'description'     => 'Full name of employee.',
            ],
            'totalabsent' => [
                'display_label'   => 'Total Absent Days',
                'header_aliases'  => 'Total Absent, Total Absent Days, Days Absent Total, Total Absenteeism',
                'payslip_section' => 'info',
                'data_type'       => 'decimal(10,2)',
                'description'     => 'Total number of absent days recorded.',
            ],";

if (strpos($content, "'name' => [") === false) {
    $content = str_replace($staffIdTarget, $staffIdTarget . "\n" . $nameCode, $content);
}

// 2. Replace getStandardColumnsWithOverrides with the tailored version + getStaticTemplateColumnsForClient
$oldMethodTarget = '    /**
     * Get all standard columns with any client or global overrides merged in.
     */
    public static function getStandardColumnsWithOverrides(string $clientDb): array
    {
        $canonical = self::getCanonicalStandardColumns();

        // Fetch overrides stored in database for this client (and \'all\')
        $overrides = static::where(\'is_custom\', false)
            ->where(\'is_active\', true)
            ->where(function ($q) use ($clientDb) {
                $q->where(\'client_dbname\', $clientDb)->orWhere(\'client_dbname\', \'all\');
            })
            ->get()
            ->keyBy(\'column_name\');

        $result = [];
        foreach ($canonical as $colName => $def) {
            $override = $overrides->get($colName);
            $result[] = [
                \'id\'              => $override?->id ?? null,
                \'client_dbname\'   => $override?->client_dbname ?? $clientDb,
                \'is_custom\'       => false,
                \'column_name\'     => $colName,
                \'display_label\'   => $override?->display_label ?? $def[\'display_label\'],
                \'default_label\'   => $def[\'display_label\'],
                \'header_aliases\'  => $override?->header_aliases ?? $def[\'header_aliases\'],
                \'default_aliases\' => $def[\'header_aliases\'],
                \'data_type\'       => $def[\'data_type\'],
                \'position_after\'  => $override?->position_after ?? null,
                \'show_in_payslip\' => $override?->show_in_payslip ?? true,
                \'payslip_section\' => $override?->payslip_section ?? $def[\'payslip_section\'],
                \'description\'     => $def[\'description\'] ?? \'\',
                \'has_override\'    => $override !== null,
            ];
        }

        return $result;
    }';

$newMethods = '    /**
     * Get standard columns with any client or global overrides merged in.
     * When $filterByClient is true and $clientDb !== \'all\', tailors the list
     * strictly to columns belonging to that client\'s schema / tables / templates.
     */
    public static function getStandardColumnsWithOverrides(string $clientDb, bool $filterByClient = true): array
    {
        $canonical = self::getCanonicalStandardColumns();

        // Fetch overrides stored in database for this client (and \'all\')
        $overrides = static::where(\'is_custom\', false)
            ->where(\'is_active\', true)
            ->where(function ($q) use ($clientDb) {
                $q->where(\'client_dbname\', $clientDb)->orWhere(\'client_dbname\', \'all\');
            })
            ->get()
            ->keyBy(\'column_name\');

        if ($clientDb === \'all\' || empty($clientDb) || !$filterByClient) {
            $result = [];
            foreach ($canonical as $colName => $def) {
                $override = $overrides->get($colName);
                $isLocked = !empty($def[\'locked\']) || in_array(strtolower($colName), [\'staff_id\', \'staffid\', \'staffida\', \'employ\', \'id\', \'employid\']);
                $result[] = [
                    \'id\'              => $override?->id ?? null,
                    \'client_dbname\'   => $override?->client_dbname ?? $clientDb,
                    \'is_custom\'       => false,
                    \'column_name\'     => $colName,
                    \'display_label\'   => $override?->display_label ?? $def[\'display_label\'],
                    \'default_label\'   => $def[\'display_label\'],
                    \'header_aliases\'  => $override?->header_aliases ?? $def[\'header_aliases\'],
                    \'default_aliases\' => $def[\'header_aliases\'],
                    \'data_type\'       => $def[\'data_type\'],
                    \'position_after\'  => $override?->position_after ?? null,
                    \'show_in_payslip\' => $override?->show_in_payslip ?? true,
                    \'payslip_section\' => $override?->payslip_section ?? $def[\'payslip_section\'],
                    \'description\'     => $def[\'description\'] ?? \'\',
                    \'has_override\'    => $override !== null,
                    \'locked\'          => $isLocked,
                ];
            }
            return $result;
        }

        // --- Client-specific tailored filtering ---
        $clientCols = [];

        // 1. Physical table schema columns for this client
        $prefixes = self::getTablePrefixesForClient($clientDb);
        $whereParts = [];
        $bindings = [];
        foreach ($prefixes as $p) {
            $whereParts[] = "TABLE_NAME LIKE ?";
            $bindings[] = "{$p}%";
        }

        try {
            $tables = \Illuminate\Support\Facades\DB::select("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND (" . implode(\' OR \', $whereParts) . ")
                  AND TABLE_NAME NOT LIKE \'%_sign\'
                  AND TABLE_NAME NOT LIKE \'%_contracts%\'
                  AND TABLE_NAME NOT LIKE \'%attendance%\'
                  AND TABLE_NAME NOT LIKE \'%sms%\'
                  AND TABLE_NAME NOT LIKE \'%data\'
                  AND TABLE_NAME NOT LIKE \'%con\'
                ORDER BY TABLE_NAME DESC 
                LIMIT 20
            ", $bindings);

            foreach ($tables as $t) {
                $cols = \Illuminate\Support\Facades\Schema::getColumnListing($t->TABLE_NAME);
                foreach ($cols as $c) {
                    $norm = strtolower(preg_replace(\'/[^a-zA-Z0-9_]/\', \'_\', $c));
                    $clientCols[$norm] = $c;
                }
            }
        } catch (\Exception $e) {
            // Gracefully ignore database query issues
        }

        // 2. Overrides already configured for this client
        foreach ($overrides as $ov) {
            $norm = strtolower(preg_replace(\'/[^a-zA-Z0-9_]/\', \'_\', $ov->column_name));
            $clientCols[$norm] = $ov->column_name;
        }

        // 3. Static fallback template columns if tables not yet populated
        $staticTemplates = self::getStaticTemplateColumnsForClient($clientDb);
        foreach ($staticTemplates as $stCol) {
            $norm = strtolower(preg_replace(\'/[^a-zA-Z0-9_]/\', \'_\', $stCol));
            if (!isset($clientCols[$norm])) {
                $clientCols[$norm] = $stCol;
            }
        }

        // 4. Always ensure primary staff_id is present
        $clientCols[\'staff_id\'] = \'staff_id\';

        $result = [];
        $processedKeys = [];

        // First pass: Match from canonical list (maintains polished descriptions, types, and sections)
        foreach ($canonical as $colName => $def) {
            $norm = strtolower(preg_replace(\'/[^a-zA-Z0-9_]/\', \'_\', $colName));
            if (isset($clientCols[$norm]) || isset($clientCols[$colName])) {
                $override = $overrides->get($colName);
                $isLocked = !empty($def[\'locked\']) || in_array(strtolower($colName), [\'staff_id\', \'staffid\', \'staffida\', \'employ\', \'id\', \'employid\']);
                $result[] = [
                    \'id\'              => $override?->id ?? null,
                    \'client_dbname\'   => $override?->client_dbname ?? $clientDb,
                    \'is_custom\'       => false,
                    \'column_name\'     => $colName,
                    \'display_label\'   => $override?->display_label ?? $def[\'display_label\'],
                    \'default_label\'   => $def[\'display_label\'],
                    \'header_aliases\'  => $override?->header_aliases ?? $def[\'header_aliases\'],
                    \'default_aliases\' => $def[\'header_aliases\'],
                    \'data_type\'       => $def[\'data_type\'],
                    \'position_after\'  => $override?->position_after ?? null,
                    \'show_in_payslip\' => $override?->show_in_payslip ?? true,
                    \'payslip_section\' => $override?->payslip_section ?? $def[\'payslip_section\'],
                    \'description\'     => $def[\'description\'] ?? \'\',
                    \'has_override\'    => $override !== null,
                    \'locked\'          => $isLocked,
                ];
                $processedKeys[$norm] = true;
                $processedKeys[$colName] = true;
            }
        }

        // Second pass: Any extra physical columns in client\'s table not in canonical
        foreach ($clientCols as $norm => $origCol) {
            if (!isset($processedKeys[$norm]) && !isset($processedKeys[$origCol])) {
                if (in_array($norm, [\'created_at\', \'updated_at\', \'deleted_at\', \'remember_token\', \'password\', \'email_verified_at\'])) {
                    continue;
                }

                $override = $overrides->get($origCol);
                $cleanLabel = ucwords(str_replace(\'_\', \' \', $norm));
                $isId = in_array($norm, [\'staff_id\', \'staffid\', \'staffida\', \'employ\', \'id\', \'employid\']);

                $result[] = [
                    \'id\'              => $override?->id ?? null,
                    \'client_dbname\'   => $override?->client_dbname ?? $clientDb,
                    \'is_custom\'       => false,
                    \'column_name\'     => $origCol,
                    \'display_label\'   => $override?->display_label ?? $cleanLabel,
                    \'default_label\'   => $cleanLabel,
                    \'header_aliases\'  => $override?->header_aliases ?? "{$origCol}, {$cleanLabel}",
                    \'default_aliases\' => "{$origCol}, {$cleanLabel}",
                    \'data_type\'       => \'varchar(100)\',
                    \'position_after\'  => $override?->position_after ?? null,
                    \'show_in_payslip\' => $override?->show_in_payslip ?? true,
                    \'payslip_section\' => $isId ? \'info\' : \'earnings\',
                    \'description\'     => "Physical payroll column \'{$origCol}\' for {$clientDb}.",
                    \'has_override\'    => $override !== null,
                    \'locked\'          => $isId,
                ];
                $processedKeys[$norm] = true;
            }
        }

        return $result;
    }

    /**
     * Fallback template columns defined per client in their payroll controllers.
     */
    public static function getStaticTemplateColumnsForClient(string $clientDb): array
    {
        $templates = [
            \'jkay\' => [
                \'staff_id\', \'name\', \'position\', \'join_date\', \'basic_salary\', \'legalworkdays\', \'daily_rate\',
                \'days_worked\', \'absentee_days\', \'absent_deductions\', \'earned_basic_salary\', \'saturdays_worked\',
                \'saturday_rate\', \'saturday_amount\', \'total_days_amount\', \'overtime_hours\', \'overtime_rate\',
                \'overtime_amount\', \'night_allowance\', \'totalovertime\', \'gross_salary\', \'management_fee\',
                \'getfund\', \'nhil\', \'covid19\', \'vat\', \'ssf\', \'cost_to_jkay\', \'ssnit_ee\', \'petra\',
                \'taxable_income\', \'paye\', \'overtime_tax\', \'surcharge_or_student_loan\', \'total_deductions\', \'take_home_pay\'
            ],
            \'melcom\' => [
                \'employ\', \'empoyeename\', \'outsourcename\', \'designation\', \'joindate\', \'shopname\', \'category\',
                \'saturdayot\', \'actualdays\', \'night\', \'halfdays\', \'satoffdays\', \'sunoffdays\', \'leavededuct\',
                \'absentdays\', \'absenthrs\', \'holiday\', \'weekdaysOT\', \'weekendOT\', \'holidayot\', \'basic\',
                \'absentwithoutpay\', \'basicentitlement\', \'transport\', \'normalovertime\', \'saturdayovertime\',
                \'holidayotamt\', \'gross\', \'total_cash_emolument\', \'total_assessable_income\', \'ssnit\', \'taxable\',
                \'paye\', \'totalovertime\', \'overtimetax\', \'totaltax\', \'trust\', \'totaldeduction\', \'banktransfer\'
            ],
            \'transsion\' => [
                \'staffida\', \'name\', \'department\', \'hiredate\', \'constart\', \'conend\', \'position\', \'grade\',
                \'categories\', \'workplace\', \'bankname\', \'alcnum\', \'branch\', \'ssnit\', \'col12\', \'workdays\',
                \'leavedays\', \'otworkdays\', \'otweekend\', \'basic\', \'performbonus\', \'coefficient\', \'performstand\',
                \'allowance\', \'commission\', \'otworkingday\', \'otholiday\', \'other\', \'annaulbonus\', \'leavededuction\',
                \'otherdeduction\', \'loan\', \'grosspay\', \'excess\', \'totalemolu\', \'accommodation\', \'chargeincome\',
                \'petra\', \'ssnitamt\', \'nssf\', \'taxbase\', \'paye\', \'annaultax\', \'netpay\', \'hrcost\', \'brandname\'
            ],
            \'transsiontec\' => [
                \'staffida\', \'name\', \'department\', \'hiredate\', \'constart\', \'conend\', \'position\', \'grade\',
                \'categories\', \'workplace\', \'bankname\', \'alcnum\', \'branch\', \'ssnit\', \'basic\', \'performbonus\',
                \'coefficient\', \'performstand\', \'allowance\', \'commission\', \'gross\', \'net_pay\'
            ],
            \'nyonkopa\' => [
                \'staff_id\', \'employee_name\', \'start_date\', \'position\', \'work_location\', \'base_salary\',
                \'fuel_and_rent_allowance\', \'gross_salary\', \'management_fee_8\', \'getfund_2_5\', \'nhil_2_5\',
                \'covid_19_1\', \'vat_15\', \'medical_insurance\', \'employer_provident_fund_5\', \'thirteenth_month_bonus\',
                \'ssnit_er_13\', \'cost_to_nyonkopa\', \'ssnit_ee_5_5\', \'taxable_income\', \'paye\', \'thirteenth_month_tax\',
                \'total_taxation\', \'total_deductions\', \'take_home_pay\'
            ],
            \'pz\' => [
                \'staffid\', \'name\', \'dept\', \'basic\', \'overtime\', \'incentive\', \'bonus\', \'other\', \'13month\',
                \'totalearn\', \'ssnitee\', \'overtimetaxt\', \'pf\', \'taxable\', \'ssniter\', \'13tax\', \'totaltaxdeduct\',
                \'totaldeduct\', \'netpay\', \'bank1\', \'account1\', \'branch1\', \'ssnit1\', \'ghcard\'
            ],
            \'shefer\' => [
                \'staff_id\', \'name1\', \'position\', \'department\', \'startdate\', \'basic\', \'total_weekdays\',
                \'no_of_days_worked\', \'leavepay\', \'absent_days\', \'basic_earned\', \'nightdays\', \'weekdayOT_hours\',
                \'weekdayOT_rate\', \'weekdayOT_amount\', \'weekendOT_hours\', \'weekendOT_rate\', \'weekendOT_amount\',
                \'sunOT_hours\', \'sunOT_rate\', \'sunOT_amount\', \'fixedotbasic\', \'fixedot\', \'total_overtime\',
                \'foodbasic\', \'food\', \'airtimebasic\', \'airtime\', \'nightallo\', \'responsebasic\', \'responseallo\',
                \'transbasic\', \'transport\', \'medicalbasic\', \'medical\', \'total_allowance\', \'gross\',
                \'emp_ssnit_13_percent\', \'ssnit_5_5_percent\', \'taxable_income\', \'paye\', \'ot_tax\', \'total_tax\',
                \'absent_without_pay\', \'salaryloan\', \'total_deductions\', \'refunds\', \'net_pay\'
            ],
            \'honda\' => [
                \'STAFFID\', \'NAME\', \'DATEOFEMPLOYMENT\', \'POSITION\', \'BASICSALARY_GHC\', \'LEGALWORKDAYS\',
                \'NUMBEROFDAYSWORKED\', \'LEAVEDAYS\', \'LEAVEAMOUNT\', \'BASICSALARYEARNED_GHC\', \'PERFORMANCEBONUSSTANDARD\',
                \'COEFFICIENT\', \'PERFORMANCEBONUS\', \'TRANSPORTALLOWANCE\', \'foodallowance\', \'medicalinsurance\',
                \'GROSSSALARY_GHC\', \'MANAGEMENTFEE_8\', \'GETFUND_2_5\', \'NHIL_2_5\', \'COVID_19_1\', \'VAT_15\',
                \'SSF_13\', \'COSTTOHONDA_GHC\', \'SSNITEE_0_5\', \'PETRA_5\', \'TAXABLEINCOME_GHC\', \'PAYE_GHC\',
                \'TOTALDEDUCTIONS_GHC\', \'TAKEHOMEPAY_GHC\', \'BANK\', \'ACCOUNTNUMBER\', \'BRANCH\', \'SSNITNUMBER\', \'GHCARDNUMBER\'
            ],
            \'bungegh\' => [
                \'STAFF_ID\', \'NAME\', \'POSITION\', \'BASE_SALARY_GHC\', \'TRANSPORT_ALLOWANCE_GHC\', \'ADDITIONAL_ALLOWANCE_GHC\',
                \'OVERTIME_GHC\', \'BONUS_EARNINGS_ALLOWANCE\', \'GROSS_SALARY_GHC\', \'MANAGEMENT_FEE_5_PERCENT\',
                \'SSNIT_ER_13_PERCENT\', \'GETFUND_2_5_PERCENT\', \'NHIL_2_5_PERCENT\', \'COVID_19_1_PERCENT\',
                \'VAT_15_PERCENT\', \'COST_TO_BUNGE_GHC\', \'gross2\', \'SSNIT_5_5_PERCENT\', \'TAXABLE_INCOME_GHC\',
                \'PAYE_GHC\', \'BONUS_TAX\', \'TOTAL_TAXATION_GHC\', \'TAKE_HOME_PAY_GHC\', \'BANK\', \'ACCOUNT_NUMBER\',
                \'BRANCH\', \'SSNIT\', \'GH_CARD_NUMBER\'
            ],
        ];

        return $templates[strtolower(trim($clientDb))] ?? [];
    }';

if (strpos($content, 'public static function getStandardColumnsWithOverrides') !== false) {
    $content = str_replace($oldMethodTarget, $newMethods, $content);
    file_put_contents($modelPath, $content);
    echo "Updated PayrollColumn.php with tailored methods!\n";
} else {
    echo "Could not find getStandardColumnsWithOverrides target in PayrollColumn.php\n";
}

