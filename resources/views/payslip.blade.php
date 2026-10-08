<!DOCTYPE html>
<html>
<head>
    <title>Payslip for {{ $formattedMonth }}</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 2px solid black; padding: 8px; text-align: left; }
        th { background-color: #343434; color: white; }
        .header { font-size: 35px; font-weight: bold; }
        .logo { width: 200px; height: 70px; }
        .total { color: blue; font-weight: bold; }
        .netpay { color: black; font-size: 20px; font-weight: bold; }
        .confidential { color: black; font-size: 35px; font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <td colspan="2">
                    <img src="https://mlnyao5byika.i.optimole.com/8LJnj5c-lhFjjhu0/w:1576/h:662/q:auto/https://sbpafrica.group/wp-content/uploads/2020/05/cropped-sbplogo-africa.png" class="logo">
                </td>
                <td width="25%" align="center"><span class="header" style="color: #f88210;">SALARY SLIP</span></td>
                <td width="35%" align="center" colspan="2"><span class="confidential">CONFIDENTIAL</span></td>
            </tr>
            <tr>
                <td colspan="2"></td>
                <td align="center">{{ $formattedMonth }}</td>
                <td colspan="2"></td>
            </tr>
            <tr>
                <td>Name</td>
                <td>{{ $payslip->name }}</td>
                <td></td>
                <td>Title</td>
                <td>{{ $payslip->position }}</td>
            </tr>
            <tr>
                <td>Employee ID</td>
                <td>{{ $payslip->staffida }}</td>
                <td></td>
                <td>Client Company</td>
                <td>{{ $payslip->brandname }}</td>
            </tr>
            <tr>
                <td>SSNIT #</td>
                <td>{{ $payslip->ssnit }}</td>
                <td></td>
                <td>Department</td>
                <td>{{ $payslip->department }}</td>
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
                <td colspan="2">Basic Salary</td>
                <td align="center">{{ $payslip->workdays }}</td>
                <td align="center">{{ number_format($payslip->basic, 2) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="2">Performance Bonus</td>
                <td align="center">{{ $payslip->coefficient }}</td>
                <td align="center">{{ number_format($payslip->performstand, 2) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="2">Allowance</td>
                <td align="center"></td>
                <td align="center">{{ number_format($payslip->allowance, 2) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="2">Overtime Weekday</td>
                <td align="right">{{ data_get($payslip, 'otworkdays', data_get($payslip, 'normalOThrs', data_get($payslip, 'weekdaysOT', 0))) }}</td>
                <td align="center">{{ number_format(data_get($payslip, 'otworkingday', data_get($payslip, 'normalovertime', 0)), 2) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="2">Overtime Weekend/Holiday</td>
                <td align="right">{{ data_get($payslip, 'otweekend', data_get($payslip, 'weekendOThrs', data_get($payslip, 'weekendOT', 0))) }}</td>
                <td align="center">{{ number_format(data_get($payslip, 'otholiday', data_get($payslip, 'saturdayovertime', 0)), 2) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="2">Other Income</td>
                <td align="center"></td>
                <td align="center">{{ number_format($payslip->other, 2) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="2">Commission</td>
                <td align="center"></td>
                <td align="center">{{ number_format($payslip->commission, 2) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="2" style="font-size: 20px; font-weight: bold;">Gross Salary</td>
                <td align="center"></td>
                <td align="center" style="font-size: 15px; font-weight: bold;">{{ number_format($payslip->grosspay, 2) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="2">Annual Bonus</td>
                <td></td>
                <td align="center" style="font-size: 15px; font-weight: bold;">{{ number_format($payslip->annaulbonus, 2) }}</td>
                <td></td>
            </tr>
            <tr>
                <td colspan="2">SSNIT</td>
                <td></td>
                <td></td>
                <td align="center">{{ number_format($ssnit_contribution, 2) }}</td>
            </tr>
            @if((float)($payslip->petra ?? $payslip->PETRA_5_PERCENT ?? 0) > 0)
            <tr>
                <td colspan="2">Petra (5%)</td>
                <td></td>
                <td></td>
                <td align="center">{{ number_format((float)($payslip->petra ?? $payslip->PETRA_5_PERCENT ?? 0), 2) }}</td>
            </tr>
            @endif
            <tr>
                <td colspan="2">Normal Tax-Regular</td>
                <td></td>
                <td></td>
                <td align="center">{{ number_format($payslip->paye, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2">Leave</td>
                <td align="center">{{ $payslip->leavedays }}</td>
                <td></td>
                <td align="center">{{ number_format($payslip->leavededuction, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2">Other Deduction</td>
                <td align="center"></td>
                <td></td>
                <td align="center">{{ number_format($payslip->otherdeduction, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2">Loan</td>
                <td align="center"></td>
                <td></td>
                <td align="center">{{ number_format($payslip->loan, 2) }}</td>
            </tr>
            <tr>
                <td colspan="2">Annual Tax</td>
                <td></td>
                <td></td>
                <td align="center">{{ number_format($payslip->annaultax, 2) }}</td>
            </tr>
            <tr>
                <td colspan="3" class="total">Total</td>
                <td align="center" class="total">{{ number_format($total_earnings, 2) }}</td>
                <td align="center" class="total">{{ number_format($total_deductions, 2) }}</td>
            </tr>
            <tr>
                <td>Bank Name</td>
                <td>{{ $payslip->bankname }}</td>
                <td></td>
                <td colspan="2" style="color: red;">NET PAY</td>
            </tr>
            <tr>
                <td>Bank Account Name</td>
                <td>{{ $payslip->name }}</td>
                <td></td>
                <td colspan="2" class="netpay">{{ number_format($payslip->netpay, 2) }}</td>
            </tr>
            <tr>
                <td>Bank Account #</td>
                <td>{{ $payslip->alcnum }}</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
            <tr>
                <td>Bank Branch</td>
                <td>{{ $payslip->branch }}</td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
