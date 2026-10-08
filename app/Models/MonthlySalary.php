<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class MonthlySalary extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $table; // Dynamic table name

    // Base columns that exist in ALL clients (staffida, name, netpay)
    protected $guarded = [];

    public function __construct($attributes = [], $clientDbName = null, $month = null)
    {
        parent::__construct($attributes);

        if ($clientDbName && $month) {
            $this->setTable(self::generateTableName($clientDbName, $month));
        }
    }

    public static function forClient($clientDbName, $month)
    {
        $tableName = self::generateTableName($clientDbName, $month);

        if (!Schema::hasTable($tableName)) {
            self::createClientTable($tableName, $clientDbName);
        }

        return new static([], $clientDbName, $month);
    }

    public static function generateTableName($clientDbName, $month)
{
    $date = Carbon::createFromFormat('Y-m', $month);
    $monthName = strtolower($date->format('F'));
    $yearShort = $date->format('y');
    return strtolower($clientDbName . $monthName . $yearShort); // ← NO UNDERSCORE
}
    public static function createClientTable($tableName, $clientDbName)
    {
        $columns = self::getClientColumns($clientDbName);

        if ($clientDbName === 'melcom') {
    $sql .= "    `employ` VARCHAR(50) NOT NULL PRIMARY KEY,\n";
    $sql .= "    `empoyeename` VARCHAR(150) NULL,\n";
} else {
    $sql .= "    `staffida` VARCHAR(50) NOT NULL PRIMARY KEY,\n";
    $sql .= "    `name` VARCHAR(150) NULL,\n";
}

foreach ($columns as $col => $type) {
    if (in_array($col, ['staffida', 'name', 'employ', 'empoyeename'])) continue;
    $sql .= "    `$col` $type NULL,\n";
}

$sql .= "    `netpay` DOUBLE NULL\n";
$sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        DB::statement($sql);
    }

    private static function getClientColumns($clientDbName)
    {
        $base = [
            'staffida' => 'VARCHAR(50)',
            'name' => 'VARCHAR(150)',
        ];

       $clientColumns = [
    'transsion' => [
        'department' => 'VARCHAR(100)',
        'hiredate' => 'VARCHAR(50)',
        'constart' => 'VARCHAR(50)',
        'conend' => 'VARCHAR(50)',
        'position' => 'VARCHAR(100)',
        'grade' => 'VARCHAR(50)',
        'categories' => 'VARCHAR(100)',
        'workplace' => 'VARCHAR(100)',
        'bankname' => 'VARCHAR(100)',
        'alcnum' => 'VARCHAR(50)',
        'branch' => 'VARCHAR(100)',
        'ssnit' => 'VARCHAR(50)',
        'col12' => 'VARCHAR(20)',
        'workdays' => 'DOUBLE',
        'leavedays' => 'DOUBLE',
        'otworkdays' => 'DOUBLE',
        'otweekend' => 'DOUBLE',
        'basic' => 'DOUBLE',
        'performbonus' => 'DOUBLE',
        'coefficient' => 'DOUBLE',
        'performstand' => 'DOUBLE',
        'allowance' => 'DOUBLE',
        'commission' => 'DOUBLE',
        'otworkingday' => 'DOUBLE',
        'otholiday' => 'DOUBLE',
        'other' => 'DOUBLE',
        'annaulbonus' => 'DOUBLE',
        'leavededuction' => 'DOUBLE',
        'otherdeduction' => 'DOUBLE',
        'loan' => 'DOUBLE',
        'grosspay' => 'DOUBLE',
        'excess' => 'DOUBLE',
        'totalemolu' => 'DOUBLE',
        'accommodation' => 'DOUBLE',
        'chargeincome' => 'DOUBLE',
        'petra' => 'DOUBLE',
        'ssnitamt' => 'DOUBLE',
        'nssf' => 'DOUBLE',
        'taxbase' => 'DOUBLE',
        'paye' => 'DOUBLE',
        'annaultax' => 'DOUBLE',
        'hrcost' => 'DOUBLE',
        'brandname' => 'VARCHAR(100)',
    ],
    'transsiontec' => [ /* same as transsion */ ], // fallback handled below

    'melcom' => [
        'employ' => 'VARCHAR(50)',           // ← THIS WAS MISSING
    'empoyeename' => 'VARCHAR(150)',     // ← THIS WAS MISSING
        'outsourcename' => 'VARCHAR(100)',
        'designation' => 'VARCHAR(100)',
        'joindate' => 'VARCHAR(50)',
        'shopname' => 'VARCHAR(100)',
        'category' => 'VARCHAR(50)',
        'saturdayot' => 'DOUBLE',
        'actualdays' => 'DOUBLE',
        'night' => 'DOUBLE',
        'halfdays' => 'DOUBLE',
        'satoffdays' => 'DOUBLE',
        'sunoffdays' => 'DOUBLE',
        'leavededuct' => 'DOUBLE',
        'absentdays' => 'DOUBLE',
        'absenthrs' => 'DOUBLE',
        'holiday' => 'DOUBLE',
        'weekdaysOT' => 'DOUBLE',
        'weekendOT' => 'DOUBLE',
        'holidayot' => 'DOUBLE',
        'basic' => 'DOUBLE',
        'absentwithoutpay' => 'DOUBLE',
        'basicentitlement' => 'DOUBLE',
        'transport' => 'DOUBLE',
        'normalovertime' => 'DOUBLE',
        'saturdayovertime' => 'DOUBLE',
        'holidayotamt' => 'DOUBLE',
        'gross' => 'DOUBLE',
        'ssnit' => 'DOUBLE',
        'taxable' => 'DOUBLE',
        'paye' => 'DOUBLE',
        'totalovertime' => 'DOUBLE',
        'overtimetax' => 'DOUBLE',
        'totaltax' => 'DOUBLE',
        'trust' => 'DOUBLE',
        'totaldeduction' => 'DOUBLE',
    ],

    'nyonkopa' => [
        'start_date' => 'VARCHAR(50)',
        'position' => 'VARCHAR(100)',
        'work_location' => 'VARCHAR(100)',
        'base_salary' => 'DOUBLE',
        'fuel_and_rent_allowance' => 'DOUBLE',
        'gross_salary' => 'DOUBLE',
        'management_fee_8' => 'DOUBLE',
        'getfund_2_5' => 'DOUBLE',
        'nhil_2_5' => 'DOUBLE',
        'covid_19_1' => 'DOUBLE',
        'vat_15' => 'DOUBLE',
        'medical_insurance' => 'DOUBLE',
        'employer_provident_fund_5' => 'DOUBLE',
        'thirteenth_month_bonus' => 'DOUBLE',
        'ssnit_er_13' => 'DOUBLE',
        'cost_to_nyonkopa' => 'DOUBLE',
        'ssnit_ee_5_5' => 'DOUBLE',
        'taxable_income' => 'DOUBLE',
        'paye' => 'DOUBLE',
        'thirteenth_month_tax' => 'DOUBLE',
        'total_taxation' => 'DOUBLE',
        'total_deductions' => 'DOUBLE',
    ],

    'honda' => [
        'DATEOFEMPLOYMENT' => 'VARCHAR(50)',
        'POSITION' => 'VARCHAR(100)',
        'BASICSALARY_GHC' => 'DOUBLE',
        'LEGALWORKDAYS' => 'DOUBLE',
        'NUMBEROFDAYSWORKED' => 'DOUBLE',
        'LEAVEDAYS' => 'DOUBLE',
        'LEAVEAMOUNT' => 'DOUBLE',
        'BASICSALARYEARNED_GHC' => 'DOUBLE',
        'PERFORMANCEBONUSSTANDARD' => 'DOUBLE',
        'COEFFICIENT' => 'DOUBLE',
        'PERFORMANCEBONUS' => 'DOUBLE',
        'TRANSPORTALLOWANCE' => 'DOUBLE',
        'foodallowance' => 'DOUBLE',
        'medicalinsurance' => 'DOUBLE',
        'GROSSSALARY_GHC' => 'DOUBLE',
        'MANAGEMENTFEE_8' => 'DOUBLE',
        'GETFUND_2_5' => 'DOUBLE',
        'NHIL_2_5' => 'DOUBLE',
        'COVID_19_1' => 'DOUBLE',
        'VAT_15' => 'DOUBLE',
        'SSF_13' => 'DOUBLE',
        'COSTTOHONDA_GHC' => 'DOUBLE',
        'SSNITEE_0_5' => 'DOUBLE',
        'PETRA_5' => 'DOUBLE',
        'TAXABLEINCOME_GHC' => 'DOUBLE',
        'PAYE_GHC' => 'DOUBLE',
        'TOTALDEDUCTIONS_GHC' => 'DOUBLE',
        'BANK' => 'VARCHAR(100)',
        'ACCOUNTNUMBER' => 'VARCHAR(50)',
        'BRANCH' => 'VARCHAR(100)',
        'SSNITNUMBER' => 'VARCHAR(50)',
        'GHCARDNUMBER' => 'VARCHAR(50)',
    ],

    'transsnet' => [
        'business_line' => 'VARCHAR(100)',
        'department' => 'VARCHAR(100)',
        'position' => 'VARCHAR(100)',
        'hired_date' => 'VARCHAR(50)',
        'last_date' => 'VARCHAR(50)',
        'work_place' => 'VARCHAR(100)',
        'staff_category' => 'VARCHAR(100)',
        'expiration_date' => 'VARCHAR(50)',
        'contracting_entity' => 'VARCHAR(100)',
        'bank_name' => 'VARCHAR(100)',
        'account_name' => 'VARCHAR(100)',
        'bank_account_no' => 'VARCHAR(50)',
        'branch_code' => 'VARCHAR(100)',
        'basic' => 'DOUBLE',
        'performance_basis' => 'DOUBLE',
        'coefficient' => 'DOUBLE',
        'performance_final' => 'DOUBLE',
        'call_allowance' => 'DOUBLE',
        'house_allowance' => 'DOUBLE',
        'traffic_allowance' => 'DOUBLE',
        'commission' => 'DOUBLE',
        'severance_pay' => 'DOUBLE',
        'other_income' => 'DOUBLE',
        'work_days' => 'DOUBLE',
        'absent_days' => 'DOUBLE',
        'absent_days_deduction' => 'DOUBLE',
        'other_deduction' => 'DOUBLE',
        'gross_salary' => 'DOUBLE',
        'petra_ee_5' => 'DOUBLE',
        'ssnit_ee_05' => 'DOUBLE',
        'base_for_paye' => 'DOUBLE',
        'ee_paye' => 'DOUBLE',
        'ssnit_er_13' => 'DOUBLE',
        'outsourced_service' => 'DOUBLE',
        'getfund' => 'DOUBLE',
        'nhiil' => 'DOUBLE',
        'covid_19' => 'DOUBLE',
        'vat_for_music_ghana' => 'DOUBLE',
        'total_cost_of_outsource_service' => 'DOUBLE',
        'ee_cost' => 'DOUBLE',
        'er_cost' => 'DOUBLE',
        'after_tax_deductions' => 'DOUBLE',
        'total_hr_cost' => 'DOUBLE',
    ],

    'jkay' => [
        'position' => 'VARCHAR(100)',
        'join_date' => 'VARCHAR(50)',
        'basic_salary' => 'DOUBLE',
        'legalworkdays' => 'DOUBLE',
        'daily_rate' => 'DOUBLE',
        'days_worked' => 'DOUBLE',
        'absentee_days' => 'DOUBLE',
        'absent_deductions' => 'DOUBLE',
        'earned_basic_salary' => 'DOUBLE',
        'saturdays_worked' => 'DOUBLE',
        'saturday_rate' => 'DOUBLE',
        'saturday_amount' => 'DOUBLE',
        'total_days_amount' => 'DOUBLE',
        'overtime_hours' => 'DOUBLE',
        'overtime_rate' => 'DOUBLE',
        'overtime_amount' => 'DOUBLE',
        'night_allowance' => 'DOUBLE',
        'totalovertime' => 'DOUBLE',
        'gross_salary' => 'DOUBLE',
        'management_fee' => 'DOUBLE',
        'getfund' => 'DOUBLE',
        'nhil' => 'DOUBLE',
        'covid19' => 'DOUBLE',
        'vat' => 'DOUBLE',
        'ssf' => 'DOUBLE',
        'cost_to_jkay' => 'DOUBLE',
        'ssnit_ee' => 'DOUBLE',
        'taxable_income' => 'DOUBLE',
        'paye' => 'DOUBLE',
        'overtime_tax' => 'DOUBLE',
        'surcharge_or_student_loan' => 'DOUBLE',
        'total_deductions' => 'DOUBLE',
    ],

    'pz' => [
        'dept' => 'VARCHAR(100)',
        'basic' => 'DOUBLE',
        'overtime' => 'DOUBLE',
        'incentive' => 'DOUBLE',
        'bonus' => 'DOUBLE',
        'other' => 'DOUBLE',
        '13month' => 'DOUBLE',
        'totalearn' => 'DOUBLE',
        'ssnitee' => 'DOUBLE',
        'overtimetaxt' => 'DOUBLE',
        'pf' => 'DOUBLE',
        'taxable' => 'DOUBLE',
        'ssniter' => 'DOUBLE',
        '13tax' => 'DOUBLE',
        'totaltaxdeduct' => 'DOUBLE',
        'totaldeduct' => 'DOUBLE',
        'bank1' => 'VARCHAR(100)',
        'account1' => 'VARCHAR(50)',
        'branch1' => 'VARCHAR(100)',
        'ssnit1' => 'VARCHAR(50)',
        'ghcard' => 'VARCHAR(50)',
    ],

    'twelliumaccra' => [
        'position' => 'VARCHAR(100)',
        'start_date' => 'VARCHAR(50)',
        'basic_salary' => 'DOUBLE',
        'legal_work_days' => 'DOUBLE',
        'days_worked' => 'DOUBLE',
        'absent_days' => 'DOUBLE',
        'absent_deduction' => 'DOUBLE',
        'basic_earned' => 'DOUBLE',
        'weekday_hours' => 'DOUBLE',
        'weekday_overtime_amt' => 'DOUBLE',
        'saturday_hours' => 'DOUBLE',
        'saturday_overtime_amt' => 'DOUBLE',
        'num_sundays_holidays' => 'DOUBLE',
        'sunday_holiday_overtime_amt' => 'DOUBLE',
        'overtimerefund' => 'DOUBLE',
        'total_overtime_amt' => 'DOUBLE',
        'gross_salary' => 'DOUBLE',
        'management_fee' => 'DOUBLE',
        'getfund' => 'DOUBLE',
        'nhil' => 'DOUBLE',
        'covid_19' => 'DOUBLE',
        'vat_15' => 'DOUBLE',
        'ssf_13' => 'DOUBLE',
        'medical_insurance' => 'DOUBLE',
        'end_of_year' => 'DOUBLE',
        'ppe' => 'DOUBLE',
        'workmen_compensation' => 'DOUBLE',
        'cost_to_twellium' => 'DOUBLE',
        'ssnit_ee' => 'DOUBLE',
        'taxable_income' => 'DOUBLE',
        'paye' => 'DOUBLE',
        'deductions' => 'DOUBLE',
        'total_deductions' => 'DOUBLE',
        'net_salary' => 'DOUBLE',
        'refund' => 'DOUBLE',
    ],

    // ... CONTINUE WITH ALL 25 BELOW ...

    'shefer' => [
        'position' => 'VARCHAR(100)',
        'department' => 'VARCHAR(100)',
        'startdate' => 'VARCHAR(50)',
        'basic' => 'DOUBLE',
        'total_weekdays' => 'DOUBLE',
        'no_of_days_worked' => 'DOUBLE',
        'leavepay' => 'DOUBLE',
        'absent_days' => 'DOUBLE',
        'basic_earned' => 'DOUBLE',
        'nightdays' => 'DOUBLE',
        'weekdayOT_hours' => 'DOUBLE',
        'weekdayOT_rate' => 'DOUBLE',
        'weekdayOT_amount' => 'DOUBLE',
        'weekendOT_hours' => 'DOUBLE',
        'weekendOT_rate' => 'DOUBLE',
        'weekendOT_amount' => 'DOUBLE',
        'sunOT_hours' => 'DOUBLE',
        'sunOT_rate' => 'DOUBLE',
        'sunOT_amount' => 'DOUBLE',
        'fixedotbasic' => 'DOUBLE',
        'fixedot' => 'DOUBLE',
        'total_overtime' => 'DOUBLE',
        'foodbasic' => 'DOUBLE',
        'food' => 'DOUBLE',
        'airtimebasic' => 'DOUBLE',
        'airtime' => 'DOUBLE',
        'nightallo' => 'DOUBLE',
        'responsebasic' => 'DOUBLE',
        'responseallo' => 'DOUBLE',
        'transbasic' => 'DOUBLE',
        'transport' => 'DOUBLE',
        'medicalbasic' => 'DOUBLE',
        'medical' => 'DOUBLE',
        'total_allowance' => 'DOUBLE',
        'gross' => 'DOUBLE',
        'emp_ssnit_13_percent' => 'DOUBLE',
        'ssnit_5_5_percent' => 'DOUBLE',
        'taxable_income' => 'DOUBLE',
        'paye' => 'DOUBLE',
        'ot_tax' => 'DOUBLE',
        'total_tax' => 'DOUBLE',
        'absent_without_pay' => 'DOUBLE',
        'salaryloan' => 'DOUBLE',
        'total_deductions' => 'DOUBLE',
        'refunds' => 'DOUBLE',
    ],

    // ... ALL OTHERS ...

    'twellium' => [
        'department' => 'VARCHAR(100)',
        'dateemploy' => 'VARCHAR(50)',
        'expected_work_days' => 'DOUBLE',
        'total_hours_worked' => 'DOUBLE',
        'basic' => 'DOUBLE',
        'basic_earned' => 'DOUBLE',
        'food_allowance' => 'DOUBLE',
        'night_allowance' => 'DOUBLE',
        'refund' => 'DOUBLE',
        'allowance' => 'DOUBLE',
        'deduction' => 'DOUBLE',
        'ssnit_ee' => 'DOUBLE',
        'taxable_income' => 'DOUBLE',
        'paye' => 'DOUBLE',
        'ssf' => 'DOUBLE',
        'welfare' => 'DOUBLE',
        'medical_insurance' => 'DOUBLE',
        'workmens_compensation' => 'DOUBLE',
        'risk_allowance' => 'DOUBLE',
        'end_of_year' => 'DOUBLE',
        'ppe' => 'DOUBLE',
        'staff_cost' => 'DOUBLE',
        'mgt_fee' => 'DOUBLE',
        'gfl' => 'DOUBLE',
        'nhil' => 'DOUBLE',
        'covid' => 'DOUBLE',
        'vat' => 'DOUBLE',
        'c2c' => 'DOUBLE',
        'weekday_overtime_hours' => 'DOUBLE',
        'weekday_overtime_rate' => 'DOUBLE',
        'weekday_overtime_amount' => 'DOUBLE',
        'offday_holiday_overtime_hours' => 'DOUBLE',
        'offday_holiday_overtime_rate' => 'DOUBLE',
        'offday_holiday_overtime_total' => 'DOUBLE',
        'special_day_overtime_hours' => 'DOUBLE',
        'special_day_overtime_rate' => 'DOUBLE',
        'special_day_overtime_amount' => 'DOUBLE',
        'total_overtime_amount' => 'DOUBLE',
        'overtime_tax' => 'DOUBLE',
        'total_taxation' => 'DOUBLE',
        'total_deductions' => 'DOUBLE',
    ],
];

        $specific = $clientColumns[strtolower($clientDbName)] ?? $clientColumns['transsion'] ?? [];

        return array_merge($base, $specific);
    }

    // Optional: Drop table (for cleanup)
    public static function dropTable($tableName)
    {
        if (Schema::hasTable($tableName)) {
            Schema::drop($tableName);
        }
    }
}
