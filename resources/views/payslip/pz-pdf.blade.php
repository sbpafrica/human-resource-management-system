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
        <td class="text-center">{{ number_format((float)($payslip->basic ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Incentives</td>
        <td class="text-right"></td>
        <td class="text-center">{{ number_format((float)($payslip->incentive ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Bonus/Transport</td>
        <td class="text-right"></td>
        <td class="text-center">{{ number_format((float)($payslip->bonus ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Overtime / Incentives</td>
        <td class="text-right"></td>
        <td class="text-center">{{ number_format((float)($payslip->overtime ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Other</td>
        <td class="text-right"></td>
        <td class="text-center">{{ number_format((float)($payslip->other ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2" class="total">Gross Salary</td>
        <td></td>
        <td class="text-center total">{{ number_format((float)($payslip->totalearn ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">Annual Bonus</td>
        <td></td>
        <td class="text-center">{{ number_format((float)data_get($payslip, '13month', 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">SSNIT</td>
        <td></td>
        <td></td>
        <td class="text-center">{{ number_format((float)($payslip->ssnitee ?? 0), 2) }}</td>
    </tr>
    @if((float)($payslip->petra ?? $payslip->petra_5_percent ?? 0) > 0)
    <tr>
        <td colspan="2">Petra (5%)</td>
        <td></td>
        <td></td>
        <td class="text-center">{{ number_format((float)($payslip->petra ?? $payslip->petra_5_percent ?? 0), 2) }}</td>
    </tr>
    @endif
    <tr>
        <td colspan="2">Annual Bonus Tax</td>
        <td></td>
        <td></td>
        <td class="text-center">{{ number_format((float)data_get($payslip, '13tax', 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">Normal Tax-Regular</td>
        <td></td>
        <td></td>
        <td class="text-center">{{ number_format((float)($payslip->totaltaxdeduct ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2" class="total">Total</td>
        <td></td>
        <td class="text-center total">{{ number_format((float)($payslip->totalearn ?? 0) + (float)data_get($payslip, '13month', 0), 2) }}</td>
        <td class="text-center total">{{ number_format((float)($payslip->totaldeduct ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td class="header">Bank Name</td>
        <td>{{ $displayBankName }}</td>
        <td></td>
        <td class="header">Account Name</td>
        <td>{{ $displayAccountName }}</td>
    </tr>
    <tr>
        <td class="header">Bank Account #</td>
        <td>{{ $displayAccountNo }}</td>
        <td></td>
        <td class="header">Branch</td>
        <td>{{ $displayBranch }}</td>
    </tr>
    <tr>
        <td colspan="3"></td>
        <td class="net-pay" style="text-align: center;">NET PAY</td>
        <td class="net-pay" style="text-align: center;">{{ number_format((float)($payslip->netpay ?? 0), 2) }}</td>
    </tr>
</table>

</body>
</html>
