<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulk Payslips - {{ $formattedMonth }}</title>
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
                $payslip = $item['payslip'] ?? $item;
                $bank    = $item['bank'] ?? [];
                $logoSrc = !empty($logoData) ? $logoData : public_path('images/sbpafricalogo.png');
                $staffSsnit = $item['ssnit'] ?? ($payslip->ssnit ?? '-');
            @endphp
            <div class="payslip-wrapper">
                <table class="payslip-table">
                    <tr>
                        <td colspan="2" style="text-align:center;">
                            <img src="{{ $logoSrc }}" class="logo" alt="Company Logo">
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
                        <td class="bold">{{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? $payslip->fullname ?? '-' }}</td>
                        <td></td>
                        <td class="bold">Title</td>
                        <td class="bold">{{ $payslip->position ?? $payslip->jobtitle ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Employee ID</td>
                        <td class="bold">{{ $payslip->staffida ?? $payslip->staffid ?? $payslip->staff_id ?? $payslip->id ?? $payslip->employ ?? '-' }}</td>
                        <td></td>
                        <td class="bold">Client Company</td>
                        <td class="bold">{{ $payslip->brandname ?? $client->short_name ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="bold">SSNIT #</td>
                        <td class="bold">{{ !empty($staffSsnit) && $staffSsnit !== '-' ? $staffSsnit : ($payslip->ssnit ?? '-') }}</td>
                        <td></td>
                        <td class="bold">Department</td>
                        <td class="bold">{{ $payslip->department ?? $payslip->dept ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="bold3">Item</td>
                        <td class="bold3">Description/Days/Hours</td>
                        <td class="bold3">Earnings</td>
                        <td class="bold3">Deductions</td>
                    </tr>
                    <tr>
                        <td colspan="2">Basic Salary</td>
                        <td style="text-align:right;">{{ data_get($payslip, 'workdays', data_get($payslip, 'actualdays', 0)) ?: '-' }}</td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'basic_earned', data_get($payslip, 'basic', 0)), 2) }}</td>
                        <td></td>
                    </tr>
                    @if((float)data_get($payslip, 'fuel_and_rent_allowance', 0) > 0)
                    <tr>
                        <td colspan="2">Fuel & Rent Allowance</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'fuel_and_rent_allowance', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'allowance', 0) > 0 && (float)data_get($payslip, 'fuel_and_rent_allowance', 0) == 0)
                    <tr>
                        <td colspan="2">Allowance</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'allowance', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'normalovertime', 0) > 0)
                    <tr>
                        <td colspan="2">Normal Overtime</td>
                        <td style="text-align:right;">{{ data_get($payslip, 'weekdaysOT', data_get($payslip, 'normal_ot_hrs', 0)) }}</td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'normalovertime', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'saturdayovertime', 0) > 0)
                    <tr>
                        <td colspan="2">Saturday Overtime</td>
                        <td style="text-align:right;">{{ data_get($payslip, 'weekendOT', data_get($payslip, 'sat', 0)) }}</td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'saturdayovertime', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'holidayotamt', 0) > 0)
                    <tr>
                        <td colspan="2">Holiday Overtime</td>
                        <td style="text-align:right;">{{ data_get($payslip, 'holiday', 0) }}</td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'holidayotamt', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="2" class="bold">Gross Salary</td>
                        <td></td>
                        <td style="text-align:center;" class="bold">{{ number_format((float)data_get($payslip, 'grosspay', data_get($payslip, 'gross', 0)), 2) }}</td>
                        <td></td>
                    </tr>
                    @if((float)data_get($payslip, 'ssnitee', 0) > 0)
                    <tr>
                        <td colspan="2">SSNIT</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'ssnitee', 0), 2) }}</td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'petra', data_get($payslip, 'petrafund', 0)) > 0)
                    <tr>
                        <td colspan="2">Petra</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'petra', data_get($payslip, 'petrafund', 0)), 2) }}</td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'employeepf', data_get($payslip, 'provident_fund', 0)) > 0)
                    <tr>
                        <td colspan="2">Provident Fund</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'employeepf', data_get($payslip, 'provident_fund', 0)), 2) }}</td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'paye', 0) > 0)
                    <tr>
                        <td colspan="2">Normal Tax (PAYE)</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'paye', 0), 2) }}</td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'overtimetax', data_get($payslip, 'ottax', 0)) > 0)
                    <tr>
                        <td colspan="2">Overtime Tax</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'overtimetax', data_get($payslip, 'ottax', 0)), 2) }}</td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'advance', 0) > 0)
                    <tr>
                        <td colspan="2">Salary Advance</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'advance', 0), 2) }}</td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'otherdeduction', data_get($payslip, 'deductions', 0)) > 0)
                    <tr>
                        <td colspan="2">Other Deductions</td>
                        <td style="text-align:center;">{{ data_get($payslip, 'deduction_description', data_get($payslip, 'description', '')) }}</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'otherdeduction', data_get($payslip, 'deductions', 0)), 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="2" class="bold2">Total</td>
                        <td></td>
                        <td style="text-align:center;" class="bold2">{{ number_format((float)data_get($payslip, 'grosspay', data_get($payslip, 'gross', 0)), 2) }}</td>
                        <td style="text-align:center;" class="bold2">{{ number_format((float)data_get($payslip, 'totaldeductions', data_get($payslip, 'totaldeduct', 0)), 2) }}</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td colspan="2">NET PAY</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td colspan="2" class="bold2" style="font-size:10px;">{{ number_format((float)data_get($payslip, 'netpay', data_get($payslip, 'take_home_pay', 0)), 2) }}</td>
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
