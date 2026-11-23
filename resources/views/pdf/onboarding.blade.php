<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Employee Onboarding Form - {{ $employee->employeename }}</title>
    <style>
        @page {
            margin: 20px 25px 25px 25px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #333333;
            line-height: 1.35;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .logo-img {
            max-height: 45px;
            width: auto;
        }
        .title {
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .subtitle {
            font-size: 10px;
            color: #64748b;
            text-align: right;
        }
        .profile-summary {
            width: 100%;
            margin-bottom: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px;
        }
        .profile-photo {
            width: 75px;
            height: 75px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid #cbd5e1;
        }
        .emp-name {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .emp-meta {
            font-size: 10px;
            color: #475569;
        }
        .section-title {
            background-color: #1e40af;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            padding: 4px 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 10px;
            margin-bottom: 4px;
            border-radius: 2px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .data-table td {
            padding: 3px 6px;
            vertical-align: top;
            font-size: 9.5px;
        }
        .label {
            width: 25%;
            font-weight: bold;
            color: #475569;
            background-color: #f1f5f9;
            border: 1px solid #e2e8f0;
        }
        .val {
            width: 25%;
            color: #0f172a;
            border: 1px solid #e2e8f0;
        }
        .sig-card-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .sig-box {
            width: 32%;
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: center;
            background: #ffffff;
            vertical-align: top;
        }
        .card-box {
            width: 34%;
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: center;
            background: #ffffff;
            vertical-align: top;
        }
        .preview-img {
            max-width: 100%;
            max-height: 110px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .box-title {
            font-size: 9px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .box-meta {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 4px;
        }
        .no-img {
            color: #94a3b8;
            font-style: italic;
            padding: 25px 0;
            font-size: 9px;
        }
        .footer {
            margin-top: 15px;
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
            font-size: 8px;
            color: #64748b;
            text-align: center;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" class="logo-img" alt="SBP Africa">
                @else
                    <span style="font-size: 16px; font-weight: bold; color: #1e3a8a;">SBP AFRICA</span>
                @endif
            </td>
            <td style="width: 50%; vertical-align: middle;">
                <div class="title">Employee Onboarding Form</div>
                <div class="subtitle">Generated on {{ date('F d, Y') }} | Staff ID: {{ $employee->staffid }}</div>
            </td>
        </tr>
    </table>

    <!-- Profile Summary Row -->
    <table class="profile-summary" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 85px; vertical-align: middle;">
                @if(!empty($profilePicBase64))
                    <img src="{{ $profilePicBase64 }}" class="profile-photo" alt="Photo">
                @else
                    <div style="width: 75px; height: 75px; background: #e2e8f0; border-radius: 4px; text-align: center; line-height: 75px; font-size: 24px; color: #64748b; font-weight: bold;">
                        {{ strtoupper(substr($employee->employeename, 0, 1)) }}
                    </div>
                @endif
            </td>
            <td style="vertical-align: middle; padding-left: 10px;">
                <div class="emp-name">{{ strtoupper($employee->employeename) }}</div>
                <div class="emp-meta"><strong>Staff ID:</strong> {{ $employee->staffid }} &nbsp;|&nbsp; <strong>Company:</strong> {{ $employee->company ?? 'N/A' }}</div>
                <div class="emp-meta"><strong>Position:</strong> {{ $employee->position ?? 'N/A' }} &nbsp;|&nbsp; <strong>Shop/Dept:</strong> {{ $employee->shop ?? 'N/A' }}</div>
                <div class="emp-meta"><strong>Ghana Card PIN:</strong> {{ $employee->ghcard ?? 'N/A' }}</div>
            </td>
        </tr>
    </table>

    <!-- Personal & Employment Information -->
    <div class="section-title">1. Personal & Employment Information</div>
    <table class="data-table">
        <tr>
            <td class="label">Date of Birth:</td>
            <td class="val">{{ $employee->dateofbirth ?? 'N/A' }}</td>
            <td class="label">Company:</td>
            <td class="val">{{ $employee->company ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Gender:</td>
            <td class="val">{{ $employee->gender ?? 'N/A' }}</td>
            <td class="label">Shop / Branch:</td>
            <td class="val">{{ $employee->shop ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Nationality:</td>
            <td class="val">{{ $employee->nationality ?? 'N/A' }}</td>
            <td class="label">Position:</td>
            <td class="val">{{ $employee->position ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Ghana Card No:</td>
            <td class="val">{{ $employee->ghcard ?? 'N/A' }}</td>
            <td class="label">Start Date:</td>
            <td class="val">{{ $employee->startdate ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Qualification:</td>
            <td class="val">{{ $employee->qualification ?? 'N/A' }}</td>
            <td class="label">GPS Address:</td>
            <td class="val">{{ $employee->gpsaddress ?? 'N/A' }}</td>
        </tr>
    </table>

    <!-- Contact & Next of Kin -->
    <div class="section-title">2. Contact, Next of Kin & Guarantor</div>
    <table class="data-table">
        <tr>
            <td class="label">Phone 1:</td>
            <td class="val">{{ $employee->phone1 ?? 'N/A' }}</td>
            <td class="label">Next of Kin:</td>
            <td class="val">{{ $employee->nextofkin ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Phone 2:</td>
            <td class="val">{{ $employee->phone2 ?? 'N/A' }}</td>
            <td class="label">Relationship:</td>
            <td class="val">{{ $employee->relation ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Email:</td>
            <td class="val">{{ $employee->email ?? 'N/A' }}</td>
            <td class="label">Next of Kin Contact:</td>
            <td class="val">{{ $employee->contact ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Guarantor Name:</td>
            <td class="val">{{ $employee->guarantor ?? 'N/A' }}</td>
            <td class="label">Guarantor Contact:</td>
            <td class="val">{{ $employee->contactgua ?? 'N/A' }}</td>
        </tr>
    </table>

    <!-- Bank, SSNIT & PETRA Details -->
    <div class="section-title">3. Statutory & Banking Information</div>
    <table class="data-table">
        <tr>
            <td class="label">Bank Name:</td>
            <td class="val">{{ $bankDetail->bank ?? 'N/A' }}</td>
            <td class="label">SSNIT Number:</td>
            <td class="val">{{ $ssnit->ssnit1a ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Account Number:</td>
            <td class="val">{{ $bankDetail->account ?? 'N/A' }}</td>
            <td class="label">SSNIT Name:</td>
            <td class="val">
                @php
                    $ssnitName = trim(($ssnit->firstname ?? '') . ' ' . ($ssnit->lastname ?? '') . ' ' . ($ssnit->othername ?? ''));
                @endphp
                {{ !empty($ssnitName) ? $ssnitName : 'N/A' }}
            </td>
        </tr>
        <tr>
            <td class="label">Bank Branch:</td>
            <td class="val">{{ $bankDetail->branch ?? 'N/A' }}</td>
            <td class="label">PETRA ID:</td>
            <td class="val">{{ $petra->petraId ?? 'N/A' }}</td>
        </tr>
    </table>

    <!-- Signature & Ghana Card Verification -->
    <div class="section-title">4. Signature & Ghana Card Verification</div>
    <table class="sig-card-table" cellpadding="0" cellspacing="4">
        <tr>
            <!-- Signature Box -->
            <td class="sig-box">
                <div class="box-title">Digital Signature</div>
                @if(!empty($signatureBase64))
                    <img src="{{ $signatureBase64 }}" class="preview-img" style="max-height: 80px;" alt="Signature">
                    <div class="box-meta">Signed: {{ $employee->signature_date ?? 'Verified' }}</div>
                @else
                    <div class="no-img">No signature available</div>
                @endif
            </td>

            <!-- Ghana Card Front Box -->
            <td class="card-box">
                <div class="box-title">Ghana Card (Front)</div>
                @if(!empty($ghanaFrontBase64))
                    <img src="{{ $ghanaFrontBase64 }}" class="preview-img" alt="Ghana Card Front">
                    <div class="box-meta">Verified ID Document</div>
                @else
                    <div class="no-img">No card front uploaded</div>
                @endif
            </td>

            <!-- Ghana Card Back Box -->
            <td class="card-box">
                <div class="box-title">Ghana Card (Back)</div>
                @if(!empty($ghanaBackBase64))
                    <img src="{{ $ghanaBackBase64 }}" class="preview-img" alt="Ghana Card Back">
                    <div class="box-meta">Verified ID Document</div>
                @else
                    <div class="no-img">No card back uploaded</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="footer">
        Confidential Employee Profile Document &bull; SBP Africa Limited &bull; Printed electronically on {{ date('Y-m-d H:i:s') }}
    </div>

</body>
</html>
