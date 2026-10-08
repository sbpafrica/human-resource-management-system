<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PZ Cussons Payslip - {{ $formattedMonth }}</title>
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

<table>
    <tr>
        <td colspan="2">
            <img src="{{ public_path('images/sbpafricalogo.png') }}" class="logo" alt="SBP Africa Logo">
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
        <td>{{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? '-' }}</td>
        <td></td>
        <td class="header">Department</td>
        <td>{{ $payslip->dept ?? '-' }}</td>
    </tr>
    <tr>
        <td class="header">Employee ID</td>
        <td>{{ $payslip->staffid ?? $payslip->staffida ?? $payslip->staff_id ?? $payslip->id ?? $payslip->employ ?? '-' }}</td>
        <td></td>
        <td class="header">Client Company</td>
        <td>PZ CUSSONS</td>
    </tr>
    <tr>
        <td class="header">SSNIT #</td>
        <td>{{ $ssnit ?? $payslip->ssnit ?? '-' }}</td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <th colspan="2" class="header" style="text-align: center;">Item</th>
        <th class="header" style="text-align: center;">Description/Days</th>
        <th class="header" style="text-align: center;">Earnings</th>
        <th class="header" style="text-align: center;">Deductions</th>
    </tr>
    <tr>
        <td colspan="2">Basic Salary</td>
        <td class="text-center">-</td>
        <td class="text-center">{{ number_format($payslip->basic ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Incentives</td>
        <td class="text-right"></td>
        <td class="text-center">{{ number_format($payslip->incentive ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Bonus/Transport</td>
        <td class="text-right"></td>
        <td class="text-center">{{ number_format($payslip->bonus ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Overtime / Incentives</td>
        <td class="text-right"></td>
        <td class="text-center">{{ number_format($payslip->overtime ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Other</td>
        <td class="text-right"></td>
        <td class="text-center">{{ number_format($payslip->other ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2" class="total">Gross Salary</td>
        <td></td>
        <td class="text-center total">{{ number_format($payslip->totalearn ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Annual Bonus</td>
        <td></td>
        <td class="text-center">{{ number_format(data_get($payslip, '13month', 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">SSNIT</td>
        <td></td>
        <td></td>
        <td class="text-center">{{ number_format($payslip->ssnitee ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Annual Bonus Tax</td>
        <td></td>
        <td></td>
        <td class="text-center">{{ number_format(data_get($payslip, '13tax', 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Normal Tax-Regular</td>
        <td></td>
        <td></td>
        <td class="text-center">{{ number_format($payslip->totaltaxdeduct ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2" class="total">Total</td>
        <td></td>
        <td class="text-center total">{{ number_format(($payslip->totalearn ?? 0) + data_get($payslip, '13month', 0), 2) }}</td>
        <td class="text-center total">{{ number_format($payslip->totaldeduct ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td class="header">Bank Name</td>
        <td>{{ data_get($bank, 'bankname', '-') }}</td>
        <td></td>
        <td class="header">Account Name</td>
        <td>{{ data_get($bank, 'name', '-') }}</td>
    </tr>
    <tr>
        <td class="header">Bank Account #</td>
        <td>{{ data_get($bank, 'alcnum', '-') }}</td>
        <td></td>
        <td class="header">Branch</td>
        <td>{{ data_get($bank, 'branch', '-') }}</td>
    </tr>
    <tr>
        <td colspan="3"></td>
        <td class="net-pay" style="text-align: center;">NET PAY</td>
        <td class="net-pay" style="text-align: center;">{{ number_format($payslip->netpay ?? 0, 2) }}</td>
    </tr>
</table>

</body>
</html>
