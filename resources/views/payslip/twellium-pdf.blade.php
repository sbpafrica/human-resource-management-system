<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Twellium Payslip - {{ $formattedMonth }}</title>
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
            font-size: 16px;
            color: white;
            background-color: #e74c3c;
        }
        .logo {
            width: 250px;
            height: 80px;
        }
        .blue-bg {
            background-color: #3498db !important;
            color: white;
        }
        .bold1 {
            font-weight: bold;
            font-size: 13px;
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
    <tr>
        <td colspan="2">
            @if($logoData)
                <img src="{{ $logoData }}" class="logo" alt="SBP Africa Logo">
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
    <tr>
        <td class="header">Name</td>
        <td>{{ $payslip->name ?? '-' }}</td>
        <td></td>
        <td class="header">Title</td>
        <td>{{ $payslip->position ?? '-' }}</td>
    </tr>
    <tr>
        <td class="header">Employee ID</td>
        <td>{{ $payslip->employ ?? $payslip->staffid ?? $payslip->staffida ?? $payslip->staff_id ?? $payslip->id ?? '-' }}</td>
        <td></td>
        <td class="header">Client Company</td>
        <td>TWELLIUM</td>
    </tr>
    <tr>
        <td class="header">SSNIT #</td>
        <td>{{ $ssnit ?? $payslip->ssnit ?? '-' }}</td>
        <td></td>
        <td class="header">Department</td>
        <td>{{ $payslip->department ?? '-' }}</td>
    </tr>
    <tr>
        <td class="header">Basic Salary</td>
        <td>{{ number_format((float)($payslip->basic ?? 0), 2) }}</td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <th colspan="2" class="header" style="text-align: center;">Item</th>
        <th class="header" style="text-align: center;">Description/Days/Hours</th>
        <th class="header" style="text-align: center;">Earnings</th>
        <th class="header" style="text-align: center;">Deductions</th>
    </tr>

    <!-- Earnings -->
    <tr>
        <td colspan="2">Basic Earned</td>
        <td style="text-align: right;">{{ $payslip->total_hours_worked ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->basic_earned ?? 0), 2) }}</td>
        <td></td>
    </tr>
    @php
        $weekdayAmt = (float)($payslip->weekday_overtime_amount ?? $payslip->weekday_overtime_amt ?? $payslip->normalovertime ?? 0);
        $weekdayHrs = $payslip->weekday_overtime_hours ?? $payslip->weekday_hours ?? $payslip->weekdaysOT ?? 0;
        $offdayAmt = (float)($payslip->offday_holiday_overtime_total ?? $payslip->saturday_overtime_amt ?? $payslip->saturdayovertime ?? 0);
        $offdayHrs = $payslip->offday_holiday_overtime_hours ?? $payslip->saturday_hours ?? $payslip->weekendOT ?? 0;
        $specialAmt = (float)($payslip->special_day_overtime_amount ?? $payslip->sunday_holiday_overtime_amt ?? $payslip->holidayotamt ?? 0);
        $specialHrs = $payslip->special_day_overtime_hours ?? $payslip->num_sundays_holidays ?? $payslip->holiday ?? 0;
        $totalOvertime = (float)($payslip->total_overtime_amount ?? $payslip->total_overtime_amt ?? $payslip->overtime ?? ($weekdayAmt + $offdayAmt + $specialAmt));
        $hasBreakdown = ($weekdayAmt > 0 || $offdayAmt > 0 || $specialAmt > 0 || (float)$weekdayHrs > 0 || (float)$offdayHrs > 0 || (float)$specialHrs > 0);
        $effectiveOvertimeForTotal = $hasBreakdown ? ($weekdayAmt + $offdayAmt + $specialAmt) : $totalOvertime;
    @endphp

    @if($hasBreakdown || $totalOvertime <= 0)
    <tr>
        <td colspan="2">Weekday Overtime</td>
        <td style="text-align: right;">{{ $weekdayHrs }}</td>
        <td style="text-align: center;">{{ number_format($weekdayAmt, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Offday/Holiday Overtime</td>
        <td style="text-align: right;">{{ $offdayHrs }}</td>
        <td style="text-align: center;">{{ number_format($offdayAmt, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Special Day Overtime</td>
        <td style="text-align: right;">{{ $specialHrs }}</td>
        <td style="text-align: center;">{{ number_format($specialAmt, 2) }}</td>
        <td></td>
    </tr>
    @else
    <tr>
        <td colspan="2">Total Overtime</td>
        <td style="text-align: right;">{{ $payslip->overtime_hours ?? $payslip->total_hours_worked ?? '-' }}</td>
        <td style="text-align: center;">{{ number_format($totalOvertime, 2) }}</td>
        <td></td>
    </tr>
    @endif
    <tr>
        <td colspan="2">Allowance</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->allowance ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Risk Allowance</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->risk_allowance ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2" class="total">Gross Salary</td>
        <td></td>
        <td style="text-align: center;" class="total">
            {{ number_format(
                (float)($payslip->basic_earned ?? 0) +
                $effectiveOvertimeForTotal +
                (float)($payslip->allowance ?? 0) +
                (float)($payslip->risk_allowance ?? 0), 2) }}
        </td>
        <td></td>
    </tr>

    <!-- Deductions -->
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
        <td colspan="2">Normal Tax-Regular</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->paye ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Overtime Tax</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->overtime_tax ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Other Deduction</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->deduction ?? 0), 2) }}</td>
    </tr>

    <tr class="blue-bg">
        <td colspan="2" class="total">Total</td>
        <td></td>
        <td style="text-align: center;" class="total">
            {{ number_format(
                (float)($payslip->basic_earned ?? 0) +
                $effectiveOvertimeForTotal +
                (float)($payslip->allowance ?? 0) +
                (float)($payslip->risk_allowance ?? 0), 2) }}
        </td>
        <td style="text-align: center;" class="total">{{ number_format((float)($payslip->total_deductions ?? 0), 2) }}</td>
    </tr>

    <!-- Bank & Net Pay -->
    <tr>
        <td class="header">Bank Name</td>
        <td>{{ $displayBankName }}</td>
        <td></td>
        <td colspan="2" class="net-pay" style="text-align: center; background-color: #343434;">NET PAY</td>
    </tr>
    <tr>
        <td class="header">Bank Account Name</td>
        <td>{{ $displayAccountName }}</td>
        <td></td>
        <td colspan="2" style="text-align: center;" class="net-pay">
            {{ number_format((float)($payslip->netpay ?? 0), 2) }}
        </td>
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
