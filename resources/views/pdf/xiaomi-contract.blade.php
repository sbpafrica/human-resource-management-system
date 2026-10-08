<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Xiaomi Fixed Term Contract of Employment</title>
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
            text-align: center;
            font-size: 16px;
            margin: 10px 0;
        }

        h4 {
            font-size: 14px;
            margin: 15px 0 8px;
            font-weight: bold;
        }

        p {
            margin: 0 0 10px 0;
            text-align: justify;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        ol {
            margin: 10px 0;
            padding-left: 25px;
            line-height: 1.8;
        }

        ul {
            margin: 8px 0;
            padding-left: 20px;
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

        .signature-table td {
            vertical-align: top;
            padding: 10px 0;
        }

        .total-line {
            border-top: 1px solid #000;
            padding-top: 3px;
        }
    </style>
</head>
<body>

    @php
        $headerPath = public_path('images/sbphead.png');
        $footerPath = public_path('images/footer.png');
        $employerSignPath = public_path('images/Annsign.png');
        $employeeSignPath = $signature ? public_path('images/xiaomicon/' . $signature) : null;

        // Format dates
        $start_date_formatted = date('F d, Y', strtotime($startdate));
        $end_date_formatted   = date('F d, Y', strtotime($enddate));
        $issue_date_formatted = date('F d, Y', strtotime($issuedate));
        $employee_sign_date   = $created_at ? date('F d, Y', strtotime($created_at)) : date('F d, Y');

        // Calculate totals (you may also calculate in controller if preferred)
        $totalProbation = number_format(
            ($basic ?? 0) + ($kpi ?? 0) + ($transport ?? 0),
            2
        );

        $totalPostProbation = number_format(
            ($basicafter ?? 0) + ($kpiafter ?? 0) + ($transportafter ?? 0),
            2
        );
    @endphp

    @if(file_exists($headerPath))
        <img src="{{ $headerPath }}" class="header-img" alt="Header">
    @else
        <p class="image-placeholder">Header image (sbphead.png) not found</p>
    @endif

    <h3>FIXED TERM CONTRACT OF EMPLOYMENT</h3>

    <p style="text-align: center;"><strong>Between</strong></p>
    <p style="text-align: center;"><strong>SBP AFRICA LTD.</strong><br>(hereinafter known as “the employer”)</p>
    <p style="text-align: center;"><strong>And</strong></p>
    <p style="text-align: center;"><strong>{{ strtoupper($employeename) }}</strong><br>(hereinafter known as “the employee”)</p>

    <p><strong>1.</strong> This Contract of Employment is made in terms of The Labour Act of Ghana 2003 (Act 651).</p>

    <h4>2. DESIGNATION AND LOCATION</h4>
    <p><strong>2.1.</strong> The employee shall report to <strong>{{ strtoupper($client ?? 'Xiaomi') }}</strong> (hereinafter known as “the Client”). The specific address location of the site to which the employee is assigned is recorded as <strong>{{ strtoupper($location) }}</strong>.</p>
    <p><strong>2.2.</strong> The employee is employed in the capacity of <strong>{{ strtoupper($position) }}</strong> or in such other capacity of a like status as the client of the company may require.</p>
    <p><strong>2.3.</strong> The employer agrees to hire the services of the employee and the employee in turn agrees to render to the employer his/her services at all fair and reasonable times on the terms and conditions set out hereunder. Notwithstanding that the employee has been employed in the above capacity he/she may be required from time to time to render additional reasonable services for which he/she will be trained but will not necessarily be entitled to additional remuneration.</p>

    <h4>3. DURATION</h4>
    <p>The contract and/or assignment shall be for a duration of <strong>Twelve (12) months</strong> and shall commence on <strong>{{ $start_date_formatted }}</strong> and endure through to <strong>{{ $end_date_formatted }}</strong>. This contract is subject to <strong>three (3) months of probation</strong> and shall be confirmed on grounds of performance and discipline and renewed after the period stipulated.</p>

    <ol>
        <li>The Client withdrawing and/or shortening the agreement period entered into between itself and the employer, or</li>
        <li>The Client indicating written or verbal dissatisfaction as to the work conduct of the employee to the employer; or</li>
        <li>The contract being terminated if the employee is found guilty of conduct justifying a dismissal or commits a breach of any term of the Contract of Employment.</li>
        <li>Given the nature of assignments awarded to the company which are dependent upon the Client’s operational requirements, the employer is not in a position to offer permanent employment to the employee, or with any degree of certainty predict the duration of an assignment and hence the fixed–term duration of this contract of employment.</li>
        <li>The employee shall only be remunerated whilst actively working on an assignment at the agreed rate between the parties.</li>
        <li>Any extension or renewal of this contract of employment must be in writing as an Addendum to this contract.</li>
    </ol>

    <h4>4. HOURS OF WORK</h4>
    <ol>
        <li>You will be required to work in accordance with the operational needs and requirements of the Client. You may, however, be required to work to a minimum of 40 ordinary hours spread over six days per week (excluding meal intervals), depending upon the operational needs and requirements of the Client at any particular time.</li>
        <li>Due to the nature of the Clients business, you will be required to work on extra time and on weekends within each 24-hour operating period on any day of the week.</li>
        <li>Your starting and finishing times will be decided upon and may be changed by management depending on the operational needs of the Clients.</li>
    </ol>

    <h4>5. REMUNERATION</h4>
    <p><strong>5.1.</strong> Payment of remuneration shall be made by way of electronic transfer directly into the employee’s bank account as provided, and will be net of any deductions made from your salary each month for SSNIT (1&2) and PAYE and any authorized deductions where applicable. Your salary will be paid monthly.</p>
    <p><strong>5.2. SALARY STRUCTURE:</strong> You will benefit from a gross monthly salary distributed as follows:</p>

    <p style="margin-top: 15px; font-weight: bold;">During Probation</p>
    <table style="font-size: 13px; margin-bottom: 10px;">
        <tr><td>· Basic Salary:</td><td style="text-align: right; width: 140px;">GHC {{ number_format($basic ?? 0, 2) }}</td></tr>
        <tr><td>· KPI Bonus: <em>(Subject to performance)</em></td><td style="text-align: right;">GHC {{ number_format($kpi ?? 0, 2) }}</td></tr>
        <tr><td>· Phone Bill/ Credit Allowance:</td><td style="text-align: right;">GHC {{ number_format($transport ?? 0, 2) }}</td></tr>
        <tr class="total-line"><td style="font-weight: bold; text-align: right; padding-right: 10px;">TOTAL</td><td style="font-weight: bold; text-align: right;">GHC {{ $totalProbation }}</td></tr>
    </table>

    <p style="margin-top: 20px; font-weight: bold;">After Probation</p>
    <table style="font-size: 13px;">
        <tr><td>· Basic Salary:</td><td style="text-align: right; width: 140px;">GHC {{ number_format($basicafter ?? 0, 2) }}</td></tr>
        <tr><td>· KPI Bonus: <em>(Subject to performance)</em></td><td style="text-align: right;">GHC {{ number_format($kpiafter ?? 0, 2) }}</td></tr>
        <tr><td>· Phone Bill/ Credit Allowance:</td><td style="text-align: right;">GHC {{ number_format($transportafter ?? 0, 2) }}</td></tr>
        <tr class="total-line"><td style="font-weight: bold; text-align: right; padding-right: 10px;">TOTAL</td><td style="font-weight: bold; text-align: right;">GHC {{ $totalPostProbation }}</td></tr>
    </table>

    <p>And the gross is before employee deductions for Tax and both Social Security contributions.<br>
    The employee will receive a pay slip indicating the various elements of the remuneration.</p>

    <p><strong>5.3.</strong> It is specifically recorded that the employee’s rate of pay may be varied with/without notice to the employee from time to time, depending on the site and/or Client to which the employee’s services are allocated, in accordance with the Agreement(s) concluded between the employer and the Clients.</p>
    <p><strong>5.4.</strong> It is recorded further that the variation in the employee’s rate of pay referred to herein is a vagary inherent in the industry, which the employee accepts as fair and reasonable standard practice.</p>
    <p><strong>5.5.</strong> You will not be entitled to any remuneration for any period of unauthorized absence including Industrial Action.</p>
    <p><strong>5.6.</strong> You hereby irrevocably authorize the Employer to deduct from your salary all amounts which may be due by you to the company...</p>

    <h4>6. ANNUAL LEAVE</h4>
    <p>The employee is entitled to <strong>15 days</strong> annual leave for a continuous service of over one year...</p>

    <h4>7. SICK AND FAMILY RESPONSIBILITY LEAVE</h4>
    <ol>
        <li>Sick leave may only be claimed when you are rostered to work but are unable to do so due to ill health.</li>
        <!-- You can add more points here if needed -->
        <li>Family Responsibility (birth, death or severe illness) is limited to a direct family relationship... entitled to up to <strong>five (5) days</strong> paid family responsibility leave per annum, non‐accumulative.</li>
    </ol>

    <!-- You may continue with other articles (8–15) if you have the full text -->

    <h4>16. GENERAL</h4>
    <ol>
        <li>The employee agrees to submit the under listed documents within <strong>one (1) week</strong> into his/her employment...
            <ul>
                <li>Copy Certified Educational Certificate</li>
                <li>2 Copies Passport Pictures</li>
                <li>Bank Account Details</li>
                <li>SSNIT Number</li>
                <li>GH Card Number</li>
            </ul>
            <p class="italic-bold" style="margin: 15px 0;">
                THE EMPLOYEE AGREES THAT FAILURE TO SUBMIT THE ABOVE DOCUMENTS MEANS HIS/HER SALARY WILL BE WITHHELD UNTIL ALL DOCUMENTS ARE RECEIVED.
            </p>
        </li>
    </ol>

    <p style="margin-top: 35px;"><strong>Consent:</strong> The provision of this contract of employment have been read and understood by me and or explained to me: and by my signature hereunder I voluntarily have accepted this contract of employment.</p>

    <p style="text-align: center; font-style: italic; font-weight: bold; margin: 20px 0 30px;">
        KINDLY RETURN A SIGNED COPY OF THIS CONTRACT TO THE HR DEPARTMENT WITHIN THREE (3) WORKING DAYS OF RECEIPT. PLEASE NOTE THAT THE CONTENT OF THIS CONTRACT CANNOT BE IMPLEMENTED UNTIL THE SIGNED COPY IS RECEIVED.
    </p>

    <table class="signature-table" style="width: 100%; margin-top: 40px; font-size: 14px;">
        <tr>
            <td style="font-weight: bold;">EMPLOYER: SBP AFRICA LTD.</td>
            <td style="font-weight: bold;">EMPLOYEE:</td>
        </tr>
        <tr>
            <td>
                @if(file_exists($employerSignPath))
                    <img src="{{ $employerSignPath }}" width="120">
                @else
                    <p class="image-placeholder">Employer signature (Annsign.png) not found</p>
                @endif
            </td>
            <td style="text-align: left; padding: 10px;">
                @if($signature && file_exists($employeeSignPath))
                    <img src="{{ $employeeSignPath }}" width="200" height="auto">
                @else
                    <div style="width: 200px; height: 60px; border: 1px dashed #ccc; margin: 0 auto; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #999;"></div>
                @endif
            </td>
        </tr>
        <tr>
            <td>
                <br>ANTOINETTE AWUKU<br>
                <div style="border-bottom: 1px solid #000; width: 180px; margin: 8px 0;"></div>
                {{ $issue_date_formatted }}
            </td>
            <td>
                <br>{{ strtoupper($employeename) }}<br>
                <div style="border-bottom: 1px solid #000; width: 180px; margin: 8px 0;"></div>
                {{ $employee_sign_date }}
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
