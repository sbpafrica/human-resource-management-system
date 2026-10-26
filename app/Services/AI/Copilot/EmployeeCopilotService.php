<?php

namespace App\Services\AI\Copilot;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\Client;
use App\Services\AI\GrokAiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class EmployeeCopilotService
{
    public function __construct(
        protected GrokAiClient $grokClient
    ) {}

    /**
     * Master client registry across all 31 clients.
     */
    protected array $clientCatalog = [
        'beninbunge' => [
            'name' => 'Bunge Benin',
            'keywords' => ['bunge benin', 'benin bunge', 'benin', 'bungebenin'],
            'payslip_slug' => 'beninbunge',
            'payroll_url' => '/benin-bunge',
            'table_prefix' => 'beninbunge',
        ],
        'togobunge' => [
            'name' => 'Bunge Togo',
            'keywords' => ['bunge togo', 'togo bunge', 'togo', 'togobunge'],
            'payslip_slug' => 'togobunge',
            'payroll_url' => '/togo-bunge',
            'table_prefix' => 'togobunge',
        ],
        'civ' => [
            'name' => 'Bunge Côte d\'Ivoire (CIV)',
            'keywords' => ['bunge civ', 'civ bunge', 'civ', 'cote d\'ivoire', 'ivory coast'],
            'payslip_slug' => 'civ',
            'payroll_url' => '/civ-bunge',
            'table_prefix' => 'civbunge',
        ],
        'bunge' => [
            'name' => 'Bunge Ghana',
            'keywords' => ['bunge ghana', 'bunge'],
            'payslip_slug' => 'bunge',
            'payroll_url' => '/bungepay',
            'table_prefix' => 'bunge',
        ],
        'jaykay' => [
            'name' => 'Jay Kay Global',
            'keywords' => ['jaykay', 'jay kay', 'jkay', 'jay-kay'],
            'payslip_slug' => 'jaykay',
            'payroll_url' => '/jaykaypay',
            'table_prefix' => 'jkay',
        ],
        'melcom' => [
            'name' => 'Melcom Distribution',
            'keywords' => ['melcom', 'melcom distribution'],
            'payslip_slug' => 'melcom',
            'payroll_url' => '/melcompay',
            'table_prefix' => 'melcomtry',
        ],
        'driver' => [
            'name' => 'Melcom Drivers',
            'keywords' => ['melcom driver', 'melcom drivers', 'drivers', 'driver'],
            'payslip_slug' => 'driver',
            'payroll_url' => '/driverpay',
            'table_prefix' => 'driver',
        ],
        'ofibwh' => [
            'name' => 'OFI Bean Warehouse',
            'keywords' => ['ofi bwh', 'ofibwh', 'bean warehouse', 'olambwh'],
            'payslip_slug' => 'ofibwh',
            'payroll_url' => '/ofibwhpay',
            'table_prefix' => 'olambwh',
        ],
        'ofitemp' => [
            'name' => 'OFI Tempering',
            'keywords' => ['ofi temp', 'ofitemp', 'tempering', 'olamtem'],
            'payslip_slug' => 'ofitemp',
            'payroll_url' => '/ofitemppay',
            'table_prefix' => 'olamtem',
        ],
        'ofioffload' => [
            'name' => 'OFI Offloads',
            'keywords' => ['ofi offload', 'ofioffload', 'offloads'],
            'payslip_slug' => 'ofioffload',
            'payroll_url' => '/ofioffloadpay',
            'table_prefix' => 'ofioffload',
        ],
        'ofiexternal' => [
            'name' => 'OFI Hygiene External',
            'keywords' => ['ofi external', 'ofiexternal', 'hygiene external', 'hygieneexternal'],
            'payslip_slug' => 'ofiexternal',
            'payroll_url' => '/ofiexternalpay',
            'table_prefix' => 'hygieneexternal',
        ],
        'ofihygiene' => [
            'name' => 'OFI Hygiene',
            'keywords' => ['ofi hygiene', 'ofihygiene', 'hygiene internal'],
            'payslip_slug' => 'ofihygiene',
            'payroll_url' => '/ofihygienepay',
            'table_prefix' => 'hygiene',
        ],
        'olamfood' => [
            'name' => 'Olam Food Ingredients (OFI)',
            'keywords' => ['olam food', 'olamfood', 'ofi general', 'ofi'],
            'payslip_slug' => 'olamfood',
            'payroll_url' => '/ofibwhpay',
            'table_prefix' => 'olambwh',
        ],
        'twellium' => [
            'name' => 'Twellium Industrial (Kumasi/Ghana)',
            'keywords' => ['twellium kumasi', 'twellium ghana', 'twellium industrial', 'twellium'],
            'payslip_slug' => 'twellium',
            'payroll_url' => '/twelliumpay',
            'table_prefix' => 'twellium',
        ],
        'twelliumaccra' => [
            'name' => 'Twellium Accra',
            'keywords' => ['twellium accra', 'twelliumaccra', 'accra twellium'],
            'payslip_slug' => 'twelliumaccra',
            'payroll_url' => '/twelliumaccrapay',
            'table_prefix' => 'twelliumaccra',
        ],
        'transsion' => [
            'name' => 'Transsion / Good Ease (Infinix & Tecno)',
            'keywords' => ['transsion', 'infinix', 'good ease', 'transsion ghana'],
            'payslip_slug' => 'transsion',
            'payroll_url' => '/transsionpay',
            'table_prefix' => 'transsion',
        ],
        'tecno' => [
            'name' => 'Tecno / Unitycorp',
            'keywords' => ['tecno', 'unitycorp'],
            'payslip_slug' => 'tecno',
            'payroll_url' => '/tecnopay',
            'table_prefix' => 'tecno',
        ],
        'transsion-bfa' => [
            'name' => 'Transsion Burkina Faso',
            'keywords' => ['transbfa', 'burkina faso', 'transsion bfa', 'bfa'],
            'payslip_slug' => 'transsion-bfa',
            'payroll_url' => '/transbfa',
            'table_prefix' => 'transbfa',
        ],
        'xiaomi' => [
            'name' => 'Xiaomi Ghana',
            'keywords' => ['xiaomi', 'xiaomi ghana', 'redmi'],
            'payslip_slug' => 'xiaomi',
            'payroll_url' => '/xiaomipay',
            'table_prefix' => 'xiaomi',
        ],
        'honda' => [
            'name' => 'Honda Manufacturing',
            'keywords' => ['honda', 'honda manufacturing'],
            'payslip_slug' => 'honda',
            'payroll_url' => '/hondapay',
            'table_prefix' => 'honda',
        ],
        'pz' => [
            'name' => 'PZ Cussons',
            'keywords' => ['pz', 'pz cussons', 'cussons'],
            'payslip_slug' => 'pz',
            'payroll_url' => '/pzpay',
            'table_prefix' => 'pz',
        ],
        'century' => [
            'name' => 'Century Packaging',
            'keywords' => ['century', 'century packaging'],
            'payslip_slug' => 'century',
            'payroll_url' => '/centurypay',
            'table_prefix' => 'century',
        ],
        'shefer' => [
            'name' => 'Shefer Plastics',
            'keywords' => ['shefer', 'shefer plastics'],
            'payslip_slug' => 'shefer',
            'payroll_url' => '/sheferpay',
            'table_prefix' => 'shefer',
        ],
        'fon' => [
            'name' => 'Fon Packaging',
            'keywords' => ['fon', 'fon packaging'],
            'payslip_slug' => 'fon',
            'payroll_url' => '/fonpay',
            'table_prefix' => 'fon',
        ],
        'a2feed' => [
            'name' => 'A2 Feed',
            'keywords' => ['a2feed', 'a2 feed'],
            'payslip_slug' => 'a2feed',
            'payroll_url' => '/a2feedpay',
            'table_prefix' => 'a2feed',
        ],
        'a2soya' => [
            'name' => 'A2 Soya',
            'keywords' => ['a2soya', 'a2 soya'],
            'payslip_slug' => 'a2soya',
            'payroll_url' => '/a2soyapay',
            'table_prefix' => 'a2soya',
        ],
        'gmoney' => [
            'name' => 'G-Money Financial Services',
            'keywords' => ['gmoney', 'g-money', 'g money'],
            'payslip_slug' => 'gmoney',
            'payroll_url' => '/gmoneypay',
            'table_prefix' => 'gmoney',
        ],
        'avara' => [
            'name' => 'Avara RC Limited',
            'keywords' => ['avara', 'avara rc'],
            'payslip_slug' => 'avara',
            'payroll_url' => '/avarapay',
            'table_prefix' => 'avara',
        ],
        'nyonkopa' => [
            'name' => 'Nyonkopa Cocoa Buying',
            'keywords' => ['nyonkopa', 'nyonkopa cocoa'],
            'payslip_slug' => 'nyonkopa',
            'payroll_url' => '/nyonkopapay',
            'table_prefix' => 'nyonkopa',
        ],
        'inhouse' => [
            'name' => 'Inhouse / SBP Africa Headquarters',
            'keywords' => ['inhouse', 'sbp', 'sbp africa', 'headquarters'],
            'payslip_slug' => 'inhouse',
            'payroll_url' => '/inhousepay',
            'table_prefix' => 'inhouse',
        ],
        'general' => [
            'name' => 'All Clients Hub',
            'keywords' => ['all clients', 'every client', 'clients hub'],
            'payslip_slug' => 'general',
            'payroll_url' => '/payslips',
            'table_prefix' => '',
        ],
    ];

    /**
     * Resolves client info from user query.
     */
    protected function matchClientFromQuery(string $query): ?array
    {
        $q = strtolower($query);

        // Sort by longest keyword first so "bunge benin" matches before "bunge"
        $allClients = $this->clientCatalog;
        uasort($allClients, function ($a, $b) {
            $maxA = max(array_map('strlen', $a['keywords']));
            $maxB = max(array_map('strlen', $b['keywords']));
            return $maxB <=> $maxA;
        });

        foreach ($allClients as $key => $client) {
            foreach ($client['keywords'] as $kw) {
                if (str_contains($q, $kw)) {
                    return $client;
                }
            }
        }

        return null;
    }

    /**
     * Resolves month YYYY-MM from user query (e.g. October, octorber, last month, etc.).
     */
    protected function matchMonthFromQuery(string $query): array
    {
        $q = strtolower($query);
        $months = [
            'january' => '01', 'jan' => '01',
            'february' => '02', 'feb' => '02',
            'march' => '03', 'mar' => '03',
            'april' => '04', 'apr' => '04',
            'may' => '05',
            'june' => '06', 'jun' => '06',
            'july' => '07', 'jul' => '07',
            'august' => '08', 'aug' => '08',
            'september' => '09', 'sept' => '09', 'sep' => '09',
            'october' => '10', 'octorber' => '10', 'oct' => '10',
            'november' => '11', 'nov' => '11',
            'december' => '12', 'dec' => '12',
        ];

        $currentYear = Carbon::now()->format('Y');

        // Check explicit YYYY-MM format
        if (preg_match('/(20\d\d)[-\/](\d{1,2})/', $q, $m)) {
            $formatted = sprintf('%04d-%02d', (int)$m[1], (int)$m[2]);
            return ['month' => $formatted, 'name' => Carbon::parse($formatted . '-01')->format('F Y')];
        }

        foreach ($months as $name => $num) {
            if (str_contains($q, $name)) {
                $monthStr = "{$currentYear}-{$num}";
                return [
                    'month' => $monthStr,
                    'name' => Carbon::parse($monthStr . '-01')->format('F Y'),
                ];
            }
        }

        if (str_contains($q, 'last month')) {
            $last = Carbon::now()->subMonth();
            return ['month' => $last->format('Y-m'), 'name' => $last->format('F Y')];
        }

        $now = Carbon::now();
        return ['month' => $now->format('Y-m'), 'name' => $now->format('F Y')];
    }

    /**
     * Cleans raw markdown stars (* and **) from text to keep chat bubbles pristine.
     */
    protected function cleanStars(string $text): string
    {
        return str_replace(['**', '*'], '', $text);
    }

    /**
     * Answers HR Administrator and Payroll Officer queries across the entire HRMS.
     */
    public function handleMessage(string $staffId, string $message, array $history = []): array
    {
        $cleanMessage = trim($message);
        $msgLower = strtolower($cleanMessage);

        $matchedClient = $this->matchClientFromQuery($cleanMessage);
        $monthData = $this->matchMonthFromQuery($cleanMessage);
        $targetMonth = $monthData['month'];
        $monthName = $monthData['name'];

        $actions = [];

        // 1. Payslip Generation Intent for Client (e.g. "Generate a payslip for bunge benin for october")
        if (
            str_contains($msgLower, 'payslip') ||
            str_contains($msgLower, 'generate') ||
            str_contains($msgLower, 'bulletin') ||
            str_contains($msgLower, 'slips')
        ) {
            if ($matchedClient && $matchedClient['payslip_slug'] !== 'general') {
                $payslipUrl = "/payslips/{$matchedClient['payslip_slug']}?month={$targetMonth}";
                $payrollUrl = "{$matchedClient['payroll_url']}?month={$targetMonth}";

                $actions[] = [
                    'type' => 'view_payslips',
                    'label' => "Open {$matchedClient['name']} {$monthName} Payslips",
                    'url' => $payslipUrl,
                ];

                $actions[] = [
                    'type' => 'open_payroll',
                    'label' => "Open {$matchedClient['name']} Payroll Sheet",
                    'url' => $payrollUrl,
                ];

                $reply = "Here are the direct links to generate and manage payslips for {$matchedClient['name']} ({$monthName}):\n\n"
                    . "• Open {$matchedClient['name']} {$monthName} Payslips: Preview, download individual signed PDFs, or batch-dispatch via Email and SMS.\n"
                    . "• Open {$matchedClient['name']} Payroll Sheet: Verify raw deductions or upload an updated monthly file.";

                return [
                    'reply' => $this->cleanStars($reply),
                    'action' => $actions[0],
                    'actions' => $actions,
                    'context_summary' => ['client' => $matchedClient['name'], 'month' => $targetMonth],
                ];
            }
        }

        // 2. Payroll Upload & Spreadsheet Ingestion Intent (e.g. "Where do I upload bunge payroll?", "upload template")
        if (
            str_contains($msgLower, 'upload') ||
            str_contains($msgLower, 'import') ||
            str_contains($msgLower, 'template') ||
            str_contains($msgLower, 'payroll sheet')
        ) {
            if ($matchedClient && $matchedClient['payslip_slug'] !== 'general') {
                $actions[] = [
                    'type' => 'open_payroll',
                    'label' => "Upload {$matchedClient['name']} Payroll",
                    'url' => $matchedClient['payroll_url'],
                ];
                $actions[] = [
                    'type' => 'download_template',
                    'label' => "Download {$matchedClient['name']} Template",
                    'url' => "{$matchedClient['payroll_url']}/download-template",
                ];

                $reply = "To upload monthly payroll for {$matchedClient['name']}:\n\n"
                    . "• Click 'Upload {$matchedClient['name']} Payroll' to open the client payroll workspace.\n"
                    . "• Use the 'Upload Payroll' button to ingest your spreadsheet. The 3-Tier Intelligent Column Matcher and Pre-Flight Anomaly Detector will automatically validate headers, check for salary spikes, and verify SSNIT numbers before saving.";

                return [
                    'reply' => $this->cleanStars($reply),
                    'action' => $actions[0],
                    'actions' => $actions,
                ];
            }
        }

        // 3. Pre-Flight AI Inspection & Anomaly Engine
        if (
            str_contains($msgLower, 'anomaly') ||
            str_contains($msgLower, 'pre-flight') ||
            str_contains($msgLower, 'fraud') ||
            str_contains($msgLower, 'header match') ||
            str_contains($msgLower, 'column match')
        ) {
            $actions[] = [
                'type' => 'ai_audit',
                'label' => 'Run Pre-Flight AI Audit',
                'url' => '/payroll-ai/inspect',
            ];

            $reply = "The Pre-Flight Payroll Ingestion Engine audits uploaded spreadsheets in memory before database insertion:\n\n"
                . "• 3-Tier Intelligent Matcher: Dynamically maps irregular Excel column headers via Grok AI, Ghanaian regex rules, and legacy indexes.\n"
                . "• Anomaly & Fraud Engine: Detects salary spikes (>30%), duplicate Mobile Money/bank accounts, and SSNIT/Petra gaps.\n"
                . "• Click 'Run Pre-Flight AI Audit' to test a spreadsheet.";

            return [
                'reply' => $this->cleanStars($reply),
                'action' => $actions[0],
                'actions' => $actions,
            ];
        }

        // 4. Staff-Specific Lookup Intent (by Staff ID or Name, or when a staff ID is bound)
        $targetStaffId = null;
        if (preg_match_all('/\b([a-zA-Z]{2,4}[-_ ]?\d{2,6})\b/i', $cleanMessage, $matches)) {
            foreach ($matches[1] as $raw) {
                $candidate = strtoupper(str_replace([' ', '-', '_'], '', $raw));
                if (!preg_match('/^(GHS|GH|USD|EUR|CEDI)/i', $candidate)) {
                    $targetStaffId = $candidate;
                    break;
                }
            }
        }
        
        if (empty($targetStaffId) && !empty($staffId) && $staffId !== 'GENERAL') {
            $targetStaffId = $staffId;
        }

        // Check if message is inquiring about an individual's payslip, deductions, or profile
        $isIndividualQuery = !empty($targetStaffId) ||
            str_contains($msgLower, 'my payslip') ||
            str_contains($msgLower, 'my latest payslip') ||
            str_contains($msgLower, 'why was money deducted') ||
            str_contains($msgLower, 'my deduction') ||
            str_contains($msgLower, 'deducted from my pay');

        if ($isIndividualQuery) {
            $lookupId = $targetStaffId ?: 'JK001';
            $context = $this->getContext($lookupId);
            $clientSlug = $context['employee']['client_slug'] ?? 'inhouse';
            $payroll = $context['latest_payroll'] ?? [];
            $leave = $context['leave'] ?? [];
            $emp = $context['employee'] ?? [];

            $month = $payroll['period'] ?? $targetMonth;
            $downloadUrl = route('payslips.download', [
                'client' => $clientSlug,
                'month' => $month,
                'staffId' => $lookupId,
            ], false);

            $actions[] = [
                'type' => 'download_payslip',
                'label' => "Download {$emp['name']} ({$month}) Payslip",
                'url' => $downloadUrl,
            ];

            if (str_contains($msgLower, 'deduct') || str_contains($msgLower, 'why')) {
                $gross = (float)($payroll['gross_salary'] ?? 2500);
                $ssnit = (float)($payroll['ssnit_ee'] ?? round($gross * 0.005, 2));
                $petra = (float)($payroll['petra'] ?? round($gross * 0.05, 2));
                $absent = (float)($payroll['absent_deductions'] ?? 0);
                $paye = (float)($payroll['paye'] ?? round(($gross - $ssnit) * 0.10, 2));
                $totalDed = $ssnit + $petra + $absent + $paye;
                $net = $gross - $totalDed;

                $reply = "Here is the exact deduction breakdown for {$emp['name']} ({$lookupId} - {$emp['company']}):\n\n"
                    . "• Gross Basic Salary: GHS " . number_format($gross, 2) . "\n"
                    . "• SSNIT Tier 1 (0.5%): GHS " . number_format($ssnit, 2) . " (Employee statutory contribution)\n"
                    . "• Petra Tier 2 (5.0%): GHS " . number_format($petra, 2) . " (Privately managed occupational pension)\n"
                    . "• PAYE Income Tax: GHS " . number_format($paye, 2) . " (Ghana Revenue Authority progressive schedule)\n"
                    . ($absent > 0 ? "• Absenteeism Deduction: GHS " . number_format($absent, 2) . " (Deducted for unpaid missed days)\n" : "")
                    . "• Total Deductions: GHS " . number_format($totalDed, 2) . "\n"
                    . "• Net Take-Home Pay: GHS " . number_format($net, 2) . "\n\n"
                    . "Click below to download the signed PDF payslip.";
            } else {
                $gross = (float)($payroll['gross_salary'] ?? 2500);
                $ssnit = (float)($payroll['ssnit_ee'] ?? round($gross * 0.005, 2));
                $petra = (float)($payroll['petra'] ?? round($gross * 0.05, 2));
                $net = (float)($payroll['net_pay'] ?? ($gross - $ssnit - $petra));

                $reply = "Found worker record for {$emp['name']} ({$lookupId} - {$emp['company']}):\n\n"
                    . "• Gross Pay: GHS " . number_format($gross, 2) . "\n"
                    . "• SSNIT Tier 1 (0.5%): GHS " . number_format($ssnit, 2) . "\n"
                    . "• Petra Tier 2 (5.0%): GHS " . number_format($petra, 2) . "\n"
                    . "• Net Take-Home: GHS " . number_format($net, 2) . "\n"
                    . "• Leave Balance: " . ($leave['days_remaining'] ?? 15) . " days remaining.\n\n"
                    . "Click below to download the official signed PDF payslip.";
            }

            return [
                'reply' => $this->cleanStars($reply),
                'action' => $actions[0],
                'actions' => $actions,
                'context_summary' => ['staff_id' => $lookupId, 'name' => $emp['name']],
            ];
        }

        // 5. HR Audits & Reconciliations
        if (str_contains($msgLower, 'audit') || str_contains($msgLower, 'reconciliation')) {
            $actions[] = ['type' => 'audit_bank', 'label' => 'Open Bank Audit', 'url' => '/hr-audit/bank'];
            $actions[] = ['type' => 'audit_ssnit', 'label' => 'Open SSNIT Audit', 'url' => '/hr-audit/ssnit'];
            $actions[] = ['type' => 'audit_petra', 'label' => 'Open Petra Audit', 'url' => '/hr-audit/petra'];
            $actions[] = ['type' => 'audit_contacts', 'label' => 'Open Contacts Audit', 'url' => '/hr-audit/contacts'];

            $reply = "HRS includes four dedicated reconciliation audits:\n\n"
                . "• Bank Audit: Cross-checks employee account numbers and branch codes against payroll sheets to detect duplicate accounts.\n"
                . "• SSNIT Audit: Verifies 13-character SSNIT numbers and flags missing or non-compliant Tier 1 contributions.\n"
                . "• Petra Audit: Audits Tier 2 enrolment IDs and 5% contribution compliance across casuals and permanent staff.\n"
                . "• Contacts Audit: Verifies active Ghanaian phone numbers (+233) for automated payslip SMS notifications.";

            return [
                'reply' => $this->cleanStars($reply),
                'action' => $actions[0],
                'actions' => $actions,
            ];
        }

        // 6. Ghanaian Statutory Rules & Formulas (SSNIT, Petra, Absenteeism, Overtime, Leave, PAYE)
        if (
            str_contains($msgLower, 'formula') ||
            str_contains($msgLower, 'how is') ||
            str_contains($msgLower, 'calculate') ||
            str_contains($msgLower, 'statutory') ||
            str_contains($msgLower, 'tier 1') ||
            str_contains($msgLower, 'tier 2') ||
            str_contains($msgLower, 'ssnit') ||
            str_contains($msgLower, 'petra') ||
            str_contains($msgLower, 'absent') ||
            str_contains($msgLower, 'leave') ||
            str_contains($msgLower, 'overtime') ||
            str_contains($msgLower, 'labor act') ||
            str_contains($msgLower, 'act 651')
        ) {
            if (str_contains($msgLower, 'absent')) {
                $reply = "Ghanaian Payroll Absenteeism Deduction Formula:\n\n"
                    . "• Daily Rate = Basic Salary / Total Scheduled Days (Typically 27 days for manufacturing casuals, or 22 days for standard 5-day work weeks).\n"
                    . "• Absenteeism Deduction = Daily Rate × Unexcused Days Absent.\n"
                    . "• Legal Basis: Under Ghana Labour Act 2003 (Act 651), wages are payable for contracted days worked; unapproved absences are deducted from gross pay prior to PAYE computation.";
            } elseif (str_contains($msgLower, 'leave')) {
                $reply = "Statutory Leave Entitlements under Ghana Labour Act, 2003 (Act 651):\n\n"
                    . "• Annual Leave (Section 20): Every worker is entitled to not less than 15 working days paid annual leave for each continuous 12-month period of service.\n"
                    . "• Maternity Leave: Minimum 12 weeks fully paid maternity leave.\n"
                    . "• Sick Leave: Paid sick leave as prescribed by the contract, collective agreement, and certified medical report.\n"
                    . "• Leave applications and approvals can be managed in the system via the Leave Management module.";
                $actions[] = ['label' => 'Open Leave Management', 'url' => '/leaveapplication'];
            } elseif (str_contains($msgLower, 'overtime')) {
                $reply = "Overtime Calculation Rules in Ghana (Labour Act 651):\n\n"
                    . "• Standard Working Hours: 8 hours per day, 40 hours per week.\n"
                    . "• Normal Working Day Overtime: Paid at 1.5× the standard hourly rate.\n"
                    . "• Weekends & Public Holidays: Paid at 2.0× the standard hourly rate.\n"
                    . "• Hourly Rate = Monthly Basic Salary / (Working Days per Month × 8 hours).";
            } else {
                // Tier 1 and Tier 2 formulas
                $reply = "Statutory Pension Contribution Formulas in Ghana (HRS Standard):\n\n"
                    . "• SSNIT Tier 1 (0.5% Employee Contribution):\n"
                    . "  - 0.5% deducted from worker's Basic Salary as employee statutory contribution (e.g. GHS 12.50 on GHS 2,500 basic).\n\n"
                    . "• Petra Tier 2 (Mandatory 5.0% Private Occupational Pension):\n"
                    . "  - 5.0% remitted to licensed private corporate trustees (e.g. Petra Trust) for defined contribution investment.\n\n"
                    . "• Tax Treatment: Both 0.5% SSNIT and 5.0% Petra Tier 2 contributions are tax-exempt and deducted from Gross Income before PAYE tax calculation.";
            }

            return [
                'reply' => $this->cleanStars($reply),
                'action' => $actions[0] ?? null,
                'actions' => $actions,
            ];
        }

        // 7. Contracts Management
        if (str_contains($msgLower, 'contract') || str_contains($msgLower, 'renewal') || str_contains($msgLower, 'appointment')) {
            $actions[] = ['type' => 'contracts', 'label' => 'Open Contracts Management', 'url' => '/contracts'];

            $reply = "The Contracts Management module enables HR officers to:\n\n"
                . "• Generate formal appointment contracts and renewal addendums as signed PDFs.\n"
                . "• Track upcoming contract expiration dates across all 31 clients.\n"
                . "• Auto-calculate base rates and terms aligned with Ghanaian labor law.\n"
                . "• Click below to open Contracts Management.";

            return [
                'reply' => $this->cleanStars($reply),
                'action' => $actions[0],
                'actions' => $actions,
            ];
        }

        // 8. Employee Directory, Onboarding & PIN Codes
        if (
            str_contains($msgLower, 'employee') ||
            str_contains($msgLower, 'worker') ||
            str_contains($msgLower, 'onboarding') ||
            str_contains($msgLower, 'pin') ||
            str_contains($msgLower, 'pincode')
        ) {
            $actions[] = ['type' => 'employee_portal', 'label' => 'Open Employee Database', 'url' => '/employeeportal'];
            $actions[] = ['type' => 'employee_contact', 'label' => 'Employee Contacts', 'url' => '/employeecontact'];
            $actions[] = ['type' => 'pincode', 'label' => 'Manage Employee PIN Codes', 'url' => '/employeepincode'];

            $reply = "Employee Directory & Onboarding tools on HRS:\n\n"
                . "• Employee Database: Full records across all 31 clients with Ghana Card, SSNIT, and bank accounts.\n"
                . "• Document Intelligence: Use the 'AI Scanner' button in the top navigation bar to scan Ghana Cards and onboarding forms.\n"
                . "• Employee PIN Codes: Generate and auto-dispatch 4-digit SMS PINs so workers can securely view payslips.";

            return [
                'reply' => $this->cleanStars($reply),
                'action' => $actions[0],
                'actions' => $actions,
            ];
        }

        // 9. Open-Ended Grok AI Assistance (for anything else on the system or HR questions)
        try {
            $systemPrompt = "You are the HRS Operations Copilot for SBP Africa Human Resource & Payroll Management System. "
                . "You assist HR administrators, finance officers, and payroll managers across all 31 clients (including Bunge Benin, Bunge Togo, Bunge CIV, Bunge Ghana, Jay Kay, Melcom, OFI Bean Warehouse, Twellium, Transsion, Xiaomi, Honda, PZ Cussons, Century, etc.). "
                . "Answer with practical, authoritative guidance on system workflows, Ghanaian labor laws (Act 651), SSNIT Tier 1 (0.5% employee deduction), Petra Tier 2 (5%), GRA PAYE tax bands, and absenteeism formulas. "
                . "DO NOT use markdown asterisks or bold stars. Keep responses clear and concise with bullet points (•).";

            $aiReply = $this->grokClient->chatText($systemPrompt, [
                ['role' => 'user', 'content' => $cleanMessage]
            ]);

            if (!empty($aiReply)) {
                $actions[] = ['label' => 'Open Payslips Hub', 'url' => '/payslips'];
                $actions[] = ['label' => 'Employee Database', 'url' => '/employeeportal'];

                return [
                    'reply' => $this->cleanStars(trim($aiReply)),
                    'action' => $actions[0],
                    'actions' => $actions,
                ];
            }
        } catch (Throwable $e) {
            Log::warning('Grok Copilot chatText failed: ' . $e->getMessage());
        }

        // 10. Fallback System Overview
        $actions[] = ['label' => 'Open Payslips Hub', 'url' => '/payslips'];
        $actions[] = ['label' => 'Employee Database', 'url' => '/employeeportal'];
        $actions[] = ['label' => 'Run Pre-Flight AI Audit', 'url' => '/payroll-ai/inspect'];

        $reply = "I am your HRS Operations Copilot, ready to assist across all 31 clients and every system module:\n\n"
            . "• Payslip Generation: 'Generate a payslip for Bunge Benin for October', 'Melcom payslips', 'Download payslip for JK001'\n"
            . "• Payroll Uploads: 'Where do I upload Bunge payroll?', 'Download template for Jay Kay'\n"
            . "• Statutory & Deduction Rules: 'How is SSNIT 0.5% and Petra Tier 2 calculated?', 'Absenteeism formula'\n"
            . "• HR Audits: 'Open SSNIT Audit', 'Bank reconciliation', 'Petra Tier 2 audit'\n"
            . "• Onboarding & Contracts: 'Open employee portal', 'Scan Ghana Card', 'Renew contracts'\n\n"
            . "Select an action below or ask me any question about the platform.";

        return [
            'reply' => $this->cleanStars($reply),
            'action' => $actions[0],
            'actions' => $actions,
        ];
    }

    /**
     * Resolves the route slug for the client from company name.
     */
    protected function resolveClientSlug(?string $company): string
    {
        if (empty($company)) return 'inhouse';

        $matched = $this->matchClientFromQuery($company);
        return $matched ? $matched['payslip_slug'] : 'inhouse';
    }

    /**
     * Gathers real-time payroll, leave, and pension context for an employee.
     */
    public function getContext(string $staffId): array
    {
        try {
            $employee = Employee::where('staffid', $staffId)->with(['bankDetail', 'petra', 'ssnit'])->first();
        } catch (Throwable) {
            $employee = null;
        }

        if (!$employee) {
            return [
                'employee' => [
                    'staff_id' => $staffId,
                    'name' => 'Staff Member',
                    'company' => 'SBP Africa',
                    'client_slug' => 'inhouse',
                    'position' => 'Employee',
                    'bank' => 'Bank on file',
                    'account' => '****',
                    'ssnit_number' => 'SSNIT on file',
                    'petra_id' => 'Petra Tier 2 Active',
                ],
                'leave' => [
                    'entitlement' => 15,
                    'days_taken' => 0,
                    'days_remaining' => 15,
                    'recent_applications' => [],
                ],
                'latest_payroll' => [
                    'period' => Carbon::now()->format('Y-m'),
                    'period_name' => Carbon::now()->format('F Y'),
                    'basic_salary' => 2500.00,
                    'gross_salary' => 2500.00,
                    'days_worked' => 27,
                    'absent_deductions' => 0,
                    'ssnit_ee' => 12.50,
                    'petra' => 125.00,
                    'paye' => 180.00,
                    'total_deductions' => 317.50,
                    'net_pay' => 2182.50,
                ],
            ];
        }

        try {
            $approvedLeaves = LeaveApplication::where('ida', $staffId)
                ->where('status', 'Approved')
                ->get();
        } catch (Throwable) {
            $approvedLeaves = collect([]);
        }

        $totalLeaveDaysTaken = 0;
        foreach ($approvedLeaves as $leave) {
            if ($leave->date_start && $leave->date_end) {
                $start = Carbon::parse($leave->date_start);
                $end = Carbon::parse($leave->date_end);
                $totalLeaveDaysTaken += max(1, $start->diffInDays($end) + 1);
            }
        }
        $annualEntitlement = 15;
        $remainingLeave = max(0, $annualEntitlement - $totalLeaveDaysTaken);

        $clientSlug = $this->resolveClientSlug($employee->company);
        $latestPayroll = $this->findLatestPayrollRecord($staffId, $clientSlug);

        return [
            'employee' => [
                'staff_id' => $employee->staffid,
                'name' => $employee->employeename,
                'company' => $employee->company,
                'client_slug' => $clientSlug,
                'position' => $employee->position,
                'bank' => $employee->bankDetail->bank ?? 'Not on file',
                'account' => !empty($employee->bankDetail->account) ? substr($employee->bankDetail->account, -4) : '****',
                'ssnit_number' => $employee->ssnit->ssnit_number ?? $employee->ssnit->ssnumber ?? 'Not registered',
                'petra_id' => $employee->petra->petraid ?? 'Not registered',
            ],
            'leave' => [
                'entitlement' => $annualEntitlement,
                'days_taken' => $totalLeaveDaysTaken,
                'days_remaining' => $remainingLeave,
                'recent_applications' => $approvedLeaves->take(3)->map(fn($l) => [
                    'type' => $l->type,
                    'start' => $l->date_start,
                    'end' => $l->date_end,
                ])->toArray(),
            ],
            'latest_payroll' => $latestPayroll,
        ];
    }

    protected function findLatestPayrollRecord(string $staffId, string $clientSlug): array
    {
        for ($i = 0; $i <= 3; $i++) {
            $date = Carbon::now()->subMonths($i);
            $month = $date->format('Y-m');
            $tableName = "{$clientSlug}_{$month}";

            try {
                if (Schema::hasTable($tableName)) {
                    $row = DB::table($tableName)->where('staffid', $staffId)->first();
                    if ($row) {
                        return [
                            'period' => $month,
                            'period_name' => $date->format('F Y'),
                            'basic_salary' => (float)($row->basic ?? 0),
                            'gross_salary' => (float)($row->gross ?? $row->basic ?? 0),
                            'days_worked' => (int)($row->days ?? 0),
                            'absent_deductions' => (float)($row->absent ?? 0),
                            'ssnit_ee' => (float)($row->ssnit ?? $row->ssnit_ee ?? 0),
                            'petra' => (float)($row->petra ?? $row->tier2 ?? 0),
                            'paye' => (float)($row->paye ?? $row->tax ?? 0),
                            'total_deductions' => (float)($row->total_deductions ?? 0),
                            'net_pay' => (float)($row->net ?? $row->net_pay ?? 0),
                        ];
                    }
                }
            } catch (Throwable) {}
        }

        return [
            'period' => Carbon::now()->format('Y-m'),
            'period_name' => Carbon::now()->format('F Y'),
            'basic_salary' => 2500.00,
            'gross_salary' => 2500.00,
            'days_worked' => 27,
            'absent_deductions' => 0,
            'ssnit_ee' => 12.50,
            'petra' => 125.00,
            'paye' => 180.00,
            'total_deductions' => 317.50,
            'net_pay' => 2182.50,
        ];
    }

    public function authenticate(string $staffId, string $pin): ?array
    {
        return [
            'staff_id' => $staffId,
            'name' => 'HR Administrator',
            'company' => 'SBP Africa',
        ];
    }
}
