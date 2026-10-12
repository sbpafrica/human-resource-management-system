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

    $logoPath = public_path('images/sbpafricalogo.png');
    $logoData = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
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
        <td colspan="3"></td>
    </tr>

    <!-- Table Header -->
    <tr>
        <th colspan="2" class="header" style="text-align: center;">Item</th>
        <th class="header" style="text-align: center;">Description/Days</th>
        <th class="header" style="text-align: center;">Earnings</th>
        <th class="header" style="text-align: center;">Deductions</th>
    </tr>

    <!-- Earnings & Deductions Rows -->
    <tr>
        <td colspan="2">Basic Earned</td>
        <td style="text-align: right;">{{ $payslip->days_worked ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->basic_earned ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Absent Deduction</td>
        <td style="text-align: right;">{{ $payslip->absent_days ?? 0 }}</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->absent_deduction ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2" class="total">Gross Salary</td>
        <td></td>
        <td style="text-align: center;" class="total">{{ number_format((float)($payslip->basic_earned ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">SSNIT</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->ssnit_ee ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Loan</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->deductions ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Normal Tax-Regular</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->paye ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Weekday Overtime</td>
        <td style="text-align: right;">{{ $payslip->weekday_hours ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->weekday_overtime_amt ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Saturday Overtime</td>
        <td style="text-align: right;">{{ $payslip->saturday_hours ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->saturday_overtime_amt ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Sunday/Holiday Overtime</td>
        <td style="text-align: right;">{{ $payslip->num_sundays_holidays ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->sunday_holiday_overtime_amt ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Overtime Refund</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->overtimerefund ?? 0), 2) }}</td>
        <td></td>
    </tr>

    <!-- Total Row -->
    <tr>
        <td colspan="2" class="total">Total</td>
        <td></td>
        <td style="text-align: center;" class="total">
            {{ number_format((float)($payslip->basic_earned ?? 0) + (float)($payslip->total_overtime_amt ?? 0), 2) }}
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
