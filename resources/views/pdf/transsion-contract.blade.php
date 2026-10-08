<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transsion Fixed-Term Employment Contract</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
            footer: html_myFooter;
        }

        body {
            font-family: timesnewroman, Times, serif;
            font-size: 12pt;
            line-height: 1.4;
            color: #000;
        }

        h3 {
            text-align: left;
            font-size: 14pt;
            margin: 20px 0 15px 0;
        }

        h4 {
            text-decoration: underline;
            font-weight: bold;
            font-size: 12pt;
            margin: 15px 0 8px 0;
        }

        p {
            margin: 0 0 10px 0;
            text-align: justify;
        }

        ul {
            margin: 10px 0;
            padding-left: 30px;
        }

        li {
            margin-bottom: 5px;
        }

        table.signature-table {
            width: 100%;
            margin-top: 50px;
            border: none;
        }

        table.signature-table td {
            vertical-align: top;
            padding: 10px 0;
        }

        .italic-bold {
            font-style: italic;
            font-weight: bold;
        }

        .header-img {
            width: 100%;
            margin-top: -50px;
            margin-bottom: 10px;
        }

        .footer-img {
            width: 100%;
            height: auto;
        }

        .image-placeholder {
            color: red;
            font-style: italic;
            font-size: 10pt;
        }
    </style>
</head>
<body>

    <!-- Header Image -->
    @php
        $headerPath = public_path('images/sbphead.png');
        $footerPath = public_path('images/footer.png');
        $employerSignPath = public_path('images/Annsign.png');
        $employeeSignPath = $signature ? public_path('images/transsioncon/' . $signature) : null;

        // Helper to format duration
        function durationToWords($num) {
            $words = [
                1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
                6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
                11 => 'eleven', 12 => 'twelve'
            ];
            return $words[$num] ?? $num;
        }

        $durationText = $duration == 12
            ? "one year (1)"
            : durationToWords($duration) . " (" . $duration . ") " . ($duration == 1 ? "month" : "months");
    @endphp

    @if(file_exists($headerPath))
        <img src="{{ $headerPath }}" class="header-img" alt="Header">
    @else
        <p class="image-placeholder">Header image (sbphead.png) not found</p>
    @endif

    <h3>FIXED-TERM EMPLOYMENT CONTRACT</h3>

    <p>
        Between the undersigned: The company <strong>SBP AFRICA LIMITED</strong>, whose registered office is located at North Legon, Tel: 0302546664, duly registered under the laws of Ghana, hereinafter referred to as "The employer" On the one hand,
    </p>
    <p>And</p>
    <p>
        <strong>{{ strtoupper($employeename) }}</strong>, with Ghana Card No. ……………………… and Digital Address ……………………………………. ("The employee”) agree and decide as follows:
    </p>

    <h4>Article 1 - Commitment, duration and place</h4>
    <p>
        The employee is appointed to the position of <strong>{{ strtoupper($position) }}</strong> or in any other capacity of the same status that the company may require under a Fixed-Term Contract. The contract and/or assignment shall be for a duration of <strong>{{ $durationText }}</strong> and shall commence from <strong>{{ date('F d, Y', strtotime($startdate)) }}</strong> to <strong>{{ date('F d, Y', strtotime($enddate)) }}</strong>. This contract is subject to three (3) months’ probation and shall be confirmed on grounds of performance and discipline.
    </p>
    <p>
        You will perform your duties with <strong>{{ strtoupper($client) }} ({{ strtoupper($department) }})</strong> and be stationed at <strong>{{ $location }}</strong>. However, the worker accepts that this assignment can be transferred to any other place in the territory of Ghana, if the performance of his duties demands or if the employer feels the need. This transfer cannot constitute a substantial modification of this employment contract.
    </p>

    <h4>Article 2 - Collective agreement and internal regulations</h4>
    <p>
        The employee will benefit from the provisions of the labor code and will be subject to the employer’s internal regulations and those of SBP Africa, in accordance with the Labor Code and regulations. You undertake to respect the instructions and work instructions of the user organization.
    </p>

    <h4>Article 3 - Obligations of the employee</h4>
    <p>
        The employee strives to put himself/herself under the control and the directing power of the user employer. You undertake to keep secret and not to divulge to third parties the information of which he/she would have been aware of the fact or on the occasion of their availability. This information is deemed confidential, for the duration of this contract and after its termination, unless there is a legal or judicial obligation or contrary authorization given in writing by the party holding or depositing the information concerned.
    </p>

    <h4>Article 4 - Leave and absences</h4>
    <p>
        The employee is entitled to one day of paid leave for every 15 days of normal time worked. You will be entitled, upon the termination of your employment, to receive payment only in lieu of any annual leave due to you in terms of the Contract of Employment and the law. Such payment will be subject to normal deductions of tax and any other applicable laws. Such annual leave accrual is subject to a maximum capping of 15 working days during each annual leave cycle.
    </p>

    <h4>Article 5 - Hours and remuneration</h4>
    <p>
        Payment of remuneration and/or overtime shall be made by way of electronic transfer directly into the employee’s bank account as provided, and will be net of any deductions made from your salary each month for SSNIT (1&2), PAYE and any authorized deductions where applicable. Your salary will be paid monthly.
    </p>
    <p>You will benefit from a gross monthly salary distributed as follows:</p>
    <p><strong>SALARY STRUCTURE</strong></p>

<ul>
    <li>Basic Salary: {{ number_format((float) $basic, 2) }}</li>

    @if(!empty($allowance))
        <li>Allowance: {{ number_format((float) $allowance, 2) }}</li>
    @endif

    @if(filled($kpi) && $kpi != 0)
        <li>KPI: {{ number_format((float) $kpi, 2) }}</li>
    @endif
</ul>
    <p>The employee will receive a pay slip indicating the various elements of the remuneration.</p>

    <h4>Article 6 - Documentation</h4>
    <p>The employee agrees to submit the under listed documents within <strong>one (1) week</strong> into his/her employment as part of his/her acceptance of this contract. The required documents are:</p>
    <ul>
        <li>1 Copy Certified Educational Certificate</li>
        <li>2 Copies Passport Pictures</li>
        <li>Bank Account Details</li>
        <li>SSNIT Number</li>
        <li>GH Card Number</li>
    </ul>

    <h4 class="italic-bold">
        THE EMPLOYEE AGREES THAT FAILURE TO SUBMIT THE ABOVE DOCUMENTS MEANS HIS/HER SALARY WILL BE WITHHELD UNTIL ALL DOCUMENTS ARE RECEIVED.
    </h4>

    <h4>Article 7 - Competition</h4>
    <p>
        The employee declares that he/she is free from any commitment and is not bound by any non-competition clause with a previous employer. You owe all of your professional activity to the employer. You are prohibited from exercising, even outside working hours, a professional activity likely to compete with or hinder the provision of services by the user employer.
    </p>

    <h4>Article 8 - Termination procedure</h4>
    <p>
        Except in the event of renewal, this contract ends at the expiration of the date agreed and specified in Article 1 hereof. However, before the expiration, it can be terminated in the cases mentioned by the Labor Code.
    </p>

    <h4>Article 9 - Sanctions regime</h4>
    <p>
        Any violation of the rules of discipline or safety will be sanctioned in accordance with the provisions of the internal regulations. In the event of serious negligence on the part of the employee established by the user employer, the employer will decide to dismiss him without notice.
    </p>

    <h4>Article 10 - Jurisdiction clause</h4>
    <p>
        For any dispute resulting from the interpretation and execution of this agreement, the parties undertake to reach an amicable settlement. In the event that disagreement persists, the most diligent party can take the case to the Labor Court for redress.
    </p>

    <p>
        The provision of this contract of employment have been read and understood by me and or explained to me: and by my signature hereunder I voluntarily have accepted this contract of employment.
    </p>

    <h4 class="italic-bold">
        KINDLY RETURN A SIGNED COPY OF THIS CONTRACT TO THE HR DEPARTMENT WITHIN THREE (3) WORKING DAYS OF RECEIPT. PLEASE NOTE THAT THE CONTENT OF THIS CONTRACT CANNOT BE IMPLEMENTED UNTIL THE SIGNED COPY IS RECEIVED.
    </h4>

    <br><br><br>

    <table class="signature-table">
        <tr>
            <td><strong>Signature of Employer</strong></td>
            <td><strong>Signature of Employee</strong></td>
        </tr>
        <tr>
            <td>
                @if(file_exists($employerSignPath))
                    <img src="{{ $employerSignPath }}" width="100">
                @else
                    <p class="image-placeholder">Employer signature (Annsign.png) not found</p>
                @endif
            </td>
            <td>
                @if($signature && file_exists($employeeSignPath))
                    <img src="{{ $employeeSignPath }}" width="200">
                @elseif($signature)
                    <p class="image-placeholder">Employee signature ({{ $signature }}) not found</p>
                @endif
            </td>
        </tr>
        <tr>
            <td>
                {{ strtoupper($employername) }}<br>
                HR-SBP Africa Ltd.<br>
                ({{ date('F d, Y', strtotime($issuedate)) }})
            </td>
            <td>
                {{ strtoupper($employeename) }}<br>
                ({{ \Carbon\Carbon::parse($created_at)->format('F d, Y') }})
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <htmlpagefooter name="myFooter">
        <div style="position: absolute; bottom: 0; left: 0; right: 0; width: 100%;">
            @if(file_exists($footerPath))
                <img src="{{ $footerPath }}" class="footer-img" alt="Footer">
            @else
                <p class="image-placeholder">Footer image (footer.png) not found</p>
            @endif
        </div>
    </htmlpagefooter>

</body>
</html>
