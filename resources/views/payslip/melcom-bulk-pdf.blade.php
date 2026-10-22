<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Melcom Payslips - {{ $formattedMonth }}</title>
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
                $payslip  = $item['payslip'] ?? $item;
                $bank     = $item['bank'] ?? [];
                $logoSrc  = !empty($logoData) ? $logoData : public_path('images/sbpafricalogo.png');
                $staffSsnit = $item['ssnit'] ?? ($payslip->ssnit ?? '-');
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
                        <td class="bold">{{ $payslip->name ?? $payslip->names ?? $payslip->empoyeename ?? '-' }}</td>
                        <td></td>
                        <td class="bold">DEPARTMENT</td>
                        <td class="bold">{{ $payslip->dept ?? $payslip->department ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Employee ID</td>
                        <td class="bold">{{ $payslip->employ ?? $payslip->staffid ?? $payslip->staffida ?? $payslip->id ?? '-' }}</td>
                        <td></td>
                        <td class="bold">Client Company</td>
                        <td class="bold">MELCOM</td>
                    </tr>
                    <tr>
                        <td class="bold">Basic</td>
                        <td class="bold">{{ number_format((float)($payslip->salary ?: ($payslip->basic ?: 0)), 2) }}</td>
                        <td></td>
                        <td class="bold"></td>
                        <td class="bold"></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="bold3">Item</td>
                        <td class="bold3">Description/Days/Hours</td>
                        <td class="bold3">Earnings</td>
                        <td class="bold3">Deductions</td>
                    </tr>
                    <tr>
                        <td colspan="2">Basic Salary</td>
                        <td style="text-align:right;">{{ $payslip->actualdays ?? $payslip->workdays ?? 0 }}</td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->basicentitlement ?? $payslip->basic ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @if((float)($payslip->absentwithoutpay ?? 0) > 0 || (float)($payslip->absentdays ?? 0) > 0)
                    <tr>
                        <td colspan="2">Absent Days</td>
                        <td style="text-align:right;">{{ $payslip->absentdays ?? 0 }}</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->absentwithoutpay ?? 0), 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="2">Normal Overtime</td>
                        <td style="text-align:right;">{{ $payslip->weekdaysOT ?? 0 }}</td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->normalovertime ?? $payslip->normal_overtime ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">Saturday & Sunday Overtime</td>
                        <td style="text-align:right;">{{ $payslip->weekendOT ?? 0 }}</td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->saturdayovertime ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">Holiday Overtime</td>
                        <td style="text-align:right;">{{ $payslip->holiday ?? 0 }}</td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->holidayotamt ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">Transport Allowance</td>
                        <td style="text-align:right;">{{ $payslip->actualdays ?? $payslip->workdays ?? 0 }}</td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->allowance ?? $payslip->transport ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="bold">Gross Salary</td>
                        <td></td>
                        <td style="text-align:center;" class="bold">{{ number_format((float)($payslip->gross ?? $payslip->grosspay ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @if((float)($payslip->trust ?? 0) > 0)
                    <tr>
                        <td colspan="2">Recovery To Shop (Trust)</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)$payslip->trust, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="2">SSNIT</td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->ssnitee ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">Petra</td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->petra ?? $payslip->petrafund ?? $payslip->tier3 ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">Normal Tax (PAYE)</td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->paye ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">Overtime Tax</td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->overtimetax ?? $payslip->ottax ?? 0), 2) }}</td>
                    </tr>
                    @if(!empty($payslip->advance) && (float)$payslip->advance > 0)
                    <tr>
                        <td colspan="2">Salary Advance</td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format((float)$payslip->advance, 2) }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td colspan="2" class="bold2">Total</td>
                        <td></td>
                        <td style="text-align:center;" class="bold2">{{ number_format((float)($payslip->gross ?? $payslip->grosspay ?? 0), 2) }}</td>
                        <td style="text-align:center;" class="bold2">{{ number_format((float)($payslip->totaldeduction ?? $payslip->totaldeduct ?? $payslip->total_deductions ?? 0), 2) }}</td>
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
                        <td colspan="2" class="bold2" style="font-size:10px;">{{ number_format((float)($payslip->banktransfer ?? $payslip->netpay ?? 0), 2) }}</td>
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
