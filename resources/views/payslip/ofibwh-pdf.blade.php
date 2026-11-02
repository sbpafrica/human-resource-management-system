<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $payslip->name ?? 'Employee' }} - {{ $formattedMonth ?? '' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
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
            font-size: 18px;
            background-color: #fff7ed;
            color: #c2410c;
            text-align: center;
        }
        .logo {
            width: 150px;
            height: auto;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <table>
        <!-- Header Row -->
        <tr>
            <td colspan="2">
                @if(file_exists(public_path('images/sbpafricalogo.png')))
                    <img src="{{ public_path('images/sbpafricalogo.png') }}" class="logo" alt="Company Logo">
                @else
                    <h3>SBP AFRICA</h3>
                @endif
            </td>
            <td colspan="1" class="text-center">
                <h2>SALARY SLIP</h2>
            </td>
            <td colspan="2" class="text-center">
                <h2>CONFIDENTIAL</h2>
            </td>
        </tr>

        <!-- Month -->
        <tr>
            <td colspan="2"></td>
            <td class="text-center" style="font-weight: bold; font-size: 14px;">
                {{ $formattedMonth ?? '-' }}
            </td>
            <td colspan="2"></td>
        </tr>

        <!-- Employee Info -->
        <tr>
            <td>Name</td>
            <td>{{ $payslip->name ?? '-' }}</td>
            <td></td>
            <td>Title</td>
            <td>{{ $payslip->position ?? 'Warehouse Staff' }}</td>
        </tr>
        <tr>
            <td>Employee ID</td>
            <td>{{ $payslip->id ?? $payslip->staffid ?? $payslip->staffida ?? '-' }}</td>
            <td></td>
            <td>Client Company</td>
            <td>{{ $payslip->brandname ?? 'OFI Bean Warehouse' }}</td>
        </tr>
        <tr>
            <td>SSNIT No.</td>
            <td>{{ (!empty($payslip->SSNIT) && $payslip->SSNIT !== '-') ? $payslip->SSNIT : ($payslip->ssnit_number ?? $ssnit ?? '-') }}</td>
            <td></td>
            <td>Department / Work Area</td>
            <td>{{ $payslip->department ?? 'Bean Warehouse' }}</td>
        </tr>
        @php
            $hasBasic = !empty($payslip->has_basic_salary) 
                ? true 
                : ((float)($payslip->raw_basic ?? 0) > 0 || ((float)($payslip->basic ?? 0) > 0 && empty($payslip->is_casual) && (float)($payslip->weekdayhr ?? 0) == 0 && (float)($payslip->weekdaytonage ?? 0) == 0 && (float)($payslip->amt ?? 0) == 0));
            $dailyRateVal = (float)($payslip->daily_rate ?? $payslip->rate ?? $payslip->weekdayrate ?? 0);
            $regularAmt = (float)($payslip->amt ?? $payslip->basic_earned ?? 0);
            $weekdayHrs = (float)($payslip->weekdayhr ?? 0);
            $weekdayTons = (float)($payslip->weekdaytonage ?? 0);
        @endphp
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
            <td>{{ $payslip->workdays ?? 0 }}</td>
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
            <td class="text-right">{{ (int)($payslip->workdays ?? 0) }} days</td>
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
            <td class="text-right">{{ (int)($payslip->workdays ?? 0) }} days</td>
            <td class="text-center">{{ number_format($regularAmt, 2) }}</td>
            <td></td>
        </tr>
        @endif

        <!-- Feeding & Transport Allowance (f&tamount) -->
        @php
            $ftAmt = (float)($payslip->ftamount ?? $payslip->fuel_and_rent_allowance ?? $payslip->allowance ?? 0);
        @endphp
        @if($ftAmt > 0)
        <tr>
            <td colspan="2">Feeding & Transport Allowance</td>
            <td class="text-right">{{ (int)($payslip->workdays ?? 0) }} days</td>
            <td class="text-center">{{ number_format($ftAmt, 2) }}</td>
            <td></td>
        </tr>
        @endif

        <!-- Weekday Overtime (weekdayotamt1) -->
        @if((float)($payslip->normalovertime ?? 0) > 0 || (float)($payslip->weekdaysOT ?? 0) > 0)
        <tr>
            <td colspan="2">Normal Overtime (Weekday)</td>
            <td class="text-right">{{ (float)($payslip->weekdaysOT ?? 0) > 0 ? round((float)$payslip->weekdaysOT, 2) . ' hrs' : '-' }}</td>
            <td class="text-center">{{ number_format((float)($payslip->normalovertime ?? 0), 2) }}</td>
            <td></td>
        </tr>
        @endif

        <!-- Weekend Overtime / Weekend Tonnage (amount) -->
        @php
            $weekendHrs = (float)($payslip->weekendOT ?? $payslip->weekendhr ?? 0);
            $weekendTons = (float)($payslip->weekendtonage ?? 0);
            $weekendAmt = (float)($payslip->saturdayovertime ?? $payslip->amount ?? 0);
        @endphp
        @if($weekendAmt > 0)
        <tr>
            <td colspan="2">
                @if($weekendTons > 0)
                    Weekend Tonnage Pay
                @else
                    Weekend Overtime
                @endif
            </td>
            <td class="text-right">
                @if($weekendTons > 0)
                    {{ $weekendTons }} tons
                @elseif($weekendHrs > 0)
                    {{ $weekendHrs }} hrs
                @else
                    -
                @endif
            </td>
            <td class="text-center">{{ number_format($weekendAmt, 2) }}</td>
            <td></td>
        </tr>
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
            <td colspan="2">SSNIT EE (5.5%)</td>
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

        @php
            $totalDeductionsCalculated = (float)($payslip->ssnitee ?? 0) 
                + (float)($payslip->petra ?? 0) 
                + (float)($payslip->paye ?? 0) 
                + (float)($payslip->otherdeduction ?? 0);

            if ($totalDeductionsCalculated == 0) {
                $totalDeductionsCalculated = (float)($payslip->totaldeductions ?? $payslip->totaltaxdeduction ?? 0);
            }
        @endphp

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

        @php
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
</body>
</html>
