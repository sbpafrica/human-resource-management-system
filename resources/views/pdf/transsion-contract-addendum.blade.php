<!DOCTYPE html>
<html>
<head>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
        }

        body {
            font-family: timesnewroman;
            margin: 0;
            padding: 0;
            font-size: 12pt;
        }

        h3 {
            font-family: timesnewroman;
            font-size: 14pt;
            margin: 10px 0;
        }

        p {
            font-family: timesnewroman;
            margin: 0 0 8px 0;
            line-height: 1.4;
            text-align: justify;
        }

        table {
            font-family: timesnewroman;
            width: 100%;
            margin-top: 10px;
        }

        td {
            vertical-align: top;
            padding: 5px;
        }

        /* DATE / TO / FROM / SUBJECT alignment */
        .letter-meta {
            width: 100%;
            border-collapse: collapse;
            margin: 0 0 12px 0;
            font-family: timesnewroman;
            font-size: 14pt;
            font-weight: bold;
        }

        .letter-meta td {
            padding: 5px 0;
            vertical-align: top;
            text-align: left;
        }

        .letter-meta td:first-child {
            width: 170px;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: auto;
            margin: 0;
            padding: 0;
            text-align: center;
            z-index: 1000;
            display: block !important;
        }

        .image-placeholder {
            font-family: timesnewroman;
            color: red;
            font-style: italic;
            font-size: 10pt;
        }

        .content {
            margin-bottom: 20mm;
        }

        @page :first {
            margin: 20mm 15mm 20mm 15mm;
        }
    </style>
</head>

<body>

    @php
        $headerPath = public_path('images/sbphead.png');
        $footerPath = public_path('images/footer.png');
        $employerSignaturePath = public_path('images/Annsign.png');

        $employeeSignatureSrc = null;
        if (!empty($signature)) {
            if (str_starts_with($signature, 'data:image')) {
                $employeeSignatureSrc = $signature;
            } else {
                $sigClean = basename($signature);
                $sigPathClean = ltrim($signature, '/');
                $candidatePaths = [
                    public_path('images/signatures/' . $sigClean),
                    public_path('images/transsioncon/' . $sigClean),
                    public_path('images/signatures/' . $signature),
                    public_path($sigPathClean),
                    public_path($signature),
                    public_path('storage/images/signatures/' . $sigClean),
                    public_path('storage/signatures/' . $sigClean),
                    public_path('storage/' . $sigPathClean),

                    storage_path('app/public/images/signatures/' . $sigClean),
                    storage_path('app/public/signatures/' . $sigClean),
                    storage_path('app/public/' . $sigPathClean),
                    storage_path('app/public/' . $signature),
                    storage_path('app/signatures/' . $sigClean),
                    storage_path('app/' . $sigPathClean),

                    base_path('../employeetrans.sbpafrica.group/public/images/signatures/' . $sigClean),
                    base_path('../employeetrans.sbpafrica.group/public/images/transsioncon/' . $sigClean),
                    base_path('../employeetrans.sbpafrica.group/storage/app/public/images/signatures/' . $sigClean),
                    base_path('../hrs.sbpafrica.group/public/images/signatures/' . $sigClean),
                    base_path('../hrs.sbpafrica.group/storage/app/public/images/signatures/' . $sigClean),

                    '/home/u1710176/employeetrans.sbpafrica.group/public/images/signatures/' . $sigClean,
                    '/home/u1710176/employeetrans.sbpafrica.group/public/images/transsioncon/' . $sigClean,
                    '/home/u1710176/employeetrans.sbpafrica.group/storage/app/public/images/signatures/' . $sigClean,
                    '/home/u1710176/hrs.sbpafrica.group/public/images/signatures/' . $sigClean,
                    '/home/u1710176/hrs.sbpafrica.group/storage/app/public/images/signatures/' . $sigClean,
                    '/home/u1710176/hrs.sbpafrica.group/public/storage/images/signatures/' . $sigClean,
                ];
                foreach ($candidatePaths as $path) {
                    if (file_exists($path) && is_readable($path)) {
                        $employeeSignatureSrc = 'file://' . realpath($path);
                        break;
                    }
                }
            }
        }
    @endphp

    <div class="content">

        @if(file_exists($headerPath))
            <img src="file://{{ $headerPath }}" style="margin-top: -50px; margin-bottom: 10px; width: 100%;">
        @else
            <p class="image-placeholder">Header image (sbphead.png) not found</p>
        @endif

        <h3 style="text-align: center;">ADDENDUM</h3>

        <!-- DATE / TO / FROM / SUBJECT -->
        <table class="letter-meta">
            <tr>
                <td>DATE:</td>
                <td>{{ strtoupper($issuedate) }}</td>
            </tr>

            <tr>
                <td>TO:</td>
                <td>{{ strtoupper($employeename) }}</td>
            </tr>

            <tr>
                <td>FROM:</td>
                <td>SBP AFRICA LTD.</td>
            </tr>

            <tr>
                <td>SUBJECT:</td>
                <td>FIXED – TERM CONTRACT RENEWAL</td>
            </tr>
        </table>

        <br>

        <p>
            Dear {{ explode(' ', $employeename)[0] ?? $employeename }},
        </p>

        <p class="mb-4" style="text-align: justify;">
            Your current Fixed Term Contract with
            <strong>SBP Africa Ltd.</strong> assigned to
            <strong>Transsion Technology Ghana Limited ({{ $contract->department }})</strong>
            as <strong>{{ $contract->position1 }}</strong> expired on
            <strong>{{ \Carbon\Carbon::parse($contract->expireddate)->format('F d, Y') }}</strong>.
        </p>

        <p class="mb-4" style="text-align: justify;">
            However, after careful assesment of your work, management has decided to renew your
            contract for one year (1 year) effective
            <strong>{{ \Carbon\Carbon::parse($contract->startdate)->format('F d, Y') }}</strong>
            and it expires on
            <strong>{{ \Carbon\Carbon::parse($contract->enddate)->format('F d, Y') }}</strong>.
        </p>

        <p class="mb-4" style="text-align: justify;">
            Additionally, please note that
            <strong>Transsion Technology GH Ltd</strong> has officially changed its registered
            name to <strong>GOOD EASE TECHNOLOGY GH LTD.</strong> This change relates solely to
            the company's legal name and shall not affect the terms, conditions, rights, or
            obligations contained in this contract renewal letter or your original contract
            under the name Transsion Technology GH Ltd.
        </p>

        <p class="mb-4" style="text-align: justify;">
            However, all the terms of your Fixed Term Contract remain unchanged.
        </p>

        <p>
            If you accept this contract extension, please sign the acceptance note and return the
            signed copy to the HR department within three (3) working days of receipt by mail –
            hr@sbpafrica.group
        </p>

        <p>
            Please note that the content of this letter cannot be implemented until the acceptance
            is received.
        </p>

        <br><br><br><br>

        <table style="width: 100%;">
            <tr>
                <td>Signature of Employer</td>
                <td>Signature of Employee</td>
            </tr>

            <tr>
                <td>
                    @if(file_exists($employerSignaturePath))
                        <img src="file://{{ $employerSignaturePath }}" width="100px">
                    @else
                        <p class="image-placeholder">
                            Employer signature (Annsign.png) not found
                        </p>
                    @endif
                </td>

                <td>
                    @if($employeeSignatureSrc)
                        <img src="{{ $employeeSignatureSrc }}" width="200px">
                    @elseif($signature)
                        <p class="image-placeholder">
                            Employee signature ({{ basename($signature) }}) not found
                        </p>
                    @endif
                </td>
            </tr>

            <tr>
                <td>
                    ANTOINETTE AWUKU<br>
                    (Human Resource Manager)<br>
                </td>

                <td>
                    {{ strtoupper($employeename) }}.<br>
                    ({{ $datecreated }})
                </td>
            </tr>
        </table>

    </div>

    @if(file_exists($footerPath))
        <div class="footer">
            <img src="file://{{ $footerPath }}"
                 style="width: 100%; height: auto; margin: 0; padding: 0;">
        </div>
    @else
        <div class="footer">
            <p class="image-placeholder">
                Footer image (footer.png) not found
            </p>
        </div>
    @endif

</body>
</html>