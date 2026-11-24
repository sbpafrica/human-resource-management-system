<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PZ Cussons Bulk Payslips - {{ $formattedMonth }}</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 5mm 8mm 5mm 8mm;
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
            margin: 4px 0 4px 0;
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
                $logoSrc = !empty($logoData) ? $logoData : public_path('images/sbpafricalogo.png');
                $staffSsnit = $item['ssnit'] ?? ($payslip->ssnit ?? '-');

                $displayBankName = (!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') 
                    ? data_get($bank, 'bankname') 
                    : ((!empty(data_get($bank, 'bank')) && data_get($bank, 'bank') !== '-') 
                        ? data_get($bank, 'bank') 
                        : (data_get($payslip, 'bank') ?: data_get($payslip, 'bankname', '-')));

                $displayAccountName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') 
                    ? data_get($bank, 'name') 
                    : (data_get($payslip, 'name') ?: data_get($payslip, 'names', data_get($payslip, 'employeename', '-')));

                $displayAccountNo = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') 
                    ? data_get($bank, 'alcnum') 
                    : ((!empty(data_get($bank, 'account')) && data_get($bank, 'account') !== '-') 
                        ? data_get($bank, 'account') 
                        : (data_get($payslip, 'accountnumber') ?: data_get($payslip, 'account_number', data_get($payslip, 'alcnum', '-'))));

                $displayBranch = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') 
                    ? data_get($bank, 'branch') 
                    : (data_get($payslip, 'branch') ?: '-');
            @endphp
            <div class="payslip-wrapper">
                <table class="payslip-table">
                    <tr>
                        <td colspan="2" style="text-align:center;">
                            <img src="{{ $logoSrc }}" class="logo" alt="SBP Africa Logo">
                        </td>
                        <td width="25%" class="header-title" style="color:#bc4904;">SALARY SLIP</td>
                        <td width="35%" colspan="2" class="header-title" style="color:#0359c9;">CONFIDENTIAL</td>
                    </tr>
                    <tr>
                        <td colspan="2"></td>
                        <td style="text-align:center; font-weight:bold;">{{ $formattedMonth }}</td>
                        <td colspan="2"></td>
                    </tr>
                    <tr>
                        <td class="bold">Name</td>
                        <td class="bold">{{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? '-' }}</td>
                        <td></td>
                        <td class="bold">Department</td>
                        <td class="bold">{{ $payslip->dept ?? $payslip->department ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Employee ID</td>
                        <td class="bold">{{ $payslip->staffid ?? $payslip->staffida ?? $payslip->staff_id ?? $payslip->id ?? $payslip->employ ?? '-' }}</td>
                        <td></td>
                        <td class="bold">Client Company</td>
                        <td class="bold">PZ CUSSONS</td>
                    </tr>
                    <tr>
                        <td class="bold">SSNIT #</td>
                        <td class="bold">{{ !empty($staffSsnit) && $staffSsnit !== '-' ? $staffSsnit : ($payslip->ssnit ?? '-') }}</td>
                        <td colspan="3"></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="bold3">Item</td>
                        <td class="bold3">Description/Days/Hours</td>
                        <td class="bold3">Earnings</td>
                        <td class="bold3">Deductions</td>
                    </tr>
                    <tr>
                        <td colspan="2">Basic Salary</td>
                        <td style="text-align:center;">{{ !empty($payslip->description) ? $payslip->description : ((float)($payslip->days_worked ?? ($payslip->workdays ?? 0)) > 0 ? ($payslip->days_worked ?? $payslip->workdays) : '') }}</td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->basic ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">Incentives</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->incentive ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">Bonus/Transport</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->bonus ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">Overtime / Incentives</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->overtime ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">Other</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->other ?? 0), 2) }}</td>
                        <td></td>
                    </tr>

                    <!-- Dynamic Custom Earnings -->
                    @if(isset($customColumns))
                        @foreach($customColumns->where('payslip_section', 'earnings') as $cc)
                            @php $val = data_get($payslip, $cc->column_name, 0); @endphp
                            @if((float)$val != 0 || (!is_numeric($val) && !empty($val)))
                            <tr>
                                <td colspan="2">{{ $cc->display_label }}</td>
                                <td></td>
                                <td style="text-align:center;">{{ is_numeric($val) ? number_format((float)$val, 2) : $val }}</td>
                                <td></td>
                            </tr>
                            @endif
                        @endforeach
                    @endif

                    <tr>
                        <td colspan="2" class="bold2">Gross Salary</td>
                        <td></td>
                        <td style="text-align:center;" class="bold2">{{ number_format((float)($payslip->totalearn ?? 0), 2) }}</td>
                        <td></td>
                    </tr>

                    <!-- Dynamic Custom Tax Reference / Info -->
                    @if(isset($customColumns))
                        @foreach($customColumns->whereIn('payslip_section', ['tax_reference', 'info']) as $cc)
                            @php $val = data_get($payslip, $cc->column_name, 0); @endphp
                            @if((float)$val != 0 || (!is_numeric($val) && !empty($val)))
                            <tr style="font-style: italic;">
                                <td colspan="2">{{ $cc->display_label }}</td>
                                <td style="text-align:center; font-size: 8px;">(Reference)</td>
                                <td style="text-align:center;">{{ is_numeric($val) ? number_format((float)$val, 2) : $val }}</td>
                                <td></td>
                            </tr>
                            @endif
                        @endforeach
                    @endif

                    <tr>
                        <td colspan="2">Annual Bonus</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, '13month', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">SSNIT</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->ssnitee ?? 0), 2) }}</td>
                    </tr>
                    @if((float)($payslip->petra ?? $payslip->petra_5_percent ?? 0) > 0)
                    <tr>
                        <td colspan="2">Petra (5%)</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->petra ?? $payslip->petra_5_percent ?? 0), 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="2">Annual Bonus Tax</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, '13tax', 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">Normal Tax-Regular</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->totaltaxdeduct ?? 0), 2) }}</td>
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
                                <td style="text-align:center;">{{ is_numeric($val) ? number_format((float)$val, 2) : $val }}</td>
                            </tr>
                            @endif
                        @endforeach
                    @endif

                    <tr>
                        <td colspan="2" class="bold2">Total</td>
                        <td></td>
                        <td style="text-align:center;" class="bold2">{{ number_format((float)($payslip->totalearn ?? 0) + (float)data_get($payslip, '13month', 0), 2) }}</td>
                        <td style="text-align:center;" class="bold2">{{ number_format((float)($payslip->totaldeduct ?? 0), 2) }}</td>
                    </tr>

                    <!-- Bank Details -->
                    <tr>
                        <td class="bold">Bank Name</td>
                        <td>{{ $displayBankName }}</td>
                        <td></td>
                        <td class="bold">Account Name</td>
                        <td>{{ $displayAccountName }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Bank Account #</td>
                        <td>{{ $displayAccountNo }}</td>
                        <td></td>
                        <td class="bold">Branch</td>
                        <td>{{ $displayBranch }}</td>
                    </tr>
                    <tr>
                        <td colspan="3"></td>
                        <td class="bold2" style="text-align:center; font-weight:bold;">NET PAY</td>
                        <td class="bold2" style="text-align:center; font-size:10px; font-weight:bold;">{{ number_format((float)($payslip->netpay ?? 0), 2) }}</td>
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
