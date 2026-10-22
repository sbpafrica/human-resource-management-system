<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? $payslip->fullname ?? 'Employee' }} - {{ $formattedMonth }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .header { font-weight: bold; }
        .total { font-weight: bold; }
        .net-pay { font-weight: bold; }
        .logo { width: 150px; height: auto; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
@php
    $displayBankName = (!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') 
        ? data_get($bank, 'bankname') 
        : ($payslip->bank ?? $payslip->bankname ?? $payslip->bank1 ?? '-');
    $displayAccountName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') 
        ? data_get($bank, 'name') 
        : ($payslip->name ?? $payslip->names ?? $payslip->employeename ?? '-');
    $displayAccountNo = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') 
        ? data_get($bank, 'alcnum') 
        : ($payslip->accountnumber ?? $payslip->account_number ?? $payslip->alcnum ?? $payslip->account1 ?? '-');
    $displayBranch = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') 
        ? data_get($bank, 'branch') 
        : ($payslip->branch ?? $payslip->branch1 ?? '-');

    $logoData = $logoData ?? (file_exists(public_path('images/sbpafricalogo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/sbpafricalogo.png'))) : null);

    $gross = (float)($payslip->gross_salary ?? $payslip->grosspay ?? $payslip->gross ?? 0);
    $basic = (float)($payslip->base_salary ?? $payslip->basic ?? 0);
    $ssnitAmt = (float)($payslip->ssnit_ee_5_5 ?? $payslip->ssnitee ?? 0);
    $petraAmt = (float)($payslip->petra ?? 0);
    $pfAmt = (float)($payslip->employeepf ?? $payslip->provident_fund ?? 0);
    $payeAmt = (float)($payslip->paye ?? 0);
    $otherDeduction = (float)($payslip->deductions ?? $payslip->otherdeduction ?? 0);
    $totalDeductions = (float)($payslip->total_deductions ?? $payslip->totaldeductions ?? ($ssnitAmt + $petraAmt + $pfAmt + $payeAmt + $otherDeduction));
    $netPay = (float)($payslip->take_home_pay ?? $payslip->netpay ?? ($gross - $totalDeductions));
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
            <td colspan="1" class="text-center"><h2>SALARY SLIP</h2></td>
            <td colspan="2" class="text-center"><h2>CONFIDENTIAL</h2></td>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td class="text-center header">{{ $formattedMonth }}</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td class="header">Name</td>
            <td>{{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? $payslip->fullname ?? '-' }}</td>
            <td></td>
            <td class="header">Title</td>
            <td>{{ $payslip->position ?? $payslip->jobtitle ?? '-' }}</td>
        </tr>
        <tr>
            <td class="header">Employee ID</td>
            <td>{{ $payslip->staff_id ?? $payslip->staffida ?? $payslip->staffid ?? '-' }}</td>
            <td></td>
            <td class="header">Client Company</td>
            <td>Nyonkopa</td>
        </tr>
        <tr>
            <td class="header">SSNIT #</td>
            <td>{{ !empty($ssnit) && $ssnit !== '-' ? $ssnit : ($payslip->ssnit_number ?? $payslip->SSNIT ?? '-') }}</td>
            <td></td>
            <td class="header">Department</td>
            <td>{{ $payslip->work_location ?? $payslip->department ?? $payslip->dept ?? '-' }}</td>
        </tr>
        @if(!empty($payslip->start_date) && $payslip->start_date !== '-')
        <tr>
            <td class="header">Start Date</td>
            <td>{{ $payslip->start_date }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        @endif
        <tr>
            <th colspan="2" class="header text-center">Item</th>
            <th class="header text-center">Description</th>
            <th class="header text-center">Earnings</th>
            <th class="header text-center">Deductions</th>
        </tr>
        <tr>
            <td colspan="2">Basic Salary</td>
            <td class="text-center">{{ $payslip->description ?? $formattedMonth }}</td>
            <td class="text-center">{{ number_format($basic, 2) }}</td>
            <td></td>
        </tr>
        @if((float)data_get($payslip, 'fuel_and_rent_allowance', 0) > 0)
        <tr>
            <td colspan="2">Fuel & Rent Allowance</td>
            <td class="text-center">Monthly Allowance</td>
            <td class="text-center">{{ number_format((float)data_get($payslip, 'fuel_and_rent_allowance', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        <tr>
            <td colspan="2" class="total">Gross Salary</td>
            <td></td>
            <td class="text-center total">{{ number_format($gross, 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">SSNIT</td>
            <td class="text-center">0.5%</td>
            <td></td>
            <td class="text-center">{{ number_format($ssnitAmt, 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Normal Tax-Regular</td>
            <td class="text-center">PAYE</td>
            <td></td>
            <td class="text-center">{{ number_format($payeAmt, 2) }}</td>
        </tr>
        @if($petraAmt > 0)
        <tr>
            <td colspan="2">Petra</td>
            <td class="text-center">5.0%</td>
            <td></td>
            <td class="text-center">{{ number_format($petraAmt, 2) }}</td>
        </tr>
        @endif
        @if($pfAmt > 0)
        <tr>
            <td colspan="2">Provident Fund</td>
            <td class="text-center">3.0%</td>
            <td></td>
            <td class="text-center">{{ number_format($pfAmt, 2) }}</td>
        </tr>
        @endif
        @if($otherDeduction > 0)
        <tr>
            <td colspan="2">Other Deduction</td>
            <td class="text-center">{{ $payslip->deduction_description ?? 'Staff Deduction' }}</td>
            <td></td>
            <td class="text-center">{{ number_format($otherDeduction, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td colspan="3" class="total">Total</td>
            <td class="total text-center">{{ number_format($gross, 2) }}</td>
            <td class="total text-center">{{ number_format($totalDeductions, 2) }}</td>
        </tr>
        <tr>
            <td>Bank Name</td>
            <td>{{ $displayBankName }}</td>
            <td></td>
            <td colspan="2" class="net-pay text-center">NET PAY</td>
        </tr>
        <tr>
            <td>Bank Account Name</td>
            <td>{{ $displayAccountName }}</td>
            <td></td>
            <td colspan="2" class="net-pay text-center">{{ number_format($netPay, 2) }}</td>
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
