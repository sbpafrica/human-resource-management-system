<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Melcom Drivers Payslips - {{ $formattedMonth }}</title>
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
        table.payslip-table td {
            border: 1px solid #777;
            padding: 2.2px 5px;
            font-size: 8.5px;
            line-height: 1.15;
        }
        .orange {
            background-color: #fff;
            color: #000;
            font-weight: 900;
            font-size: 11px;
            text-align: center;
        }
        .orange2 {
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
                $payslip = is_array($item) && isset($item['payslip']) ? $item['payslip'] : (is_object($item) && isset($item->payslip) ? $item->payslip : $item);
                $gross   = (float)($payslip->basic ?? 0)
                         + (float)($payslip->allowance ?? 0)
                         + (float)($payslip->satOT ?? 0);

                $basicEarned = (float)($payslip->basic ?? 0);
                $totalSsf    = (float)($payslip->ssfyeed ?? $payslip->ssfyee ?? 0);
                $petraAmt    = (isset($payslip->petra) && (float)$payslip->petra > 0)
                                ? (float)$payslip->petra
                                : round($basicEarned * 0.05, 2);
                $ssnitAmt    = ($totalSsf > 0)
                                ? round($totalSsf - $petraAmt, 2)
                                : round($basicEarned * 0.005, 2);

                $logoSrc = !empty($logoData) ? $logoData : public_path('images/sbpafricalogo.png');
            @endphp
            <div class="payslip-wrapper">
                <table class="payslip-table">
                    <tr>
                        <td colspan="2" style="text-align:center;">
                            <img src="{{ $logoSrc }}" class="logo" alt="SBP Africa Logo">
                        </td>
                        <td width="25%" class="orange">SALARY SLIP</td>
                        <td width="35%" colspan="2" class="orange2">CONFIDENTIAL</td>
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
                        <td class="bold">DEPARTMENT</td>
                        <td class="bold">{{ $payslip->dept ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Employee ID</td>
                        <td class="bold">{{ $payslip->employ ?? $payslip->staffid ?? $payslip->staffida ?? $payslip->staff_id ?? $payslip->id ?? '-' }}</td>
                        <td></td>
                        <td class="bold">Client Company</td>
                        <td class="bold">MELCOM</td>
                    </tr>
                    <tr>
                        <td class="bold">Basic</td>
                        <td class="bold">{{ number_format((float)($payslip->new_basic ?? $payslip->salary ?? $payslip->basic ?? 0), 2) }}</td>
                        <td></td>
                        <td class="bold"></td>
                        <td class="bold"></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="bold3">Item</td>
                        <td class="bold3">Description/Days</td>
                        <td class="bold3">Earnings</td>
                        <td class="bold3">Deductions</td>
                    </tr>
                    <tr>
                        <td colspan="2">Basic Salary</td>
                        <td style="text-align:right;">{{ $payslip->week ?? '' }}</td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->basic ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">Allowance</td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->allowance ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">Saturday Overtime</td>
                        <td style="text-align:right;">{{ $payslip->sat ?? '' }}</td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->satOT ?? 0), 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2" class="bold">Gross Salary</td>
                        <td></td>
                        <td style="text-align:center;" class="bold">{{ number_format($gross, 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">SSNIT</td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format($ssnitAmt, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">Petra</td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format($petraAmt, 2) }}</td>
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
                        <td colspan="2">Normal Tax-Regular</td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->paye ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">Overtime Tax</td>
                        <td style="text-align:right;"></td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format((float)($payslip->ottax ?? 0), 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="bold2">Total</td>
                        <td></td>
                        <td style="text-align:center;" class="bold2">{{ number_format($gross, 2) }}</td>
                        <td style="text-align:center;" class="bold2">{{ number_format((float)($payslip->totaldeduct ?? 0), 2) }}</td>
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
                        <td colspan="2" class="bold2" style="font-size:10px;">{{ number_format((float)($payslip->netpay ?? 0), 2) }}</td>
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
