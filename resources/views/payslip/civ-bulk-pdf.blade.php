<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulletins de Paie CIV - {{ $formattedMonth }}</title>
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

                $basicAmount = $num(data_get($payslip, 'basic') ?: data_get($payslip, 'basicfixed', 0));
                $grossAmount = $num(data_get($payslip, 'grosssalary') ?: data_get($payslip, 'grosssalary1', 0));

                $displayBankName = (!empty(data_get($bank, 'bank')) && data_get($bank, 'bank') !== '-') 
                    ? data_get($bank, 'bank') 
                    : ((!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') 
                        ? data_get($bank, 'bankname') 
                        : (data_get($payslip, 'bank') ?: data_get($payslip, 'bankname', '-')));
                $displayAccountName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') 
                    ? data_get($bank, 'name') 
                    : (data_get($payslip, 'fullname') ?: data_get($payslip, 'name', '-'));
                $displayAccountNo = (!empty(data_get($bank, 'account')) && data_get($bank, 'account') !== '-') 
                    ? data_get($bank, 'account') 
                    : ((!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') 
                        ? data_get($bank, 'alcnum') 
                        : (data_get($payslip, 'accountnumber') ?: data_get($payslip, 'alcnum', '-')));
                $displayBranch = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') 
                    ? data_get($bank, 'branch') 
                    : (data_get($payslip, 'branch', '-'));

                $leaveAllowance = $num(data_get($payslip, 'leaveallowance', 0));
                $callAllowance  = $num(data_get($payslip, 'callallowance', 0));
                $transport      = $num(data_get($payslip, 'transport', 0));
                $overtime       = $num(data_get($payslip, 'overtime', 0));
                $otAllowance    = $num(data_get($payslip, 'overtimeallowance', 0));
                $monthlyKpi     = $num(data_get($payslip, 'monthlykpi', 0));

                $cnps = $num(data_get($payslip, 'cnpssalaries', 0));
                $its  = $num(data_get($payslip, 'itsalaries', 0));
                $otherDeduct = $num(data_get($payslip, 'otherdeductions', 0));

                $totalDeduct = $num(data_get($payslip, 'totaldeduction', 0));
                if ($totalDeduct <= 0 && ($cnps > 0 || $its > 0 || $otherDeduct > 0)) {
                    $totalDeduct = $cnps + $its + $otherDeduct;
                }

                $netPay = $num(data_get($payslip, 'netpay', 0));
                if ($netPay <= 0 && $grossAmount > 0) {
                    $netPay = max(0, $grossAmount - $totalDeduct);
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
                        <td class="bold">{{ data_get($payslip, 'fullname', data_get($payslip, 'name', '-')) }}</td>
                        <td></td>
                        <td class="bold">Fonction</td>
                        <td class="bold">{{ data_get($payslip, 'jobtitle', data_get($payslip, 'position', '-')) }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Matricule</td>
                        <td class="bold">{{ data_get($payslip, 'id', data_get($payslip, 'staffid', '-')) }}</td>
                        <td></td>
                        <td class="bold">Entreprise</td>
                        <td class="bold">{{ data_get($payslip, '1stlevel', 'BUNGE CIV') }}</td>
                    </tr>
                    <tr>
                        <td class="bold">N° CNPS</td>
                        <td>{{ !empty($item['ssnit']) && $item['ssnit'] !== '-' ? $item['ssnit'] : (data_get($payslip, 'cnpsno') ?: data_get($payslip, 'ssnit', '-')) }}</td>
                        <td></td>
                        <td class="bold">Département</td>
                        <td>{{ data_get($payslip, '2ndlevel', data_get($payslip, 'city', '-')) }}</td>
                    </tr>
                    <tr>
                        <th colspan="2" class="bold2" style="text-align:center;">Rubriques</th>
                        <th class="bold2" style="text-align:center;">Base / Jours</th>
                        <th class="bold2" style="text-align:center;">Gains (XOF)</th>
                        <th class="bold2" style="text-align:center;">Retenues (XOF)</th>
                    </tr>
                    <tr>
                        <td colspan="2">Salaire de Base</td>
                        <td style="text-align:right;">{{ data_get($payslip, 'actualwork', 22) }} jrs</td>
                        <td style="text-align:right;">{{ number_format($basicAmount, 2) }}</td>
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
                    @if($leaveAllowance > 0)
                    <tr>
                        <td colspan="2">Indemnité de Repas / Congés (Lunch)</td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($leaveAllowance, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($callAllowance > 0)
                    <tr>
                        <td colspan="2">Allocation Téléphonique</td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($callAllowance, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($monthlyKpi > 0)
                    <tr>
                        <td colspan="2">Prime de Performance (KPI)</td>
                        <td style="text-align:right;">{{ data_get($payslip, 'coefficient', '1.00') }}</td>
                        <td style="text-align:right;">{{ number_format($monthlyKpi, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($overtime > 0 || $otAllowance > 0)
                    <tr>
                        <td colspan="2">Heures Supplémentaires</td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($overtime + $otAllowance, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($cnps > 0)
                    <tr>
                        <td colspan="2">CNPS Part Salariale</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($cnps, 2) }}</td>
                    </tr>
                    @endif
                    @if($its > 0)
                    <tr>
                        <td colspan="2">Impôt sur le Salaire (ITS / RICF)</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($its, 2) }}</td>
                    </tr>
                    @endif
                    @if($otherDeduct > 0)
                    <tr>
                        <td colspan="2">Autres Déductions</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($otherDeduct, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="bold2">
                        <td colspan="2">SALAIRE BRUT</td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($grossAmount, 2) }}</td>
                        <td></td>
                    </tr>
                    <tr class="bold2">
                        <td colspan="2">TOTAL DES DÉDUCTIONS</td>
                        <td></td>
                        <td></td>
                        <td style="text-align:right;">{{ number_format($totalDeduct, 2) }}</td>
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
