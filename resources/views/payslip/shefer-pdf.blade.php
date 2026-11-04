<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Shefer Payslip - {{ $formattedMonth }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 5px;
            text-align: left;
        }
        .header {
            font-weight: bold;
        }
        .total {
            font-weight: bold;
        }
        .net-pay {
            font-weight: bold;
        }
        .logo {
            width: 250px;
            height: 80px;
        }
        /* Safe styling for the tax reference rows */
        .reference-row td {
            font-style: italic;
        }
    </style>
</head>
<body>
@php
    $displayBankName = (!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') 
        ? data_get($bank, 'bankname') 
        : ($payslip->bank ?? $payslip->bankname ?? '-');
    $displayAccountName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') 
        ? data_get($bank, 'name') 
        : ($payslip->name ?? $payslip->names ?? $payslip->employeename ?? $payslip->name1 ?? '-');
    $displayAccountNo = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') 
        ? data_get($bank, 'alcnum') 
        : ($payslip->accountnumber ?? $payslip->account_number ?? $payslip->alcnum ?? '-');
    $displayBranch = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') 
        ? data_get($bank, 'branch') 
        : ($payslip->branch ?? '-');

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
        <td colspan="1" style="text-align: center; color:#bc4904"><h2>SALARY SLIP</h2></td>
        <td colspan="2" style="text-align: center; color:rgb(3, 89, 201)"><h2>CONFIDENTIAL</h2></td>
    </tr>
    <tr>
        <td colspan="2"></td>
        <td style="text-align: center; font-weight: bold;">{{ $formattedMonth }}</td>
        <td colspan="2"></td>
    </tr>
    <tr>
        <td class="header">Name</td>
        <td>{{ $payslip->name1 ?? $payslip->name ?? $payslip->employeename ?? '-' }}</td>
        <td></td>
        <td class="header">Title</td>
        <td>{{ $payslip->position ?? '-' }}</td>
    </tr>
    <tr>
        <td class="header">Employee ID</td>
        <td>{{ $payslip->staff_id ?? $payslip->staffid ?? $payslip->staffida ?? $payslip->id ?? $payslip->employ ?? '-' }}</td>
        <td></td>
        <td class="header">Client Company</td>
        <td>SHEFER</td>
    </tr>
    <tr>
        <td class="header">SSNIT #</td>
        <td>{{ $ssnit ?? $payslip->ssnit ?? '-' }}</td>
        <td></td>
        <td class="header">Department</td>
        <td>{{ $payslip->department ?? '-' }}</td>
    </tr>
    <tr>
        <td class="header">{{ $columnLabels['basic'] ?? 'Basic Salary' }}</td>
        <td>{{ number_format((float)($payslip->basic ?? 0), 2) }}</td>
        <td></td>
        <td class="header">Expected Work Days</td>
        <td>{{ (float)($payslip->expected_work_days ?? ($payslip->legal_work_days ?? 0)) > 0 ? (int)($payslip->expected_work_days ?? $payslip->legal_work_days) : '-' }}</td>
    </tr>
    <tr>
        <th colspan="2" class="header" style="text-align: center;">Item</th>
        <th class="header" style="text-align: center;">Description/Days/Hours</th>
        <th class="header" style="text-align: center;">Earnings</th>
        <th class="header" style="text-align: center;">Deductions</th>
    </tr>

    <!-- Earnings -->
    <tr>
        <td colspan="2">{{ $columnLabels['basic_earned'] ?? 'Basic Entitled' }}</td>
        <td style="text-align: right;">{{ (float)($payslip->no_of_days_worked ?? ($payslip->days_worked ?? 0)) > 0 ? (int)($payslip->no_of_days_worked ?? $payslip->days_worked) : ($payslip->description ?? 0) }}</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->basic_earned ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['absent_days'] ?? 'Absent Days' }}</td>
        <td style="text-align: right;">{{ $payslip->absent_days ?? 0 }}</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->absent_without_pay ?? ($payslip->absent_deduction ?? 0)), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['weekdayOT_amount'] ?? 'Weekday Overtime' }}</td>
        <td style="text-align: right;">{{ $payslip->weekdayOT_hours ?? ($payslip->weekday_overtime_hours ?? 0) }}</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->weekdayOT_amount ?? ($payslip->weekday_overtime_amount ?? 0)), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['weekendOT_amount'] ?? 'Saturday Overtime' }}</td>
        <td style="text-align: right;">{{ $payslip->weekendOT_hours ?? ($payslip->offday_holiday_overtime_hours ?? 0) }}</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->weekendOT_amount ?? ($payslip->offday_holiday_overtime_total ?? 0)), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['sunOT_amount'] ?? 'Sunday Overtime' }}</td>
        <td style="text-align: right;">{{ $payslip->sunOT_hours ?? ($payslip->special_day_overtime_hours ?? 0) }}</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->sunOT_amount ?? ($payslip->special_day_overtime_amount ?? 0)), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['fixedot'] ?? 'Fixed Overtime' }}</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->fixedot ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['food'] ?? 'Food Allowance' }}</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->food ?? ($payslip->food_allowance ?? 0)), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['airtime'] ?? 'Airtime Allowance' }}</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->airtime ?? ($payslip->airtime_allowance ?? 0)), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['medical'] ?? 'Medical Allowance' }}</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->medical ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['responseallo'] ?? 'Responsibility Allowance' }}</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->responseallo ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['transport'] ?? 'Transport Allowance' }}</td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->transport ?? 0), 2) }}</td>
        <td></td>
    </tr>

    <!-- Dynamic Custom Earnings -->
    @if(isset($customColumns))
        @foreach($customColumns->where('payslip_section', 'earnings') as $cc)
            @php $val = $payslip->{$cc->column_name} ?? null; @endphp
            <tr>
                <td colspan="2">{{ $cc->display_label }}</td>
                <td></td>
                <td style="text-align: center;">{{ is_numeric($val) ? number_format((float)$val, 2) : ($val ?? '0.00') }}</td>
                <td></td>
            </tr>
        @endforeach
    @endif

    <!-- Gross Salary -->
    <tr>
        <td colspan="2" class="total">{{ $columnLabels['gross'] ?? 'Gross Salary' }}</td>
        <td></td>
        <td style="text-align: center;" class="total">{{ number_format((float)($payslip->gross ?? 0), 2) }}</td>
        <td></td>
    </tr>

    <!-- Tax Reference Values (Dynamic Display Labels from Custom Columns) -->
    @php
        $cashEmolumentCol = isset($customColumns) ? $customColumns->firstWhere('column_name', 'total_cash_emolument') : null;
        $assessableCol    = isset($customColumns) ? $customColumns->firstWhere('column_name', 'total_assessable_income') : null;
        $cashLabel        = $cashEmolumentCol ? $cashEmolumentCol->display_label : 'Total Cash Emolument';
        $assessableLabel  = $assessableCol ? $assessableCol->display_label : 'Total Assessable Income';
    @endphp
    <tr class="reference-row">
        <td colspan="2">{{ $cashLabel }}</td>
        <td style="text-align: center; font-size: 10px;">(Gross - OT)</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->total_cash_emolument ?? 0), 2) }}</td>
        <td></td>
    </tr>
    <tr class="reference-row">
        <td colspan="2">{{ $assessableLabel }}</td>
        <td style="text-align: center; font-size: 10px;">(Taxable Base)</td>
        <td style="text-align: center;">{{ number_format((float)($payslip->total_assessable_income ?? 0), 2) }}</td>
        <td></td>
    </tr>

    <!-- Dynamic Custom Tax Reference / Info -->
    @if(isset($customColumns))
        @foreach($customColumns->whereIn('payslip_section', ['tax_reference', 'info']) as $cc)
            @if(!in_array($cc->column_name, ['total_cash_emolument', 'total_assessable_income']))
                @php $val = $payslip->{$cc->column_name} ?? null; @endphp
                <tr class="reference-row">
                    <td colspan="2">{{ $cc->display_label }}</td>
                    <td style="text-align: center; font-size: 10px;">(Reference)</td>
                    <td style="text-align: center;">{{ is_numeric($val) ? number_format((float)$val, 2) : ($val ?? '0.00') }}</td>
                    <td></td>
                </tr>
            @endif
        @endforeach
    @endif

    <!-- Deductions -->
    <tr>
        <td colspan="2">{{ $columnLabels['ssnit_5_5_percent'] ?? 'SSNIT' }}</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->ssnit_5_5_percent ?? ($payslip->ssnitee ?? 0)), 2) }}</td>
    </tr>
    @if((float)($payslip->petra ?? $payslip->petra_5_percent ?? 0) > 0)
    <tr>
        <td colspan="2">{{ $columnLabels['petra'] ?? 'Petra (5%)' }}</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->petra ?? $payslip->petra_5_percent ?? 0), 2) }}</td>
    </tr>
    @endif
    <tr>
        <td colspan="2">{{ $columnLabels['paye'] ?? 'Normal Tax-Regular' }}</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->paye ?? 0), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['ot_tax'] ?? 'Overtime Tax' }}</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->ot_tax ?? ($payslip->overtimetax ?? 0)), 2) }}</td>
    </tr>
    <tr>
        <td colspan="2">{{ $columnLabels['salaryloan'] ?? 'Salary Loan' }}</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">{{ number_format((float)($payslip->salaryloan ?? 0), 2) }}</td>
    </tr>

    <!-- Dynamic Custom Deductions -->
    @if(isset($customColumns))
        @foreach($customColumns->where('payslip_section', 'deductions') as $cc)
            @php $val = $payslip->{$cc->column_name} ?? null; @endphp
            <tr>
                <td colspan="2">{{ $cc->display_label }}</td>
                <td></td>
                <td></td>
                <td style="text-align: center;">{{ is_numeric($val) ? number_format((float)$val, 2) : ($val ?? '0.00') }}</td>
            </tr>
        @endforeach
    @endif

    <!-- Total -->
    <tr>
        <td colspan="2" class="total">Total</td>
        <td></td>
        <td style="text-align: center;" class="total">{{ number_format((float)($payslip->gross ?? 0), 2) }}</td>
        <td style="text-align: center;" class="total">{{ number_format((float)($payslip->total_deductions ?? 0), 2) }}</td>
    </tr>

    <!-- Bank & Net Pay -->
    <tr>
        <td class="header">Bank Name</td>
        <td>{{ $displayBankName }}</td>
        <td></td>
        <td colspan="2" class="net-pay" style="text-align: center;">{{ strtoupper($columnLabels['net_pay'] ?? 'NET PAY') }}</td>
    </tr>
    <tr>
        <td class="header">Bank Account Name</td>
        <td>{{ $displayAccountName }}</td>
        <td></td>
        <td colspan="2" style="text-align: center;" class="net-pay">
            {{ number_format((float)($payslip->net_pay ?? 0), 2) }}
        </td>
    </tr>
    <tr>
        <td class="header">Bank Account #</td>
        <td>{{ $displayAccountNo }}</td>
        <td colspan="3"></td>
    </tr>
    <tr>
        <td class="header">Bank Branch</td>
        <td>{{ $displayBranch }}</td>
        <td colspan="3"></td>
    </tr>
</table>

</body>
</html>