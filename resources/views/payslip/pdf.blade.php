<!DOCTYPE html>
<html>
<head>
    <title>Payslip - {{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? $payslip->fullname ?? $payslip->name1 ?? 'Employee' }} - {{ $formattedMonth }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .header { font-weight: bold;  }
        .total { font-weight: bold;}
        .net-pay { font-weight: bold;  }
        .logo { width: 150px; height: auto; }
    </style>
</head>
<body>
@php
    $displayBankName = (!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') 
        ? data_get($bank, 'bankname') 
        : ($payslip->bank ?? $payslip->bankname ?? $payslip->bank1 ?? '-');
    $displayAccountName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') 
        ? data_get($bank, 'name') 
        : ($payslip->name ?? $payslip->names ?? $payslip->employeename ?? '-');
    $displayAccountNo = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') 
        ? data_get($bank, 'alcnum') 
        : ($payslip->accountnumber ?? $payslip->account_number ?? $payslip->alcnum ?? $payslip->account1 ?? '-');
    $displayBranch = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') 
        ? data_get($bank, 'branch') 
        : ($payslip->branch ?? $payslip->branch1 ?? '-');

    $logoData = $logoData ?? (file_exists(public_path('images/sbpafricalogo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/sbpafricalogo.png'))) : null);
@endphp
    <table>
        <tr>
            <td colspan="2">
                @if($logoData)
                    <img src="{{ $logoData }}" class="logo" alt="Company Logo">
                @else
                    <h3 style="color: #0359c9; margin: 0;">SBP AFRICA</h3>
                @endif
            </td>
            <td colspan="1" style="text-align: center;"><h2>SALARY SLIP</h2></td>
            <td colspan="2" style="text-align: center;"><h2>CONFIDENTIAL</h2></td>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td style="text-align: center;">{{ $formattedMonth }}</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td>Name</td>
            <td>{{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? $payslip->fullname ?? $payslip->name1 ?? '-' }}</td>
            <td></td>
            <td>Title</td>
            <td>{{ $payslip->position ?? $payslip->jobtitle ?? '-' }}</td>
        </tr>
        <tr>
            <td>Employee ID</td>
            <td>{{ $payslip->staffida ?? $payslip->staffid ?? $payslip->staff_id ?? $payslip->id ?? '-' }}</td>
            <td></td>
            <td>Client Company</td>
            <td>{{ $payslip->brandname ?? $payslip->company ?? $payslip->brand ?? '-' }}</td>
        </tr>
        <tr>
            <td>SSNIT #</td>
            <td>{{ !empty($ssnit) && $ssnit !== '-' ? $ssnit : ($payslip->ssnit_number ?? $payslip->SSNIT ?? $payslip->ssnit12 ?? '-') }}</td>
            <td></td>
            <td>Department</td>
            <td>{{ $payslip->department ?? $payslip->dept ?? $payslip->workarea ?? $payslip->work_location ?? '-' }}</td>
        </tr>
        @if(!empty($payslip->start_date) && $payslip->start_date !== '-')
        <tr>
            <td>Start Date</td>
            <td>{{ $payslip->start_date }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        @endif
        <tr>
            <th colspan="2" class="header" style="text-align: center;">Item</th>
            <th class="header" style="text-align: center;">Description/Days/Hours</th>
            <th class="header" style="text-align: center;">Earnings</th>
            <th class="header" style="text-align: center;">Deductions</th>
        </tr>
        <tr>
            <td colspan="2">Basic Salary</td>
            <td style="text-align: right;">{{ data_get($payslip, 'workdays', 0) ?: (data_get($payslip, 'description') ?: (data_get($payslip, 'start_date') ? 'Start: ' . data_get($payslip, 'start_date') : '')) }}</td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'basic_earned', data_get($payslip, 'basic', 0)), 2) }}</td>
            <td></td>
        </tr>
        @if((float)data_get($payslip, 'fuel_and_rent_allowance', 0) > 0)
        <tr>
            <td colspan="2">Fuel & Rent Allowance</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'fuel_and_rent_allowance', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'transport', 0) > 0)
        <tr>
            <td colspan="2">Transport Allowance</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'transport', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'nightallowance', 0) > 0)
        <tr>
            <td colspan="2">Night Allowance</td>
            <td style="text-align: right;">{{ data_get($payslip, 'night', 0) ?: '-' }}</td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'nightallowance', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'medical', 0) > 0)
        <tr>
            <td colspan="2">Medical Allowance</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'medical', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'leave1', 0) > 0)
        <tr>
            <td colspan="2">Leave Allowance</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'leave1', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'general_allowance', 0) > 0 && (float)data_get($payslip, 'fuel_and_rent_allowance', 0) == 0 && (float)data_get($payslip, 'transport', 0) == 0)
        <tr>
            <td colspan="2">Allowance</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'general_allowance', 0), 2) }}</td>
            <td></td>
        </tr>
        @elseif((float)data_get($payslip, 'allowance', 0) > 0 && (float)data_get($payslip, 'fuel_and_rent_allowance', 0) == 0 && (float)data_get($payslip, 'transport', 0) == 0 && (float)data_get($payslip, 'nightallowance', 0) == 0 && (float)data_get($payslip, 'medical', 0) == 0 && (float)data_get($payslip, 'leave1', 0) == 0)
        <tr>
            <td colspan="2">Allowance</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'allowance', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'performstand', 0) > 0)
        <tr>
            <td colspan="2">Performance Bonus</td>
            <td style="text-align: right;">{{ data_get($payslip, 'coefficient', 0) ?: '-' }}</td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'performstand', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'normalovertime', data_get($payslip, 'otworkingday', 0)) > 0)
        <tr>
            <td colspan="2">Overtime Weekday</td>
            <td style="text-align: right;">{{ data_get($payslip, 'weekdaysOT', data_get($payslip, 'normalOThrs', data_get($payslip, 'otworkdays', 0))) ?: '-' }}</td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'normalovertime', data_get($payslip, 'otworkingday', 0)), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'saturdayovertime', data_get($payslip, 'otholiday', 0)) > 0)
        <tr>
            <td colspan="2">Overtime Weekend/Holiday</td>
            <td style="text-align: right;">{{ data_get($payslip, 'weekendOT', data_get($payslip, 'weekendOThrs', data_get($payslip, 'otweekend', 0))) ?: '-' }}</td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'saturdayovertime', data_get($payslip, 'otholiday', 0)), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'holidayotamt', 0) > 0)
        <tr>
            <td colspan="2">Holiday Overtime</td>
            <td style="text-align: right;">{{ data_get($payslip, 'holidayot', data_get($payslip, 'holiday', '-')) ?: '-' }}</td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'holidayotamt', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'normalovertime', 0) <= 0 && (float)data_get($payslip, 'saturdayovertime', 0) <= 0 && (float)data_get($payslip, 'holidayotamt', 0) <= 0 && (float)data_get($payslip, 'total_overtime_amt', data_get($payslip, 'overtime', 0)) > 0)
        <tr>
            <td colspan="2">Total Overtime</td>
            <td style="text-align: right;">{{ data_get($payslip, 'overtime_hours', data_get($payslip, 'weekdaysOT', '-')) ?: '-' }}</td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'total_overtime_amt', data_get($payslip, 'overtime', 0)), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'overtimerefund', 0) > 0)
        <tr>
            <td colspan="2">Overtime Refund</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'overtimerefund', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'other', 0) > 0)
        <tr>
            <td colspan="2">Other Income</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'other', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'commission', 0) > 0)
        <tr>
            <td colspan="2">Commission</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'commission', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'quarterlybonus', 0) > 0)
        <tr>
            <td colspan="2">Quarterly Bonus</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'quarterlybonus', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'endcontractrights', 0) > 0)
        <tr>
            <td colspan="2">End of Contract Rights</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'endcontractrights', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        <tr>
            <td colspan="2" style="font-weight: bold;">Gross Salary</td>
            <td></td>
            <td style="text-align: center; font-weight: bold;">{{ number_format((float)data_get($payslip, 'grosspay', data_get($payslip, 'totalamount', data_get($payslip, 'gross', 0))), 2) }}</td>
            <td></td>
        </tr>
        @if((float)data_get($payslip, 'annaulbonus', 0) > 0)
        <tr>
            <td colspan="2">Annual Bonus</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'annaulbonus', 0), 2) }}</td>
            <td></td>
        </tr>
        @endif
        <tr>
            <td colspan="2">SSNIT</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">
                {{ number_format((float)data_get($payslip, 'ssnitee', 0), 2) }}
            </td>
        </tr>
        <tr>
            <td colspan="2">Normal Tax-Regular</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'paye', 0), 2) }}</td>
        </tr>
        @if((float)data_get($payslip, 'overtimetax', data_get($payslip, 'ottax', 0)) > 0)
        <tr>
            <td colspan="2">Overtime Tax</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'overtimetax', data_get($payslip, 'ottax', 0)), 2) }}</td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'petra', 0) > 0 || (float)data_get($payslip, 'petrafund', 0) > 0 || (float)data_get($payslip, 'tier2', 0) > 0 || (float)data_get($payslip, 'tier3', 0) > 0)
        <tr>
            <td colspan="2">Petra</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'petra', data_get($payslip, 'petrafund', data_get($payslip, 'tier2', data_get($payslip, 'tier3', 0)))), 2) }}</td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'employeepf', data_get($payslip, 'provident_fund', 0)) > 0)
        <tr>
            <td colspan="2">Provident Fund</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'employeepf', data_get($payslip, 'provident_fund', 0)), 2) }}</td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'leavededuction', 0) > 0 || (float)data_get($payslip, 'leavedays', 0) > 0)
        <tr>
            <td colspan="2">Leave</td>
            <td style="text-align: center;">{{ data_get($payslip, 'leavedays', 0) ?: '-' }}</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'leavededuction', 0), 2) }}</td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'otherdeduction', data_get($payslip, 'deductions', 0)) > 0)
        <tr>
            <td colspan="2">Other Deduction</td>
            <td style="text-align: center;">{{ data_get($payslip, 'deduction_description', data_get($payslip, 'description', '')) }}</td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'otherdeduction', data_get($payslip, 'deductions', 0)), 2) }}</td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'loan', 0) > 0)
        <tr>
            <td colspan="2">Loan</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'loan', 0), 2) }}</td>
        </tr>
        @endif
        @if((float)data_get($payslip, 'annaultax', 0) > 0)
        <tr>
            <td colspan="2">Annual Tax</td>
            <td></td>
            <td></td>
            <td style="text-align: center;">{{ number_format((float)data_get($payslip, 'annaultax', 0), 2) }}</td>
        </tr>
        @endif
        <tr>
            <td colspan="3" class="total">Total</td>
            <td class="total" style="text-align: center;">
                {{
                    number_format(
                        (float)data_get($payslip, 'grosspay', 0) +
                        (float)data_get($payslip, 'annualbonus', 0),
                    2)
                }}
            </td>
            <td class="total" style="text-align: center;">
                {{
                    number_format(
                        (float)data_get($payslip, 'totaldeductions', 0) > 0
                            ? (float)data_get($payslip, 'totaldeductions')
                            : (
                                (float)data_get($payslip, 'ssnitee', 0) +
                                (float)data_get($payslip, 'paye', 0) +
                                (float)data_get($payslip, 'petra', 0) +
                                (float)data_get($payslip, 'employeepf', data_get($payslip, 'provident_fund', 0)) +
                                (float)data_get($payslip, 'leavededuction', 0) +
                                (float)data_get($payslip, 'otherdeduction', 0) +
                                (float)data_get($payslip, 'loan', 0) +
                                (float)data_get($payslip, 'annaultax', 0)
                            ),
                        2
                    )
                }}
            </td>
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
            <td colspan="2" class="net-pay" >{{ number_format((float)data_get($payslip, 'netpay', data_get($payslip, 'takehome', data_get($payslip, 'takehomepay', data_get($payslip, 'net_pay', 0)))), 2) }}</td>
        </tr>
        <tr>
            <td>Bank Account #</td>
            <td>{{ $displayAccountNo }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td>Bank Branch</td>
            <td>{{ $displayBranch }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
    </table>
</body>
</html>
