<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Honda Bulk Payslips - {{ $formattedMonth }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 4mm 6mm 4mm 6mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 8px;
            color: #333;
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
            border: 1px solid #ccc;
        }
        table.payslip-table th, table.payslip-table td {
            border: 1px solid #ccc;
            padding: 1.8px 4px;
            text-align: left;
            font-size: 8px;
            line-height: 1.15;
        }
        table.payslip-table th {
            background-color: #4b5563;
            color: white;
            text-align: center;
            font-weight: bold;
        }
        .header-title {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            color: orange;
        }
        .confidential {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            color: black;
        }
        .total {
            font-weight: bold;
            background-color: #f9fafb;
        }
        .net-pay {
            font-weight: bold;
            font-size: 10px;
            text-align: center;
        }
        .net-pay-value {
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            border: 1.5px solid black;
        }
        .logo {
            max-width: 110px;
            max-height: 22px;
            height: auto;
            display: block;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .cut-guide {
            border-bottom: 1px dashed #999;
            margin: 3px 0;
            height: 1px;
        }
    </style>
</head>
<body>
@foreach($chunks as $chunkIndex => $chunk)
    <div class="bulk-page {{ !$loop->last ? 'page-break' : '' }}">
        @foreach($chunk as $itemIndex => $item)
            @php
                $payslip = $item['payslip'] ?? $item;
                $bank    = $item['bank'] ?? [];
                $logoSrc = !empty($logoData) ? $logoData : (file_exists(public_path('images/sbpafricalogo.png')) ? public_path('images/sbpafricalogo.png') : null);
                $staffSsnit = $item['ssnit'] ?? ($payslip->ssnit ?? '-');

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
            @endphp
            <div class="payslip-wrapper">
                <table class="payslip-table">
                    <thead>
                        <tr>
                            <td colspan="2">
                                @if(!empty($logoSrc))
                                    <img src="{{ $logoSrc }}" class="logo" alt="Company Logo">
                                @else
                                    <h3 style="color: #0359c9; margin: 0; font-size: 10px;">SBP AFRICA</h3>
                                @endif
                            </td>
                            <td class="header-title">SALARY SLIP</td>
                            <td colspan="2" class="confidential">CONFIDENTIAL</td>
                        </tr>
                        <tr>
                            <td colspan="2"></td>
                            <td style="text-align: center; font-weight: bold; font-size: 9px;">{{ $formattedMonth }}</td>
                            <td colspan="2"></td>
                        </tr>
                        <tr>
                            <td style="width: 18%;">Name</td>
                            <td style="width: 28%; font-weight: 500;">{{ $payslip->NAME ?? $payslip->name ?? $payslip->names ?? $payslip->employeename ?? '-' }}</td>
                            <td style="width: 20%;"></td>
                            <td style="width: 17%;">Title</td>
                            <td style="width: 17%;">{{ $payslip->POSITION ?? $payslip->position ?? '-' }}</td>
                        </tr>
                        <tr>
                            <td>Employee ID</td>
                            <td style="font-weight: 500;">{{ $payslip->STAFFID ?? $payslip->STAFF_ID ?? $payslip->staffid ?? $payslip->staffida ?? $payslip->staff_id ?? $payslip->id ?? '-' }}</td>
                            <td></td>
                            <td>Client Company</td>
                            <td>HONDA</td>
                        </tr>
                        <tr>
                            <td>SSNIT #</td>
                            <td>{{ $staffSsnit ?? $ssnit ?? $payslip->ssnit ?? '-' }}</td>
                            <td></td>
                            <td>Department</td>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td>Basic Salary</td>
                            <td>{{ number_format((float)($payslip->BASICSALARY_GHC ?? $payslip->basic ?? 0), 2) }}</td>
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
                            <td style="text-align: right;">{{ $payslip->NUMBEROFDAYSWORKED ?? $payslip->workdays ?? 0 }}</td>
                            <td style="text-align: center;">{{ number_format((float)($payslip->BASICSALARYEARNED_GHC ?? $payslip->basic_earned ?? 0), 2) }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="2">Transport Allowance</td>
                            <td style="text-align: right;">{{ $payslip->NUMBEROFDAYSWORKED ?? $payslip->workdays ?? 0 }}</td>
                            <td style="text-align: center;">{{ number_format((float)($payslip->TRANSPORTALLOWANCE ?? $payslip->transport ?? $payslip->transport_allowance ?? 0), 2) }}</td>
                            <td></td>
                        </tr>
                        @if((float)($payslip->PERFORMANCEBONUS ?? 0) > 0 || (float)($payslip->COEFFICIENT ?? 0) > 0)
                        <tr>
                            <td colspan="2">Performance Bonus</td>
                            <td style="text-align: right;">{{ $payslip->COEFFICIENT ?? 0 }}</td>
                            <td style="text-align: center;">{{ number_format((float)($payslip->PERFORMANCEBONUS ?? 0), 2) }}</td>
                            <td></td>
                        </tr>
                        @endif

                    <!-- Dynamic Custom Earnings -->
                    @if(isset($customColumns))
                        @foreach($customColumns->where('payslip_section', 'earnings') as $cc)
                            @php $val = data_get($payslip, $cc->column_name, 0); @endphp
                            @if((float)$val != 0 || (!is_numeric($val) && !empty($val)))
                            <tr>
                                <td colspan="2">{{ $cc->display_label }}</td>
                                <td></td>
                                <td style="text-align: center;">{{ is_numeric($val) ? number_format((float)$val, 2) : $val }}</td>
                                <td></td>
                            </tr>
                            @endif
                        @endforeach
                    @endif

                        <tr>
                            <td colspan="2" class="total">Gross Salary</td>
                            <td></td>
                            <td style="text-align: center;" class="total">{{ number_format((float)($payslip->GROSSSALARY_GHC ?? $payslip->grosspay ?? 0), 2) }}</td>
                            <td></td>
                        </tr>

                    <!-- Dynamic Custom Tax Reference / Info -->
                    @if(isset($customColumns))
                        @foreach($customColumns->whereIn('payslip_section', ['tax_reference', 'info']) as $cc)
                            @php $val = data_get($payslip, $cc->column_name, 0); @endphp
                            @if((float)$val != 0 || (!is_numeric($val) && !empty($val)))
                            <tr style="font-style: italic;">
                                <td colspan="2">{{ $cc->display_label }}</td>
                                <td style="text-align: center; font-size: 8px;">(Reference)</td>
                                <td style="text-align: center;">{{ is_numeric($val) ? number_format((float)$val, 2) : $val }}</td>
                                <td></td>
                            </tr>
                            @endif
                        @endforeach
                    @endif

                        <tr>
                            <td colspan="2">SSNIT</td>
                            <td></td>
                            <td></td>
                            <td style="text-align: center;">{{ number_format((float)($payslip->SSNITEE_0_5 ?? $payslip->ssnitee ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="2">PETRA</td>
                            <td></td>
                            <td></td>
                            <td style="text-align: center;">{{ number_format((float)($payslip->PETRA_5 ?? $payslip->petra ?? 0), 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="2">PAYE</td>
                            <td></td>
                            <td></td>
                            <td style="text-align: center;">{{ number_format((float)($payslip->PAYE_GHC ?? $payslip->paye ?? 0), 2) }}</td>
                        </tr>

                    <!-- Dynamic Custom Deductions -->
                    @if(isset($customColumns))
                        @foreach($customColumns->where('payslip_section', 'deductions') as $cc)
                            @php $val = data_get($payslip, $cc->column_name, 0); @endphp
                            @if((float)$val != 0 || (!is_numeric($val) && !empty($val)))
                            <tr>
                                <td colspan="2">{{ $cc->display_label }}</td>
                                <td></td>
                                <td></td>
                                <td style="text-align: center;">{{ is_numeric($val) ? number_format((float)$val, 2) : $val }}</td>
                            </tr>
                            @endif
                        @endforeach
                    @endif

                        <tr>
                            <td colspan="3" class="total">Total</td>
                            <td style="text-align: center;" class="total">{{ number_format((float)($payslip->GROSSSALARY_GHC ?? $payslip->grosspay ?? 0), 2) }}</td>
                            <td style="text-align: center;" class="total">{{ number_format((float)($payslip->TOTALDEDUCTIONS_GHC ?? $payslip->totaldeductions ?? 0), 2) }}</td>
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
                            <td colspan="2" class="net-pay-value">{{ number_format((float)($payslip->TAKEHOMEPAY_GHC ?? $payslip->netpay ?? 0), 2) }}</td>
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
            </div>
            @if(!$loop->last)
                <div class="cut-guide"></div>
            @endif
        @endforeach
    </div>
@endforeach
</body>
</html>
