<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulk Payslips - {{ $formattedMonth }}</title>
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
            padding: 2px 4px;
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
                        : (data_get($payslip, 'bank') ?: data_get($payslip, 'bankname', data_get($payslip, 'bank1', '-'))));

                $displayAccountName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') 
                    ? data_get($bank, 'name') 
                    : (data_get($payslip, 'accountname') ?: data_get($payslip, 'name', data_get($payslip, 'names', data_get($payslip, 'employeename', data_get($payslip, 'fullname', data_get($payslip, 'name1', '-'))))));

                $displayAccountNo = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') 
                    ? data_get($bank, 'alcnum') 
                    : ((!empty(data_get($bank, 'account')) && data_get($bank, 'account') !== '-') 
                        ? data_get($bank, 'account') 
                        : (data_get($payslip, 'accountnumber') ?: data_get($payslip, 'account_number', data_get($payslip, 'alcnum', data_get($payslip, 'account1', '-')))));

                $displayBranch = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') 
                    ? data_get($bank, 'branch') 
                    : (data_get($payslip, 'branch') ?: data_get($payslip, 'branch1', '-'));

                // Basic and Overtime resolution
                $basicEarned = (float) data_get($payslip, 'basic_earned', data_get($payslip, 'basic', data_get($payslip, 'actualbasic', 0)));
                $hasDetailedOt = (float)data_get($payslip, 'normalovertime', 0) > 0 || (float)data_get($payslip, 'saturdayovertime', 0) > 0 || (float)data_get($payslip, 'holidayotamt', 0) > 0;
            @endphp
            <div class="payslip-wrapper">
                <table class="payslip-table">
                    <tr>
                        <td colspan="2" style="text-align:center;">
                            <img src="{{ $logoSrc }}" class="logo" alt="Company Logo">
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
                        <td class="bold">{{ $payslip->name ?? $payslip->names ?? $payslip->employeename ?? $payslip->fullname ?? $payslip->name1 ?? '-' }}</td>
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

                    <!-- Basic Salary -->
                    <tr>
                        <td colspan="2">{{ $columnLabels['basic'] ?? 'Basic Salary' }}</td>
                        <td style="text-align:center;">{{ data_get($payslip, 'workdays', data_get($payslip, 'actualdays', data_get($payslip, 'days_worked', data_get($payslip, 'description', '')))) ?: '-' }}</td>
                        <td style="text-align:center;">{{ number_format($basicEarned, 2) }}</td>
                        <td></td>
                    </tr>

                    <!-- Incentives -->
                    @if((float)data_get($payslip, 'incentive', data_get($payslip, 'incentives', data_get($payslip, 'monthlykpi', 0))) > 0)
                    <tr>
                        <td colspan="2">{{ $columnLabels['incentive'] ?? 'Incentives' }}</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'incentive', data_get($payslip, 'incentives', data_get($payslip, 'monthlykpi', 0))), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif

                    <!-- Bonus / Transport -->
                    @if((float)data_get($payslip, 'bonus', data_get($payslip, 'transport', data_get($payslip, 'bonustransport', 0))) > 0)
                    <tr>
                        <td colspan="2">{{ $columnLabels['bonus'] ?? 'Bonus / Transport' }}</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'bonus', data_get($payslip, 'transport', data_get($payslip, 'bonustransport', 0))), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif

                    <!-- Allowances -->
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
                    @if((float)data_get($payslip, 'risk_allowance', 0) > 0)
                    <tr>
                        <td colspan="2">Risk Allowance</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'risk_allowance', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'food_allowance', 0) > 0)
                    <tr>
                        <td colspan="2">Food Allowance</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'food_allowance', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'night_allowance', 0) > 0)
                    <tr>
                        <td colspan="2">Night Allowance</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'night_allowance', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif

                    <!-- Detailed Overtime -->
                    @if((float)data_get($payslip, 'normalovertime', 0) > 0)
                    <tr>
                        <td colspan="2">Normal Overtime</td>
                        <td style="text-align:center;">{{ data_get($payslip, 'weekdaysOT', data_get($payslip, 'normal_ot_hrs', '')) }}</td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'normalovertime', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'saturdayovertime', 0) > 0)
                    <tr>
                        <td colspan="2">Saturday Overtime</td>
                        <td style="text-align:center;">{{ data_get($payslip, 'weekendOT', data_get($payslip, 'sat', '')) }}</td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'saturdayovertime', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'holidayotamt', 0) > 0)
                    <tr>
                        <td colspan="2">Holiday Overtime</td>
                        <td style="text-align:center;">{{ data_get($payslip, 'holiday', '') }}</td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'holidayotamt', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if(!$hasDetailedOt && (float)data_get($payslip, 'total_overtime_amt', data_get($payslip, 'overtime', 0)) > 0)
                    <tr>
                        <td colspan="2">Total Overtime</td>
                        <td style="text-align:center;">{{ data_get($payslip, 'overtime_hours', data_get($payslip, 'weekdaysOT', '-')) }}</td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'total_overtime_amt', data_get($payslip, 'overtime', 0)), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'overtimerefund', 0) > 0)
                    <tr>
                        <td colspan="2">Overtime Refund</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'overtimerefund', 0), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif

                    <!-- Commission -->
                    @if((float)data_get($payslip, 'commission_earned', data_get($payslip, 'commission', 0)) > 0)
                    <tr>
                        <td colspan="2">Commission Earned</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'commission_earned', data_get($payslip, 'commission', 0)), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif

                    <!-- Other Earnings / Refund -->
                    @if((float)data_get($payslip, 'other', data_get($payslip, 'other_allowances', data_get($payslip, 'refund', 0))) > 0)
                    <tr>
                        <td colspan="2">Other Earnings</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'other', data_get($payslip, 'other_allowances', data_get($payslip, 'refund', 0))), 2) }}</td>
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
                                <td style="text-align:center;">{{ is_numeric($val) ? number_format((float)$val, 2) : $val }}</td>
                                <td></td>
                            </tr>
                            @endif
                        @endforeach
                    @endif

                    <!-- Gross Salary -->
                    <tr>
                        <td colspan="2" class="bold2">{{ $columnLabels['gross'] ?? 'Gross Salary' }}</td>
                        <td></td>
                        <td style="text-align:center;" class="bold2">
                            {{ number_format((float)data_get($payslip, 'totalearn', data_get($payslip, 'grosspay', data_get($payslip, 'gross', data_get($payslip, 'gross_salary', 0)))), 2) }}
                        </td>
                        <td></td>
                    </tr>

                    <!-- Dynamic Custom Tax Reference / Info -->
                    @if(isset($customColumns))
                        @foreach($customColumns->whereIn('payslip_section', ['tax_reference', 'info']) as $cc)
                            @if(!in_array($cc->column_name, ['total_cash_emolument', 'total_assessable_income']))
                                @php $val = data_get($payslip, $cc->column_name, 0); @endphp
                                @if((float)$val != 0 || (!is_numeric($val) && !empty($val)))
                                <tr style="font-style: italic;">
                                    <td colspan="2">{{ $cc->display_label }}</td>
                                    <td style="text-align:center; font-size: 8px;">(Reference)</td>
                                    <td style="text-align:center;">{{ is_numeric($val) ? number_format((float)$val, 2) : $val }}</td>
                                    <td></td>
                                </tr>
                                @endif
                            @endif
                        @endforeach
                    @endif

                    <!-- Annual Bonus (Earnings side or Bonus line) -->
                    @if((float)data_get($payslip, '13month', data_get($payslip, 'annualbonus', data_get($payslip, 'annual_bonus', data_get($payslip, 'endyearmonth', 0)))) > 0)
                    <tr>
                        <td colspan="2">Annual Bonus</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, '13month', data_get($payslip, 'annualbonus', data_get($payslip, 'annual_bonus', data_get($payslip, 'endyearmonth', 0)))), 2) }}</td>
                        <td></td>
                    </tr>
                    @endif

                    <!-- Deductions -->
                    @if((float)data_get($payslip, 'ssnitee', data_get($payslip, 'ssnit_5_5_percent', data_get($payslip, 'ssnit_ee', data_get($payslip, 'ssnitpay', 0)))) > 0)
                    <tr>
                        <td colspan="2">{{ $columnLabels['ssnit_5_5_percent'] ?? 'SSNIT' }}</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'ssnitee', data_get($payslip, 'ssnit_5_5_percent', data_get($payslip, 'ssnit_ee', data_get($payslip, 'ssnitpay', 0)))), 2) }}</td>
                    </tr>
                    @endif

                    @if((float)data_get($payslip, 'petra', data_get($payslip, 'petrafund', data_get($payslip, 'petra_5_percent', data_get($payslip, 'tier3', 0)))) > 0)
                    <tr>
                        <td colspan="2">{{ $columnLabels['petra'] ?? 'Petra (5%)' }}</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'petra', data_get($payslip, 'petrafund', data_get($payslip, 'petra_5_percent', data_get($payslip, 'tier3', 0)))), 2) }}</td>
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

                    <!-- Normal Tax -->
                    @if((float)data_get($payslip, 'totaltaxdeduct', data_get($payslip, 'paye', data_get($payslip, 'totaltax', data_get($payslip, 'tax', 0)))) > 0)
                    <tr>
                        <td colspan="2">{{ $columnLabels['paye'] ?? 'Normal Tax-Regular' }}</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'totaltaxdeduct', data_get($payslip, 'paye', data_get($payslip, 'totaltax', data_get($payslip, 'tax', 0)))), 2) }}</td>
                    </tr>
                    @endif

                    <!-- Annual Bonus Tax -->
                    @if((float)data_get($payslip, '13tax', data_get($payslip, 'annaultax', data_get($payslip, 'endyeartax', 0))) > 0)
                    <tr>
                        <td colspan="2">Annual Bonus Tax</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, '13tax', data_get($payslip, 'annaultax', data_get($payslip, 'endyeartax', 0))), 2) }}</td>
                    </tr>
                    @endif

                    <!-- Overtime Tax -->
                    @if((float)data_get($payslip, 'overtimetax', data_get($payslip, 'ottax', data_get($payslip, 'ot_tax', data_get($payslip, 'overtime_tax', 0)))) > 0)
                    <tr>
                        <td colspan="2">Overtime Tax</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'overtimetax', data_get($payslip, 'ottax', data_get($payslip, 'ot_tax', data_get($payslip, 'overtime_tax', 0)))), 2) }}</td>
                    </tr>
                    @endif

                    <!-- Commission Tax -->
                    @if((float)data_get($payslip, 'commission_tax', data_get($payslip, 'commissiontax', data_get($payslip, 'bonustax', 0))) > 0)
                    <tr>
                        <td colspan="2">Commission Tax</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'commission_tax', data_get($payslip, 'commissiontax', data_get($payslip, 'bonustax', 0))), 2) }}</td>
                    </tr>
                    @endif

                    <!-- Salary Advance / Loan -->
                    @if((float)data_get($payslip, 'advance', 0) > 0)
                    <tr>
                        <td colspan="2">Salary Advance</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'advance', 0), 2) }}</td>
                    </tr>
                    @endif
                    @if((float)data_get($payslip, 'salaryloan', data_get($payslip, 'loan', data_get($payslip, 'loans', 0))) > 0)
                    <tr>
                        <td colspan="2">{{ $columnLabels['salaryloan'] ?? 'Salary Loan' }}</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'salaryloan', data_get($payslip, 'loan', data_get($payslip, 'loans', 0))), 2) }}</td>
                    </tr>
                    @endif

                    <!-- Absent Days / Leave Deduction -->
                    @if((float)data_get($payslip, 'absent_days_deduction', data_get($payslip, 'leavededuction', 0)) > 0)
                    <tr>
                        <td colspan="2">Leave / Absence Deduction</td>
                        <td style="text-align:center;">{{ data_get($payslip, 'absent_days', data_get($payslip, 'leavedays', '')) }}</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'absent_days_deduction', data_get($payslip, 'leavededuction', 0)), 2) }}</td>
                    </tr>
                    @endif

                    <!-- Other Deductions -->
                    @if((float)data_get($payslip, 'otherdeduction', data_get($payslip, 'deductions', data_get($payslip, 'deduction', 0))) > 0)
                    <tr>
                        <td colspan="2">Other Deductions</td>
                        <td style="text-align:center;">{{ data_get($payslip, 'deduction_description', data_get($payslip, 'description', '')) }}</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format((float)data_get($payslip, 'otherdeduction', data_get($payslip, 'deductions', data_get($payslip, 'deduction', 0))), 2) }}</td>
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
                                <td style="text-align:center;">{{ is_numeric($val) ? number_format((float)$val, 2) : $val }}</td>
                            </tr>
                            @endif
                        @endforeach
                    @endif

                    <!-- Total Row -->
                    <tr>
                        <td colspan="2" class="bold2">Total</td>
                        <td></td>
                        <td style="text-align:center;" class="bold2">
                            {{ number_format(
                                (float)data_get($payslip, 'totalearn', data_get($payslip, 'grosspay', data_get($payslip, 'gross', data_get($payslip, 'gross_salary', 0)))) +
                                (float)data_get($payslip, '13month', data_get($payslip, 'annualbonus', data_get($payslip, 'annual_bonus', data_get($payslip, 'endyearmonth', 0)))),
                                2
                            ) }}
                        </td>
                        <td style="text-align:center;" class="bold2">
                            {{ number_format((float)data_get($payslip, 'totaldeduct', data_get($payslip, 'totaldeductions', data_get($payslip, 'total_deductions', data_get($payslip, 'totaldeduction', 0)))), 2) }}
                        </td>
                    </tr>

                    <!-- Bank Details & Net Pay -->
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
                        <td class="bold2" style="text-align:center; font-weight:bold;">{{ strtoupper($columnLabels['net_pay'] ?? 'NET PAY') }}</td>
                        <td class="bold2" style="text-align:center; font-size:10px; font-weight:bold;">
                            {{ number_format((float)data_get($payslip, 'netpay', data_get($payslip, 'net_pay', data_get($payslip, 'take_home_pay', data_get($payslip, 'takehome', 0)))), 2) }}
                        </td>
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
