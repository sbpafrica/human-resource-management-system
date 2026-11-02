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
            <td>{{ $payslip->position ?? '-' }}</td>
        </tr>
        <tr>
            <td>Employee ID</td>
            <td>{{ $payslip->staffid ?? $payslip->staffida ?? '-' }}</td>
            <td></td>
            <td>Client Company</td>
            <td>{{ $payslip->brandname ?? 'Avara RC Limited' }}</td>
        </tr>
        <tr>
            <td>SSNIT No.</td>
            <td>{{ (!empty($payslip->SSNIT) && $payslip->SSNIT !== '-') ? $payslip->SSNIT : ($payslip->ssnit_number ?? $ssnit ?? '-') }}</td>
            <td></td>
            <td>Department / Shop</td>
            <td>{{ $payslip->department ?? $payslip->SHOP ?? '-' }}</td>
        </tr>
        <tr>
            <td>Basic Salary</td>
            <td>{{ number_format((float)($payslip->BASIC_SALARY_GHC ?? $payslip->basic ?? $payslip->BASIC_SALARY_EARNED_GHC ?? 0), 2) }}</td>
            <td></td>
            <td>Days Worked</td>
            <td>{{ $payslip->workdays ?? $payslip->NUMBER_OF_DAYS_WORKED ?? 0 }}</td>
        </tr>

        <!-- Table Header -->
        <tr>
            <th colspan="2" class="header text-center">Item</th>
            <th class="header text-center">Description/Days/Hours</th>
            <th class="header text-center">Earnings (GHS)</th>
            <th class="header text-center">Deductions (GHS)</th>
        </tr>

        <!-- Earnings -->
        <tr>
            <td colspan="2">Basic Salary Earned</td>
            <td class="text-right">{{ (int)($payslip->workdays ?? $payslip->NUMBER_OF_DAYS_WORKED ?? 0) }}</td>
            <td class="text-center">{{ number_format((float)($payslip->BASIC_SALARY_EARNED_GHC ?? $payslip->basic_earned ?? $payslip->basic ?? 0), 2) }}</td>
            <td></td>
        </tr>
        @if(($payslip->absentdays ?? $payslip->ABSENT_DAYS ?? 0) > 0 || ($payslip->absentwithoutpay ?? $payslip->ABSENT_DAYS_DEDUCTIONS_GHC ?? 0) > 0)
        <tr>
            <td colspan="2">Absent Days Deduction</td>
            <td class="text-right">{{ (int)($payslip->absentdays ?? $payslip->ABSENT_DAYS ?? 0) }}</td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->absentwithoutpay ?? $payslip->ABSENT_DAYS_DEDUCTIONS_GHC ?? 0), 2) }}</td>
        </tr>
        @endif
        
        <!-- Normal Overtime -->
        <tr>
            <td colspan="2">Normal Overtime</td>
            <td class="text-right">{{ (float)($payslip->weekdaysOT ?? $payslip->WEEKDAY_OVERTIME_HRS ?? 0) == 0 ? 0 : round((float)($payslip->weekdaysOT ?? $payslip->WEEKDAY_OVERTIME_HRS ?? 0), 2) }}</td>
            <td class="text-center">
                {{ number_format((float)($payslip->normalovertime ?? $payslip->WEEKDAY_OVERTIME_AMOUNT_GHC ?? 0), 2) }}
            </td>
            <td></td>
        </tr>

        @php
            $basicSal = (float)($payslip->BASIC_SALARY_GHC ?? $payslip->basic ?? 0);
            $legalDays = (float)($payslip->LEGAL_WORK_DAYS ?? 26);
            $dailyRate = ($basicSal > 0 && $legalDays > 0) ? ($basicSal / $legalDays) : 0;
            
            $weekendOtAmt = (float)($payslip->saturdayovertime ?? $payslip->WEEKEND_OVERTIME_AMOUNT_GHC ?? 0);
            if ($weekendOtAmt > 0 && $dailyRate > 0) {
                $rawWeekendDays = $weekendOtAmt / $dailyRate;
                $weekendDaysFormatted = (abs($rawWeekendDays - round($rawWeekendDays)) < 0.15) ? (int)round($rawWeekendDays) : round($rawWeekendDays, 2);
            } else {
                $weekendDaysFormatted = 0;
            }

            $transportAmt = (float)($payslip->allowance ?? $payslip->TRANSPORT_GHC ?? 0);
            $transportDaysFormatted = ($transportAmt > 0) ? (int)($payslip->workdays ?? $payslip->NUMBER_OF_DAYS_WORKED ?? 0) : 0;
        @endphp

        <!-- Weekend Overtime -->
        <tr>
            <td colspan="2">Weekend Overtime</td>
            <td class="text-right">{{ $weekendDaysFormatted }}</td>
            <td class="text-center">{{ number_format((float)($payslip->saturdayovertime ?? $payslip->WEEKEND_OVERTIME_AMOUNT_GHC ?? 0), 2) }}</td>
            <td></td>
        </tr>

        <!-- Holiday Overtime -->
        <tr>
            <td colspan="2">Holiday Overtime</td>
            <td class="text-right">{{ (float)($payslip->holiday ?? $payslip->HOLIDAY_OVERTIME_HRS ?? 0) == 0 ? 0 : round((float)($payslip->holiday ?? $payslip->HOLIDAY_OVERTIME_HRS ?? 0), 2) }}</td>
            <td class="text-center">{{ number_format((float)($payslip->holidayotamt ?? $payslip->HOLIDAY_OVERTIME_AMOUNT_GHC ?? 0), 2) }}</td>
            <td></td>
        </tr>

        <!-- Transport Allowance -->
        <tr>
            <td colspan="2">Transport Allowance</td>
            <td class="text-right">{{ $transportDaysFormatted }}</td>
            <td class="text-center">{{ number_format((float)($payslip->allowance ?? $payslip->TRANSPORT_GHC ?? 0), 2) }}</td>
            <td></td>
        </tr>

        <!-- Gross Salary -->
        <tr>
            <td colspan="2" style="font-weight: bold;">Gross Salary</td>
            <td></td>
            <td class="text-center" style="font-weight: bold;">
                {{ number_format((float)($payslip->grosspay ?? $payslip->GROSS_SALARY_GHC ?? 0), 2) }}
            </td>
            <td></td>
        </tr>

        <!-- Deductions -->
        <tr>
            <td colspan="2">SSNIT EE (0.5%)</td>
            <td></td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->ssnitee ?? $payslip->SSNIT_EE_0_5_PERCENT ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Petra (5%)</td>
            <td></td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->petra ?? $payslip->PETRA_5_PERCENT ?? 0), 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Normal Tax (PAYE)</td>
            <td></td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->paye ?? $payslip->PAYE_GHC ?? 0), 2) }}</td>
        </tr>
        @if(($payslip->overtimetax ?? $payslip->OVERTIME_TAX_GHC ?? 0) > 0)
        <tr>
            <td colspan="2">Overtime Tax</td>
            <td></td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->overtimetax ?? $payslip->OVERTIME_TAX_GHC ?? 0), 2) }}</td>
        </tr>
        @endif
        @if(($payslip->otherdeduction ?? $payslip->DEDUCTIONS_SURCHARGE_STUDENT_LOAN_GHC ?? 0) > 0)
        <tr>
            <td colspan="2">Other Deductions / Student Loan</td>
            <td></td>
            <td></td>
            <td class="text-center">{{ number_format((float)($payslip->otherdeduction ?? $payslip->DEDUCTIONS_SURCHARGE_STUDENT_LOAN_GHC ?? 0), 2) }}</td>
        </tr>
        @endif

        @php
            $totalDeductionsCalculated = (float)($payslip->ssnitee ?? 0) 
                + (float)($payslip->petra ?? 0) 
                + (float)($payslip->paye ?? 0) 
                + (float)($payslip->overtimetax ?? 0) 
                + (float)($payslip->otherdeduction ?? 0);

            if ($totalDeductionsCalculated == 0) {
                $totalDeductionsCalculated = (float)($payslip->totaldeductions ?? $payslip->TOTAL_DEDUCTIONS_GHC ?? $payslip->TOTALDEDUCTIONS_GHC ?? 0);
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

        <!-- Net Pay Section -->
        <tr>
            <td>Bank Name</td>
            <td>{{ (!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') ? data_get($bank, 'bankname') : ($payslip->BANK ?? $payslip->bankname ?? $payslip->bank ?? '-') }}</td>
            <td></td>
            <td colspan="2" class="net-pay">NET PAY</td>
        </tr>
        <tr>
            <td>Bank Account Name</td>
            <td>{{ (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') ? data_get($bank, 'name') : ($payslip->NAME ?? $payslip->name ?? '-') }}</td>
            <td></td>
            <td colspan="2" class="net-pay">
                {{ number_format((float)($payslip->netpay ?? 0), 2) }}
            </td>
        </tr>
        <tr>
            <td>Bank Account #</td>
            <td>{{ (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') ? data_get($bank, 'alcnum') : ($payslip->ACCOUNT_NUMBER ?? $payslip->account_number ?? $payslip->alcnum ?? '-') }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Bank Branch</td>
            <td>{{ (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') ? data_get($bank, 'branch') : ($payslip->BRANCH ?? $payslip->branch ?? '-') }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>
</body>
</html>
