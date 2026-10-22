<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulletin de Paie - {{ $payslip->fullname ?? $payslip->name ?? 'Employe' }} - {{ $formattedMonth }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 7px 8px; text-align: left; }
        .header { font-weight: bold; }
        .total { font-weight: bold; }
        .net-pay { font-weight: bold; font-size: 14px; text-align: center; }
        .logo { width: 150px; height: auto; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
@php
    $displayBankName = (!empty(data_get($bank, 'bankname')) && data_get($bank, 'bankname') !== '-') 
        ? data_get($bank, 'bankname') 
        : ($payslip->bank ?? $payslip->bankname ?? '-');
    $displayAccountName = (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') 
        ? data_get($bank, 'name') 
        : ($payslip->fullname ?? $payslip->name ?? '-');
    $displayAccountNo = (!empty(data_get($bank, 'alcnum')) && data_get($bank, 'alcnum') !== '-') 
        ? data_get($bank, 'alcnum') 
        : ($payslip->alcnum ?? $payslip->accountnumber ?? '-');

    $logoData = $logoData ?? (file_exists(public_path('images/sbpafricalogo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/sbpafricalogo.png'))) : null);

    $basic = (float)($payslip->basic ?? $payslip->base_salary ?? 0);
    $transport = (float)($payslip->transport ?? 0);
    $housing = (float)($payslip->housing ?? 0);
    $work = (float)($payslip->work ?? 0);
    $kpi = (float)($payslip->kpiactual ?? $payslip->kpistandard ?? 0);
    $overtime = (float)($payslip->overtime ?? 0);
    $salescomm = (float)($payslip->salescomm ?? 0);
    $progra = (float)($payslip->progra ?? 0);

    $gross = (float)($payslip->gross ?? $payslip->grossnew ?? $payslip->grosspay ?? 0);
    if ($gross == 0) {
        $gross = $basic + $transport + $housing + $work + $kpi + $overtime + $salescomm + $progra;
    }

    $cnss = (float)($payslip->cnss ?? $payslip->cnsssalaire ?? 0);
    if ($cnss == 0 && $basic > 0) {
        $cnss = round($basic * 0.055, 2);
    }

    $fsp = (float)($payslip->fsp ?? $payslip->wht ?? 0);
    $nap = (float)($payslip->nap ?? 0);
    $finalNet = (float)($payslip->finalnetpay ?? 0);

    if ($fsp == 0 && $nap > 0 && $finalNet > 0 && $nap > $finalNet) {
        $fsp = round($nap - $finalNet, 2);
    } elseif ($fsp == 0 && $nap > 0) {
        $fsp = round($nap * 0.01, 2);
    }

    $iuts = (float)($payslip->iuts ?? $payslip->iutsnet ?? 0);
    $lateness = (float)($payslip->lateness ?? 0);
    $casual = (float)($payslip->casual ?? 0);
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
@endphp
    <table>
        <tr>
            <td colspan="2">
                @if($logoData)
                    <img src="{{ $logoData }}" class="logo" alt="SBP Africa Logo">
                @else
                    <h3 style="color: #0359c9; margin: 0;">SBP AFRICA</h3>
                @endif
            </td>
            <td colspan="1" class="text-center"><h2>BULLETIN DE PAIE</h2></td>
            <td colspan="2" class="text-center"><h2>CONFIDENTIEL</h2></td>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td class="text-center header">{{ $formattedMonth }}</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td class="header">Nom & Prénoms</td>
            <td>{{ $payslip->fullname ?? $payslip->name ?? '-' }}</td>
            <td></td>
            <td class="header">Fonction</td>
            <td>{{ $payslip->position ?? '-' }}</td>
        </tr>
        <tr>
            <td class="header">Matricule</td>
            <td>{{ $payslip->staffid ?? $payslip->staff_id ?? '-' }}</td>
            <td></td>
            <td class="header">Entreprise</td>
            <td>Transsion BFA</td>
        </tr>
        <tr>
            <td class="header">N° CNSS</td>
            <td>{{ !empty($payslip->cnssno) && $payslip->cnssno !== '-' ? $payslip->cnssno : (!empty($ssnit) && $ssnit !== '-' ? $ssnit : '-') }}</td>
            <td></td>
            <td class="header">Département</td>
            <td>{{ $payslip->department ?? '-' }}</td>
        </tr>
        @if(!empty($payslip->hiredate) && $payslip->hiredate !== '-')
        <tr>
            <td class="header">Date d'embauche</td>
            <td>{{ $payslip->hiredate }}</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        @endif
        <tr>
            <th colspan="2" class="header text-center">Rubrique</th>
            <th class="header text-center">Description / Days / Hours</th>
            <th class="header text-center">Gains (FCFA)</th>
            <th class="header text-center">Retenues (FCFA)</th>
        </tr>
        <tr>
            <td colspan="2">Salaire de Base</td>
            <td class="text-center">{{ (float)($payslip->lwork ?? 0) > 0 ? (int)$payslip->lwork : ((float)($payslip->acwork ?? 0) > 0 ? (int)$payslip->acwork : ((float)($payslip->workdays ?? 0) > 0 ? (int)$payslip->workdays : '30')) }}</td>
            <td class="text-center">{{ number_format($basic, 2) }}</td>
            <td></td>
        </tr>
        @if($transport > 0)
        <tr>
            <td colspan="2">Indemnité de Transport</td>
            <td class="text-center"></td>
            <td class="text-center">{{ number_format($transport, 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if($housing > 0)
        <tr>
            <td colspan="2">Indemnité de Logement</td>
            <td class="text-center"></td>
            <td class="text-center">{{ number_format($housing, 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if($work > 0)
        <tr>
            <td colspan="2">Indemnité de Fonction</td>
            <td class="text-center"></td>
            <td class="text-center">{{ number_format($work, 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if($kpi > 0)
        <tr>
            <td colspan="2">Prime de Rendement (KPI)</td>
            <td class="text-center">{{ !empty($payslip->kpiindex) && is_numeric($payslip->kpiindex) && (float)$payslip->kpiindex > 0 ? $payslip->kpiindex : '' }}</td>
            <td class="text-center">{{ number_format($kpi, 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if($overtime > 0)
        <tr>
            <td colspan="2">Heures Supplémentaires</td>
            <td class="text-center">{{ (float)($payslip->overtimes ?? $payslip->overtime_hours ?? 0) > 0 ? (float)($payslip->overtimes ?? $payslip->overtime_hours) : '' }}</td>
            <td class="text-center">{{ number_format($overtime, 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if($salescomm > 0)
        <tr>
            <td colspan="2">Commission sur Ventes</td>
            <td class="text-center"></td>
            <td class="text-center">{{ number_format($salescomm, 2) }}</td>
            <td></td>
        </tr>
        @endif
        @if($progra > 0)
        <tr>
            <td colspan="2">Autre Prime / Gratification</td>
            <td class="text-center"></td>
            <td class="text-center">{{ number_format($progra, 2) }}</td>
            <td></td>
        </tr>
        @endif
        <tr>
            <td colspan="2" class="total">Salaire Brut</td>
            <td></td>
            <td class="text-center total">{{ number_format($gross, 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="2">Cotisation CNSS</td>
            <td class="text-center">5.5%</td>
            <td></td>
            <td class="text-center">{{ number_format($cnss, 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">IUTS (Impôt sur Salaire)</td>
            <td class="text-center"></td>
            <td></td>
            <td class="text-center">{{ number_format($iuts, 2) }}</td>
        </tr>
        <tr>
            <td colspan="2">Fonds de Soutien Patriotique (FSP)</td>
            <td class="text-center">1%</td>
            <td></td>
            <td class="text-center">{{ number_format($fsp, 2) }}</td>
        </tr>
        @if($lateness > 0)
        <tr>
            <td colspan="2">Déduction Retard</td>
            <td class="text-center"></td>
            <td></td>
            <td class="text-center">{{ number_format($lateness, 2) }}</td>
        </tr>
        @endif
        @if($casual > 0)
        <tr>
            <td colspan="2">Déduction Absence / Congé</td>
            <td class="text-center"></td>
            <td></td>
            <td class="text-center">{{ number_format($casual, 2) }}</td>
        </tr>
        @endif
        @if($otherdeduct > 0)
        <tr>
            <td colspan="2">Autre Déduction</td>
            <td class="text-center"></td>
            <td></td>
            <td class="text-center">{{ number_format($otherdeduct, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td colspan="3" class="total">Total</td>
            <td class="total text-center">{{ number_format($gross, 2) }}</td>
            <td class="total text-center">{{ number_format($totalDeductions, 2) }}</td>
        </tr>
        @if($nap > 0 && abs($nap - $netPay) > 0.01)
        <tr>
            <td colspan="3">Net à Payer (Avant FSP)</td>
            <td colspan="2" class="text-center">{{ number_format($nap, 2) }} FCFA</td>
        </tr>
        @endif
        <tr>
            <td>Banque</td>
            <td>{{ $displayBankName }}</td>
            <td></td>
            <td colspan="2" class="net-pay">NET VIRÉ / NET PAYÉ</td>
        </tr>
        <tr>
            <td>Titulaire du Compte</td>
            <td>{{ $displayAccountName }}</td>
            <td></td>
            <td colspan="2" class="net-pay" style="font-size: 15px; color: #1e3a8a;">
                {{ number_format($netPay, 2) }} FCFA
            </td>
        </tr>
        <tr>
            <td>N° de Compte</td>
            <td>{{ $displayAccountNo }}</td>
            <td colspan="3"></td>
        </tr>
    </table>
</body>
</html>
