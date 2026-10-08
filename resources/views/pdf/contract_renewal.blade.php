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
                    public_path('images/melcomcon/' . $sigClean),
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

                    base_path('../hrs.sbpafrica.group/public/images/signatures/' . $sigClean),
                    base_path('../hrs.sbpafrica.group/storage/app/public/images/signatures/' . $sigClean),

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
        <h3 style="text-align: left;">Date: {{ $issuedate }}</h3>
        <h3 style="text-align: left;">TO: {{ $employeename }}</h3>
        <h3 style="text-align: left;">FROM: SBP AFRICA LTD</h3>
        <h3 style="text-align: left;">SUBJECT: FIXED TERM CONTRACT AMENDMENT</h3>
        <br><br>

        <p>Dear {{ $firstname }},</p>

        <p>This addendum serves to amend your current Fixed Term Contract with <strong>SBP AFRICA LTD.</strong> assigned to <strong>MELCOM GHANA LIMITED </strong>{{ $location }} as an {{ $position }}.</p>

        <p>Your basic salary has been adjusted by <strong>{{ $adjusted }}</strong> effective <strong>{{ $startdate }}</strong>  as stated below:</p>

        <p>
            <ul>
                <li><strong> Salary: GHC 800.00</strong>
                </li></ul></p>
        <p>
            However, all the other terms and conditions of your fixed term contract remain unchanged.
        </p>

        <p>If you accept this contract amendment, kindly sign and return a copy of this letter to the HR department within three (3) working days of receipt by mail – hr@sbpafrica.group.</p>

        <p>Please note that the content of this letter cannot be implemented until the acceptance is received.</p>

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
                        <p class="image-placeholder">Employer signature (Annsign.png) not found</p>
                    @endif
                </td>
                <td>
                    @if($employeeSignatureSrc)
                        <img src="{{ $employeeSignatureSrc }}" width="200px">
                    @elseif($signature)
                        <p class="image-placeholder">Employee signature ({{ basename($signature) }}) not found</p>
                    @endif
                </td>
            </tr>
            <tr>
                <td>Antoinette Awuku <br>HR-SBP Africa Ltd.<br>({{ $issuedate }})</td>
                <td>{{ $employeename }}.<br>({{ $datecreated }})</td>
            </tr>
        </table>
    </div>

    @if(file_exists($footerPath))
        <div class="footer">
            <p style="font-size: 8pt; color: blue;">Footer Debug: Should Appear on All Pages</p>
            <img src="file://{{ $footerPath }}" style="width: 100%; height: auto; margin: 0; padding: 0;">
        </div>
    @else
        <div class="footer">
            <p class="image-placeholder">Footer image (footer.png) not found</p>
        </div>
    @endif
</body>
</html>
