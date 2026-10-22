<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nyonkopa Payslips - {{ $formattedMonth }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 6mm 8mm 6mm 8mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 8.5px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        .page-break {
            page-break-after: always;
        }
        .bulk-page {
            width: 100%;
        }
        .payslip-wrapper {
            margin: 0;
            padding: 0;
        }
        table.payslip-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.payslip-table td, table.payslip-table th {
            border: 1px solid #777;
            padding: 2.2px 5px;
            font-size: 8.5px;
            line-height: 1.15;
        }
        .header-title {
            background-color: #fff;
            color: #000;
            font-weight: 900;
            font-size: 11px;
            text-align: center;
        }
        .bold  { font-weight: bold; }
        .bold2 { font-weight: bold; background-color: #f9fafb; }
        .bold3 { font-weight: bold; background-color: #e5e7eb; text-align: center; }
        .bold4 { background-color: #e5e7eb; height: 3px; }
        .logo  { width: 75px; height: 26px; }
        .cut-guide {
            border-bottom: 1px dashed #888;
            margin: 5px 0 5px 0;
            height: 1px;
        }
    </style>
</head>
<body>
@foreach($chunks as $chunkIndex => $chunk)
    <div class="bulk-page {{ !$loop->last ? 'page-break' : '' }}">
        @foreach($chunk as $itemIndex => $item)
            @php
                $payslip    = $item['payslip'] ?? $item;
                $bank       = $item['bank'] ?? [];
                $logoSrc    = !empty($logoData) ? $logoData : public_path('images/sbpafricalogo.png');
                $staffSsnit = $item['ssnit'] ?? ($payslip->ssnit ?? '-');

                $gross           = (float)($payslip->gross_salary ?? $payslip->grosspay ?? $payslip->gross ?? 0);
                $basic           = (float)($payslip->base_salary ?? $payslip->basic ?? 0);
                $ssnitAmt        = (float)($payslip->ssnit_ee_5_5 ?? $payslip->ssnitee ?? 0);
                $petraAmt        = (float)($payslip->petra ?? 0);
                $pfAmt           = (float)($payslip->employeepf ?? $payslip->provident_fund ?? 0);
                $payeAmt         = (float)($payslip->paye ?? 0);
                $otherDeduction  = (float)($payslip->deductions ?? $payslip->otherdeduction ?? 0);
                $totalDeductions = (float)($payslip->total_deductions ?? $payslip->totaldeductions ?? ($ssnitAmt + $petraAmt + $pfAmt + $payeAmt + $otherDeduction));
                $netPay          = (float)($payslip->take_home_pay ?? $payslip->netpay ?? ($gross - $totalDeductions));

                $displayBankName    = (!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') ? data_get($bank, 'bankname') : ($payslip->bank ?? '-');
                $displayAccountName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') ? data_get($bank, 'name') : ($payslip->name ?? $payslip->names ?? $payslip->employeename ?? '-');
                $displayAccountNo   = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') ? data_get($bank, 'alcnum') : ($payslip->accountnumber ?? $payslip->account_number ?? '-');
                $displayBranch      = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') ? data_get($bank, 'branch') : ($payslip->branch ?? '-');
            @endphp
            <div class="payslip-wrapper">
                <table class="payslip-table">
                    <tr>
                        <td colspan="2" style="text-align:center;">
                            <img src="{{ $logoSrc }}" class="logo" alt="SBP Africa Logo">
                        </td>
                        <td width="25%" class="header-title">SALARY SLIP</td>
                        <td width="35%" colspan="2" class="header-title">CONFIDENTIAL</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td style="text-align:center; font-weight:bold;">{{ $formattedMonth }}</td>
                        <td colspan="2"></td>
                    </tr>
                    <tr>
                        <td class="bold">Name</td>
                        <td class="bold">{{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? '-' }}</td>
                        <td></td>
                        <td class="bold">Title</td>
                        <td class="bold">{{ $payslip->position ?? $payslip->jobtitle ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Employee ID</td>
                        <td class="bold">{{ $payslip->staff_id ?? $payslip->staffida ?? $payslip->staffid ?? '-' }}</td>
                        <td></td>
                        <td class="bold">Client Company</td>
                        <td class="bold">Nyonkopa</td>
                    </tr>
                    <tr>
                        <td class="bold">SSNIT #</td>
                        <td class="bold">{{ !empty($staffSsnit) && $staffSsnit !== '-' ? $staffSsnit : ($payslip->ssnit_number ?? $payslip->SSNIT ?? '-') }}</td>
                        <td></td>
                        <td class="bold">Department</td>
                        <td class="bold">{{ $payslip->work_location ?? $payslip->department ?? '-' }}</td>
                    </tr>
                    @if(!empty($payslip->start_date) && $payslip->start_date !== '-')
                    <tr>
                        <td class="bold">Start Date</td>
                        <td class="bold">{{ $payslip->start_date }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="2" class="bold3">Item</td>
                        <td class="bold3">Description</td>
                        <td class="bold3">Earnings</td>
                        <td class="bold3">Deductions</td>
                    </tr>
                    <tr>
                        <td colspan="2">Basic Salary</td>
                        <td style="text-align:center;">{{ $payslip->description ?? $formattedMonth }}</td>
                        <td style="text-align:center;">{{ number_format($basic, 2) }}</td>
                        <td></td>
                    </tr>
                    @if((float)data_get($payslip, 'fuel_and_rent_allowance', 0) > 0)
                    <tr>
                        <td colspan="2">Fuel & Rent Allowance</td>
                        <td style="text-align:center;">Monthly Allowance</td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'fuel_and_rent_allowance', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="2" class="bold">Gross Salary</td>
                        <td></td>
                        <td style="text-align:center;" class="bold">{{ number_format($gross, 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">SSNIT</td>
                        <td style="text-align:center;">0.5%</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($ssnitAmt, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">Normal Tax-Regular</td>
                        <td style="text-align:center;">PAYE</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($payeAmt, 2) }}</td>
                    </tr>
                    @if($petraAmt > 0)
                    <tr>
                        <td colspan="2">Petra</td>
                        <td style="text-align:center;">5.0%</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($petraAmt, 2) }}</td>
                    </tr>
                    @endif
                    @if($pfAmt > 0)
                    <tr>
                        <td colspan="2">Provident Fund</td>
                        <td style="text-align:center;">3.0%</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($pfAmt, 2) }}</td>
                    </tr>
                    @endif
                    @if($otherDeduction > 0)
                    <tr>
                        <td colspan="2">Other Deduction</td>
                        <td style="text-align:center;">{{ $payslip->deduction_description ?? 'Staff Deduction' }}</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($otherDeduction, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="2" class="bold2">Total</td>
                        <td></td>
                        <td style="text-align:center;" class="bold2">{{ number_format($gross, 2) }}</td>
                        <td style="text-align:center;" class="bold2">{{ number_format($totalDeductions, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Bank Name</td>
                        <td>{{ $displayBankName }}</td>
                        <td></td>
                        <td colspan="2" class="bold2" style="text-align:center;">NET PAY</td>
                    </tr>
                    <tr>
                        <td class="bold">Account Name</td>
                        <td>{{ $displayAccountName }}</td>
                        <td></td>
                        <td colspan="2" class="bold2" style="text-align:center; font-size:10px;">{{ number_format($netPay, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Account #</td>
                        <td>{{ $displayAccountNo }}</td>
                        <td class="bold">Branch</td>
                        <td colspan="2">{{ $displayBranch }}</td>
                    </tr>
                    <tr>
                        <td colspan="5" class="bold4"></td>
                    </tr>
                </table>
            </div>
            @if(!$loop->last)
                <div class="cut-guide"></div>
            @endif
        @endforeach
    </div>
@endforeach
</body>
</html>
