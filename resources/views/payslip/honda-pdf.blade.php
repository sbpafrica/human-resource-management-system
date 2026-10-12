<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Honda Payslip - {{ $formattedMonth }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        table { width: 100%; border-collapse: collapse; border: 1px solid #ccc; }
        th, td { padding: 8px; border: 1px solid #ccc; text-align: left; }
        th { background-color: #4b5563; color: white; text-align: center; font-weight: bold; }
        .logo { width: 150px; height: auto; }
        .header-title { font-size: 18px; font-weight: bold; text-align: center; }
        .confidential { font-size: 18px; font-weight: bold; text-align: center; color: black; }
        .total, .net-pay { font-weight: bold; font-size: 14px; }
        .net-pay-value { font-size: 16px; font-weight: bold; text-align: center; border: 2px solid black; }
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
    <thead>
        <tr>
            <td colspan="2">
                @if($logoData)
                    <img src="{{ $logoData }}" class="logo" alt="Company Logo">
                @else
                    <h3 style="color: #0359c9; margin: 0;">SBP AFRICA</h3>
                @endif
            </td>
            <td class="header-title" style="color: orange;">SALARY SLIP</td>
            <td colspan="2" class="confidential">CONFIDENTIAL</td>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td style="text-align: center; font-weight: bold;">{{ $formattedMonth }}</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td>Name</td>
            <td>{{ $payslip->NAME ?? $payslip->name ?? $payslip->names ?? $payslip->employeename ?? '-' }}</td>
            <td></td>
            <td>Title</td>
            <td>{{ $payslip->POSITION ?? $payslip->position ?? '-' }}</td>
        </tr>
        <tr>
            <td>Employee ID</td>
            <td>{{ $payslip->STAFFID ?? $payslip->STAFF_ID ?? $payslip->staffid ?? $payslip->staffida ?? $payslip->staff_id ?? $payslip->id ?? '-' }}</td>
            <td></td>
            <td>Client Company</td>
            <td>HONDA</td>
        </tr>
        <tr>
            <td>SSNIT #</td>
            <td>{{ $ssnit ?? $payslip->ssnit ?? '-' }}</td>
            <td></td>
            <td>Department</td>
            <td>-</td>
        </tr>
        <tr>
            <td>Basic Salary</td>
            <td>{{ number_format((float)($payslip->BASICSALARY_GHC ?? 0), 2) }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <th colspan="2">Item</th>
            <th>Description/Days</th>
            <th>Earnings</th>
            <th>Deductions</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="2">Basic Salary Earned</td>
            <td style="text-align: right;">{{ $payslip->NUMBEROFDAYSWORKED ?? 0 }}</td>
            <td style="text-align: center;">{{ number_format((float)($payslip->BASICSALARYEARNED_GHC ?? 0), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Performance Bonus</td>
            <td style="text-align: right;">{{ $payslip->COEFFICIENT ?? 0 }}</td>
            <td style="text-align: center;">{{ number_format((float)($payslip->PERFORMANCEBONUS ?? 0), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Transport Allowance</td>
            <td style="text-align: right;">{{ $payslip->NUMBEROFDAYSWORKED ?? 0 }}</td>
            <td style="text-align: center;">{{ number_format((float)($payslip->TRANSPORTALLOWANCE ?? 0), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Food Allowance</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)($payslip->foodallowance ?? 0), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2" class="total">Gross Salary</td>
            <td></td>
            <td style="text-align: center;" class="total">{{ number_format((float)($payslip->GROSSSALARY_GHC ?? 0), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">SSNIT</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)($payslip->SSNITEE_0_5 ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">PETRA</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)($payslip->PETRA_5 ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">PAYE</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)($payslip->PAYE_GHC ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="3" class="total">Total</td>
            <td style="text-align: center;" class="total">{{ number_format((float)($payslip->GROSSSALARY_GHC ?? 0), 2) }}</td>
            <td style="text-align: center;" class="total">{{ number_format((float)($payslip->TOTALDEDUCTIONS_GHC ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Bank Name</td>
            <td>{{ $displayBankName }}</td>
            <td></td>
            <td colspan="2" class="net-pay">NET PAY</td>
        </tr>
        <tr>
            <td>Bank Account Name</td>
            <td>{{ $displayAccountName }}</td>
            <td></td>
            <td colspan="2" class="net-pay-value">{{ number_format((float)($payslip->TAKEHOMEPAY_GHC ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td>Bank Account #</td>
            <td>{{ $displayAccountNo }}</td>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td>Bank Branch</td>
            <td>{{ $displayBranch }}</td>
            <td colspan="3"></td>
        </tr>
    </tbody>
</table>

</body>
</html>
