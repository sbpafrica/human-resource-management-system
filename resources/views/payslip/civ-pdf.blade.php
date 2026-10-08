<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CIV Bunge Payslip - {{ $formattedMonth }}</title>
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
            text-align: center;
        }

        .logo {
            width: 180px;
            height: auto;
        }
    </style>
</head>
<body>

<table>
    <!-- Header -->
    <tr>
        <td colspan="2">
            <img src="{{ public_path('images/sbpafricalogo.png') }}" class="logo" alt="Company Logo">
        </td>
        <td colspan="1" style="text-align: center;">
            <h2>SALARY SLIP</h2>
        </td>
        <td colspan="2" style="text-align: center;">
            <h2>CONFIDENTIAL</h2>
        </td>
    </tr>

    <!-- Month -->
    <tr>
        <td colspan="2"></td>
        <td style="text-align: center; font-weight: bold;">
            {{ $formattedMonth }}
        </td>
        <td colspan="2"></td>
    </tr>

    <!-- Employee Information -->
    <tr>
        <td class="header">Name</td>
        <td>{{ data_get($payslip, 'fullname', '-') }}</td>
        <td></td>
        <td class="header">Title</td>
        <td>{{ data_get($payslip, 'jobtitle', '-') }}</td>
    </tr>

    <tr>
        <td class="header">Employee ID</td>
        <td>{{ data_get($payslip, 'id', '-') }}</td>
        <td></td>
        <td class="header">Client Company</td>
        <td>{{ data_get($payslip, '1stlevel', 'CIV Bunge') }}</td>
    </tr>

    <tr>
        <td class="header">SSNIT #</td>
        <td>{{ $ssnit ?? '-' }}</td>
        <td></td>
        <td class="header">Department</td>
        <td>{{ data_get($payslip, '2ndlevel', data_get($payslip, 'city', '-')) }}</td>
    </tr>

    <!-- Table Header -->
    <tr>
        <th colspan="2" class="header" style="text-align: center;">Item</th>
        <th class="header" style="text-align: center;">Description / Days / Hours</th>
        <th class="header" style="text-align: center;">Earnings</th>
        <th class="header" style="text-align: center;">Deductions</th>
    </tr>

    <!-- Earnings -->
    <tr>
        <td colspan="2">Basic Salary</td>
        <td style="text-align: right;">
            {{ data_get($payslip, 'actualwork', 0) }}
        </td>
        <td style="text-align: center;">
            {{ number_format((float) data_get($payslip, 'basic', 0), 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Monthly KPI</td>
        <td style="text-align: right;">
            {{ data_get($payslip, 'coefficient', 0) }}
        </td>
        <td style="text-align: center;">
            {{ number_format((float) data_get($payslip, 'monthlykpi', 0), 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Transport Allowance</td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format((float) data_get($payslip, 'transport', 0), 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Call Allowance</td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format((float) data_get($payslip, 'callallowance', 0), 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Leave Allowance</td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format((float) data_get($payslip, 'leaveallowance', 0), 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Overtime</td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format((float) data_get($payslip, 'overtime', 0), 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Overtime Allowance</td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format((float) data_get($payslip, 'overtimeallowance', 0), 2) }}
        </td>
        <td></td>
    </tr>

    <!-- Gross Salary -->
    <tr>
        <td colspan="2" class="total">Gross Salary</td>
        <td></td>
        <td style="text-align: center;" class="total">
            {{ number_format((float) data_get($payslip, 'grosssalary', 0), 2) }}
        </td>
        <td></td>
    </tr>

    <!-- Deductions -->
    <tr>
        <td colspan="2">CNPS Employee</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format((float) data_get($payslip, 'cnpssalaries', 0), 2) }}
        </td>
    </tr>

    <tr>
        <td colspan="2">Income Tax</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format((float) data_get($payslip, 'itsalaries', 0), 2) }}
        </td>
    </tr>

    <tr>
        <td colspan="2">Other Deductions</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format((float) data_get($payslip, 'otherdeductions', 0), 2) }}
        </td>
    </tr>

    <!-- Total -->
    <tr>
        <td colspan="3" class="total">Total</td>
        <td style="text-align: center;" class="total">
            {{ number_format((float) data_get($payslip, 'grosssalary', 0), 2) }}
        </td>
        <td style="text-align: center;" class="total">
            {{ number_format((float) data_get($payslip, 'totaldeduction', 0), 2) }}
        </td>
    </tr>

    <!-- Bank & Net Pay -->
    <tr>
        <td>Bank Name</td>
        <td>{{ data_get($bank ?? [], 'bankname', '-') }}</td>
        <td></td>
        <td colspan="2" class="net-pay">NET PAY</td>
    </tr>

    <tr>
        <td>Bank Account Name</td>
        <td>{{ data_get($bank ?? [], 'name', '-') }}</td>
        <td></td>
        <td colspan="2" class="net-pay">
            {{ number_format((float) data_get($payslip, 'netpay', 0), 2) }}
        </td>
    </tr>

    <tr>
        <td>Bank Account #</td>
        <td>{{ data_get($bank ?? [], 'alcnum', '-') }}</td>
        <td colspan="3"></td>
    </tr>

    <tr>
        <td>Bank Branch</td>
        <td>{{ data_get($bank ?? [], 'branch', '-') }}</td>
        <td colspan="3"></td>
    </tr>

</table>

</body>
</html>