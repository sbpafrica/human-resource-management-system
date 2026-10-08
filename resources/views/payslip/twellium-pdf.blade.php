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

<table>
    <tr>
        <td colspan="2"><img src="{{ public_path('images/sbpafricalogo.png') }}" class="logo" alt="SBP Africa Logo"></td>
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
        <td>{{ number_format($payslip->basic ?? 0, 2) }}</td>
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
        <td style="text-align: center;">{{ number_format($payslip->basic_earned ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Weekday Overtime</td>
        <td style="text-align: right;">{{ $payslip->weekday_overtime_hours ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format($payslip->weekday_overtime_amount ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Offday/Holiday Overtime</td>
        <td style="text-align: right;">{{ $payslip->offday_holiday_overtime_hours ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format($payslip->offday_holiday_overtime_total ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Special Day Overtime</td>
        <td style="text-align: right;">{{ $payslip->special_day_overtime_hours ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format($payslip->special_day_overtime_amount ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Allowance</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->allowance ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Risk Allowance</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->risk_allowance ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2" class="total">Gross Salary</td>
        <td></td>
        <td style="text-align: center;" class="total">
            {{ number_format(
                ($payslip->basic_earned ?? 0) +
                ($payslip->weekday_overtime_amount ?? 0) +
                ($payslip->offday_holiday_overtime_total ?? 0) +
                ($payslip->special_day_overtime_amount ?? 0) +
                ($payslip->allowance ?? 0) +
                ($payslip->risk_allowance ?? 0), 2) }}
        </td>
        <td></td>
    </tr>

    <!-- Deductions -->
    <tr>
        <td colspan="2">SSNIT</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->ssnit_ee ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Normal Tax-Regular</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->paye ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Overtime Tax</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->overtime_tax ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Other Deduction</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->deduction ?? 0, 2) }}</td>
    </tr>

    <tr class="blue-bg">
        <td colspan="2" class="total">Total</td>
        <td></td>
        <td style="text-align: center;" class="total">
            {{ number_format(
                ($payslip->basic_earned ?? 0) +
                ($payslip->weekday_overtime_amount ?? 0) +
                ($payslip->offday_holiday_overtime_total ?? 0) +
                ($payslip->special_day_overtime_amount ?? 0) +
                ($payslip->allowance ?? 0) +
                ($payslip->risk_allowance ?? 0), 2) }}
        </td>
        <td style="text-align: center;" class="total">{{ number_format($payslip->total_deductions ?? 0, 2) }}</td>
    </tr>

    <!-- Bank & Net Pay -->
    <tr>
        <td class="header">Bank Name</td>
        <td>{{ $bank['bankname'] }}</td>
        <td></td>
        <td colspan="2" class="net-pay" style="text-align: center; background-color: #343434;">NET PAY</td>
    </tr>
    <tr>
        <td class="header">Bank Account Name</td>
        <td>{{ $bank['name'] }}</td>
        <td></td>
        <td colspan="2" style="text-align: center;" class="net-pay">
            {{ number_format($payslip->netpay ?? 0, 2) }}
        </td>
    </tr>
    <tr>
        <td class="header">Bank Account #</td>
        <td>{{ $bank['alcnum'] }}</td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td class="header">Bank Branch</td>
        <td>{{ $bank['branch'] }}</td>
        <td colspan="3"></td>
    </tr>
</table>

</body>
</html>
