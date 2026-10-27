<?php

namespace App\Services\Grok;

use App\Models\AssistantMessage;
use App\Models\AssistantThread;
use App\Models\Employee;
use App\Models\LeaveApplication;
use Illuminate\Support\Facades\Log;

class HrAssistantService
{
    protected GrokClient $grokClient;

    public function __construct(GrokClient $grokClient)
    {
        $this->grokClient = $grokClient;
    }

    /**
     * Get system prompt instructions for the HR Assistant persona.
     */
    public function getSystemPrompt(): string
    {
        return <<<PROMPT
You are "SBP HR & Payroll Assistant", an intelligent AI agent for SBP Africa and its subsidiaries (including Melcom, Transsion, PZ Cussons, Twellium, Jaykay, Bunge, Honda, Xiaomi, OFI, etc.).

Your primary responsibilities:
1. Answer employee questions about company HR policies, payslip breakdowns, and leave procedures.
2. Provide precise statutory calculations according to Ghana Labor and Tax Laws:
   - SSNIT Tier 1 & 2: Employee pays 5.5% of basic salary; Employer contributes 13.0%.
   - Petra Trust Provident Fund: Standard 5.0% employee deduction.
   - GRA PAYE Income Tax: Progressive personal income tax brackets.
3. Use available tools to look up employee records and leave applications when requested.
4. Maintain a professional, helpful, polite, and confidential tone. Never disclose sensitive banking or tax details to unauthorized parties.
PROMPT;
    }

    /**
     * Define the function tools Grok can call.
     */
    public function getTools(): array
    {
        return [
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'lookup_employee',
                    'description' => 'Look up an employee profile by their staff ID or name.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'identifier' => [
                                'type'        => 'string',
                                'description' => 'Staff ID (e.g. SBP-001) or employee name',
                            ],
                        ],
                        'required'   => ['identifier'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'check_leave_status',
                    'description' => 'Check leave applications and approval status for a staff ID.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'staff_id' => [
                                'type'        => 'string',
                                'description' => 'Employee staff ID',
                            ],
                        ],
                        'required'   => ['staff_id'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'calculate_statutory_deductions',
                    'description' => 'Calculate Ghana statutory payroll deductions (SSNIT 5.5%, Petra 5%, and estimated PAYE tax) for a given gross salary amount in GHS.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'basic_salary' => [
                                'type'        => 'number',
                                'description' => 'Basic monthly salary in Ghana Cedis (GHS)',
                            ],
                        ],
                        'required'   => ['basic_salary'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'check_attendance',
                    'description' => 'Check attendance logs and presence status for an employee.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'staff_id' => [
                                'type'        => 'string',
                                'description' => 'Employee staff ID',
                            ],
                        ],
                        'required'   => ['staff_id'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'lookup_receipt',
                    'description' => 'Look up a receipt and its payment status by receipt number.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'receipt_number' => [
                                'type'        => 'string',
                                'description' => 'Receipt number (e.g. REC12345)',
                            ],
                        ],
                        'required'   => ['receipt_number'],
                    ],
                ],
            ],
            [
                'type'     => 'function',
                'function' => [
                    'name'        => 'lookup_bank_details',
                    'description' => 'Check bank and mobile money payment details for an employee.',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'staff_id' => [
                                'type'        => 'string',
                                'description' => 'Employee staff ID',
                            ],
                        ],
                        'required'   => ['staff_id'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Process a user message within a thread, execute any tool calls, and return the reply.
     */
    public function handleUserMessage(AssistantThread $thread, string $userPrompt): array
    {
        // 1. Record the user message in DB
        $userMessage = AssistantMessage::create([
            'thread_id' => $thread->id,
            'role'      => 'user',
            'content'   => $userPrompt,
        ]);

        // If thread has default title, set a concise title based on first user prompt
        if ($thread->title === 'New Conversation') {
            $thread->title = mb_substr($userPrompt, 0, 40) . (mb_strlen($userPrompt) > 40 ? '...' : '');
            $thread->save();
        }

        // 2. Build conversation history for Grok
        $messagesPayload = [
            ['role' => 'system', 'content' => $this->getSystemPrompt()],
        ];

        $pastMessages = $thread->messages()->orderBy('id', 'asc')->get();
        foreach ($pastMessages as $msg) {
            $item = ['role' => $msg->role];
            if ($msg->content !== null) {
                $item['content'] = $msg->content;
            }
            if (!empty($msg->tool_calls)) {
                $item['tool_calls'] = $msg->tool_calls;
            }
            if (!empty($msg->tool_call_id)) {
                $item['tool_call_id'] = $msg->tool_call_id;
            }
            $messagesPayload[] = $item;
        }

        // 3. Send request to Grok with function tools
        $response = $this->grokClient->chatCompletion($messagesPayload, $this->getTools());

        $choice = $response['choices'][0]['message'] ?? [
            'role'    => 'assistant',
            'content' => "I'm sorry, I could not generate a response at this moment.",
        ];

        // 4. Check if Grok wants to invoke tools
        if (!empty($choice['tool_calls'])) {
            // Save the tool call intention message
            AssistantMessage::create([
                'thread_id'  => $thread->id,
                'role'       => 'assistant',
                'content'    => $choice['content'] ?? null,
                'tool_calls' => $choice['tool_calls'],
            ]);

            // Append to payload
            $messagesPayload[] = $choice;

            // Execute each requested tool
            foreach ($choice['tool_calls'] as $toolCall) {
                $fnName = $toolCall['function']['name'] ?? '';
                $rawArgs = $toolCall['function']['arguments'] ?? '{}';
                $args = is_string($rawArgs) ? json_decode($rawArgs, true) : (array) $rawArgs;

                $toolResult = $this->executeTool($fnName, $args ?: []);

                // Save tool response message
                AssistantMessage::create([
                    'thread_id'    => $thread->id,
                    'role'         => 'tool',
                    'content'      => json_encode($toolResult),
                    'tool_call_id' => $toolCall['id'] ?? 'tool_1',
                ]);

                $messagesPayload[] = [
                    'role'         => 'tool',
                    'tool_call_id' => $toolCall['id'] ?? 'tool_1',
                    'content'      => json_encode($toolResult),
                ];
            }

            // Call Grok once more with tool results to synthesize final answer
            $finalResponse = $this->grokClient->chatCompletion($messagesPayload, []);
            $choice = $finalResponse['choices'][0]['message'] ?? [
                'role'    => 'assistant',
                'content' => "Data retrieved successfully.",
            ];
        }

        // 5. Save final assistant response
        $assistantMsg = AssistantMessage::create([
            'thread_id' => $thread->id,
            'role'      => 'assistant',
            'content'   => $choice['content'] ?? '',
        ]);

        return [
            'thread_id' => $thread->id,
            'message'   => [
                'id'         => $assistantMsg->id,
                'role'       => $assistantMsg->role,
                'content'    => $assistantMsg->content,
                'created_at' => $assistantMsg->created_at->toIso8601String(),
            ],
        ];
    }

    /**
     * Local tool execution handlers.
     */
    protected function executeTool(string $name, array $args): array
    {
        switch ($name) {
            case 'lookup_employee':
                $identifier = $args['identifier'] ?? '';
                $employee = Employee::where('staffid', $identifier)
                    ->orWhere('employeename', 'like', "%{$identifier}%")
                    ->first();

                if (!$employee) {
                    return ['status' => 'not_found', 'message' => "No employee found matching '{$identifier}'."];
                }

                return [
                    'status'    => 'found',
                    'staff_id'  => $employee->staffid,
                    'name'      => $employee->employeename,
                    'company'   => $employee->company,
                    'position'  => $employee->position,
                    'phone'     => $employee->phone1,
                    'start_date'=> $employee->startdate,
                ];

            case 'check_leave_status':
                $staffId = $args['staff_id'] ?? '';
                $leaves = LeaveApplication::where('ida', $staffId)
                    ->latest('id')
                    ->limit(3)
                    ->get(['id', 'type', 'date_start', 'date_end', 'status']);

                return [
                    'staff_id' => $staffId,
                    'count'    => $leaves->count(),
                    'records'  => $leaves->toArray(),
                ];

            case 'calculate_statutory_deductions':
                $basic = (float) ($args['basic_salary'] ?? 0);
                $ssnit = round($basic * 0.055, 2);       // 5.5% Tier 2
                $petra = round($basic * 0.05, 2);        // 5.0% Provident Fund
                $taxable = max(0, $basic - $ssnit - $petra);

                // Simplified progressive tax estimate for Ghana PAYE
                $paye = 0.0;
                if ($taxable > 490) {
                    $excess = $taxable - 490;
                    $paye += min($excess, 110) * 0.05;
                }
                if ($taxable > 600) {
                    $excess = $taxable - 600;
                    $paye += min($excess, 130) * 0.10;
                }
                if ($taxable > 730) {
                    $excess = $taxable - 730;
                    $paye += min($excess, 3166.67) * 0.175;
                }
                if ($taxable > 3896.67) {
                    $excess = $taxable - 3896.67;
                    $paye += min($excess, 16103.33) * 0.25;
                }
                if ($taxable > 20000) {
                    $paye += ($taxable - 20000) * 0.30;
                }

                $totalDeductions = round($ssnit + $petra + $paye, 2);
                $netSalary = round($basic - $totalDeductions, 2);

                return [
                    'currency'                  => 'GHS',
                    'basic_salary'              => $basic,
                    'ssnit_tier_2_deduction'    => $ssnit,
                    'petra_trust_5_percent'     => $petra,
                    'estimated_gra_paye_tax'    => round($paye, 2),
                    'total_statutory_deductions'=> $totalDeductions,
                    'estimated_net_salary'      => $netSalary,
                ];

            case 'check_attendance':
                $staffId = $args['staff_id'] ?? '';
                $records = \Illuminate\Support\Facades\DB::table('attendanceinhouse')
                    ->where('employee_id', $staffId)
                    ->orderBy('date', 'desc')
                    ->limit(5)
                    ->get(['date', 'status', 'check_in_time', 'check_out_time']);

                return [
                    'staff_id' => $staffId,
                    'count'    => $records->count(),
                    'recent'   => $records->toArray(),
                ];

            case 'lookup_receipt':
                $number = $args['receipt_number'] ?? '';
                $receipt = \App\Models\Receipt::where('receipt_number', $number)->first();

                if (!$receipt) {
                    return ['status' => 'not_found', 'message' => "Receipt '{$number}' was not found."];
                }

                return [
                    'status'         => 'found',
                    'receipt_number' => $receipt->receipt_number,
                    'applicant_name' => $receipt->applicant_name,
                    'amount_paid'    => (float) $receipt->amount_paid,
                    'client'         => $receipt->client,
                    'date'           => $receipt->receipt_date?->toDateString(),
                    'payment_status' => $receipt->status,
                ];

            case 'lookup_bank_details':
                $staffId = $args['staff_id'] ?? '';
                $bank = \App\Models\BankDetail::where('employid', $staffId)->first();

                if (!$bank) {
                    return ['status' => 'not_found', 'message' => "No bank record found for staff ID '{$staffId}'."];
                }

                return [
                    'status'       => 'found',
                    'staff_id'     => $staffId,
                    'bank'         => $bank->bank,
                    'account'      => $bank->account ? ('****' . substr($bank->account, -4)) : null,
                    'branch'       => $bank->branch,
                    'momo_number'  => $bank->momonum ? ('****' . substr($bank->momonum, -4)) : null,
                ];

            default:
                return ['status' => 'unknown_tool', 'name' => $name];
        }
    }
}
