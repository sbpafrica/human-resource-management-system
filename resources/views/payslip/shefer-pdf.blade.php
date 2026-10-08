<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shefer Payslip - {{ $formattedMonth }}</title>
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
            padding: 5px;
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

<table>
    <tr>
        <td colspan="2"><img src="{{ public_path('images/sbpafricalogo.png') }}" class="logo" alt="Company Logo"></td>
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
        <td>{{ $payslip->name1 ?? '-' }}</td>
        <td></td>
        <td class="header">Title</td>
        <td>{{ $payslip->position ?? '-' }}</td>
    </tr>
    <tr>
        <td class="header">Employee ID</td>
        <td>{{ $payslip->staff_id ?? $payslip->staffid ?? $payslip->staffida ?? $payslip->id ?? $payslip->employ ?? '-' }}</td>
        <td></td>
        <td class="header">Client Company</td>
        <td>SHEFER</td>
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
        <td colspan="2">Basic Entitled</td>
        <td style="text-align: right;">{{ $payslip->no_of_days_worked ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format($payslip->basic_earned ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Absent Days</td>
        <td style="text-align: right;">{{ $payslip->absent_days ?? 0 }}</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->absent_without_pay ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Weekday Overtime</td>
        <td style="text-align: right;">{{ $payslip->weekdayOT_hours ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format($payslip->weekdayOT_amount ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Saturday Overtime</td>
        <td style="text-align: right;">{{ $payslip->weekendOT_hours ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format($payslip->weekendOT_amount ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Sunday Overtime</td>
        <td style="text-align: right;">{{ $payslip->sunOT_hours ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format($payslip->sunOT_amount ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Fixed Overtime</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->fixedot ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Food Allowance</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->food ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Airtime Allowance</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->airtime ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Medical Allowance</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->medical ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Responsibility Allowance</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->responseallo ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Transport Allowance</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->transport ?? 0, 2) }}</td>
        <td></td>
    </tr>

    <!-- Gross Salary -->
    <tr>
        <td colspan="2" class="total">Gross Salary</td>
        <td></td>
        <td style="text-align: center;" class="total">{{ number_format($payslip->gross ?? 0, 2) }}</td>
        <td></td>
    </tr>

    <!-- Deductions -->
    <tr>
        <td colspan="2">SSNIT</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->ssnit_5_5_percent ?? 0, 2) }}</td>
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
        <td style="text-align: center;">{{ number_format($payslip->ot_tax ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Salary Loan</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->salaryloan ?? 0, 2) }}</td>
    </tr>

    <!-- Total -->
    <tr>
        <td colspan="2" class="total">Total</td>
        <td></td>
        <td style="text-align: center;" class="total">{{ number_format($payslip->gross ?? 0, 2) }}</td>
        <td style="text-align: center;" class="total">{{ number_format($payslip->total_deductions ?? 0, 2) }}</td>
    </tr>

    <!-- Bank & Net Pay -->
    <tr>
        <td class="header">Bank Name</td>
        <td>{{ $bank['bankname'] }}</td>
        <td></td>
        <td colspan="2" class="net-pay" style="text-align: center;">NET PAY</td>
    </tr>
    <tr>
        <td class="header">Bank Account Name</td>
        <td>{{ $bank['name'] }}</td>
        <td></td>
        <td colspan="2" style="text-align: center;" class="net-pay">
            {{ number_format($payslip->net_pay ?? 0, 2) }}
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
