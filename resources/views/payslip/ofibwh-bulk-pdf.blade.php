<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>OFI Bean WH Bulk Payslips - {{ $formattedMonth }}</title>
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
        }
        table.payslip-table th, table.payslip-table td {
            border: 1px solid #ccc;
            padding: 1.8px 4px;
            text-align: left;
            font-size: 8px;
            line-height: 1.15;
        }
        .header {
            font-weight: bold;
            background-color: #f3f4f6;
        }
        .total {
            font-weight: bold;
            background-color: #f9fafb;
        }
        .net-pay {
            font-weight: bold;
            font-size: 11px;
            background-color: #fff7ed;
            color: #c2410c;
            text-align: center;
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
            margin: 2px 0;
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

                $hasBasic = !empty($payslip->has_basic_salary)
                    ? true
                    : ((float)($payslip->raw_basic ?? 0) > 0 || ((float)($payslip->basic ?? 0) > 0 && empty($payslip->is_casual) && (float)($payslip->weekdayhr ?? 0) == 0 && (float)($payslip->weekdaytonage ?? 0) == 0 && (float)($payslip->amt ?? 0) == 0));
                $dailyRateVal = (float)($payslip->daily_rate ?? $payslip->rate ?? $payslip->weekdayrate ?? 0);
                $regularAmt = (float)($payslip->amt ?? $payslip->basic_earned ?? 0);
                $weekdayHrs = (float)($payslip->weekdayhr ?? 0);
                $weekdayTons = (float)($payslip->weekdaytonage ?? 0);

                $ftAmt = (float)($payslip->ftamount ?? $payslip->fuel_and_rent_allowance ?? $payslip->allowance ?? 0);
                $weekendHrs = (float)($payslip->weekendOT ?? $payslip->weekendhr ?? 0);
                $weekendTons = (float)($payslip->weekendtonage ?? 0);
                $weekendAmt = (float)($payslip->saturdayovertime ?? $payslip->amount ?? 0);

                $totalDeductionsCalculated = (float)($payslip->ssnitee ?? 0)
                    + (float)($payslip->petra ?? 0)
                    + (float)($payslip->paye ?? 0)
                    + (float)($payslip->otherdeduction ?? 0);

                if ($totalDeductionsCalculated == 0) {
                    $totalDeductionsCalculated = (float)($payslip->totaldeductions ?? $payslip->totaltaxdeduction ?? 0);
                }

                $displayBankName = (!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-')
                    ? data_get($bank, 'bankname')
                    : ($payslip->bank ?? $payslip->bank1 ?? '-');

                $displayName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-')
                    ? data_get($bank, 'name')
                    : ($payslip->name ?? '-');

                $displayAccount = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-')
                    ? data_get($bank, 'alcnum')
                    : ($payslip->accountnumber ?? $payslip->account2 ?? '-');

                $displayBranch = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-')
                    ? data_get($bank, 'branch')
                    : ($payslip->branch ?? '-');
            @endphp
            <div class="payslip-wrapper">
                <table class="payslip-table">
                    <!-- Header Row -->
                    <tr>
                        <td colspan="2">
                            @if(!empty($logoSrc))
                                <img src="{{ $logoSrc }}" class="logo" alt="Company Logo">
                            @else
                                <span style="font-weight: bold; font-size: 11px;">SBP AFRICA</span>
                            @endif
                        </td>
                        <td colspan="1" class="text-center" style="font-weight: bold; font-size: 11px;">
                            SALARY SLIP
                        </td>
                        <td colspan="2" class="text-center" style="font-weight: bold; font-size: 11px;">
                            CONFIDENTIAL
                        </td>
                    </tr>

                    <!-- Month -->
                    <tr>
                        <td colspan="2"></td>
                        <td class="text-center" style="font-weight: bold; font-size: 9px;">
                            {{ $formattedMonth ?? '-' }}
                        </td>
                        <td colspan="2"></td>
                    </tr>

                    <!-- Employee Info -->
                    <tr>
                        <td style="width: 18%;">Name</td>
                        <td style="width: 28%; font-weight: 500;">{{ $payslip->name ?? '-' }}</td>
                        <td style="width: 20%;"></td>
                        <td style="width: 17%;">Title</td>
                        <td style="width: 17%;">{{ (!empty($payslip->position) && $payslip->position !== 'Warehouse Staff') ? $payslip->position : '-' }}</td>
                    </tr>
                    <tr>
                        <td>Employee ID</td>
                        <td style="font-weight: 500;">{{ $payslip->id ?? $payslip->staffid ?? $payslip->staffida ?? '-' }}</td>
                        <td></td>
                        <td>Client Company</td>
                        <td>{{ $payslip->brandname ?? $client->short_name ?? 'OFI Bean WH' }}</td>
                    </tr>
                    <tr>
                        <td>SSNIT No.</td>
                        <td>{{ (!empty($payslip->SSNIT) && $payslip->SSNIT !== '-') ? $payslip->SSNIT : ($payslip->ssnit_number ?? $staffSsnit ?? '-') }}</td>
                        <td></td>
                        <td>Department / Work Area</td>
                        <td>{{ (!empty($payslip->department) && $payslip->department !== 'Bean Warehouse') ? $payslip->department : '-' }}</td>
                    </tr>
                    <tr>
                        @if($hasBasic)
                            <td>Basic Salary</td>
                            <td>GHS {{ number_format((float)($payslip->raw_basic ?? $payslip->basic ?? 0), 2) }}</td>
                        @else
                            <td>Daily Rate</td>
                            <td>
                                @if($dailyRateVal > 0)
                                    GHS {{ number_format($dailyRateVal, 2) }}
                                @else
                                    Daily Rate
                                @endif
                            </td>
                        @endif
                        <td></td>
                        <td>Days Worked</td>
                        <td>{{ (int)($payslip->workdays ?? 0) }}</td>
                    </tr>

                    <!-- Table Header -->
                    <tr>
                        <th colspan="2" class="header text-center">Item</th>
                        <th class="header text-center">Description/Days/Hours</th>
                        <th class="header text-center">Earnings (GHS)</th>
                        <th class="header text-center">Deductions (GHS)</th>
                    </tr>

                    <!-- Basic Salary / Regular Hours / Weekday Tonnage / Daily Rate -->
                    @if($hasBasic)
                    <tr>
                        <td colspan="2">Basic Salary Earned</td>
                        <td class="text-right">{{ (int)($payslip->workdays ?? 0) }}</td>
                        <td class="text-center">{{ number_format((float)($payslip->basic_earned ?? $payslip->basic ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @elseif($weekdayHrs > 0)
                    <tr>
                        <td colspan="2">Regular Hours Pay</td>
                        <td class="text-right">{{ $weekdayHrs }} hrs</td>
                        <td class="text-center">{{ number_format($regularAmt, 2) }}</td>
                        <td></td>
                    </tr>
                    @elseif($weekdayTons > 0)
                    <tr>
                        <td colspan="2">Weekday Tonnage Pay</td>
                        <td class="text-right">{{ $weekdayTons }} tons</td>
                        <td class="text-center">{{ number_format($regularAmt, 2) }}</td>
                        <td></td>
                    </tr>
                    @elseif($regularAmt > 0 || (int)($payslip->workdays ?? 0) > 0)
                    <tr>
                        <td colspan="2">Daily Rate Earned</td>
                        <td class="text-right">{{ (int)($payslip->workdays ?? 0) }}</td>
                        <td class="text-center">{{ number_format($regularAmt, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif

                    <!-- Transport Allowance -->
                    @if($ftAmt > 0)
                    <tr>
                        <td colspan="2">Transport Allowance</td>
                        <td class="text-right">{{ (int)($payslip->workdays ?? 0) }}</td>
                        <td class="text-center">{{ number_format($ftAmt, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif

                    <!-- Weekday Overtime -->
                    @if((float)($payslip->normalovertime ?? 0) > 0 || (float)($payslip->weekdaysOT ?? 0) > 0)
                    <tr>
                        <td colspan="2">Weekday Overtime</td>
                        <td class="text-right">{{ (float)($payslip->weekdaysOT ?? 0) > 0 ? round((float)$payslip->weekdaysOT, 2) : '-' }}</td>
                        <td class="text-center">{{ number_format((float)($payslip->normalovertime ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif

                    <!-- Weekend Overtime / Weekend Tonnage Pay -->
                    @if($weekendAmt > 0)
                    <tr>
                        <td colspan="2">
                            @if($weekendTons > 0)
                                Weekend Tonnage Pay(Weekend Overtime)
                            @else
                                Weekend Overtime
                            @endif
                        </td>
                        <td class="text-right">
                            @if($weekendTons > 0)
                                {{ $weekendTons }}
                            @elseif($weekendHrs > 0)
                                {{ $weekendHrs }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="text-center">{{ number_format($weekendAmt, 2) }}</td>
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
                                <td class="text-center">{{ is_numeric($val) ? number_format((float)$val, 2) : $val }}</td>
                                <td></td>
                            </tr>
                            @endif
                        @endforeach
                    @endif

                    <!-- Gross Salary -->
                    <tr>
                        <td colspan="2" style="font-weight: bold;">Gross Salary</td>
                        <td></td>
                        <td class="text-center" style="font-weight: bold;">
                            {{ number_format((float)($payslip->grosspay ?? 0), 2) }}
                        </td>
                        <td></td>
                    </tr>

                    <!-- Deductions -->
                    <tr>
                        <td colspan="2">SSNIT EE (0.5%)</td>
                        <td></td>
                        <td></td>
                        <td class="text-center">{{ number_format((float)($payslip->ssnitee ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">Petra (5%)</td>
                        <td></td>
                        <td></td>
                        <td class="text-center">{{ number_format((float)($payslip->petra ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">Normal Tax (PAYE)</td>
                        <td></td>
                        <td></td>
                        <td class="text-center">{{ number_format((float)($payslip->paye ?? 0), 2) }}</td>
                    </tr>
                    @if((float)($payslip->otherdeduction ?? 0) > 0)
                    <tr>
                        <td colspan="2">Other Deductions</td>
                        <td></td>
                        <td></td>
                        <td class="text-center">{{ number_format((float)($payslip->otherdeduction ?? 0), 2) }}</td>
                    </tr>
                    @endif

                    <!-- Dynamic Custom Deductions -->
                    @if(isset($customColumns))
                        @foreach($customColumns->where('payslip_section', 'deductions') as $cc)
                            @php $val = data_get($payslip, $cc->column_name, 0); @endphp
                            @if((float)$val != 0 || (!is_numeric($val) && !empty($val)))
                            <tr>
                                <td colspan="2">{{ $cc->display_label }}</td>
                                <td></td>
                                <td></td>
                                <td class="text-center">{{ is_numeric($val) ? number_format((float)$val, 2) : $val }}</td>
                            </tr>
                            @endif
                        @endforeach
                    @endif

                    <!-- Total Row -->
                    <tr>
                        <td colspan="3" class="total">Total</td>
                        <td class="total text-center">
                            {{ number_format((float)($payslip->grosspay ?? 0), 2) }}
                        </td>
                        <td class="total text-center">
                            {{ number_format($totalDeductionsCalculated, 2) }}
                        </td>
                    </tr>

                    <!-- Bank Details and Net Pay -->
                    <tr>
                        <td>Bank Name</td>
                        <td>{{ $displayBankName }}</td>
                        <td></td>
                        <td colspan="2" class="net-pay">NET PAY</td>
                    </tr>
                    <tr>
                        <td>Bank Account Name</td>
                        <td>{{ $displayName }}</td>
                        <td></td>
                        <td colspan="2" class="net-pay">
                            GHS {{ number_format((float)($payslip->netpay ?? 0), 2) }}
                        </td>
                    </tr>
                    <tr>
                        <td>Bank Account #</td>
                        <td>{{ $displayAccount }}</td>
                        <td colspan="3"></td>
                    </tr>
                    <tr>
                        <td>Bank Branch</td>
                        <td>{{ $displayBranch }}</td>
                        <td colspan="3"></td>
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
