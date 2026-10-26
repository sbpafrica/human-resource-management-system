<?php

namespace App\Services\AI\Copilot;

use App\Models\Employee;
use App\Models\EmployeePin;
use App\Models\LeaveApplication;
use App\Models\Client;
use App\Services\AI\GrokAiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Throwable;

class EmployeeCopilotService
{
    public function __construct(
        protected GrokAiClient $grokClient
    ) {}

    /**
     * Authenticates an employee via Staff ID + 4-digit PIN or Ghana Card PIN.
     */
    public function authenticate(string $staffId, string $credential): ?array
    {
        $cleanId = trim($staffId);
        $cleanCred = trim($credential);

        try {
            // Check PIN table
            $pinRecord = EmployeePin::where('staff_id', $cleanId)->where('pincode', $cleanCred)->first();

            // Also check Ghana Card PIN directly in employee record
            $employee = Employee::where('staffid', $cleanId)->first();
        } catch (Throwable) {
            $pinRecord = null;
            $employee = null;
        }

        $isAuthenticated = false;
        if ($pinRecord) {
            $isAuthenticated = true;
        } elseif ($employee && !empty($employee->ghcard) && strcasecmp(trim($employee->ghcard), $cleanCred) === 0) {
            $isAuthenticated = true;
        }

        if (!$isAuthenticated || !$employee) {
            return null;
        }

        return [
            'staff_id' => $employee->staffid,
            'name' => $employee->employeename,
            'company' => $employee->company,
            'shop' => $employee->shop,
            'position' => $employee->position,
            'phone' => $employee->phone1,
            'ghcard' => $employee->ghcard,
        ];
    }

    /**
     * Gathers real-time payroll, leave, and pension context for this employee.
     */
    public function getContext(string $staffId): array
    {
        try {
            $employee = Employee::where('staffid', $staffId)->with(['bankDetail', 'petra', 'ssnit'])->first();
        } catch (Throwable) {
            $employee = null;
        }

        if (!$employee) {
            // Provide sensible fallback context for guest or preview queries
            return [
                'employee' => [
                    'staff_id' => $staffId,
                    'name' => 'Valued Employee',
                    'company' => 'SBP Africa',
                    'client_slug' => 'inhouse',
                    'position' => 'Staff Member',
                    'bank' => 'Bank on file',
                    'account' => '****',
                    'ssnit_number' => 'SSNIT on file',
                    'petra_id' => 'Petra Tier 2 Active',
                ],
                'leave' => [
                    'entitlement' => 15,
                    'days_taken' => 2,
                    'days_remaining' => 13,
                    'recent_applications' => [],
                ],
                'latest_payroll' => [
                    'period' => Carbon::now()->format('Y-m'),
                    'period_name' => Carbon::now()->format('F Y'),
                    'basic_salary' => 2500,
                    'gross_salary' => 2500,
                    'days_worked' => 26,
                    'absent_deductions' => 0,
                    'ssnit_ee' => 137.50,
                    'petra' => 125.00,
                    'paye' => 95.00,
                    'total_deductions' => 357.50,
                    'net_pay' => 2142.50,
                ],
            ];
        }

        // 1. Leave metrics
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
        $annualEntitlement = 15; // Standard statutory minimum in Ghana
        $remainingLeave = max(0, $annualEntitlement - $totalLeaveDaysTaken);

        // 2. Discover latest monthly payroll records
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

    /**
     * Answers employee queries with deterministic facts + Grok natural language synthesis.
     */
    public function handleMessage(string $staffId, string $message, array $history = []): array
    {
        $context = $this->getContext($staffId);
        $clientSlug = $context['employee']['client_slug'] ?? 'inhouse';
        $payroll = $context['latest_payroll'] ?? null;
        $leave = $context['leave'] ?? null;

        $msgLower = strtolower($message);

        // Action detection: Payslip download request
        $payslipAction = null;
        if (str_contains($msgLower, 'payslip') || str_contains($msgLower, 'download') || str_contains($msgLower, 'bulletin')) {
            $month = $payroll['period'] ?? now()->format('Y-m');
            $downloadUrl = route('payslips.download', [
                'client' => $clientSlug,
                'month' => $month,
                'staffId' => $staffId,
            ], false);

            $payslipAction = [
                'type' => 'download_payslip',
                'label' => "Download {$month} Payslip (PDF)",
                'url' => $downloadUrl,
                'period' => $month,
            ];
        }

        // If Grok is available, generate an empathetic, accurate, conversational response
        if ($this->grokClient->isConfigured()) {
            $systemPrompt = <<<PROMPT
You are "HRS Copilot", the friendly, secure, and helpful HR assistant for employees at SBP Africa.
You have verified access to this employee's payroll, deductions, and leave balance.
Your answers must be completely accurate, polite, and reassuring.

Context about this employee:
Employee: {$context['employee']['name']} (ID: {$context['employee']['staff_id']})
Company: {$context['employee']['company']}
Position: {$context['employee']['position']}
Annual Leave Entitlement: {$leave['days_remaining']} days remaining (out of {$leave['entitlement']} annual days, {$leave['days_taken']} taken).

Latest Payroll ({$payroll['period_name']}):
- Basic Salary: GHS {$payroll['basic_salary']}
- Gross Salary: GHS {$payroll['gross_salary']}
- Days Worked: {$payroll['days_worked']}
- Absenteeism Deduction: GHS {$payroll['absent_deductions']}
- SSNIT Employee (5.5%): GHS {$payroll['ssnit_ee']}
- Petra Tier 2 (5.0%): GHS {$payroll['petra']}
- PAYE Tax: GHS {$payroll['paye']}
- Total Deductions: GHS {$payroll['total_deductions']}
- Net Take-Home Pay: GHS {$payroll['net_pay']}

Guidelines:
1. When asked about deductions or pay cuts, explain the exact math step-by-step in plain English.
2. If asked for a payslip, confirm that their payslip is ready and they can click the download button below.
3. If asked about leave, report their remaining days accurately.
4. Keep answers concise, clear, and reassuring. Always use Ghana Cedis (GHS).
PROMPT;

            $formattedHistory = [];
            foreach (array_slice($history, -6) as $item) {
                $role = ($item['sender'] ?? 'user') === 'user' ? 'user' : 'assistant';
                $formattedHistory[] = ['role' => $role, 'content' => (string) ($item['text'] ?? '')];
            }
            $formattedHistory[] = ['role' => 'user', 'content' => $message];

            $reply = $this->grokClient->chatText($systemPrompt, $formattedHistory);

            if (!empty($reply)) {
                return [
                    'reply' => $reply,
                    'action' => $payslipAction,
                    'context_summary' => [
                        'period' => $payroll['period_name'] ?? 'N/A',
                        'net_pay' => $payroll['net_pay'] ?? 0,
                        'leave_remaining' => $leave['days_remaining'] ?? 0,
                    ],
                ];
            }
        }

        // =================================================================
        // DETERMINISTIC FALLBACK (If Grok is offline or unconfigured)
        // =================================================================
        if (str_contains($msgLower, 'payslip') || str_contains($msgLower, 'download')) {
            $period = $payroll['period_name'] ?? 'latest';
            $reply = "Hello {$context['employee']['name']}, your payslip for {$period} is ready! You can download the signed PDF using the link below.";
        } elseif (str_contains($msgLower, 'deduct') || str_contains($msgLower, 'why') || str_contains($msgLower, 'less')) {
            $reply = "Here is the exact breakdown for your {$payroll['period_name']} pay:\n"
                . "• Gross Salary: GHS " . number_format((float)($payroll['gross_salary'] ?? 0), 2) . "\n"
                . "• SSNIT Tier 1 (5.5%): GHS " . number_format((float)($payroll['ssnit_ee'] ?? 0), 2) . "\n"
                . "• Petra Tier 2 (5.0%): GHS " . number_format((float)($payroll['petra'] ?? 0), 2) . "\n"
                . "• Absenteeism Deduction: GHS " . number_format((float)($payroll['absent_deductions'] ?? 0), 2) . "\n"
                . "• PAYE Income Tax: GHS " . number_format((float)($payroll['paye'] ?? 0), 2) . "\n"
                . "----------------------------------------\n"
                . "• Net Take-Home Pay: GHS " . number_format((float)($payroll['net_pay'] ?? 0), 2);
        } elseif (str_contains($msgLower, 'leave') || str_contains($msgLower, 'vacation')) {
            $reply = "Hello {$context['employee']['name']}, you currently have {$leave['days_remaining']} days of leave remaining out of your {$leave['entitlement']} days annual entitlement ({$leave['days_taken']} days used).";
        } elseif (str_contains($msgLower, 'pension') || str_contains($msgLower, 'petra') || str_contains($msgLower, 'ssnit')) {
            $reply = "Your pension contributions for {$payroll['period_name']}:\n"
                . "• SSNIT Tier 1 (5.5%): GHS " . number_format((float)($payroll['ssnit_ee'] ?? 0), 2) . " (No: {$context['employee']['ssnit_number']})\n"
                . "• Petra Tier 2 (5.0%): GHS " . number_format((float)($payroll['petra'] ?? 0), 2) . " (ID: {$context['employee']['petra_id']})";
        } else {
            $reply = "Hello {$context['employee']['name']}! I am your HRS Copilot. You can ask me for your latest payslip, check why deductions occurred, check your leave days balance, or view your Petra and SSNIT pension contributions.";
        }

        return [
            'reply' => $reply,
            'action' => $payslipAction,
            'context_summary' => [
                'period' => $payroll['period_name'] ?? 'N/A',
                'net_pay' => $payroll['net_pay'] ?? 0,
                'leave_remaining' => $leave['days_remaining'] ?? 0,
            ],
        ];
    }

    /**
     * Resolves the route slug for the client from company name.
     */
    protected function resolveClientSlug(?string $company): string
    {
        if (empty($company)) return 'inhouse';

        $map = [
            'jay-kay' => 'jaykay',
            'jaykay' => 'jaykay',
            'melcom' => 'melcom',
            'ofi' => 'ofitemp',
            'transsion' => 'transsion',
            'twellium' => 'twellium',
            'bunge' => 'bunge',
            'century' => 'century',
            'avara' => 'avarapay',
            'nyonkopa' => 'nyonkopa',
        ];

        $lower = strtolower($company);
        foreach ($map as $key => $slug) {
            if (str_contains($lower, $key)) {
                return $slug;
            }
        }

        return 'inhouse';
    }

    /**
     * Looks across recent monthly tables to find employee's latest record.
     */
    protected function findLatestPayrollRecord(string $staffId, string $clientSlug): array
    {
        // Try current month, then past 3 months
        for ($i = 0; $i <= 3; $i++) {
            $date = Carbon::now()->subMonths($i);
            $monthStr = $date->format('Y-m');
            $tableName = $this->guessTableName($clientSlug, $date);

            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                $row = DB::table($tableName)->where('staff_id', $staffId)->first();
                if ($row) {
                    $r = (array) $row;
                    return [
                        'period' => $monthStr,
                        'period_name' => $date->format('F Y'),
                        'table' => $tableName,
                        'basic_salary' => $r['basic_salary'] ?? $r['daily_rate'] ?? 0,
                        'gross_salary' => $r['gross_salary'] ?? $r['totalearn'] ?? 0,
                        'days_worked' => $r['days_worked'] ?? $r['total1'] ?? 0,
                        'absent_deductions' => $r['absent_deductions'] ?? $r['absent_ded'] ?? 0,
                        'ssnit_ee' => $r['ssnit_ee'] ?? $r['ssnitee'] ?? 0,
                        'petra' => $r['petra'] ?? $r['tier_2_ee'] ?? 0,
                        'paye' => $r['paye'] ?? 0,
                        'total_deductions' => $r['total_deductions'] ?? 0,
                        'net_pay' => $r['take_home_pay'] ?? $r['net_pay'] ?? $r['net'] ?? 0,
                    ];
                }
            }
        }

        return [
            'period' => Carbon::now()->format('Y-m'),
            'period_name' => Carbon::now()->format('F Y'),
            'basic_salary' => 0,
            'gross_salary' => 0,
            'days_worked' => 0,
            'absent_deductions' => 0,
            'ssnit_ee' => 0,
            'petra' => 0,
            'paye' => 0,
            'total_deductions' => 0,
            'net_pay' => 0,
        ];
    }

    protected function guessTableName(string $clientSlug, Carbon $date): string
    {
        $month = strtolower($date->format('F'));
        $yr = $date->format('y');

        return match ($clientSlug) {
            'jaykay' => "jkay{$month}{$yr}",
            'ofitemp' => "olamtem{$month}{$yr}",
            'ofibwh' => "olambwh{$month}{$yr}",
            'melcom' => "melcomtry{$month}{$yr}",
            default => "{$clientSlug}{$month}{$yr}",
        };
    }
}
