<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bulletin de Paie CIV - {{ data_get($payslip, 'fullname', data_get($payslip, 'name', 'Employe')) }} - {{ $formattedMonth }}</title>
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
            padding: 8px;
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
            font-size: 16px;
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
        <td style="text-align: center; font-weight: bold;">
            {{ $displayMonth }}
        </td>
        <td colspan="2"></td>
    </tr>

    <!-- Employee Information -->
    <tr>
        <td class="header">Nom & Prénoms</td>
        <td>{{ data_get($payslip, 'fullname', data_get($payslip, 'name', '-')) }}</td>
        <td></td>
        <td class="header">Fonction / Titre</td>
        <td>{{ data_get($payslip, 'jobtitle', data_get($payslip, 'position', '-')) }}</td>
    </tr>

    <tr>
        <td class="header">Matricule</td>
        <td>{{ data_get($payslip, 'id', data_get($payslip, 'staffid', '-')) }}</td>
        <td></td>
        <td class="header">Entreprise / Client</td>
        <td>{{ data_get($payslip, '1stlevel', 'BUNGE CIV') }}</td>
    </tr>

    <tr>
        <td class="header">N° CNPS</td>
        <td>{{ !empty($ssnit) && $ssnit !== '-' ? $ssnit : (data_get($payslip, 'cnpsno') ?: data_get($payslip, 'ssnit', '-')) }}</td>
        <td></td>
        <td class="header">Département / Ville</td>
        <td>{{ data_get($payslip, '2ndlevel', data_get($payslip, 'city', '-')) }}</td>
    </tr>

    <!-- Table Header -->
    <tr>
        <th colspan="2" class="header" style="text-align: center;">Rubriques</th>
        <th class="header" style="text-align: center;">Base / Jours / Coeff.</th>
        <th class="header" style="text-align: center;">Gains (XOF)</th>
        <th class="header" style="text-align: center;">Retenues (XOF)</th>
    </tr>

    <!-- Earnings -->
    <tr>
        <td colspan="2">Salaire de Base</td>
        <td style="text-align: right;">
            {{ data_get($payslip, 'actualwork', 0) }} jrs
        </td>
        <td style="text-align: center;">
            {{ number_format($basicAmount, 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Prime de Performance (KPI)</td>
        <td style="text-align: right;">
            {{ data_get($payslip, 'coefficient', 0) }}
        </td>
        <td style="text-align: center;">
            {{ number_format($num(data_get($payslip, 'monthlykpi', 0)), 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Indemnité de Transport</td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format($num(data_get($payslip, 'transport', 0)), 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Indemnité Téléphone & Carburant</td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format($num(data_get($payslip, 'callallowance', 0)), 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Prime d'Ancienneté / Congés</td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format($num(data_get($payslip, 'leaveallowance', 0)), 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Indemnité de Responsabilité</td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format($num(data_get($payslip, 'overtime', 0)), 2) }}
        </td>
        <td></td>
    </tr>

    <tr>
        <td colspan="2">Heures Supplémentaires</td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format($num(data_get($payslip, 'overtimeallowance', 0)), 2) }}
        </td>
        <td></td>
    </tr>

    <!-- Gross Salary -->
    <tr>
        <td colspan="2" class="total">Salaire Brut</td>
        <td></td>
        <td style="text-align: center;" class="total">
            {{ number_format($grossAmount, 2) }}
        </td>
        <td></td>
    </tr>

    <!-- Deductions -->
    <tr>
        <td colspan="2">CNPS Part Salariale (6.3%)</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format($num(data_get($payslip, 'cnpssalaries', 0)), 2) }}
        </td>
    </tr>

    <tr>
        <td colspan="2">Impôt sur Salaires (ITS)</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format($num(data_get($payslip, 'itsalaries', 0)), 2) }}
        </td>
    </tr>

    <tr>
        <td colspan="2">Autres Déductions</td>
        <td></td>
        <td></td>
        <td style="text-align: center;">
            {{ number_format($num(data_get($payslip, 'otherdeductions', 0)), 2) }}
        </td>
    </tr>

    <!-- Total -->
    <tr>
        <td colspan="3" class="total">Total Général</td>
        <td style="text-align: center;" class="total">
            {{ number_format($grossAmount, 2) }}
        </td>
        <td style="text-align: center;" class="total">
            {{ number_format($num(data_get($payslip, 'totaldeduction', 0)), 2) }}
        </td>
    </tr>

    <!-- Bank & Net Pay -->
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
            {{ number_format($num(data_get($payslip, 'netpay', 0)), 2) }} XOF
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

</table>

</body>
</html>