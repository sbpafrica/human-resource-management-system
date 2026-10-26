<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulletins de Paie BÉNIN - {{ $formattedMonth }}</title>
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
@php
    $frMonths = [
        'January' => 'Janvier', 'February' => 'Février', 'March' => 'Mars',
        'April' => 'Avril', 'May' => 'Mai', 'June' => 'Juin',
        'July' => 'Juillet', 'August' => 'Août', 'September' => 'Septembre',
        'October' => 'Octobre', 'November' => 'Novembre', 'December' => 'Décembre'
    ];
    $displayMonth = $formattedMonth;
    foreach ($frMonths as $en => $fr) {
        if (stripos($displayMonth, $en) !== false) {
            $displayMonth = str_ireplace($en, $fr, $displayMonth);
            break;
        }
    }
    $num = function($val) {
        if ($val === null || $val === '' || $val === '-') return 0.0;
        $clean = str_replace([',', ' ', "\xc2\xa0"], '', trim((string)$val));
        return is_numeric($clean) ? (float)$clean : 0.0;
    };
@endphp
@foreach($chunks as $chunkIndex => $chunk)
    <div class="bulk-page {{ !$loop->last ? 'page-break' : '' }}">
        @foreach($chunk as $itemIndex => $item)
            @php
                $payslip = $item['payslip'] ?? $item;
                $bank    = $item['bank'] ?? [];
                $logoSrc = !empty($logoData) ? $logoData : public_path('images/sbpafricalogo.png');

                $names = trim((string)data_get($payslip, 'names', ''));
                $firstNames = trim((string)data_get($payslip, 'first_names', ''));
                $displayName = trim($names . ' ' . $firstNames);
                if (empty($displayName)) {
                    $displayName = data_get($payslip, 'name', '-');
                }

                $staffId = data_get($payslip, 'staff_id') ?: (data_get($payslip, 'staffid') ?: data_get($payslip, 'id', '-'));

                $displayBankName = (!empty(data_get($bank, 'bank')) && data_get($bank, 'bank') !== '-') 
                    ? data_get($bank, 'bank') 
                    : ((!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') 
                        ? data_get($bank, 'bankname') 
                        : (data_get($payslip, 'bank_name') ?: data_get($payslip, 'bank', '-')));
                $displayAccountName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') 
                    ? data_get($bank, 'name') 
                    : $displayName;
                $displayAccountNo = (!empty(data_get($bank, 'account')) && data_get($bank, 'account') !== '-') 
                    ? data_get($bank, 'account') 
                    : ((!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') 
                        ? data_get($bank, 'alcnum') 
                        : (data_get($payslip, 'bank_account_number') ?: data_get($payslip, 'accountnumber', '-')));

                $basicSalary = $num(data_get($payslip, 'basic_salary') ?: data_get($payslip, 'basic', 0));
                $transport = $num(data_get($payslip, 'transport_allowance') ?: data_get($payslip, 'transport', 0));
                $lunch = $num(data_get($payslip, 'other_special_bonus_subsidy_etc') ?: data_get($payslip, 'lunch', 0));
                $communication = $num(data_get($payslip, 'communication_allowance', 0));
                $overtime = $num(data_get($payslip, 'bonuses') ?: data_get($payslip, 'overtime', 0));
                $kpi = $num(data_get($payslip, 'kpi_result') ?: (data_get($payslip, 'basic_kpi') ?: data_get($payslip, 'performstand', 0)));
                $sursalaire = $num(data_get($payslip, 'sursalaire', 0));
                $commission = $num(data_get($payslip, 'commission', 0));

                $gross = $num(data_get($payslip, 'gross_salaries') ?: (data_get($payslip, 'gross_amount') ?: data_get($payslip, 'gross', 0)));
                if ($gross <= 0 && $basicSalary > 0) {
                    $gross = $basicSalary + $transport + $lunch + $communication + $overtime + $kpi + $sursalaire + $commission;
                }

                $psPension = $num(data_get($payslip, 'ps_pension_salarie') ?: data_get($payslip, 'ssnitee', 0));
                $its = $num(data_get($payslip, 'its') ?: (data_get($payslip, 'its2') ?: data_get($payslip, 'paye', 0)));
                $otherDeduction = $num(data_get($payslip, 'deduction') ?: data_get($payslip, 'otherdeduction', 0));

                $totalDeductions = $num(data_get($payslip, 'its_deduction_totale') ?: data_get($payslip, 'totaldeductions', 0));
                if ($totalDeductions <= 0 && ($psPension > 0 || $its > 0 || $otherDeduction > 0)) {
                    $totalDeductions = $psPension + $its + $otherDeduction;
                }

                $netPay = $num(data_get($payslip, 'net_a_payer') ?: data_get($payslip, 'netpay', 0));
                if ($netPay <= 0 && $gross > 0) {
                    $netPay = max(0, $gross - $totalDeductions);
                }
            @endphp
            <div class="payslip-wrapper">
                <table class="payslip-table">
                    <tr>
                        <td colspan="2" style="text-align:center;">
                            <img src="{{ $logoSrc }}" class="logo" alt="SBP Africa Logo">
                        </td>
                        <td width="25%" class="header-title">BULLETIN DE PAIE</td>
                        <td width="35%" colspan="2" class="header-title">CONFIDENTIEL</td>
                    </tr>
                    <tr>
                        <td></td>
                        <td></td>
                        <td style="text-align:center; font-weight:bold;">{{ $displayMonth }}</td>
                        <td colspan="2"></td>
                    </tr>
                    <tr>
                        <td class="bold">Nom & Prénoms</td>
                        <td class="bold">{{ $displayName }}</td>
                        <td></td>
                        <td class="bold">Fonction</td>
                        <td class="bold">{{ data_get($payslip, 'position') ?: 'Logistics Officer' }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Matricule</td>
                        <td class="bold">{{ $staffId }}</td>
                        <td></td>
                        <td class="bold">Entreprise</td>
                        <td class="bold">{{ data_get($payslip, 'client', 'BUNGE BÉNIN') }}</td>
                    </tr>
                    <tr>
                        <td class="bold">N° CNSS</td>
                        <td>{{ data_get($payslip, 'social_security_number') ?: ($item['ssnit'] ?? '-') }}</td>
                        <td></td>
                        <td class="bold">Lieu de Travail</td>
                        <td>{{ data_get($payslip, 'work_place', 'Bénin') }}</td>
                    </tr>
                    <tr>
                        <th colspan="2" class="bold2" style="text-align:center;">Rubriques</th>
                        <th class="bold2" style="text-align:center;">Base / Jours</th>
                        <th class="bold2" style="text-align:center;">Gains (XOF)</th>
                        <th class="bold2" style="text-align:center;">Retenues (XOF)</th>
                    </tr>
                    <tr>
                        <td colspan="2">Salaire de Base</td>
                        <td style="text-align:right;">{{ data_get($payslip, 'work_days', data_get($payslip, 'legal_work_days', 22)) }} jrs</td>
                        <td style="text-align:right;">{{ number_format($basicSalary, 2) }}</td>
                        <td></td>
                    </tr>
                    @if($transport > 0)
                    <tr>
                        <td colspan="2">Indemnité de Transport</td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($transport, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($lunch > 0)
                    <tr>
                        <td colspan="2">Indemnité de Restauration / Repas</td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($lunch, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($communication > 0)
                    <tr>
                        <td colspan="2">Indemnité de Communication</td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($communication, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($overtime > 0)
                    <tr>
                        <td colspan="2">Heures Supplémentaires Payées</td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($overtime, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($kpi > 0)
                    <tr>
                        <td colspan="2">Prime de Performance (KPI)</td>
                        <td style="text-align:right;">{{ data_get($payslip, 'kpi_score', '0.00') }}</td>
                        <td style="text-align:right;">{{ number_format($kpi, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($sursalaire > 0)
                    <tr>
                        <td colspan="2">Sursalaire / Rappel</td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($sursalaire, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($commission > 0)
                    <tr>
                        <td colspan="2">Commission / Bonus</td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($commission, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($psPension > 0)
                    <tr>
                        <td colspan="2">Cotisation Retraite CNSS (Part Salariale)</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($psPension, 2) }}</td>
                    </tr>
                    @endif
                    @if($its > 0)
                    <tr>
                        <td colspan="2">ITS (Impôt sur les Traitements et Salaires)</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($its, 2) }}</td>
                    </tr>
                    @endif
                    @if($otherDeduction > 0)
                    <tr>
                        <td colspan="2">Autres Déductions</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($otherDeduction, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="bold2">
                        <td colspan="2">SALAIRE BRUT</td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($gross, 2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="bold2">
                        <td colspan="2">TOTAL DES DÉDUCTIONS</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($totalDeductions, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Banque: {{ $displayBankName }}</td>
                        <td class="bold">Compte: {{ $displayAccountNo }}</td>
                        <td></td>
                        <td colspan="2" class="bold3" style="font-size:10px;">NET À PAYER: {{ number_format($netPay, 2) }} XOF</td>
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
