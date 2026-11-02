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
            <td>{{ $payslip->name ?? $payslip->names ?? '-' }}</td>
            <td></td>
            <td>Title</td>
            <td>{{ $payslip->position ?? $payslip->dept ?? '-' }}</td>
        </tr>
        <tr>
            <td>Employee ID</td>
            <td>{{ $payslip->id ?? $payslip->staffid ?? $payslip->staffida ?? '-' }}</td>
            <td></td>
            <td>Client Company</td>
            <td>{{ $payslip->brandname ?? 'OFI Tempering' }}</td>
        </tr>
        <tr>
            <td>SSNIT No.</td>
            <td>{{ (!empty($payslip->SSNIT) && $payslip->SSNIT !== '-') ? $payslip->SSNIT : ($payslip->ssnit_number ?? $ssnit ?? '-') }}</td>
            <td></td>
            <td>Department / Work Area</td>
            <td>{{ $payslip->department ?? $payslip->dept ?? '-' }}</td>
        </tr>
        <tr>
            <td>Daily Rate</td>
            <td>GHS {{ number_format((float)($payslip->rate ?? $payslip->BASIC_SALARY_GHC ?? 0), 2) }}</td>
            <td></td>
            <td>Days Worked</td>
            <td>{{ $payslip->workdays ?? $payslip->noofdays ?? $payslip->total1 ?? 0 }}</td>
        </tr>

        <!-- Table Header -->
        <tr>
            <th colspan="2" class="header text-center">Item</th>
            <th class="header text-center">Description/Days/Hours</th>
            <th class="header text-center">Earnings (GHS)</th>
            <th class="header text-center">Deductions (GHS)</th>
        </tr>

        <!-- Basic Salary Earned -->
        <tr>
            <td colspan="2">Basic Salary Earned</td>
            <td class="text-right">{{ (int)($payslip->workdays ?? $payslip->noofdays ?? $payslip->total1 ?? 0) }} days</td>
            <td class="text-center">{{ number_format((float)($payslip->BASIC_SALARY_EARNED_GHC ?? $payslip->basic_earned ?? $payslip->basic ?? $payslip->total2 ?? 0), 2) }}</td>
            <td></td>
        </tr>

        <!-- Normal Overtime (Weekday) -->
        <tr>
            <td colspan="2">Normal Overtime (Weekday)</td>
            <td class="text-right">{{ (float)($payslip->weekdaysOT ?? 0) == 0 ? '-' : round((float)($payslip->weekdaysOT ?? 0), 2) . ' hrs' }}</td>
            <td class="text-center">
                {{ number_format((float)($payslip->normalovertime ?? 0), 2) }}
            </td>
            <td></td>
        </tr>

        <!-- Weekend Overtime -->
        <tr>
            <td colspan="2">Weekend Overtime</td>
            <td class="text-right">{{ (float)($payslip->weekendOT ?? 0) == 0 ? '-' : round((float)($payslip->weekendOT ?? 0), 2) . ' hrs' }}</td>
            <td class="text-center">{{ number_format((float)($payslip->saturdayovertime ?? 0), 2) }}</td>
            <td></td>
        </tr>

        <!-- Transport Allowance -->
        <tr>
            <td colspan="2">Transport Allowance</td>
            <td class="text-right">{{ (int)($payslip->workdays ?? $payslip->noofdays ?? 0) }} days</td>
            <td class="text-center">{{ number_format((float)($payslip->transport ?? $payslip->allowance ?? 0), 2) }}</td>
            <td></td>
        </tr>

        <!-- Night Allowance (if any) -->
        @if((float)($payslip->nightallowance ?? 0) > 0)
        <tr>
            <td colspan="2">Night Allowance</td>
            <td class="text-right">{{ (int)($payslip->night ?? 0) }} shifts</td>
            <td class="text-center">{{ number_format((float)($payslip->nightallowance ?? 0), 2) }}</td>
            <td></td>
        </tr>
        @endif

        <!-- Gross Salary -->
        <tr>
            <td colspan="2" style="font-weight: bold;">Gross Salary</td>
            <td></td>
            <td class="text-center" style="font-weight: bold;">
                {{ number_format((float)($payslip->grosspay ?? $payslip->totalamount ?? 0), 2) }}
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
                {{ number_format((float)($payslip->grosspay ?? $payslip->totalamount ?? 0), 2) }}
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
                : ($payslip->name ?? $payslip->names ?? '-');

            $displayAccount = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') 
                ? data_get($bank, 'alcnum') 
                : ($payslip->accountnumber ?? $payslip->account1 ?? '-');

            $displayBranch = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') 
                ? data_get($bank, 'branch') 
                : ($payslip->branch ?? $payslip->branch1 ?? '-');
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
                GHS {{ number_format((float)($payslip->netpay ?? $payslip->takehome ?? 0), 2) }}
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
