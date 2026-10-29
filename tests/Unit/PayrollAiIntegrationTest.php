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
        $this->assertEquals(22, $result['mapping']['basic_salary']);
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
        $this->assertEquals(4.0, $normalized->weekendOT);
        $this->assertEquals(450.0, $normalized->normalovertime);
        $this->assertEquals(400.0, $normalized->saturdayovertime);
        $this->assertEquals(850.0, $normalized->totalovertime);
        $this->assertEquals(123.25, $normalized->overtimetax);
        $this->assertEquals(3100.25, $normalized->net_pay);
    }
}
