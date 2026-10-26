<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulletins de Paie Transsion BFA - {{ $formattedMonth }}</title>
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
@endphp
@foreach($chunks as $chunkIndex => $chunk)
    <div class="bulk-page {{ !$loop->last ? 'page-break' : '' }}">
        @foreach($chunk as $itemIndex => $item)
            @php
                $payslip = $item['payslip'] ?? $item;
                $bank    = $item['bank'] ?? [];
                $logoSrc = !empty($logoData) ? $logoData : public_path('images/sbpafricalogo.png');

                $basic       = (float)($payslip->basic ?? $payslip->base_salary ?? 0);
                $transport   = (float)($payslip->transport ?? 0);
                $housing     = (float)($payslip->housing ?? 0);
                $work        = (float)($payslip->work ?? 0);
                $kpi         = (float)($payslip->kpiactual ?? $payslip->kpistandard ?? 0);
                $overtime    = (float)($payslip->overtime ?? 0);
                $salescomm   = (float)($payslip->salescomm ?? 0);
                $progra      = (float)($payslip->progra ?? 0);

                $gross = (float)($payslip->gross ?? $payslip->grossnew ?? $payslip->grosspay ?? 0);
                if ($gross == 0) {
                    $gross = $basic + $transport + $housing + $work + $kpi + $overtime + $salescomm + $progra;
                }

                $cnss = (float)($payslip->cnss ?? $payslip->cnsssalaire ?? 0);
                if ($cnss == 0 && $basic > 0) {
                    $cnss = round($basic * 0.055, 2);
                }

                $fsp      = (float)($payslip->fsp ?? $payslip->wht ?? 0);
                $nap      = (float)($payslip->nap ?? 0);
                $finalNet = (float)($payslip->finalnetpay ?? 0);

                if ($fsp == 0 && $nap > 0 && $finalNet > 0 && $nap > $finalNet) {
                    $fsp = round($nap - $finalNet, 2);
                } elseif ($fsp == 0 && $nap > 0) {
                    $fsp = round($nap * 0.01, 2);
                }

                $iuts        = (float)($payslip->iuts ?? $payslip->iutsnet ?? 0);
                $lateness    = (float)($payslip->lateness ?? 0);
                $casual      = (float)($payslip->casual ?? 0);
                $otherdeduct = (float)($payslip->otherdeduct ?? $payslip->otherdeduction ?? 0);

                if ($iuts == 0 && $gross > 0 && ($nap > 0 || $finalNet > 0)) {
                    $effectiveNap = $nap > 0 ? $nap : ($finalNet + $fsp);
                    $calcIuts = round($gross - $cnss - $effectiveNap - $otherdeduct - $lateness - $casual, 2);
                    if ($calcIuts > 0) {
                        $iuts = $calcIuts;
                    }
                }

                $totalDeductions = round($cnss + $iuts + $fsp + $otherdeduct + $lateness + $casual, 2);
                $netPay = $finalNet > 0 ? $finalNet : ($nap > 0 ? round($nap - $fsp, 2) : max(0, round($gross - $totalDeductions, 2)));

                $displayBankName    = (!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') ? data_get($bank, 'bankname') : ($payslip->bank ?? '-');
                $displayAccountName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') ? data_get($bank, 'name') : ($payslip->fullname ?? $payslip->name ?? '-');
                $displayAccountNo   = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') ? data_get($bank, 'alcnum') : ($payslip->alcnum ?? $payslip->accountnumber ?? '-');
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
                        <td class="bold">{{ $payslip->fullname ?? $payslip->name ?? '-' }}</td>
                        <td></td>
                        <td class="bold">Fonction</td>
                        <td class="bold">{{ $payslip->position ?? '-' }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Matricule</td>
                        <td class="bold">{{ $payslip->staffid ?? $payslip->staff_id ?? '-' }}</td>
                        <td></td>
                        <td class="bold">Entreprise</td>
                        <td class="bold">Transsion BFA</td>
                    </tr>
                    <tr>
                        <td class="bold">N° CNSS</td>
                        <td>{{ !empty($payslip->cnssno) && $payslip->cnssno !== '-' ? $payslip->cnssno : ($item['ssnit'] ?? '-') }}</td>
                        <td></td>
                        <td class="bold">Département</td>
                        <td>{{ $payslip->department ?? '-' }}</td>
                    </tr>
                    <tr class="bold3">
                        <th colspan="2">Rubrique</th>
                        <th>Description / Jours / Heures</th>
                        <th>Gains (FCFA)</th>
                        <th>Retenues (FCFA)</th>
                    </tr>
                    <tr>
                        <td colspan="2">Salaire de Base</td>
                        <td style="text-align:center;">{{ (float)($payslip->lwork ?? 0) > 0 ? (int)$payslip->lwork : ((float)($payslip->acwork ?? 0) > 0 ? (int)$payslip->acwork : ((float)($payslip->workdays ?? 0) > 0 ? (int)$payslip->workdays : '30')) }}</td>
                        <td style="text-align:center;">{{ number_format($basic, 2) }}</td>
                        <td></td>
                    </tr>
                    @if($transport > 0)
                    <tr>
                        <td colspan="2">Indemnité de Transport</td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format($transport, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($housing > 0)
                    <tr>
                        <td colspan="2">Indemnité de Logement</td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format($housing, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($work > 0)
                    <tr>
                        <td colspan="2">Indemnité de Fonction</td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format($work, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($kpi > 0)
                    <tr>
                        <td colspan="2">Prime de Rendement (KPI)</td>
                        <td style="text-align:center;">{{ !empty($payslip->kpiindex) && is_numeric($payslip->kpiindex) && (float)$payslip->kpiindex > 0 ? $payslip->kpiindex : '' }}</td>
                        <td style="text-align:center;">{{ number_format($kpi, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($overtime > 0)
                    <tr>
                        <td colspan="2">Heures Supplémentaires</td>
                        <td style="text-align:center;">{{ (float)($payslip->overtimes ?? $payslip->overtime_hours ?? 0) > 0 ? (float)($payslip->overtimes ?? $payslip->overtime_hours) : '' }}</td>
                        <td style="text-align:center;">{{ number_format($overtime, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($salescomm > 0)
                    <tr>
                        <td colspan="2">Commission sur Ventes</td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format($salescomm, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    @if($progra > 0)
                    <tr>
                        <td colspan="2">Autre Prime / Gratification</td>
                        <td style="text-align:center;"></td>
                        <td style="text-align:center;">{{ number_format($progra, 2) }}</td>
                        <td></td>
                    </tr>
                    @endif
                    <tr class="bold2">
                        <td colspan="2">Salaire Brut</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($gross, 2) }}</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">Cotisation CNSS</td>
                        <td style="text-align:center;">5.5%</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($cnss, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">IUTS (Impôt sur Salaire)</td>
                        <td style="text-align:center;"></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($iuts, 2) }}</td>
                    </tr>
                    <tr>
                        <td colspan="2">Fonds de Soutien Patriotique (FSP)</td>
                        <td style="text-align:center;">1%</td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($fsp, 2) }}</td>
                    </tr>
                    @if($lateness > 0)
                    <tr>
                        <td colspan="2">Déduction Retard</td>
                        <td style="text-align:center;"></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($lateness, 2) }}</td>
                    </tr>
                    @endif
                    @if($casual > 0)
                    <tr>
                        <td colspan="2">Déduction Absence / Congé</td>
                        <td style="text-align:center;"></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($casual, 2) }}</td>
                    </tr>
                    @endif
                    @if($otherdeduct > 0)
                    <tr>
                        <td colspan="2">Autre Déduction</td>
                        <td style="text-align:center;"></td>
                        <td></td>
                        <td style="text-align:center;">{{ number_format($otherdeduct, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="bold2">
                        <td colspan="3">Total</td>
                        <td style="text-align:center;">{{ number_format($gross, 2) }}</td>
                        <td style="text-align:center;">{{ number_format($totalDeductions, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Banque</td>
                        <td>{{ $displayBankName }}</td>
                        <td></td>
                        <td colspan="2" class="bold" style="text-align:center; font-size:10px;">NET VIRÉ / NET PAYÉ</td>
                    </tr>
                    <tr>
                        <td class="bold">Titulaire</td>
                        <td>{{ $displayAccountName }}</td>
                        <td></td>
                        <td colspan="2" class="bold" style="text-align:center; font-size:11px; color:#1e3a8a;">
                            {{ number_format($netPay, 2) }} FCFA
                        </td>
                    </tr>
                    <tr>
                        <td class="bold">N° Compte</td>
                        <td>{{ $displayAccountNo }}</td>
                        <td colspan="3"></td>
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
