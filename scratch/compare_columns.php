<?php
/**
 * Extract ALL unique column names from controller header arrays
 * and compare against getCanonicalStandardColumns().
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;

$canonical = PayrollColumn::getCanonicalStandardColumns();
$canonicalKeys = array_map('strtolower', array_keys($canonical));
echo "CANONICAL COUNT: " . count($canonicalKeys) . "\n\n";

// All header columns from SalaryPaymentController + individual controllers
// (extracted manually from templates)
$allControllerCols = [
    // Transsion DB mapping columns
    'staffida', 'name', 'department', 'hiredate', 'constart', 'conend', 'position', 'grade',
    'categories', 'workplace', 'bankname', 'alcnum', 'branch', 'ssnit', 'col12', 'workdays',
    'leavedays', 'otworkdays', 'otweekend', 'basic', 'performbonus', 'coefficient', 'performstand',
    'allowance', 'commission', 'otworkingday', 'otholiday', 'other', 'annaulbonus', 'leavededuction',
    'otherdeduction', 'loan', 'grosspay', 'excess', 'totalemolu', 'accommodation', 'chargeincome',
    'petra', 'ssnitamt', 'nssf', 'taxbase', 'paye', 'annaultax', 'netpay', 'hrcost', 'brandname',

    // Melcom template columns (DB col names)
    'employ', 'empoyeename', 'outsourcename', 'designation', 'joindate', 'shopname', 'category',
    'saturdayot', 'actualdays', 'night', 'halfdays', 'satoffdays', 'sunoffdays', 'leavededuct',
    'absentdays', 'absenthrs', 'holiday', 'weekdaysOT', 'weekendOT', 'holidayot',
    'absentwithoutpay', 'basicentitlement', 'transport', 'normalovertime', 'saturdayovertime',
    'holidayotamt', 'gross', 'total_cash_emolument', 'total_assessable_income',
    'taxable', 'totalovertime', 'overtimetax', 'totaltax', 'trust', 'totaldeduction', 'banktransfer',

    // Nyonkopa
    'staff_id', 'employee_name', 'start_date', 'work_location', 'base_salary',
    'fuel_and_rent_allowance', 'gross_salary', 'management_fee_8', 'getfund_2_5', 'nhil_2_5',
    'covid_19_1', 'vat_15', 'medical_insurance', 'employer_provident_fund_5', 'thirteenth_month_bonus',
    'ssnit_er_13', 'cost_to_nyonkopa', 'ssnit_ee_5_5', 'taxable_income', 'paye',
    'thirteenth_month_tax', 'total_taxation', 'total_deductions', 'take_home_pay',

    // Honda
    'DATEOFEMPLOYMENT', 'BASICSALARY_GHC', 'LEGALWORKDAYS', 'NUMBEROFDAYSWORKED',
    'LEAVEDAYS', 'LEAVEAMOUNT', 'BASICSALARYEARNED_GHC', 'PERFORMANCEBONUSSTANDARD',
    'PERFORMANCEBONUS', 'TRANSPORTALLOWANCE', 'foodallowance', 'medicalinsurance',
    'GROSSSALARY_GHC', 'MANAGEMENTFEE_8', 'GETFUND_2_5', 'NHIL_2_5', 'COVID_19_1', 'VAT_15',
    'SSF_13', 'COSTTOHONDA_GHC', 'SSNITEE_0_5', 'PETRA_5', 'TAXABLEINCOME_GHC', 'PAYE_GHC',
    'TOTALDEDUCTIONS_GHC', 'TAKEHOMEPAY_GHC', 'BANK', 'ACCOUNTNUMBER', 'BRANCH', 'SSNITNUMBER', 'GHCARDNUMBER',

    // Transsnet
    'staffid', 'business_line', 'hired_date', 'last_date', 'work_place', 'staff_category',
    'expiration_date', 'contracting_entity', 'bank_name', 'account_name', 'bank_account_no',
    'branch_code', 'performance_basis', 'performance_final', 'call_allowance', 'house_allowance',
    'traffic_allowance', 'severance_pay', 'other_income', 'work_days', 'absent_days',
    'absent_days_deduction', 'other_deduction', 'petra_ee_5', 'ssnit_ee_05', 'base_for_paye',
    'ee_paye', 'ssnit_er_13', 'outsourced_service', 'getfund', 'nhiil', 'covid_19',
    'vat_for_music_ghana', 'total_cost_of_outsource_service', 'ee_cost', 'er_cost',
    'after_tax_deductions', 'total_hr_cost',

    // Shefer
    'name1', 'startdate', 'total_weekdays', 'no_of_days_worked', 'leavepay', 'absent_days',
    'basic_earned', 'nightdays', 'weekdayOT_hours', 'weekdayOT_rate', 'weekdayOT_amount',
    'weekendOT_hours', 'weekendOT_rate', 'weekendOT_amount', 'sunOT_hours', 'sunOT_rate',
    'sunOT_amount', 'fixedotbasic', 'fixedot', 'total_overtime', 'foodbasic', 'food',
    'airtimebasic', 'airtime', 'nightallo', 'responsebasic', 'responseallo', 'transbasic',
    'medicalbasic', 'medical', 'total_allowance', 'emp_ssnit_13_percent',
    'ssnit_5_5_percent', 'ot_tax', 'total_tax', 'absent_without_pay', 'salaryloan',
    'refunds', 'net_pay',

    // Fon Package
    'date_of_employment', 'basic_salary', 'legal_work_days', 'number_of_days_worked',
    'no_of_absent_days', 'absent_days_deduction', 'basic_salary_earned',
    'number_of_days_on_night_duty', 'normal_weekday_overtime_hours', 'normal_weekday_overtime_rate',
    'weekday_overtime_total', 'saturday_overtime_hours', 'saturday_overtime_rate',
    'saturday_overtime_total', 'sunday_holiday_overtime_hours', 'sunday_holiday_overtime_rate',
    'sunday_holiday_overtime_total', 'total_overtime_amount', 'night_duty_allowance',
    'ssf_13', 'management_fee_10', 'cost_to_fon_packaging',
    'gross_salary_repeat', 'ssnit_ee_5_5', 'overtime_tax', 'total_taxation',

    // Drivers
    'report', 'dob', 'ssnitnum', 'age', 'remarks', 'salary', 'week', 'sat', 'satOT',
    'ssfyee', 'advance', 'servicecharge',

    // Century
    'outsourced', 'desig', 'nightallowance',

    // A2 Feed
    'absenteeism_days', 'absent_days_deductions', 'risk_allowance', 'rent_allowance',
    'transport_standard', 'transport_earned', 'weekday_overtime_hours', 'weekday_overtime_rate',
    'weekday_overtime_amount', 'sunday_overtime_hours', 'sunday_overtime_rate', 'sunday_overtime_amount',
    'total_overtime_amount', 'management_fee_8', 'getfund_2_5', 'nhil_2_5', 'covid_1', 'vat_15',
    'ssf_13', 'workmens_compensation', 'cost_to_a2_agro',

    // A2 Poultry
    'legalwork', 'fixed_sunday_overtime', 'sundays_worked',

    // OFI Bean WH
    'workdays', 'f_trate', 'weekdayhr', 'weekdaytonage', 'weekdayrate', 'amt',
    'weekdayothr', 'weekdayottonage', 'weekdayotrate', 'weekdayotamt1',
    'weekendhr', 'weekendtonage', 'weekendrate', 'amount', 'totalamount',
    'phonecredit', 'ssnit13', 'f_tamount', 'costtoolam', 'ssnitee', 'taxincome',

    // OFI Offload
    'employeename', 'workarea', 'basicsalary', 'arrears', 'costtoolam', 'takehomepay',

    // OFI Hygiene External
    'noofdayspresent', 'basicsalaryearned', 'weekdayovertimehr', 'weekdayovertimetotal',
    'weekendovertimehr', 'weekendovertimetotal',

    // OFI Hygiene
    'names', 'noofdays', 'extraldays', 'total1', 'rate', 'total2',
    'overtimehr', 'overtimetotal', 'overtimeweekend', 'overtimetotal2',
    'leave1', 'costofolam',

    // OFI Tempering (adds crane)
    'crane',

    // Bunge GH
    'BASE_SALARY_GHC', 'TRANSPORT_ALLOWANCE_GHC', 'ADDITIONAL_ALLOWANCE_GHC', 'OVERTIME_GHC',
    'BONUS_EARNINGS_ALLOWANCE', 'GROSS_SALARY_GHC', 'MANAGEMENT_FEE_5_PERCENT',
    'SSNIT_ER_13_PERCENT', 'GETFUND_2_5_PERCENT', 'NHIL_2_5_PERCENT', 'COVID_19_1_PERCENT',
    'VAT_15_PERCENT', 'COST_TO_BUNGE_GHC', 'gross2', 'SSNIT_5_5_PERCENT',
    'TAXABLE_INCOME_GHC', 'PAYE_GHC', 'BONUS_TAX', 'TOTAL_TAXATION_GHC',
    'TAKE_HOME_PAY_GHC', 'GH_CARD_NUMBER',

    // Twellium Accra
    'legal_work_days', 'absent_deduction', 'weekday_hours', 'weekday_overtime_amt',
    'saturday_hours', 'saturday_overtime_amt', 'num_sundays_holidays',
    'sunday_holiday_overtime_amt', 'overtimerefund', 'total_overtime_amt',
    'management_fee', 'covid_19', 'vat_15', 'ssf_13', 'end_of_year',
    'ppe', 'workmen_compensation', 'cost_to_twellium', 'ssnit_ee',
    'deductions', 'net_salary', 'refund',

    // Twellium GH
    'dateemploy', 'expected_work_days', 'total_hours_worked', 'food_allowance',
    'night_allowance', 'deduction', 'welfare', 'risk_allowance', 'staff_cost',
    'mgt_fee', 'gfl', 'c2c', 'offday_holiday_overtime_hours',
    'offday_holiday_overtime_rate', 'offday_holiday_overtime_total',
    'special_day_overtime_hours', 'special_day_overtime_rate', 'special_day_overtime_amount',

    // PZ
    'dept', 'overtime', 'incentive', '13month', 'totalearn', 'ssnitee',
    'overtimetaxt', 'pf', 'ssniter', '13tax', 'totaltaxdeduct', 'totaldeduct',
    'bank1', 'account1', 'branch1', 'ssnit1', 'ghcard',

    // JKay
    'daily_rate', 'days_worked', 'absentee_days', 'absent_deductions',
    'earned_basic_salary', 'saturdays_worked', 'saturday_rate', 'saturday_amount',
    'total_days_amount', 'overtime_hours', 'overtime_rate', 'overtime_amount',
    'night_allowance', 'totalovertime', 'management_fee', 'getfund', 'nhil',
    'covid19', 'vat', 'ssf', 'cost_to_jkay', 'ssnit_ee',
    'surcharge_or_student_loan',
];

// Normalize and deduplicate
$unique = [];
foreach ($allControllerCols as $col) {
    $lower = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $col));
    $unique[$lower] = $col;
}

// Find missing from canonical
$missing = [];
foreach ($unique as $lower => $original) {
    // Skip common non-payroll columns
    if (in_array($lower, ['staff_id', 'staffid', 'staffida', 'name', 'name1', 'names', 'employ', 'empoyeename', 'employeename', 'id'])) continue;
    if (!in_array($lower, $canonicalKeys)) {
        $missing[$lower] = $original;
    }
}

ksort($missing);
echo "CONTROLLER UNIQUE COLS: " . count($unique) . "\n";
echo "MISSING FROM CANONICAL: " . count($missing) . "\n\n";

foreach ($missing as $lower => $original) {
    echo "  '{$lower}' (original: '{$original}')\n";
}

