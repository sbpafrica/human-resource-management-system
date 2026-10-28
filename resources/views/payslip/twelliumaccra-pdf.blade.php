<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Twellium Accra Payslip - {{ $formattedMonth }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        .header {
            font-weight: bold;
        }
        .total {
            font-weight: bold;
        }
        .net-pay {
            font-weight: bold;
        }
        .logo {
            width: 250px;
            height: 80px;
        }
    </style>
</head>
<body>
@php
    $displayBankName = (!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') 
        ? data_get($bank, 'bankname') 
        : ($payslip->bank ?? $payslip->bankname ?? '-');
    $displayAccountName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') 
        ? data_get($bank, 'name') 
        : ($payslip->name ?? $payslip->names ?? $payslip->employeename ?? '-');
    $displayAccountNo = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') 
        ? data_get($bank, 'alcnum') 
        : ($payslip->accountnumber ?? $payslip->account_number ?? $payslip->alcnum ?? '-');
    $displayBranch = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') 
        ? data_get($bank, 'branch') 
        : ($payslip->branch ?? '-');

    $logoData = $logoData ?? (file_exists(public_path('images/sbpafricalogo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/sbpafricalogo.png'))) : null);
@endphp
<table>
    <!-- Header: Logo, Salary Slip, Confidential -->
    <tr>
        <td colspan="2">
            @if($logoData)
                <img src="{{ $logoData }}" class="logo" alt="Company Logo">
            @else
                <h3 style="color: #0359c9; margin: 0;">SBP AFRICA</h3>
            @endif
        </td>
        <td colspan="1" style="text-align: center; color:#bc4904"><h2>SALARY SLIP</h2></td>
        <td colspan="2" style="text-align: center; color:rgb(3, 89, 201)"><h2>CONFIDENTIAL</h2></td>
    </tr>
    <tr>
        <td colspan="2"></td>
        <td style="text-align: center; font-weight: bold;">{{ $formattedMonth }}</td>
        <td colspan="2"></td>
    </tr>

    <!-- Employee Info -->
    <tr>
        <td class="header">Name</td>
        <td>{{ $payslip->name ?? '-' }}</td>
        <td></td>
        <td class="header">Title</td>
        <td>{{ $payslip->position ?? '-' }}</td>
    </tr>
    <tr>
        <td class="header">Employee ID</td>
        <td>{{ $payslip->staff_id ?? $payslip->staffid ?? $payslip->staffida ?? $payslip->id ?? $payslip->employ ?? '-' }}</td>
        <td></td>
        <td class="header">Client Company</td>
        <td>TWELLIUM</td>
    </tr>
    <tr>
        <td class="header">Basic Salary</td>
        <td>{{ number_format((float)($payslip->basic_salary ?? 0), 2) }}</td>
        <td></td>
        <td class="header">Legal Work Days</td>
        <td>{{ (float)($payslip->legal_work_days ?? 0) > 0 ? (int)$payslip->legal_work_days : '-' }}</td>
    </tr>

    <!-- Table Header -->
    <tr>
        <th colspan="2" class="header" style="text-align: center;">Item</th>
        <th class="header" style="text-align: center;">Description/Days/Hours</th>
        <th class="header" style="text-align: center;">Earnings</th>
        <th class="header" style="text-align: center;">Deductions</th>
    </tr>

    <!-- Earnings & Deductions Rows -->
    <tr>
        <td colspan="2">Basic Earned</td>
        <td style="text-align: right;">{{ !empty($payslip->description) ? $payslip->description : ((float)($payslip->days_worked ?? ($payslip->workdays ?? 0)) > 0 ? ($payslip->days_worked ?? $payslip->workdays) : '') }}</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->basic_earned ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Absent Deduction</td>
        <td style="text-align: right;">{{ (float)($payslip->absent_days ?? 0) > 0 ? $payslip->absent_days : (!empty($payslip->deduction_description) ? $payslip->deduction_description : '') }}</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->absent_deduction ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2" class="total">Gross Salary</td>
        <td></td>
        <td style="text-align: center;" class="total">{{ number_format((float)($payslip->gross_salary ?? $payslip->basic_earned ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">SSNIT</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->ssnit_ee ?? $payslip->ssnitee ?? 0), 2) }}</td>
    </tr>
    @if((float)($payslip->petra ?? $payslip->petra_5_percent ?? 0) > 0)
    <tr>
        <td colspan="2">Petra (5%)</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->petra ?? $payslip->petra_5_percent ?? 0), 2) }}</td>
    </tr>
    @endif
    <tr>
        <td colspan="2">Loan</td>
        <td style="text-align: right;">{{ !empty($payslip->loan_description) ? $payslip->loan_description : '' }}</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->deductions ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Normal Tax-Regular</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->paye ?? 0), 2) }}</td>
    </tr>
    @php
        $weekdayAmt = (float)($payslip->weekday_overtime_amt ?? $payslip->weekdays_overtime ?? $payslip->normalovertime ?? 0);
        $weekdayHrs = $payslip->weekday_hours ?? $payslip->weekdaysOT ?? 0;
        $saturdayAmt = (float)($payslip->saturday_overtime_amt ?? $payslip->saturdays_overtime ?? $payslip->saturdayovertime ?? 0);
        $saturdayHrs = $payslip->saturday_hours ?? $payslip->num_saturdays ?? $payslip->weekendOT ?? $payslip->sat ?? 0;
        $sundayAmt = (float)($payslip->sunday_holiday_overtime_amt ?? $payslip->sundays_holidays_overtime ?? $payslip->holidayotamt ?? 0);
        $sundayHrs = $payslip->num_sundays_holidays ?? $payslip->sundays_holiday_overtime ?? $payslip->holiday ?? 0;
        $otRefund = (float)($payslip->overtimerefund ?? $payslip->overtime_refund ?? 0);
        $totalOvertime = (float)($payslip->total_overtime_amt ?? $payslip->total_overtime ?? $payslip->overtime ?? ($weekdayAmt + $saturdayAmt + $sundayAmt));
        $hasDetailedOt = ($weekdayAmt > 0 || $saturdayAmt > 0 || $sundayAmt > 0 || (float)$weekdayHrs > 0 || (float)$saturdayHrs > 0 || (float)$sundayHrs > 0 || $otRefund > 0);
    @endphp

    @if($hasDetailedOt || $totalOvertime <= 0)
    <tr>
        <td colspan="2">Weekday Overtime</td>
        <td style="text-align: right;">{{ (float)($weekdayHrs ?? 0) > 0 ? $weekdayHrs : (!empty($payslip->weekday_ot_description) ? $payslip->weekday_ot_description : '') }}</td>
        <td style="text-align: center;">{{ number_format($weekdayAmt, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Saturday Overtime</td>
        <td style="text-align: right;">{{ (float)($saturdayHrs ?? 0) > 0 ? $saturdayHrs : (!empty($payslip->saturday_ot_description) ? $payslip->saturday_ot_description : '') }}</td>
        <td style="text-align: center;">{{ number_format($saturdayAmt, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Sunday/Holiday Overtime</td>
        <td style="text-align: right;">{{ (float)($sundayHrs ?? 0) > 0 ? $sundayHrs : (!empty($payslip->sunday_ot_description) ? $payslip->sunday_ot_description : '') }}</td>
        <td style="text-align: center;">{{ number_format($sundayAmt, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Overtime Refund</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($otRefund, 2) }}</td>
        <td></td>
    </tr>
    @else
    <tr>
        <td colspan="2">Total Overtime</td>
        <td style="text-align: right;">{{ (float)($payslip->overtime_hours ?? ($payslip->total_hours_worked ?? 0)) > 0 ? ($payslip->overtime_hours ?? $payslip->total_hours_worked) : (!empty($payslip->overtime_description) ? $payslip->overtime_description : '') }}</td>
        <td style="text-align: center;">{{ number_format($totalOvertime, 2) }}</td>
        <td></td>
    </tr>
    @if($otRefund > 0)
    <tr>
        <td colspan="2">Overtime Refund</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($otRefund, 2) }}</td>
        <td></td>
    </tr>
    @endif
    @endif

    <!-- Total Row -->
    <tr>
        <td colspan="2" class="total">Total</td>
        <td></td>
        <td style="text-align: center;" class="total">
            {{ number_format((float)($payslip->basic_earned ?? 0) + $totalOvertime, 2) }}
        </td>
        <td style="text-align: center;" class="total">
            {{ number_format((float)($payslip->total_deductions ?? 0), 2) }}
        </td>
    </tr>

    <!-- Refund (after Total, in Earnings column) -->
    <tr>
        <td colspan="2">Refund</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->refund ?? 0), 2) }}</td>
        <td></td>
    </tr>

    <!-- Net Pay Section -->
    <tr>
        <td colspan="3"></td>
        <td colspan="2" class="net-pay" style="text-align: center;">NET PAY</td>
    </tr>
    <tr>
        <td colspan="3"></td>
        <td colspan="2" style="text-align: center;" class="net-pay">
            {{ number_format((float)($payslip->take_home_pay ?? 0), 2) }}
        </td>
    </tr>

    <!-- Bank Details -->
    <tr>
        <td class="header">Bank Name</td>
        <td>{{ $displayBankName }}</td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td class="header">Bank Account Name</td>
        <td>{{ $displayAccountName }}</td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td class="header">Bank Account #</td>
        <td>{{ $displayAccountNo }}</td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td class="header">Bank Branch</td>
        <td>{{ $displayBranch }}</td>
        <td colspan="3"></td>
    </tr>
</table>

</body>
</html>
