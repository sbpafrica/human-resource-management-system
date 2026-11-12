<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Jay Kay Bulk Payslips - {{ $formattedMonth }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 8mm 12mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 0;
            padding: 0;
            line-height: 1.3;
        }
        .page-break {
            page-break-after: always;
        }
        .outer-border {
            border: 1.5px solid #222;
            padding: 10px 14px 12px 14px;
            min-height: 980px;
            box-sizing: border-box;
        }
        .title-header {
            position: relative;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 8px;
        }
        .date-right {
            position: absolute;
            right: 0;
            top: 0;
            font-size: 11px;
            font-weight: normal;
        }
        .salary-slip-title {
            text-align: center;
            margin: 16px 0 10px 0;
            font-size: 12px;
            font-weight: bold;
            text-decoration: underline;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 8px;
        }
        .meta-table td {
            vertical-align: top;
            padding: 1.5px 0;
        }
        .meta-label {
            width: 110px;
            color: #222;
        }
        .meta-val {
            color: #000;
        }
        .days-box {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            margin-top: 14px;
        }
        .days-box th {
            font-weight: normal;
            font-size: 11px;
            padding: 2px 4px;
        }
        .days-box td {
            font-size: 11px;
            padding: 2px 4px;
        }
        .main-table {
            width: 100%;
            border-collapse: collapse;
            border-top: 1.5px solid #222;
            border-bottom: 1.5px solid #222;
            font-size: 11px;
        }
        .main-table th {
            font-weight: bold;
            padding: 4px 6px;
            border-bottom: 1px solid #222;
        }
        .main-table td {
            padding: 2.5px 6px;
            vertical-align: top;
        }
        .col-divider {
            border-right: 1.5px solid #222;
        }
        .amount-blue {
            color: #1a56db;
            font-weight: 500;
            text-align: right;
        }
        .amount-blue-bold {
            color: #1a56db;
            font-weight: bold;
            text-align: right;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-top: 2px;
        }
        .totals-table td {
            padding: 3px 6px;
        }
        .totals-border-bottom {
            border-bottom: 1.5px solid #222;
        }
        .ytd-box {
            border: 1px solid #222;
            margin-top: 12px;
        }
        .ytd-header {
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            letter-spacing: 0.5px;
            padding: 3px 0;
            border-bottom: 1px solid #222;
        }
        .ytd-subheader {
            padding: 4px 8px;
            font-size: 10.5px;
            font-weight: bold;
            border-bottom: 1px solid #222;
            min-height: 28px;
        }
        .tax-grid {
            width: 100%;
            border-collapse: collapse;
            text-align: center;
            font-size: 10.5px;
        }
        .tax-grid th {
            border: 1px solid #222;
            padding: 3px 2px;
            font-size: 10px;
            font-weight: normal;
        }
        .tax-grid td {
            border: 1px solid #222;
            padding: 3px 2px;
            font-size: 10.5px;
        }
    </style>
</head>
<body>

@php
    if (!function_exists('jaykayAmountToWords')) {
        function jaykayAmountToWords($number) {
            $dictionary = [
                0                   => 'Zero',
                1                   => 'One',
                2                   => 'Two',
                3                   => 'Three',
                4                   => 'Four',
                5                   => 'Five',
                6                   => 'Six',
                7                   => 'Seven',
                8                   => 'Eight',
                9                   => 'Nine',
                10                  => 'Ten',
                11                  => 'Eleven',
                12                  => 'Twelve',
                13                  => 'Thirteen',
                14                  => 'Fourteen',
                15                  => 'Fifteen',
                16                  => 'Sixteen',
                17                  => 'Seventeen',
                18                  => 'Eighteen',
                19                  => 'Nineteen',
                20                  => 'Twenty',
                30                  => 'Thirty',
                40                  => 'Forty',
                50                  => 'Fifty',
                60                  => 'Sixty',
                70                  => 'Seventy',
                80                  => 'Eighty',
                90                  => 'Ninety',
                100                 => 'Hundred',
                1000                => 'Thousand',
                1000000             => 'Million',
                1000000000          => 'Billion',
            ];

            if (!is_numeric($number)) return '';
            $number = (float)$number;
            if ($number < 0) return 'Negative ' . jaykayAmountToWords(abs($number));

            $parts = explode('.', number_format($number, 2, '.', ''));
            $whole = (int)$parts[0];
            $fraction = (int)$parts[1];

            $convert = function($num) use (&$convert, $dictionary) {
                if ($num < 21) return $dictionary[$num];
                if ($num < 100) {
                    $tens = ((int)($num / 10)) * 10;
                    $units = $num % 10;
                    return $dictionary[$tens] . ($units ? '-' . $dictionary[$units] : '');
                }
                if ($num < 1000) {
                    $hundreds = (int)($num / 100);
                    $remainder = $num % 100;
                    return $dictionary[$hundreds] . ' Hundred' . ($remainder ? ' ' . $convert($remainder) : '');
                }
                $baseUnit = pow(1000, floor(log($num, 1000)));
                $numBaseUnits = (int)($num / $baseUnit);
                $remainder = $num % $baseUnit;
                return $convert($numBaseUnits) . ' ' . $dictionary[$baseUnit] . ($remainder ? ' ' . $convert($remainder) : '');
            };

            $words = ($whole > 0 ? $convert($whole) : 'Zero') . ' Ghana Cedis';
            if ($fraction > 0) {
                $words .= ' And ' . $convert($fraction) . ' Pesewas Only';
            } else {
                $words .= ' Only';
            }
            return $words;
        }
    }

    $allEmployees = [];
    if (isset($chunks) && is_array($chunks)) {
        foreach ($chunks as $chunk) {
            foreach ($chunk as $item) {
                $allEmployees[] = $item;
            }
        }
    } elseif (isset($preparedData) && is_array($preparedData)) {
        $allEmployees = $preparedData;
    }
@endphp

@foreach($allEmployees as $index => $item)
    @php
        $payslip = $item['payslip'] ?? (object)[];
        $bank    = $item['bank'] ?? [];
        $ssnit   = $item['ssnit'] ?? ($payslip->ssnit ?? '-');

        $displayBankName = (!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') 
            ? data_get($bank, 'bankname') 
            : ($payslip->bank ?? $payslip->bankname ?? '');
        $displayAccountNo = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') 
            ? data_get($bank, 'alcnum') 
            : ($payslip->accountnumber ?? $payslip->account_number ?? $payslip->alcnum ?? '');
        $displayBranch = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') 
            ? data_get($bank, 'branch') 
            : ($payslip->branch ?? '');

        // Working days
        $payDays     = (float)($payslip->legalworkdays ?? $payslip->legal_work_days ?? 22);
        $presentDays = (float)($payslip->days_worked ?? 0);
        $absentDays  = (float)($payslip->absentee_days ?? $payslip->absent_days ?? 0);

        // Rates
        $basicSalary = (float)($payslip->basic_salary ?? $payslip->basic ?? 0);
        $dailyRate   = (float)($payslip->daily_rate ?: ($payDays > 0 ? ($basicSalary / $payDays) : 0));
        $basicEarned = (float)($payslip->earned_basic_salary ?: ($presentDays * $dailyRate));

        // Expense Refunds mapping (matching Jay Kay Payroll Excel):
        // 1. Expense Refund (Day Incurred): from overtime_hours (Excel Col 15: Expense Refund)
        $expenseRefund = (float)(
            $payslip->expense_refund 
            ?? $payslip->expenserefund 
            ?? $payslip->overtime_hours 
            ?? 0
        );

        // 2. Expense Refund Paid (Already Paid / Advance): from overtime_rate (Excel Col 16: Expense Refund Paid)
        $expenseRefundPaid = (float)(
            $payslip->expense_refund_paid 
            ?? $payslip->expenserefundpaid 
            ?? $payslip->overtime_rate 
            ?? 0
        );

        // 3. Expense Refund To Be Paid (Day Balance): from night_allowance (Excel Col 18: Expense Refund To Be Paid)
        $expenseRefundToBePaid = (float)(
            $payslip->expense_refund_to_be_paid 
            ?? $payslip->expenserefundtobepaid 
            ?? $payslip->night_allowance 
            ?? $payslip->nightallowance 
            ?? 0
        );

        // 4. Expense Refund (Night): from overtime_amount (Excel Col 17: Expense Refund (Night))
        $expenseRefundNight = (float)(
            $payslip->expense_refund_night 
            ?? $payslip->expenses_refund_night 
            ?? $payslip->expenserefundnight 
            ?? $payslip->overtime_amount 
            ?? 0
        );

        // 5. Total Expense Refund: from totalovertime or total_expense_refund (Excel Col 19: Total Expense Refund)
        $totalExpenseRefund = (float)(
            $payslip->total_expense_refund 
            ?? $payslip->totalexpenserefund 
            ?? $payslip->totalovertime 
            ?? $payslip->total_overtime_amt 
            ?? 0
        );

        // Fallback reconciliation if historical data differs:
        if ($totalExpenseRefund == 0 && ($expenseRefundToBePaid > 0 || $expenseRefundNight > 0)) {
            $totalExpenseRefund = $expenseRefundToBePaid + $expenseRefundNight;
        }

        $saturdayOtHours  = (float)($payslip->saturdays_worked ?? $payslip->saturday_ot_hours ?? 0);
        $saturdayOtRate   = (float)($payslip->saturday_rate ?? $payslip->saturday_ot_rate ?? 0);
        $saturdayOtAmount = (float)($payslip->saturday_amount ?? $payslip->saturday_ot_amount ?? 0);

        // Deductions: Income Tax, SSNIT (0.5%), and Petra (5%)
        $incomeTax      = (float)($payslip->paye ?? 0);
        $ssnit05        = (float)($payslip->ssnit_ee ?? $payslip->ssnitee ?? 0);
        $petra5         = (float)($payslip->petra ?? $payslip->petra_5_percent ?? 0);

        // If ssnit_ee was stored as the full 5.5% and petra is 0, decompose it
        if ($petra5 == 0 && $ssnit05 > 0 && $basicEarned > 0) {
            $expected55 = round($basicEarned * 0.055, 2);
            if (abs($ssnit05 - $expected55) <= 0.05) {
                $petra5 = round($basicEarned * 0.05, 2);
                $ssnit05 = round($basicEarned * 0.005, 2);
            }
        }
        $ssnitEmployee  = $ssnit05 + $petra5;

        // Company Contribution
        $employerSsf    = (float)($payslip->ssf ?? $payslip->employer_ssnit ?? 0);
        $totalSsf135    = (float)($payslip->ssf_13_5 ?? 0);

        // Totals
        $totalEarnings   = (float)($payslip->gross_salary ?: ($basicEarned + $totalExpenseRefund + $saturdayOtAmount));
        $totalDeductions = (float)($payslip->total_deductions ?: ($incomeTax + $ssnitEmployee + (float)($payslip->surcharge_or_student_loan ?? 0)));
        $netPay          = (float)($payslip->take_home_pay ?: ($totalEarnings - $totalDeductions));
        $amountInWords   = jaykayAmountToWords($netPay);

        // Tax Grid Computations
        $otIncome   = $totalExpenseRefund > 0 ? $totalExpenseRefund : (float)($payslip->totalovertime ?? 0);
        $otTax      = (float)($payslip->overtime_tax ?? 0);
        $grossRem   = $basicEarned;
        $taxRelief  = 0.00;
        $firstTier  = $employerSsf;
        $secondTier = $ssnitEmployee;
        $taxableAmt = (float)($payslip->taxable_income ?: max(0, $grossRem - $secondTier));
        $taxExOt    = $incomeTax;
        $taxTotal   = $taxExOt + $otTax;

        // Build structured items for dual-column table
        $earningsList = [];
        $earningsList[] = [
            'label'  => 'Basic',
            'hours'  => number_format($presentDays, 2),
            'rate'   => number_format($dailyRate, 2),
            'amount' => number_format($basicEarned, 2),
        ];

        if ($expenseRefund > 0) {
            $earningsList[] = [
                'label'  => 'Expense Refund',
                'hours'  => '',
                'rate'   => '',
                'amount' => number_format($expenseRefund, 2),
            ];
        }

        if ($expenseRefundPaid > 0) {
            $earningsList[] = [
                'label'  => 'Expense Refund Paid',
                'hours'  => '',
                'rate'   => '',
                'amount' => number_format($expenseRefundPaid, 2),
            ];
        }

        if ($expenseRefundToBePaid > 0) {
            $earningsList[] = [
                'label'  => 'Expense Refund To Be Paid',
                'hours'  => '',
                'rate'   => '',
                'amount' => number_format($expenseRefundToBePaid, 2),
            ];
        }

        if ($expenseRefundNight > 0) {
            $earningsList[] = [
                'label'  => 'Expense Refund (Night)',
                'hours'  => '',
                'rate'   => '',
                'amount' => number_format($expenseRefundNight, 2),
            ];
        }

        if ($totalExpenseRefund > 0) {
            $earningsList[] = [
                'label'  => 'Total Expense Refund',
                'hours'  => '',
                'rate'   => '',
                'amount' => number_format($totalExpenseRefund, 2),
            ];
        }

        if ($saturdayOtAmount > 0) {
            $earningsList[] = [
                'label'  => 'EXP Saturday@1.5',
                'hours'  => (float)$saturdayOtHours > 0 ? number_format($saturdayOtHours, 2) : '',
                'rate'   => (float)$saturdayOtRate > 0 ? number_format($saturdayOtRate, 2) : '',
                'amount' => number_format($saturdayOtAmount, 2),
            ];
        }

        $deductionsList = [];
        if ($incomeTax > 0 || $totalDeductions > 0) {
            $deductionsList[] = [
                'label'  => 'Income Tax',
                'amount' => number_format($incomeTax, 2),
            ];
        }
        $deductionsList[] = [
            'label'  => 'SSNIT (0.5%)',
            'amount' => number_format($ssnit05, 2),
        ];
        $deductionsList[] = [
            'label'  => 'Petra (5%)',
            'amount' => number_format($petra5, 2),
        ];
        if ((float)($payslip->surcharge_or_student_loan ?? 0) > 0) {
            $deductionsList[] = [
                'label'  => 'Other Deduction',
                'amount' => number_format((float)$payslip->surcharge_or_student_loan, 2),
            ];
        }

        // Handled column names to prevent duplication from payroll_columns custom loop
        $handledColumns = [
            'basic_salary', 'basic', 'earned_basic_salary', 'daily_rate', 'legalworkdays', 'legal_work_days',
            'days_worked', 'absentee_days', 'absent_days', 'absent_deductions',
            'overtime_hours', 'overtime_rate', 'overtime_amount', 'night_allowance', 'totalovertime', 'total_overtime_amt', 'total_expense_refund', 'totalexpenserefund', 'expense_refund', 'expense_refund_paid', 'expense_refund_night', 'expense_refund_to_be_paid', 'expenserefund', 'expenserefundpaid', 'expenserefundnight', 'expenserefundtobepaid',
            'saturdays_worked', 'saturday_rate', 'saturday_amount', 'saturday_ot_hours', 'saturday_ot_rate', 'saturday_ot_amount', 'total_days_amount',
            'gross_salary', 'management_fee', 'getfund', 'nhil', 'vat', 'ssf', 'employer_ssnit', 'cost_to_jkay',
            'ssnit_ee', 'ssnitee', 'petra', 'petra_5_percent', 'taxable_income', 'paye', 'overtime_tax',
            'surcharge_or_student_loan', 'total_deductions', 'take_home_pay', 'net_pay',
            'name', 'staff_id', 'staffida', 'staffid', 'position', 'join_date', 'start_date', 'category', 'department', 'region', 'zone', 'ssnit', 'gh_card_number', 'gh_no', 'bank', 'bankname', 'accountnumber', 'account_number', 'branch', 'totalabsent'
        ];

        if (isset($customColumns)) {
            foreach ($customColumns->where('payslip_section', 'earnings') as $cc) {
                if (in_array(strtolower($cc->column_name), $handledColumns)) continue;
                $val = data_get($payslip, $cc->column_name, 0);
                if ((float)$val != 0 || (!is_numeric($val) && !empty($val))) {
                    $earningsList[] = [
                        'label'  => $cc->display_label,
                        'hours'  => '',
                        'rate'   => '',
                        'amount' => is_numeric($val) ? number_format((float)$val, 2) : $val,
                    ];
                }
            }
            foreach ($customColumns->where('payslip_section', 'deductions') as $cc) {
                if (in_array(strtolower($cc->column_name), $handledColumns)) continue;
                $val = data_get($payslip, $cc->column_name, 0);
                if ((float)$val != 0 || (!is_numeric($val) && !empty($val))) {
                    $deductionsList[] = [
                        'label'  => $cc->display_label,
                        'amount' => is_numeric($val) ? number_format((float)$val, 2) : $val,
                    ];
                }
            }
        }

        $numMainRows = max(count($earningsList), count($deductionsList));

        // Slip generation date
        $slipDate = !empty($payslip->slip_date) 
            ? \Carbon\Carbon::parse($payslip->slip_date)->format('d-m-Y') 
            : (!empty($month) ? \Carbon\Carbon::parse($month)->endOfMonth()->format('d-m-Y') : date('d-m-Y'));
    @endphp

    <div class="outer-border">

        <!-- Top Header -->
        <div class="title-header">
            <span>{{ strtoupper($payslip->category ?? $payslip->brandname ?? 'CONTRACTOR-SBP') }}</span>
            <span class="date-right">{{ $slipDate }}</span>
        </div>

        <!-- Subtitle -->
        <div class="salary-slip-title">
            Salary Slip For {{ strtoupper(str_replace(' ', ' - ', $formattedMonth)) }}
        </div>

        <div style="border-top: 1px solid #222; margin-bottom: 8px;"></div>

        <!-- Employee Metadata Grid -->
        <table class="meta-table">
            <tr>
                <!-- Left Info Column -->
                <td style="width: 50%; padding-right: 12px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td class="meta-label">Employee Id:</td>
                            <td class="meta-val">{{ $payslip->staff_id ?? $payslip->staffida ?? $payslip->staffid ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Title:</td>
                            <td class="meta-val">{{ $payslip->title ?? '' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Name:</td>
                            <td class="meta-val" style="font-weight: 500;">{{ strtoupper($payslip->name ?? '-') }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Department:</td>
                            <td class="meta-val">{{ strtoupper($payslip->department ?? 'ADMINISTRATION') }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Position:</td>
                            <td class="meta-val">{{ strtoupper($payslip->position ?? 'LABOUR') }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Dt.Of Joining:</td>
                            <td class="meta-val">{{ $payslip->join_date ?? $payslip->start_date ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Category:</td>
                            <td class="meta-val">{{ $payslip->category ?? 'CONTRACTOR-SBP' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Region:</td>
                            <td class="meta-val">{{ $payslip->region ?? $payslip->zone ?? 'ZONE' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Employee Basic:</td>
                            <td class="meta-val">{{ number_format($basicSalary, 2) }}</td>
                        </tr>
                    </table>
                </td>

                <!-- Right Info Column -->
                <td style="width: 50%; padding-left: 12px;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td class="meta-label">SSF No. :</td>
                            <td class="meta-val">{{ $ssnit ?? $payslip->ssnit ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">GH No:</td>
                            <td class="meta-val">{{ $payslip->gh_card_number ?? $payslip->gh_no ?? $payslip->national ?? $payslip->staff_id ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Bank Name :</td>
                            <td class="meta-val">{{ $displayBankName ?: '' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Branch Name :</td>
                            <td class="meta-val">{{ $displayBranch ?: '' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Bank Acc No. :</td>
                            <td class="meta-val">{{ $displayAccountNo ?: '' }}</td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <table class="days-box">
                                    <tr>
                                        <th>Pay Days</th>
                                        <th>Present Days</th>
                                        <th>Absent Days</th>
                                    </tr>
                                    <tr>
                                        <td>{{ (int)$payDays }}</td>
                                        <td>{{ (int)$presentDays }}</td>
                                        <td>{{ (int)$absentDays }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Main Earnings & Deductions Table -->
        <table class="main-table">
            <thead>
                <tr>
                    <th style="width: 25%; text-align: left;">Earnings</th>
                    <th style="width: 10%; text-align: right;">Hours/Days</th>
                    <th style="width: 8%; text-align: right;">Rate</th>
                    <th class="col-divider" style="width: 9%; text-align: right;">Amount</th>
                    <th style="width: 36%; text-align: left;">Deductions</th>
                    <th style="width: 12%; text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @for($i = 0; $i < $numMainRows; $i++)
                    @php
                        $er = $earningsList[$i] ?? null;
                        $dr = $deductionsList[$i] ?? null;
                    @endphp
                    <tr>
                        <td>{{ $er['label'] ?? '' }}</td>
                        <td style="text-align: right;">{{ $er['hours'] ?? '' }}</td>
                        <td style="text-align: right;">{{ $er['rate'] ?? '' }}</td>
                        <td class="col-divider amount-blue">{{ $er['amount'] ?? '' }}</td>
                        <td>{{ $dr['label'] ?? '' }}</td>
                        <td class="amount-blue">{{ $dr['amount'] ?? '' }}</td>
                    </tr>
                @endfor

                <!-- Empty filler rows for visual spacing -->
                @php
                    $blankRowsNeeded = max(2, 6 - $numMainRows);
                @endphp
                @for($b = 0; $b < $blankRowsNeeded; $b++)
                <tr>
                    <td style="height: 14px;"></td>
                    <td></td>
                    <td></td>
                    <td class="col-divider"></td>
                    <td></td>
                    <td></td>
                </tr>
                @endfor

                <!-- Company Contribution Section (under Deductions) -->
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="col-divider"></td>
                    <td colspan="2" style="text-align: center; font-weight: bold; padding-top: 10px; padding-bottom: 4px;">
                        Company Contribution
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="col-divider"></td>
                    <td>Employer SSF 13%</td>
                    <td style="text-align: right; font-weight: normal; color: #111;">{{ number_format($employerSsf, 2) }}</td>
                </tr>
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td class="col-divider"></td>
                    <td>Total SSF1 13.5%</td>
                    <td style="text-align: right; font-weight: normal; color: #111;">{{ $totalSsf135 > 0 ? number_format($totalSsf135, 2) : '' }}</td>
                </tr>

                <!-- Extra spacer before totals -->
                <tr>
                    <td style="height: 12px;"></td>
                    <td></td>
                    <td></td>
                    <td class="col-divider"></td>
                    <td></td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        <!-- Totals Table -->
        <table class="totals-table">
            <tr class="totals-border-bottom">
                <td style="width: 25%; font-weight: bold;">Total Earnings :</td>
                <td class="col-divider amount-blue-bold" style="width: 27%;">{{ number_format($totalEarnings, 2) }}</td>
                <td style="width: 36%; font-weight: bold;">Total Deductions:</td>
                <td class="amount-blue-bold" style="width: 12%;">{{ number_format($totalDeductions, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2" style="vertical-align: middle;">
                    <span style="font-weight: bold;">In Word :</span>&nbsp;&nbsp;<span>{{ $amountInWords }}</span>
                </td>
                <td style="font-weight: bold; text-align: left; vertical-align: middle;">Net Amount :</td>
                <td class="amount-blue-bold" style="font-size: 11.5px; vertical-align: middle;">{{ number_format($netPay, 2) }}</td>
            </tr>
        </table>

        <div style="border-top: 1px solid #222; margin-top: 4px; margin-bottom: 6px;"></div>

        <!-- Year to Date & Tax Breakdown Box -->
        <div class="ytd-box">
            <div class="ytd-header">
                YEAR TO DATE FIGURE
            </div>
            <div class="ytd-subheader">
                <span style="margin-left: 20px;">PAYE</span>
                <span style="margin-left: 60px;">EMPLOYEE SSF</span>
            </div>
            <table class="tax-grid">
                <thead>
                    <tr>
                        <th style="width: 11%;">OT Income</th>
                        <th style="width: 10%;">OT Tax</th>
                        <th style="width: 12%;">GrossRem**</th>
                        <th style="width: 11%;">Tax Relief</th>
                        <th style="width: 11%;">1st Tier</th>
                        <th style="width: 11%;">2nd Tier</th>
                        <th style="width: 12%;">Taxable Amt</th>
                        <th style="width: 11%;">Tax ExOT</th>
                        <th style="width: 11%;">Tax Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="height: 22px;">
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

    @if(!$loop->last)
        <div class="page-break"></div>
    @endif
@endforeach

</body>
</html>
