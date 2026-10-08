<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Inhouse Payslip - {{ $formattedMonth }}</title>
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
        <td>{{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? '-' }}</td>
        <td></td>
        <td class="header">Title</td>
        <td>{{ $payslip->position ?? '-' }}</td>
    </tr>
    <tr>
        <td class="header">Employee ID</td>
        <td>{{ $payslip->id ?? $payslip->staffid ?? $payslip->staffida ?? $payslip->staff_id ?? $payslip->employ ?? '-' }}</td>
        <td></td>
        <td class="header">Client Company</td>
        <td>SBP AFRICA LTD</td>
    </tr>
    <tr>
        <td class="header">SSNIT #</td>
        <td>{{ $payslip->ssnit ?? '-' }}</td>
        <td></td>
        <td class="header">Department</td>
        <td>-</td>
    </tr>
    <tr>
        <th colspan="2" class="header" style="text-align: center;">Item</th>
        <th class="header" style="text-align: center;">Description/Days</th>
        <th class="header" style="text-align: center;">Earnings</th>
        <th class="header" style="text-align: center;">Deductions</th>
    </tr>

    <!-- Earnings -->
    <tr>
        <td colspan="2">Basic Salary</td>
        <td style="text-align: right;">{{ $payslip->actualwork ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format($payslip->actualbasic ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Monthly KPI</td>
        <td style="text-align: right;">{{ $payslip->coefficient ?? 0 }}</td>
        <td style="text-align: center;">{{ number_format($payslip->monthlykpi ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Overtime</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->overtime ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Transport Subsidy</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->transport ?? 0, 2) }}</td>
        <td></td>
    </tr>

    <!-- Gross Salary -->
    <tr>
        <td colspan="2" class="total">Gross Salary</td>
        <td></td>
        <td style="text-align: center;" class="total">
            {{ number_format(($payslip->actualbasic ?? 0) + ($payslip->monthlykpi ?? 0) + ($payslip->overtime ?? 0) + ($payslip->transport ?? 0), 2) }}
        </td>
        <td></td>
    </tr>

    <!-- Deductions -->
    <tr>
        <td colspan="2">SSNIT</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->ssnitpay ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">PAYE</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->totaltax ?? 0, 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Salary Loan</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->loans ?? 0, 2) }}</td>
    </tr>

    <!-- Annual Bonus (Earnings) -->
    <tr>
        <td colspan="2">Annual Bonus</td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->endyearmonth ?? 0, 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Annual Tax</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format($payslip->endyeartax ?? 0, 2) }}</td>
    </tr>

    <!-- Total -->
    <tr>
        <td colspan="3" class="total">Total</td>
        <td style="text-align: center;" class="total">
            {{ number_format(($payslip->actualbasic ?? 0) + ($payslip->monthlykpi ?? 0) + ($payslip->overtime ?? 0) + ($payslip->transport ?? 0) + ($payslip->endyearmonth ?? 0), 2) }}
        </td>
        <td style="text-align: center;" class="total">
            {{ number_format($payslip->totaldeduction ?? 0, 2) }}
        </td>
    </tr>

    <!-- Bank & Net Pay -->
    <tr>
        <td>Bank Name</td>
        <td>{{ $bank['bankname'] }}</td>
        <td></td>
        <td colspan="2" class="net-pay" style="text-align: center;">NET PAY</td>
    </tr>
    <tr>
        <td>Bank Account Name</td>
        <td>{{ $bank['name'] }}</td>
        <td></td>
        <td colspan="2" style="text-align: center;" class="net-pay">
            {{ number_format($payslip->netpay ?? 0, 2) }}
        </td>
    </tr>
    <tr>
        <td>Bank Account #</td>
        <td>{{ $bank['alcnum'] }}</td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td>Bank Branch</td>
        <td>{{ $bank['branch'] }}</td>
        <td colspan="3"></td>
    </tr>
</table>

</body>
</html>
