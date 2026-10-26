<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AI\Copilot\EmployeeCopilotService;
use App\Services\AI\DocumentIntelligence\DocumentOcrService;
use App\Services\AI\Analytics\ExecutiveAnalyticsService;
use App\Services\AI\GrokAiClient;

class AdditionalAiFeaturesTest extends TestCase
{
    public function test_document_ocr_service_normalizes_ghana_card_and_bank_details(): void
    {
        $ocr = app(DocumentOcrService::class);

        // Reflection to test normalization logic directly
        $method = new \ReflectionMethod(DocumentOcrService::class, 'normalizeExtractedFields');
        $method->setAccessible(true);

        $rawFields = [
            'ghcard' => '712345678-1', // Missing GHA-
            'dateofbirth' => '18th May 1994',
            'account' => 'GH144-100-1234567', // Contains dashes and letters
        ];

        $normalized = $method->invoke($ocr, $rawFields, 'ghana_card');

        $this->assertEquals('GHA-712345678-1', $normalized['ghcard']);
        $this->assertEquals('1994-05-18', $normalized['dateofbirth']);
        $this->assertEquals('1441001234567', $normalized['account']);
    }

    public function test_executive_analytics_returns_structured_metrics_and_chart(): void
    {
        $analytics = app(ExecutiveAnalyticsService::class);

        $result = $analytics->ask("What was our total Petra Tier 2 liability last quarter?");

        $this->assertNotEmpty($result['headline']);
        $this->assertNotEmpty($result['metrics']);
        $this->assertArrayHasKey('chart', $result);
        $this->assertArrayHasKey('labels', $result['chart']);
        $this->assertArrayHasKey('table', $result);
        $this->assertNotEmpty($result['follow_up_questions']);
    }

    public function test_employee_copilot_service_generates_payslip_action_and_deduction_breakdown(): void
    {
        $copilot = app(EmployeeCopilotService::class);

        // Test message asking for payslip
        $response = $copilot->handleMessage('JK999', 'Please give me my latest payslip');

        $this->assertNotEmpty($response['reply']);
        $this->assertNotNull($response['action']);
        $this->assertEquals('download_payslip', $response['action']['type']);
        $this->assertStringContainsString('payslips', $response['action']['url']);

        // Test message asking for deduction breakdown
        $deductionResponse = $copilot->handleMessage('JK999', 'Why was money deducted from my pay?');
        $this->assertNotEmpty($deductionResponse['reply']);
        $this->assertStringContainsString('SSNIT', $deductionResponse['reply']);
        $this->assertStringContainsString('Petra', $deductionResponse['reply']);
    }

    public function test_copilot_generates_client_payslip_links_and_payroll_actions(): void
    {
        $copilot = app(EmployeeCopilotService::class);

        // Test Bunge Benin payslip generation for October (handling typos like octorber)
        $res = $copilot->handleMessage('GENERAL', 'Generate a payslip for bunge benin for octorber');

        $this->assertStringContainsString('Bunge Benin', $res['reply']);
        $this->assertNotEmpty($res['actions']);
        $this->assertStringContainsString('/payslips/beninbunge', $res['actions'][0]['url']);
        $this->assertStringContainsString('10', $res['actions'][0]['url']);
        $this->assertEquals('/benin-bunge?month=2026-10', $res['actions'][1]['url']);

        // Test Melcom payslip generation
        $melcomRes = $copilot->handleMessage('GENERAL', 'Generate payslips for Melcom for September');
        $this->assertStringContainsString('Melcom Distribution', $melcomRes['reply']);
        $this->assertStringContainsString('/payslips/melcom', $melcomRes['actions'][0]['url']);
    }

    public function test_copilot_explains_statutory_formulas_and_system_audits(): void
    {
        $copilot = app(EmployeeCopilotService::class);

        // Test statutory pension formulas
        $statutory = $copilot->handleMessage('GENERAL', 'How is SSNIT 5.5% and Petra Tier 2 5% calculated?');
        $this->assertStringContainsString('SSNIT Tier 1', $statutory['reply']);
        $this->assertStringContainsString('Petra Tier 2', $statutory['reply']);
        $this->assertStringContainsString('5.5%', $statutory['reply']);
        $this->assertStringContainsString('5.0%', $statutory['reply']);

        // Test absenteeism formula
        $absent = $copilot->handleMessage('GENERAL', 'How are absenteeism deductions calculated in Ghanaian payroll?');
        $this->assertStringContainsString('Absenteeism', $absent['reply']);
        $this->assertStringContainsString('Basic Salary', $absent['reply']);
        $this->assertStringContainsString('Act 651', $absent['reply']);

        // Test HR audits navigation
        $audit = $copilot->handleMessage('GENERAL', 'Open SSNIT Audit and Bank Audit');
        $this->assertNotEmpty($audit['actions']);
        $urls = array_column($audit['actions'], 'url');
        $this->assertContains('/hr-audit/bank', $urls);
        $this->assertContains('/hr-audit/ssnit', $urls);
    }
}
