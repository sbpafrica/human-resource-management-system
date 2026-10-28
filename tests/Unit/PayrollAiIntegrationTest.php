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
}
