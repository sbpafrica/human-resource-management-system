<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AI\AiPayloadSanitizer;
use App\Services\Payroll\ColumnMatcher\LegacyColumnMatcher;
use App\Services\Payroll\ColumnMatcher\RuleBasedColumnMatcher;
use App\Services\Payroll\ColumnMatcher\HybridColumnMatcher;
use App\Services\Payroll\ColumnMatcher\GrokColumnMatcher;
use App\Services\Payroll\Anomaly\RuleBasedAnomalyDetector;
use App\Services\Payroll\Anomaly\HybridAnomalyDetector;
use App\Services\Payroll\Anomaly\GrokAnomalyDetector;
use App\Services\AI\GrokAiClient;

class PayrollAiIntegrationTest extends TestCase
{
    public function test_ai_payload_sanitizer_scrubs_pii_for_act_843_compliance(): void
    {
        $sanitizer = new AiPayloadSanitizer();

        $headers = ['Staff ID', 'Full Name', 'Ghana Card PIN', 'Basic Salary', 'Bank Account'];
        $sampleRows = [
            ['JK001', 'Kwame Mensah', 'GHA-712345678-1', '2500.00', '1441001234567'],
        ];

        $sanitized = $sanitizer->sanitizeForColumnMatching($headers, $sampleRows);

        // Header preservation
        $this->assertEquals($headers, $sanitized['headers']);

        // Check sample row masking: text replaced, numbers preserved
        $sample = $sanitized['samples'][0];
        $this->assertEquals('[TEXT_STRING]', $sample[0]); // ID text masked
        $this->assertEquals('[TEXT_STRING]', $sample[1]); // Real Name NEVER sent to AI
        $this->assertEquals('GHA-*********', $sample[2]); // Ghana card masked
        $this->assertEquals(2500.00, $sample[3]);          // Number preserved for scale
        $this->assertEquals(1441001234567.0, $sample[4]);  // Numeric preserved
    }

    public function test_rule_based_matcher_resolves_standard_ghanaian_payroll_headers(): void
    {
        $matcher = new RuleBasedColumnMatcher();

        $headers = [
            'Emp ID',
            'Employee Name',
            'Designation',
            'Base Salary',
            'Days Worked',
            'Total Overtime',
            'Total Earnings',
            'SSNIT 5.5%',
            'Petra Tier 2',
            'P.A.Y.E.',
            'Take Home Pay',
            'Bank Name',
            'Account Number',
        ];

        $result = $matcher->match($headers, 'jaykay');

        $this->assertNotEmpty($result['mapping']);
        $this->assertEquals(0, $result['mapping']['staff_id']);
        $this->assertEquals(1, $result['mapping']['name']);
        $this->assertEquals(2, $result['mapping']['position']);
        $this->assertEquals(3, $result['mapping']['basic_salary']);
        $this->assertEquals(4, $result['mapping']['days_worked']);
        $this->assertEquals(5, $result['mapping']['overtime_amount']);
        $this->assertEquals(6, $result['mapping']['gross_salary']);
        $this->assertEquals(7, $result['mapping']['ssnit_ee']);
        $this->assertEquals(8, $result['mapping']['petra']);
        $this->assertEquals(9, $result['mapping']['paye']);
        $this->assertEquals(10, $result['mapping']['net_pay']);
        $this->assertEquals(11, $result['mapping']['bank']);
        $this->assertEquals(12, $result['mapping']['account']);
        $this->assertEquals('rule', $result['tier']);
        $this->assertGreaterThanOrEqual(0.85, $result['confidence']);
    }

    public function test_legacy_matcher_returns_safe_offsets_for_jaykay_and_ofi(): void
    {
        $matcher = new LegacyColumnMatcher();

        $jaykayResult = $matcher->match([], 'jaykay');
        $this->assertEquals(0, $jaykayResult['mapping']['staff_id']);
        $this->assertEquals(4, $jaykayResult['mapping']['basic_salary']);
        $this->assertEquals(27, $jaykayResult['mapping']['ssnit_ee']);
        $this->assertEquals(28, $jaykayResult['mapping']['petra']);
        $this->assertEquals(34, $jaykayResult['mapping']['net_pay']);

        $ofiResult = $matcher->match([], 'ofitemp');
        $this->assertEquals(0, $ofiResult['mapping']['staff_id']);
        $this->assertEquals(4, $ofiResult['mapping']['gross_salary']);
        $this->assertEquals(5, $ofiResult['mapping']['ssnit_ee']);
        $this->assertEquals(6, $ofiResult['mapping']['petra']);
        $this->assertEquals(8, $ofiResult['mapping']['net_pay']);

        $twelliumAccraResult = $matcher->match([], 'twelliumaccra');
        $this->assertEquals(0, $twelliumAccraResult['mapping']['staff_id']);
        $this->assertEquals(10, $twelliumAccraResult['mapping']['weekday_hours']);
        $this->assertEquals(11, $twelliumAccraResult['mapping']['weekday_overtime_amt']);
        $this->assertEquals(12, $twelliumAccraResult['mapping']['saturday_hours']);
        $this->assertEquals(13, $twelliumAccraResult['mapping']['saturday_overtime_amt']);
        $this->assertEquals(14, $twelliumAccraResult['mapping']['num_sundays_holidays']);
        $this->assertEquals(15, $twelliumAccraResult['mapping']['sunday_holiday_overtime_amt']);
        $this->assertEquals(16, $twelliumAccraResult['mapping']['overtimerefund']);
        $this->assertEquals(17, $twelliumAccraResult['mapping']['total_overtime_amt']);
        $this->assertEquals(37, $twelliumAccraResult['mapping']['net_pay']);
    }

    public function test_rule_based_matcher_resolves_twellium_accra_overtime_headers(): void
    {
        $matcher = new RuleBasedColumnMatcher();

        $headers = [
            'Staff ID',
            'Name',
            'Position',
            'Basic Salary',
            'Weekday Hours',
            'Weekday Overtime Amt',
            'Saturday Hours',
            'Saturday Overtime Amt',
            'Num Sundays Holidays',
            'Sunday Holiday Overtime Amt',
            'Overtime Refund',
            'Total Overtime Amt',
            'Gross Salary',
            'Take Home Pay',
        ];

        $result = $matcher->match($headers, 'twelliumaccra');

        $this->assertNotEmpty($result['mapping']);
        $this->assertEquals(0, $result['mapping']['staff_id']);
        $this->assertEquals(1, $result['mapping']['name']);
        $this->assertEquals(2, $result['mapping']['position']);
        $this->assertEquals(3, $result['mapping']['basic_salary']);
        $this->assertEquals(4, $result['mapping']['weekday_hours']);
        $this->assertEquals(5, $result['mapping']['weekday_overtime_amt']);
        $this->assertEquals(6, $result['mapping']['saturday_hours']);
        $this->assertEquals(7, $result['mapping']['saturday_overtime_amt']);
        $this->assertEquals(8, $result['mapping']['num_sundays_holidays']);
        $this->assertEquals(9, $result['mapping']['sunday_holiday_overtime_amt']);
        $this->assertEquals(10, $result['mapping']['overtimerefund']);
        $this->assertEquals(11, $result['mapping']['total_overtime_amt']);
        $this->assertEquals(12, $result['mapping']['gross_salary']);
        $this->assertEquals(13, $result['mapping']['net_pay']);
    }

    public function test_rule_based_matcher_resolves_melcom_payroll_headers(): void
    {
        $matcher = new RuleBasedColumnMatcher();

        $headers = [
            'Sr. No',
            'Sr. No',
            'Emp',
            'Employee',
            'OUTSOURCE NAME',
            'Designation',
            'Join Date',
            'Location',
            'CATEGORY',
            'SATURDAY OVERTIME RATE',
            'Present Full days',
            'Present Night days',
            'Present Half days',
            'SAT _ OFF DAYS',
            'SUN _ OFF DAYS',
            'Leave Taken',
            'Absent days',
            'Abesnt Hrs',
            'Holidays',
            'Normal OT HRS',
            'Saturday OT HRS',
            'Holiday OT HRS',
            'Basic (Scale)',
            'Absent without pay',
            'Basic (Entitle)',
            'TRANSPORT',
            'Normal Overtime',
            'Saturday Overtime',
            'Holiday Overtime',
            "GROSS SALARY\n(AA=V+W+X+Y-Z)",
            'TOTAL CASH EMOLUMENT',
            'TOTAL ASSESSABLE INCOME',
            'SSNIT EE 0.5%',
            'SSNIT EE 5%',
            'Taxable Income',
            'PAYE',
            'Total Overtime',
            'OVERTIME TAX',
            'Total Taxation',
            'TO RECOVER & PAY TO SHOP/ SSNIT LOAN',
            'TOTAL DEDUCTIONS',
            'TAKE HOME PAY',
        ];

        $result = $matcher->match($headers, 'melcom');

        $this->assertNotEmpty($result['mapping']);
        $this->assertEquals(2, $result['mapping']['staff_id']);
        $this->assertEquals(3, $result['mapping']['name']);
        $this->assertEquals(5, $result['mapping']['position']);
        $this->assertEquals(7, $result['mapping']['shop']);
        $this->assertEquals(10, $result['mapping']['days_worked']);
        $this->assertEquals(19, $result['mapping']['weekday_hours']);
        $this->assertEquals(20, $result['mapping']['saturday_hours']);
        $this->assertEquals(21, $result['mapping']['num_sundays_holidays']);
        $this->assertEquals(22, $result['mapping']['basic_salary']);
        $this->assertEquals(26, $result['mapping']['weekday_overtime_amt']);
        $this->assertEquals(27, $result['mapping']['saturday_overtime_amt']);
        $this->assertEquals(28, $result['mapping']['sunday_holiday_overtime_amt']);
        $this->assertEquals(29, $result['mapping']['gross_salary']);
        $this->assertEquals(30, $result['mapping']['total_cash_emolument']);
        $this->assertEquals(31, $result['mapping']['total_assessable_income']);
        $this->assertEquals(32, $result['mapping']['ssnit_ee']);
        $this->assertEquals(34, $result['mapping']['taxable_income']);
        $this->assertEquals(35, $result['mapping']['paye']);
        $this->assertEquals(36, $result['mapping']['total_overtime_amt']);
        $this->assertEquals(37, $result['mapping']['overtime_tax']);
        $this->assertEquals(38, $result['mapping']['total_tax']);
        $this->assertEquals(40, $result['mapping']['total_deductions']);
        $this->assertEquals(41, $result['mapping']['net_pay']);
        $this->assertEquals(1.0, $result['confidence']);
    }

    public function test_payslip_controller_normalizes_twellium_accra_overtime_properly(): void
    {
        $client = (object)[
            'client_dbname' => 'twelliumaccra',
            'slug'          => 'twellium-industrial-company-accra',
            'short_name'    => 'Twellium Accra',
        ];

        $rawRecord = (object)[
            'staff_id'                    => 'SBPTWMA01',
            'name'                        => 'Francis Ohimeh',
            'position'                    => 'Supervisor',
            'basic_salary'                => 4024.43,
            'basic_earned'                => 3841.50,
            'weekday_hours'               => 10.00,
            'weekday_overtime_amt'        => 250.00,
            'saturday_hours'              => 12.00,
            'saturday_overtime_amt'       => 1200.00,
            'num_sundays_holidays'        => 8.00,
            'sunday_holiday_overtime_amt' => 900.00,
            'overtimerefund'              => 50.00,
            'total_overtime_amt'          => 2400.00,
            'gross_salary'                => 6241.50,
            'take_home_pay'               => 5500.00,
        ];

        $controller = new \App\Http\Controllers\PayslipController();
        $refMethod = new \ReflectionMethod($controller, 'normalizePayslip');
        $normalized = $refMethod->invoke($controller, $rawRecord, $client, '-');

        $this->assertEquals(10.00, $normalized->weekday_hours);
        $this->assertEquals(250.00, $normalized->weekday_overtime_amt);
        $this->assertEquals(12.00, $normalized->saturday_hours);
        $this->assertEquals(1200.00, $normalized->saturday_overtime_amt);
        $this->assertEquals(8.00, $normalized->num_sundays_holidays);
        $this->assertEquals(900.00, $normalized->sunday_holiday_overtime_amt);
        $this->assertEquals(50.00, $normalized->overtimerefund);
        $this->assertEquals(2400.00, $normalized->total_overtime_amt);
        $this->assertEquals(2400.00, $normalized->overtime);
        $this->assertEquals(250.00, $normalized->normalovertime);
        $this->assertEquals(1200.00, $normalized->saturdayovertime);
        $this->assertEquals(900.00, $normalized->holidayotamt);
    }

    public function test_hybrid_column_matcher_falls_back_gracefully_to_rule_or_legacy(): void
    {
        $matcher = app(HybridColumnMatcher::class);

        $headers = ['Staff ID', 'Name', 'Gross Pay', 'Net Salary'];
        $result = $matcher->match($headers, 'melcom');

        $this->assertNotEmpty($result['mapping']);
        $this->assertContains($result['tier'], ['ai', 'rule', 'legacy']);
        $this->assertArrayHasKey('staff_id', $result['mapping']);
    }

    public function test_rule_based_anomaly_detector_catches_duplicate_accounts_and_statutory_gaps(): void
    {
        $detector = new RuleBasedAnomalyDetector();

        $records = [
            [
                'staff_id' => 'EMP001',
                'name' => 'Kofi Annan',
                'basic_salary' => 2000.00,
                'gross_salary' => 2000.00,
                'ssnit_ee' => 110.00, // 5.5% correct
                'petra' => 100.00,    // 5.0% correct
                'net_pay' => 1600.00,
                'account' => '1234567890',
            ],
            [
                'staff_id' => 'EMP002',
                'name' => 'Ama Konadu',
                'basic_salary' => 2000.00,
                'gross_salary' => 2000.00,
                'ssnit_ee' => 0.00,   // Missing SSNIT!
                'petra' => 0.00,      // Missing Petra!
                'net_pay' => 1800.00,
                'account' => '1234567890', // DUPLICATE ACCOUNT WITH EMP001!
            ],
            [
                'staff_id' => 'EMP003',
                'name' => 'Yaw Mensah',
                'basic_salary' => 1500.00,
                'gross_salary' => 1500.00,
                'ssnit_ee' => 82.50,
                'petra' => 75.00,
                'net_pay' => -50.00, // NEGATIVE TAKE HOME!
                'account' => '9876543210',
            ],
        ];

        $report = $detector->detect($records, 'jaykay', '2026-10');

        $this->assertFalse($report['passed']); // Should fail approval due to critical anomalies
        $this->assertGreaterThanOrEqual(1, $report['summary']['critical_count']);

        $types = array_column($report['anomalies'], 'type');
        $this->assertContains('duplicate_bank_account', $types);
        $this->assertContains('negative_net_pay', $types);
        $this->assertContains('missing_ssnit_deduction', $types);
        $this->assertContains('missing_petra_deduction', $types);
    }

    public function test_melcom_exact_user_row_overtime_extraction(): void
    {
        $headers = [
            'Staff ID', 'Name', 'Outsourcename', 'Designation', 'Join Date', 'Shop Name',
            'Category', 'Saturday OT', 'Actual Days', 'Night', 'Half Days', 'Sat Off Days',
            'Sun Off Days', 'Leave Deduct', 'Absent Days', 'Absent Hrs', 'Holiday',
            'Weekdays OT', 'Weekend OT', 'Holiday OT', 'Basic', 'Absent Without Pay',
            'Basic Entitlement', 'Transport', 'Normal Overtime', 'Saturday Overtime',
            'Holiday OT Amt', 'Gross', 'SSNIT ER', 'SSNIT EE', 'SSNIT', 'Taxable',
            'PAYE', 'Total Overtime', 'Overtime Tax', 'Total Tax', 'Trust', 'Total Deduction',
            'Bank Transfer'
        ];

        $matcher = new \App\Services\Payroll\ColumnMatcher\RuleBasedColumnMatcher();
        $res = $matcher->match($headers, 'melcom');

        $mapping = $res['mapping'];
        $this->assertEquals(7, $mapping['saturday_rate']);
        $this->assertEquals(17, $mapping['weekday_hours']);
        $this->assertEquals(18, $mapping['saturday_hours']);
        $this->assertEquals(19, $mapping['num_sundays_holidays']);
        $this->assertEquals(24, $mapping['weekday_overtime_amt']);
        $this->assertEquals(25, $mapping['saturday_overtime_amt']);
        $this->assertEquals(26, $mapping['sunday_holiday_overtime_amt']);
        $this->assertEquals(33, $mapping['total_overtime_amt']);
        $this->assertEquals(34, $mapping['overtime_tax']);
        $this->assertEquals(38, $mapping['net_pay']);

        // Now test normalization on the actual row
        $row = [
            'STF00421', 'Akosua Mensah', 'Carlcare', 'Shop Assistant', '15/03/2023', 'Kumasi - Adum',
            'Outsourced', 1, 26, 8, null, null, 1, null, null, null, null,
            6, 4, null, 1800, null, 1800, 300, 450, 400,
            null, 3950, 247.5, 247.5, 247.5, 3702.5,
            428.75, 850, 123.25, 552, 50, 849.75,
            3100.25
        ];

        // Map row to database record object as MelcomPayController does
        $record = (object)[
            'employ'           => $row[$mapping['staff_id']],
            'empoyeename'      => $row[$mapping['name']],
            'satot'            => $row[$mapping['saturday_rate']],
            'actualdays'       => $row[$mapping['days_worked']],
            'weekdaysOT'       => $row[$mapping['weekday_hours']],
            'weekendOT'        => $row[$mapping['saturday_hours']],
            'holiday'          => $row[$mapping['num_sundays_holidays']],
            'basic'            => $row[$mapping['basic_salary']],
            'basicentitlement' => $row[$mapping['basic_entitlement']],
            'allowance'        => $row[$mapping['transport']],
            'normalovertime'   => $row[$mapping['weekday_overtime_amt']],
            'saturdayovertime' => $row[$mapping['saturday_overtime_amt']],
            'holidayotamt'     => $row[$mapping['sunday_holiday_overtime_amt']],
            'totalovertime'    => $row[$mapping['total_overtime_amt']],
            'overtimetax'      => $row[$mapping['overtime_tax']],
            'grosspay'         => $row[$mapping['gross_salary']],
            'paye'             => $row[$mapping['paye']],
            'netpay'           => $row[$mapping['net_pay']],
        ];

        $client = (object)[
            'client_dbname' => 'melcom',
            'slug'          => 'melcom-ghana',
            'client_name'   => 'Melcom Limited',
        ];

        $controller = new \App\Http\Controllers\PayslipController();
        $reflector = new \ReflectionClass($controller);
        $normMethod = $reflector->getMethod('normalizePayslip');
        $normMethod->setAccessible(true);

        $normalized = $normMethod->invoke($controller, $record, $client);

        $this->assertEquals(6.0, $normalized->weekdaysOT);
        $this->assertEquals(4, $normalized->weekendOT);
        $this->assertIsInt($normalized->weekendOT);
        $this->assertEquals(450.0, $normalized->normalovertime);
        $this->assertEquals(400.0, $normalized->saturdayovertime);
        $this->assertEquals(850.0, $normalized->totalovertime);
        $this->assertEquals(123.25, $normalized->overtimetax);
        $this->assertEquals(3100.25, $normalized->net_pay);
    }

    public function test_melcom_october_2026_saturday_ot_hrs_resolution_and_integer_format(): void
    {
        $headers = [
            'Sr. No', 'Sr. No', 'Emp', 'Employee', 'OUTSOURCE NAME', 'Designation', 'Join Date',
            'Location', 'CATEGORY', 'SATURDAY OVERTIME RATE', 'Present Full days', 'Present Night days',
            'Present Half days', 'SAT _ OFF DAYS', 'SUN _ OFF DAYS', 'Leave Taken', 'Absent days',
            'Abesnt Hrs', 'Holidays', 'Normal OT HRS', 'Saturday OT HRS', 'Holiday OT HRS',
            'Basic (Scale)', 'Absent without pay', 'Basic (Entitle)', 'TRANSPORT', 'Normal Overtime',
            'Saturday Overtime', 'Holiday Overtime', "GROSS SALARY\n(AA=V+W+X+Y-Z)",
            'TOTAL CASH EMOLUMENT', 'TOTAL ASSESSABLE INCOME', 'SSF Employer %', 'SSNIT EE 0.5%',
            'SSNIT EE 5%', 'Taxable Income', 'PAYE', 'Total Overtime', 'OVERTIME TAX',
            'Total Taxation', 'TO RECOVER & PAY TO SHOP/ SSNIT LOAN', 'TOTAL DEDUCTIONS', 'TAKE HOME PAY'
        ];

        // 1. AI Rule-based matcher test
        $matcher = new \App\Services\Payroll\ColumnMatcher\RuleBasedColumnMatcher();
        $aiRes = $matcher->match($headers, 'melcom');
        $this->assertEquals(20, $aiRes['mapping']['saturday_hours']);
        $this->assertEquals(19, $aiRes['mapping']['weekday_hours']);
        $this->assertEquals(21, $aiRes['mapping']['num_sundays_holidays']);

        // 2. Legacy fallback matcher test
        $legacy = new \App\Services\Payroll\ColumnMatcher\LegacyColumnMatcher();
        $legacyRes = $legacy->match($headers, 'melcom');
        $this->assertEquals(20, $legacyRes['mapping']['saturday_hours']);

        // 3. Normalizer integer test for Melcom
        $client = (object)[
            'client_dbname' => 'melcom',
            'slug'          => 'melcom-ghana',
            'client_name'   => 'Melcom Limited',
        ];

        $record = (object)[
            'employ'           => '60517595',
            'empoyeename'      => 'GEORGE KOFI EHUN',
            'basic'            => 900.0,
            'actualdays'       => 23,
            'absentdays'       => 0,
            'Saturday OT HRS'  => 48,
            'weekendOT'        => 48,
            'saturdayovertime' => 150.0,
            'grosspay'         => 1050.0,
            'netpay'           => 950.0,
        ];

        $controller = new \App\Http\Controllers\PayslipController();
        $reflector = new \ReflectionClass($controller);
        $normMethod = $reflector->getMethod('normalizePayslip');
        $normMethod->setAccessible(true);

        $normalized = $normMethod->invoke($controller, $record, $client);

        $this->assertIsInt($normalized->weekendOT);
        $this->assertEquals(48, $normalized->weekendOT);
        $this->assertEquals(23, $normalized->expected_work_days);
        $this->assertEquals(23, $normalized->legal_work_days);
    }

    public function test_twellium_kumasi_payslip_includes_total_cash_emolument_and_total_assessable_income(): void
    {
        $client = (object)[
            'client_dbname' => 'twellium',
            'slug'          => 'twellium-industrial-company-ghana',
            'short_name'    => 'Twellium Kumasi',
        ];

        $rawRecord = (object)[
            'employ'                  => 'EMP00123',
            'name'                    => 'Ama Serwaa',
            'basic'                   => 4800.00,
            'basic_earned'            => 4800.00,
            'food_allowance'          => 400.00,
            'night_allowance'         => 600.00,
            'refund'                  => 150.00,
            'allowance'               => 1150.00,
            'total_cash_emolument'    => 7100.00,
            'deduction'               => 200.00,
            'total_assessable_income' => 6900.00,
            'netpay'                  => 5897.62,
        ];

        $controller = new \App\Http\Controllers\PayslipController();
        $refMethod = new \ReflectionMethod($controller, 'normalizePayslip');
        $normalized = $refMethod->invoke($controller, $rawRecord, $client, '-');

        $this->assertEquals(7100.00, $normalized->total_cash_emolument);
        $this->assertEquals(6900.00, $normalized->total_assessable_income);
        $this->assertEquals(400.00, $normalized->food_allowance);
        $this->assertEquals(600.00, $normalized->night_allowance);
        $this->assertEquals(150.00, $normalized->refund);

        // Verify view rendering
        $view = view('payslip.twellium-pdf', [
            'payslip'        => $normalized,
            'formattedMonth' => 'October 2026',
            'ssnit'          => 'C123456789012',
            'bank'           => [
                'bankname' => 'GCB Bank',
                'name'     => 'Ama Serwaa',
                'alcnum'   => '123456789',
                'branch'   => 'Kumasi Main',
            ],
        ])->render();

        $this->assertStringContainsString('Total Cash Emolument', $view);
        $this->assertStringContainsString('7,100.00', $view);
        $this->assertStringContainsString('Total Assessable Income', $view);
        $this->assertStringContainsString('6,900.00', $view);
    }

    public function test_inhouse_payroll_matcher_resolves_commission_earned_and_commission_tax(): void
    {
        $headers = [
            'S/N',
            'EM ID',
            'NAME',
            'POSITION',
            'DATE JOINED',
            'SSNIT NUMBER',
            'GH-CARD NO',
            'BANK NAME',
            'A/C NUMBER',
            'BRANCH',
            'Legal Working Days',
            'Actual working days for work (Attendance)',
            'Basic Salary ',
            'Actual Basic Salary',
            'Basic KPI',
            'Coefficient ',
            'Monthly KPI Bonus',
            'Overtime Allowance',
            'Transport Subsidy ',
            'LEAVE',
            'Gross Salary',
            'SSNIT ER',
            'SSNIT EE',
            'PETRA',
            'TAXABLE INCOME',
            'PAYE',
            'OVERTIME TAX',
            'TOTAL TAXATION',
            'TOTAL DEDUCTIONS',
            'NET SALARY',
            'COMMISSION/ BONUS EARNED',
            'COMMISSION/ BONUS TAX',
            'COMMISSION/ BONUS FINAL',
            'NO OF MONTHS WORKED',
            '13TH MONTH',
            '13TH TAX',
            '13TH MONTH PAYABLE',
            'LOANS',
            'ACTUAL NET',
        ];

        // 1. RuleBased Matcher
        $ruleMatcher = new RuleBasedColumnMatcher();
        $ruleResult = $ruleMatcher->match($headers, 'inhouse');

        $this->assertEquals(1, $ruleResult['mapping']['staff_id']);
        $this->assertEquals(2, $ruleResult['mapping']['name']);
        $this->assertEquals(3, $ruleResult['mapping']['position']);
        $this->assertEquals(4, $ruleResult['mapping']['join_date']);
        $this->assertEquals(30, $ruleResult['mapping']['commission_earned']);
        $this->assertEquals(31, $ruleResult['mapping']['commission_tax']);
        $this->assertEquals(32, $ruleResult['mapping']['commission_final']);
        $this->assertEquals(38, $ruleResult['mapping']['net_pay']);

        // 2. Legacy Matcher
        $legacyMatcher = new LegacyColumnMatcher();
        $legacyResult = $legacyMatcher->match($headers, 'inhouse');

        $this->assertEquals(1, $legacyResult['mapping']['staff_id']);
        $this->assertEquals(30, $legacyResult['mapping']['commission_earned']);
        $this->assertEquals(31, $legacyResult['mapping']['commission_tax']);
        $this->assertEquals(32, $legacyResult['mapping']['commission_final']);
        $this->assertEquals(38, $legacyResult['mapping']['net_pay']);
    }

    public function test_inhouse_payslip_includes_commission_earned_and_commission_tax(): void
    {
        $client = (object)[
            'client_dbname' => 'inhouse',
            'slug'          => 'inhouse',
            'short_name'    => 'Inhouse',
        ];

        $rawRecord = (object)[
            'id'                => 'INH001',
            'name'              => 'Kofi Annan',
            'position'          => 'Sales Executive',
            'actualwork'        => 22,
            'actualbasic'       => 3500.00,
            'monthlykpi'        => 450.00,
            'overtime'          => 200.00,
            'transport'         => 300.00,
            'ssnitpay'          => 17.50,
            'petra'             => 175.00,
            'totaltax'          => 420.00,
            'commission_earned' => 1250.00,
            'commission_tax'    => 125.00,
            'commission_final'  => 1125.00,
            'loans'             => 100.00,
            'netpay'            => 4662.50,
        ];

        $controller = new \App\Http\Controllers\PayslipController();
        $refMethod = new \ReflectionMethod($controller, 'normalizePayslip');
        $normalized = $refMethod->invoke($controller, $rawRecord, $client, 'D987654321012');

        $this->assertEquals(1250.00, $normalized->commission_earned);
        $this->assertEquals(1250.00, $normalized->commission);
        $this->assertEquals(125.00, $normalized->commission_tax);
        $this->assertEquals(125.00, $normalized->commissiontax);

        $view = view('payslip.inhouse-pdf', [
            'payslip'        => $normalized,
            'formattedMonth' => 'October 2026',
            'ssnit'          => 'D987654321012',
            'bank'           => [
                'bankname' => 'Fidelity Bank',
                'name'     => 'Kofi Annan',
                'alcnum'   => '10987654321',
                'branch'   => 'Accra Ridge',
            ],
        ])->render();

        $this->assertStringContainsString('Commission Earned', $view);
        $this->assertStringContainsString('1,250.00', $view);
        $this->assertStringContainsString('Commission Tax', $view);
        $this->assertStringContainsString('125.00', $view);
    }

    public function test_twellium_kumasi_payslip_query_does_not_join_unrelated_overtime_table(): void
    {
        $controller = new \App\Http\Controllers\PayslipController();
        $refMethod = new \ReflectionMethod($controller, 'getPayslipQuery');
        $query = $refMethod->invoke($controller, 'twellium', 'employ');
        $sql = $query->toSql();

        $this->assertStringNotContainsString('twelliumovertime', $sql);
        $this->assertStringContainsString('twellium', $sql);
        $this->assertStringContainsString('as "b"', str_replace('`', '"', $sql));
    }

    public function test_rule_based_matcher_resolves_twellium_kumasi_columns(): void
    {
        $headers = [
            'EMPLOY',
            'NAME',
            'TITLE',
            'DEPARTMENT',
            'BANKNAME',
            'ACCNO',
            'TOTALDAYS',
            'TOTALWORK',
            'DAILYWAGE',
            'BASIC',
            'NORMALOVERTIME',
            'OVERTIME',
            'NORMALOVERTIME2',
            'OVERTIME2',
            'TOTAL',
            'SSNIT',
            'TOTALTAX',
            'TOTALDEDUCTION',
            'NETPAY',
        ];

        $matcher = new RuleBasedColumnMatcher();
        $result = $matcher->match($headers, 'twellium');

        $this->assertEquals(0, $result['mapping']['staff_id']);
        $this->assertEquals(1, $result['mapping']['name']);
        $this->assertEquals(2, $result['mapping']['position']);
        $this->assertEquals(6, $result['mapping']['legalworkdays']);
        $this->assertEquals(7, $result['mapping']['days_worked']);
        $this->assertEquals(8, $result['mapping']['daily_rate']);
        $this->assertEquals(9, $result['mapping']['basic_salary']);
        $this->assertEquals(12, $result['mapping']['saturday_hours']);
        $this->assertEquals(13, $result['mapping']['saturday_overtime_amt']);
        $this->assertEquals(14, $result['mapping']['gross_salary']);
        $this->assertEquals(15, $result['mapping']['ssnit_ee']);
        $this->assertEquals(16, $result['mapping']['paye']);
        $this->assertEquals(17, $result['mapping']['total_deductions']);
        $this->assertEquals(18, $result['mapping']['net_pay']);
    }

    public function test_twellium_kumasi_payslip_normalizes_expected_days_and_overtime_descriptions(): void
    {
        $client = (object)[
            'client_dbname' => 'twellium',
            'slug'          => 'twellium-industrial-company-ghana',
            'short_name'    => 'Twellium Kumasi',
        ];

        $rawRecord = (object)[
            'employ'          => 'SBPTWLM07',
            'name'            => 'ADDO KWAYISSI EMMANUEL',
            'title'           => 'ELECTRICAL ENGINEER',
            'department'      => 'KAASE',
            'bankname'        => 'ECOBANK',
            'accno'           => '1441000123456',
            'totaldays'       => 22,
            'totalwork'       => 22,
            'dailywage'       => 74.8,
            'basic'           => 1645.53,
            'normalovertime'  => 1,
            'overtime'        => 14.02,
            'normalovertime2' => 8,
            'overtime2'       => 149.59,
            'total'           => 1809.15,
            'ssnit'           => 90.50,
            'totaltax'        => 213.39,
            'totaldeduction'  => 303.89,
            'netpay'          => 1505.26,
        ];

        $controller = new \App\Http\Controllers\PayslipController();
        $refMethod = new \ReflectionMethod($controller, 'normalizePayslip');
        $normalized = $refMethod->invoke($controller, $rawRecord, $client, 'F019002080210');

        $this->assertEquals(22, $normalized->legal_work_days);
        $this->assertEquals(22, $normalized->expected_work_days);
        $this->assertEquals(22, $normalized->days_worked);
        $this->assertEquals(22, $normalized->total_hours_worked);
        $this->assertEquals(74.8, $normalized->daily_rate);
        $this->assertEquals(1645.53, $normalized->basic);
        $this->assertEquals(1.0, $normalized->weekday_overtime_hours);
        $this->assertEquals(14.02, $normalized->weekday_overtime_amount);
        $this->assertEquals(8.0, $normalized->offday_holiday_overtime_hours);
        $this->assertEquals(149.59, $normalized->offday_holiday_overtime_total);
        $this->assertEquals(163.61, $normalized->total_overtime_amount);
        $this->assertEquals(1809.15, $normalized->gross_salary);
        $this->assertEquals(90.50, $normalized->ssnit_ee);
        $this->assertEquals(213.39, $normalized->paye);
        $this->assertEquals(303.89, $normalized->total_deductions);
        $this->assertEquals(1505.26, $normalized->netpay);

        $view = view('payslip.twellium-pdf', [
            'payslip'        => $normalized,
            'formattedMonth' => 'November 2026',
            'ssnit'          => 'F019002080210',
            'bank'           => [
                'bankname' => 'ECOBANK',
                'name'     => 'ADDO KWAYISSI EMMANUEL',
                'alcnum'   => '1441000123456',
                'branch'   => 'Kaase',
            ],
        ])->render();

        // 1. Expected work days must show 22
        $this->assertStringContainsString('Expected Work Days', $view);
        $this->assertStringContainsString('22', $view);

        // 2. Overtime hours and amounts rendered properly
        $this->assertStringContainsString('Weekday Overtime', $view);
        $this->assertStringContainsString('14.02', $view);
        $this->assertStringContainsString('Offday/Holiday Overtime', $view);
        $this->assertStringContainsString('149.59', $view);

        // 3. Gross salary rendered as 1,809.15
        $this->assertStringContainsString('1,809.15', $view);
        $this->assertStringContainsString('1,505.26', $view);
    }

    public function test_shefer_payslip_normalizes_expected_work_days_descriptions_and_emoluments(): void
    {
        $client = (object)[
            'client_dbname' => 'shefer',
            'slug'          => 'shefer-plastics-ltd',
            'short_name'    => 'Shefer Plastics',
        ];

        // Raw record structure as found in shefer table for EDWARD KOFFIE AMENU
        $rawRecord = (object)[
            'STAFF_ID'                        => '75490729-2',
            'NAME'                            => 'EDWARD KOFFIE AMENU',
            'POSITION'                        => 'HELPER',
            'BASIC_SALARY'                    => '600.00',
            'NUMBER_OF_DAYS_WORKED'           => 4,
            'NO_OF_ABSENT_DAYS'               => 15,
            'ABSENT_DAYS_DEDUCTION'           => '473.68',
            'BASIC_SALARY_ENTITLED'           => '126.32',
            'NORMAL_WEEKDAY_OVERTIME_HOURS'   => '0.00',
            'NORMAL_WEEKDAY_OVERTIME_RATE'    => '1.50',
            'WEEKDAY_OVERTIME_TOTAL'          => '0.00',
            'SATURDAY_OVERTIME_HOURS'         => '1.00',
            'SATURDAY_OVERTIME_RATE'          => '1.50',
            'SATURDAY_OVERTIME_TOTAL'         => '5.63',
            'SUNDAYS_HOLIDAYS_OVERTIME_HOURS' => '0.00',
            'SUNDAYS_HOLIDAYS_OVERTIME_RATE'  => '2.00',
            'SUN_HOL_OVERTIME_TOTAL'          => '0.00',
            'TOTAL_OVERTIME_AMOUNT'           => '5.63',
            'GROSS_SALARY'                    => '131.94',
            'SSNIT_EE'                        => '26.95',
            'TAXABLE_INCOME'                  => '99.37',
            'PAYE'                            => '0.00',
            'OVERTIME_TAX'                    => '0.28',
            'TOTAL_TAXATION'                  => '0.28',
            'TOTAL_DEDUCTIONS'                => '27.23',
            'TAKE_HOME_PAY'                   => '104.71',
            'BANK'                            => 'FNB',
            'ACCOUNT_NUMBER'                  => '62002101111',
            'BRANCH'                          => 'ACCRA MALL',
            'SSNIT_NUMBER'                    => 'A160302090014',
        ];

        $controller = new \App\Http\Controllers\PayslipController();
        $refMethod = new \ReflectionMethod($controller, 'normalizePayslip');
        $normalized = $refMethod->invoke($controller, $rawRecord, $client, 'A160302090014');

        // 1. Legal / Expected work days: 4 + 15 = 19
        $this->assertEquals(19, $normalized->legal_work_days);
        $this->assertEquals(19, $normalized->expected_work_days);

        // 2. Days worked and absent days descriptions
        $this->assertEquals(4, $normalized->days_worked);
        $this->assertEquals(4, $normalized->no_of_days_worked);
        $this->assertEquals(15, $normalized->absent_days);
        $this->assertEquals(473.68, $normalized->absent_without_pay);
        $this->assertEquals(473.68, $normalized->absent_deduction);

        // 3. Basic entitled earned
        $this->assertEquals(126.32, $normalized->basic_earned);

        // 4. Overtime mapping
        $this->assertEquals(1.00, $normalized->weekendOT_hours);
        $this->assertEquals(5.63, $normalized->weekendOT_amount);
        $this->assertEquals(5.63, $normalized->total_overtime);

        // 5. Total cash emolument & Total assessable income
        $this->assertEquals(126.31, $normalized->total_cash_emolument);
        $this->assertEquals(99.37, $normalized->total_assessable_income);

        // 6. Tax separation: PAYE is 0.00, Overtime tax is 0.28
        $this->assertEquals(0.00, $normalized->paye);
        $this->assertEquals(0.28, $normalized->ot_tax);
        $this->assertEquals(104.71, $normalized->netpay);

        // Render blade view
        $view = view('payslip.shefer-pdf', [
            'payslip'        => $normalized,
            'formattedMonth' => 'November 2026',
            'ssnit'          => 'A160302090014',
            'bank'           => [
                'bankname' => 'FNB',
                'name'     => 'EDWARD KOFFIE AMENU',
                'alcnum'   => '62002101111',
                'branch'   => 'ACCRA MALL',
            ],
        ])->render();

        $this->assertStringContainsString('Expected Work Days', $view);
        $this->assertStringContainsString('19', $view);
        $this->assertStringContainsString('126.32', $view);
        $this->assertStringContainsString('473.68', $view);
        $this->assertStringContainsString('5.63', $view);
        $this->assertStringContainsString('126.31', $view);
        $this->assertStringContainsString('99.37', $view);
        $this->assertStringContainsString('(Gross - OT)', $view);
    }

    public function test_rule_based_matcher_resolves_shefer_columns(): void
    {
        $matcher = new \App\Services\Payroll\ColumnMatcher\RuleBasedColumnMatcher();

        $headers = [
            'Staff ID',
            'Name',
            'Position',
            'Total Weekdays',
            'Number of Days Worked',
            'No of Absent Days',
            'Absent Days Deduction',
            'Basic Salary Entitled',
            'Normal Weekday Overtime Hours',
            'Saturday Overtime Hours',
            'Saturday Overtime Total',
            'Gross Salary',
            'Total Cash Emolument',
            'Total Assessable Income',
            'SSNIT EE',
            'PAYE',
            'Overtime Tax',
            'Take Home Pay',
        ];

        $result = $matcher->match($headers, 'shefer');
        $mapping = $result['mapping'];

        $this->assertArrayHasKey('staff_id', $mapping);
        $this->assertEquals(0, $mapping['staff_id']);

        $this->assertArrayHasKey('legalworkdays', $mapping);
        $this->assertEquals(3, $mapping['legalworkdays']);

        $this->assertArrayHasKey('days_worked', $mapping);
        $this->assertEquals(4, $mapping['days_worked']);

        $this->assertArrayHasKey('absentee_days', $mapping);
        $this->assertEquals(5, $mapping['absentee_days']);

        $this->assertArrayHasKey('absent_deductions', $mapping);
        $this->assertEquals(6, $mapping['absent_deductions']);

        $this->assertArrayHasKey('earned_basic_salary', $mapping);
        $this->assertEquals(7, $mapping['earned_basic_salary']);

        $this->assertArrayHasKey('weekday_hours', $mapping);
        $this->assertEquals(8, $mapping['weekday_hours']);

        $this->assertArrayHasKey('saturday_hours', $mapping);
        $this->assertEquals(9, $mapping['saturday_hours']);

        $this->assertArrayHasKey('saturday_overtime_amt', $mapping);
        $this->assertEquals(10, $mapping['saturday_overtime_amt']);

        $this->assertArrayHasKey('gross_salary', $mapping);
        $this->assertEquals(11, $mapping['gross_salary']);

        $this->assertArrayHasKey('total_cash_emolument', $mapping);
        $this->assertEquals(12, $mapping['total_cash_emolument']);

        $this->assertArrayHasKey('total_assessable_income', $mapping);
        $this->assertEquals(13, $mapping['total_assessable_income']);
    }
}
