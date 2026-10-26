<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulletin de Paie BÉNIN - {{ data_get($payslip, 'name', 'Employe') }} - {{ $formattedMonth }}</title>
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
            padding: 7px 8px;
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
            font-size: 15px;
            text-align: center;
        }

        .logo {
            width: 180px;
            height: auto;
        }
    </style>
</head>
<body>
@php
    $num = function($val) {
        if ($val === null || $val === '' || $val === '-') return 0.0;
        $clean = str_replace([',', ' ', "\xc2\xa0"], '', trim((string)$val));
        return is_numeric($clean) ? (float)$clean : 0.0;
    };

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
    $displayBranch = (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') 
        ? data_get($bank, 'branch') 
        : (data_get($payslip, 'branch', '-'));

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

    $logoData = $logoData ?? (file_exists(public_path('images/sbpafricalogo.png')) ? 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/sbpafricalogo.png'))) : null);

    // French month translation
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

<table>
    <!-- Header -->
    <tr>
        <td colspan="2">
            @if($logoData)
                <img src="{{ $logoData }}" class="logo" alt="SBP Africa Logo">
            @else
                <img src="{{ public_path('images/sbpafricalogo.png') }}" class="logo" alt="Company Logo">
            @endif
        </td>
        <td colspan="1" style="text-align: center;">
            <h2>BULLETIN DE PAIE</h2>
        </td>
        <td colspan="2" style="text-align: center;">
            <h2>CONFIDENTIEL</h2>
        </td>
    </tr>

    <!-- Month -->
    <tr>
        <td colspan="2"></td>
        <td style="text-align: center; font-weight: bold; font-size: 13px;">
            {{ $displayMonth }}
        </td>
        <td colspan="2"></td>
    </tr>

    <!-- Employee Information -->
    <tr>
        <td class="header">Nom & Prénoms</td>
        <td>{{ $displayName }}</td>
        <td></td>
        <td class="header">Fonction / Titre</td>
        <td>{{ data_get($payslip, 'position') ?: 'Logistics Officer' }}</td>
    </tr>

    <tr>
        <td class="header">Matricule</td>
        <td>{{ $staffId }}</td>
        <td></td>
        <td class="header">Entreprise / Client</td>
        <td>{{ data_get($payslip, 'client', 'BUNGE BÉNIN') }}</td>
    </tr>

    <tr>
        <td class="header">N° CNSS / Sécurité Sociale</td>
        <td>{{ data_get($payslip, 'social_security_number') ?: (data_get($payslip, 'ssnit') ?: '-') }}</td>
        <td></td>
        <td class="header">Lieu de Travail</td>
        <td>{{ data_get($payslip, 'work_place', data_get($payslip, 'department', 'Bénin')) }}</td>
    </tr>

    <!-- Table Header -->
    <tr>
        <th colspan="2" class="header" style="text-align: center;">Rubriques</th>
        <th class="header" style="text-align: center;">Base / Jours</th>
        <th class="header" style="text-align: center;">Gains (XOF)</th>
        <th class="header" style="text-align: center;">Retenues (XOF)</th>
    </tr>

    <!-- Earnings -->
    <tr>
        <td colspan="2">Salaire de Base</td>
        <td style="text-align: right;">
            {{ data_get($payslip, 'work_days', data_get($payslip, 'legal_work_days', 22)) }} jrs
        </td>
        <td style="text-align: right;">
            {{ number_format($basicSalary, 2) }}
        </td>
        <td></td>
    </tr>

    @if($transport > 0)
    <tr>
        <td colspan="2">Indemnité de Transport</td>
        <td></td>
        <td style="text-align: right;">
            {{ number_format($transport, 2) }}
        </td>
        <td></td>
    </tr>
    @endif

    @if($lunch > 0)
    <tr>
        <td colspan="2">Indemnité de Restauration / Repas</td>
        <td></td>
        <td style="text-align: right;">
            {{ number_format($lunch, 2) }}
        </td>
        <td></td>
    </tr>
    @endif

    @if($communication > 0)
    <tr>
        <td colspan="2">Indemnité de Communication</td>
        <td></td>
        <td style="text-align: right;">
            {{ number_format($communication, 2) }}
        </td>
        <td></td>
    </tr>
    @endif

    @if($overtime > 0)
    <tr>
        <td colspan="2">Heures Supplémentaires Payées</td>
        <td></td>
        <td style="text-align: right;">
            {{ number_format($overtime, 2) }}
        </td>
        <td></td>
    </tr>
    @endif

    @if($kpi > 0)
    <tr>
        <td colspan="2">Prime de Performance (KPI)</td>
        <td style="text-align: right;">
            {{ data_get($payslip, 'kpi_score', '0.00') }}
        </td>
        <td style="text-align: right;">
            {{ number_format($kpi, 2) }}
        </td>
        <td></td>
    </tr>
    @endif

    @if($sursalaire > 0)
    <tr>
        <td colspan="2">Sursalaire / Rappel de Salaire</td>
        <td></td>
        <td style="text-align: right;">
            {{ number_format($sursalaire, 2) }}
        </td>
        <td></td>
    </tr>
    @endif

    @if($commission > 0)
    <tr>
        <td colspan="2">Commission / Bonus</td>
        <td></td>
        <td style="text-align: right;">
            {{ number_format($commission, 2) }}
        </td>
        <td></td>
    </tr>
    @endif

    <!-- Deductions -->
    @if($psPension > 0)
    <tr>
        <td colspan="2">Cotisation Retraite CNSS (Part Salariale - PS)</td>
        <td></td>
        <td></td>
        <td style="text-align: right;">
            {{ number_format($psPension, 2) }}
        </td>
    </tr>
    @endif

    @if($its > 0)
    <tr>
        <td colspan="2">ITS (Impôt sur les Traitements et Salaires)</td>
        <td></td>
        <td></td>
        <td style="text-align: right;">
            {{ number_format($its, 2) }}
        </td>
    </tr>
    @endif

    @if($otherDeduction > 0)
    <tr>
        <td colspan="2">Autres Déductions</td>
        <td></td>
        <td></td>
        <td style="text-align: right;">
            {{ number_format($otherDeduction, 2) }}
        </td>
    </tr>
    @endif

    <!-- Blank separator row -->
    <tr>
        <td colspan="5" style="border: none; padding: 4px;"></td>
    </tr>

    <!-- Totals -->
    <tr class="total">
        <td colspan="2">SALAIRE BRUT</td>
        <td></td>
        <td style="text-align: right;">
            {{ number_format($gross, 2) }}
        </td>
        <td></td>
    </tr>

    <tr class="total">
        <td colspan="2">TOTAL DES DÉDUCTIONS</td>
        <td></td>
        <td></td>
        <td style="text-align: right;">
            {{ number_format($totalDeductions, 2) }}
        </td>
    </tr>

    <!-- Bank Details -->
    <tr>
        <td class="header" colspan="2">DÉTAILS BANCAIRES</td>
        <td colspan="3"></td>
    </tr>

    <tr>
        <td>Banque</td>
        <td>{{ $displayBankName }}</td>
        <td></td>
        <td colspan="2" class="net-pay">NET À PAYER</td>
    </tr>

    <tr>
        <td>Titulaire du Compte</td>
        <td>{{ $displayAccountName }}</td>
        <td></td>
        <td colspan="2" class="net-pay">
            {{ number_format($netPay, 2) }} XOF
        </td>
    </tr>

    <tr>
        <td>N° de Compte Bancaire</td>
        <td>{{ $displayAccountNo }}</td>
        <td colspan="3"></td>
    </tr>

    <tr>
        <td>Agence / Guichet</td>
        <td>{{ $displayBranch }}</td>
        <td colspan="3"></td>
    </tr>

    <!-- Footnote -->
    <tr>
        <td colspan="5" style="font-size: 10px; color: #555; text-align: center; border-top: 2px solid #aaa; padding-top: 6px;">
            Ce bulletin de paie est établi sous réserve des vérifications d'usage conformément au Code du Travail du Bénin.
        </td>
    </tr>
</table>
</body>
</html>
