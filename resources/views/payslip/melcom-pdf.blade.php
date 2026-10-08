<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $payslip->empoyeename ?? $payslip->name ?? 'Employee' }} - {{ $formattedMonth ?? '' }}</title>
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
            <td>{{ $payslip->empoyeename ?? $payslip->name ?? '-' }}</td>
            <td></td>
            <td>Title</td>
            <td>{{ $payslip->designation ?? $payslip->position ?? '-' }}</td>
        </tr>
        <tr>
            <td>Employee ID</td>
            <td>{{ $payslip->employ ?? $payslip->staffid ?? $payslip->staffida ?? '-' }}</td>
            <td></td>
            <td>Client Company</td>
            <td>{{ $payslip->shopname ?? '' }} Melcom</td>
        </tr>
        <tr>
            <td>SSNIT</td>
            <td>{{ $ssnit ?? '-' }}</td>
            <td></td>
            <td>Department</td>
            <td>{{ $payslip->department ?? '-' }}</td>
        </tr>
        <tr>
            <td>Basic Salary</td>
            <td>{{ number_format((float)($payslip->basic ?? 0), 2) }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>

        <!-- Table Header -->
        <tr>
            <th colspan="2" class="header text-center">Item</th>
            <th class="header text-center">Description/Days/Hours</th>
            <th class="header text-center">Earnings</th>
            <th class="header text-center">Deductions</th>
        </tr>

        <!-- Earnings -->
        <tr>
            <td colspan="2">Basic Salary</td>
            <td class="text-right">{{ $payslip->actualdays ?? $payslip->workdays ?? 0 }}</td>
            <td class="text-center">{{ number_format((float)($payslip->basicentitlement ?? $payslip->basic ?? 0), 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Absent Days</td>
            <td class="text-right">{{ $payslip->absentdays ?? 0 }}</td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->absentwithoutpay ?? 0), 2) }}</td>
        </tr>
        
        <!-- Normal Overtime -->
        <tr>
            <td colspan="2">Normal Overtime</td>
            <td class="text-right">{{ $payslip->weekdaysOT ?? 0 }}</td>
            <td class="text-center">
                {{ number_format((float)($payslip->normalovertime ?? $payslip->normal_overtime ?? 0), 2) }}
            </td>
            <td></td>
        </tr>

        <!-- Saturday & Sunday Overtime -->
        <tr>
            <td colspan="2">Saturday & Sunday Overtime</td>
            <td class="text-right">{{ $payslip->weekendOT ?? 0 }}</td>
            <td class="text-center">{{ number_format((float)($payslip->saturdayovertime ?? 0), 2) }}</td>
            <td></td>
        </tr>

        <!-- Holiday Overtime -->
        <tr>
            <td colspan="2">Holiday Overtime</td>
            <td class="text-right">{{ $payslip->holiday ?? 0 }}</td>
            <td class="text-center">{{ number_format((float)($payslip->holidayotamt ?? 0), 2) }}</td>
            <td></td>
        </tr>

        <!-- Transport Allowance -->
        <tr>
            <td colspan="2">Transport Allowance</td>
            <td class="text-right">{{ $payslip->actualdays ?? $payslip->workdays ?? 0 }}</td>
            <td class="text-center">{{ number_format((float)($payslip->allowance ?? $payslip->transport ?? 0), 2) }}</td>
            <td></td>
        </tr>

        <!-- Gross Salary -->
        <tr>
            <td colspan="2" style="font-weight: bold;">Gross Salary</td>
            <td></td>
            <td class="text-center" style="font-weight: bold;">
                {{ number_format((float)($payslip->gross ?? $payslip->grosspay ?? 0), 2) }}
            </td>
            <td></td>
        </tr>

        <!-- Deductions -->
        <tr>
            <td colspan="2">Recovery To Shop (Trust)</td>
            <td></td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->trust ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">SSNIT</td>
            <td></td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->ssnitee ?? $payslip->ssnit ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Petra</td>
            <td></td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->petra ?? $payslip->petrafund ?? $payslip->tier3 ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Normal Tax (PAYE)</td>
            <td></td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->paye ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Overtime Tax</td>
            <td></td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->overtimetax ?? 0), 2) }}</td>
        </tr>

        <!-- Total Row -->
        <tr>
            <td colspan="3" class="total">Total</td>
            <td class="total text-center">
                {{ number_format((float)($payslip->gross ?? $payslip->grosspay ?? 0), 2) }}
            </td>
            <td class="total text-center">
                {{ number_format((float)($payslip->totaldeduction ?? $payslip->otherdeduction ?? 0), 2) }}
            </td>
        </tr>

        <!-- Net Pay Section -->
        <tr>
            <td>Bank Name</td>
            <td>{{ data_get($bank, 'bankname', '-') }}</td>
            <td></td>
            <td colspan="2" class="net-pay">NET PAY</td>
        </tr>
        <tr>
            <td>Bank Account Name</td>
            <td>{{ data_get($bank, 'name', '-') }}</td>
            <td></td>
            <td colspan="2" class="net-pay">
                {{ number_format((float)($payslip->banktransfer ?? $payslip->netpay ?? 0), 2) }}
            </td>
        </tr>
        <tr>
            <td>Bank Account #</td>
            <td>{{ data_get($bank, 'alcnum', '-') }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Bank Branch</td>
            <td>{{ data_get($bank, 'branch', '-') }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>
</body>
</html>