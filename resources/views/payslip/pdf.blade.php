<!DOCTYPE html>
<html>
<head>
    <title>Payslip - {{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? $payslip->fullname ?? $payslip->name1 ?? 'Employee' }} - {{ $formattedMonth }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .header { font-weight: bold;  }
        .total { font-weight: bold;}
        .net-pay { font-weight: bold;  }
        .logo { width: 150px; height: auto; }
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
                    <img src="{{ $logoData }}" class="logo" alt="Company Logo">
                @else
                    <h3 style="color: #0359c9; margin: 0;">SBP AFRICA</h3>
                @endif
            </td>
            <td colspan="1" style="text-align: center;"><h2>SALARY SLIP</h2></td>
            <td colspan="2" style="text-align: center;"><h2>CONFIDENTIAL</h2></td>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td style="text-align: center;">{{ $formattedMonth }}</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td>Name</td>
            <td>{{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? $payslip->fullname ?? $payslip->name1 ?? '-' }}</td>
            <td></td>
            <td>Title</td>
            <td>{{ $payslip->position ?? $payslip->jobtitle ?? '-' }}</td>
        </tr>
        <tr>
            <td>Employee ID</td>
            <td>{{ $payslip->staffida ?? $payslip->staffid ?? $payslip->staff_id ?? $payslip->id ?? '-' }}</td>
            <td></td>
            <td>Client Company</td>
            <td>{{ $payslip->brandname ?? $payslip->company ?? $payslip->brand ?? '-' }}</td>
        </tr>
        <tr>
            <td>SSNIT #</td>
            <td>{{ $ssnit }}</td>
            <td></td>
            <td>Department</td>
            <td>{{ $payslip->department ?? $payslip->dept ?? $payslip->workarea ?? '-' }}</td>
        </tr>
        <tr>
            <th colspan="2" class="header" style="text-align: center;">Item</th>
            <th class="header" style="text-align: center;">Description/Days/Hours</th>
            <th class="header" style="text-align: center;">Earnings</th>
            <th class="header" style="text-align: center;">Deductions</th>
        </tr>
        <tr>
            <td colspan="2">Basic Salary</td>
            <td style="text-align: right;">{{ data_get($payslip, 'workdays', 0) }}</td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'basic', 0), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Performance Bonus</td>
            <td style="text-align: right;">{{ data_get($payslip, 'coefficient', 0) }}</td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'performstand', 0), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Allowance</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'allowance', 0), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Overtime Weekday</td>
            <td style="text-align: right;">{{ data_get($payslip, 'otworkdays', data_get($payslip, 'normalOThrs', data_get($payslip, 'weekdaysOT', 0))) }}</td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'otworkingday', data_get($payslip, 'normalovertime', 0)), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Overtime Weekend/Holiday</td>
            <td style="text-align: right;">{{ data_get($payslip, 'otweekend', data_get($payslip, 'weekendOThrs', data_get($payslip, 'weekendOT', 0))) }}</td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'otholiday', data_get($payslip, 'saturdayovertime', 0)), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Other Income</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'other', 0), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Commission</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'commission', 0), 2) }}</td>
            <td></td>
        </tr>
        @if((float)data_get($payslip, 'quarterlybonus', 0) > 0)
        <tr>
            <td colspan="2">Quarterly Bonus</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'quarterlybonus', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'endcontractrights', 0) > 0)
        <tr>
            <td colspan="2">End of Contract Rights</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'endcontractrights', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        <tr>
            <td colspan="2" style="font-weight: bold;">Gross Salary</td>
            <td></td>
            <td style="text-align: center; font-weight: bold;">{{ number_format((float)data_get($payslip, 'grosspay', data_get($payslip, 'totalamount', data_get($payslip, 'gross', 0))), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Annual Bonus</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'annaulbonus', 0), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">SSNIT</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">
    {{ number_format((float)data_get($payslip, 'ssnitee', (float)data_get($payslip, 'basic', 0) * 0.055), 2) }}
</td>
        </tr>
        <tr>
            <td colspan="2">Normal Tax-Regular</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'paye', 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Leave</td>
            <td style="text-align: center;">{{ data_get($payslip, 'leavedays', 0) }}</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'leavededuction', 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Other Deduction</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'otherdeduction', 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Loan</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'loan', 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Annual Tax</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'annaultax', 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="3" class="total">Total</td>
            <td class="total" style="text-align: center;">
    {{
        number_format(
            (float)data_get($payslip, 'grosspay', 0) +
            (float)data_get($payslip, 'annualbonus', 0),
        2)
    }}
</td>
            <td class="total" style="text-align: center;">
    {{
        number_format(
            (float)data_get($payslip, 'ssnitee', (float)data_get($payslip, 'basic', 0) * 0.055) +
            (float)data_get($payslip, 'leavededuction', 0) +
            (float)data_get($payslip, 'otherdeduction', 0) +
            (float)data_get($payslip, 'loan', 0) +
            (float)data_get($payslip, 'paye', 0) +
            (float)data_get($payslip, 'annaultax', 0),
        2)
    }}
</td>
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
            <td colspan="2" class="net-pay" >{{ number_format((float)data_get($payslip, 'netpay', data_get($payslip, 'takehome', data_get($payslip, 'takehomepay', data_get($payslip, 'net_pay', 0)))), 2) }}</td>
        </tr>
        <tr>
            <td>Bank Account #</td>
            <td>{{ $displayAccountNo }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Bank Branch</td>
            <td>{{ $displayBranch }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>
</body>
</html>
