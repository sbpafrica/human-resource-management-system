<!DOCTYPE html>
<html>
<head>
    <style>
        @page {
            margin: 20mm 15mm 5mm 15mm; /* Bottom margin 5mm */
        }
        body {
            font-family: timesnewroman;
            margin: 0;
            padding: 0;
            font-size: 12pt;
        }
        h3 {
            font-family: timesnewroman;
            text-align: center;
            font-size: 14pt;
            margin-bottom: 10px;
        }
        h4 {
            font-family: timesnewroman;
            text-decoration: underline;
            font-weight: bold;
            font-size: 12pt;
            margin-top: 10px;
        }
        ul {
            font-family: timesnewroman;
            list-style-type: disc;
            padding-left: 20px;
            margin: 10px 0;
        }
        ol {
            font-family: timesnewroman;
            list-style-type: lower-roman;
            padding-left: 20px;
            margin: 10px 0;
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
        p, li {
            font-family: timesnewroman;
            margin: 0 0 8px 0;
            line-height: 1.4;
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
            display: block !important; /* Force footer rendering */
        }
        .image-placeholder {
            font-family: timesnewroman;
            color: red;
            font-style: italic;
            font-size: 10pt;
        }
        .content {
            margin-bottom: 5mm; /* Match bottom margin */
        }
        .section {
            page-break-inside: avoid; /* Prevent splitting of section */
            page-break-before: auto; /* Allow natural page breaks */
            page-break-after: auto; /* Prevent extra breaks after section */
            margin-bottom: 10px;
        }
        @page :first {
            margin: 20mm 15mm 5mm 15mm;
            footer: html_footer; /* Reference footer */
        }
        /* Force footer on first page */
        .footer-first {
            position: absolute;
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
    </style>
</head>
<body>
    @php
        $headerPath = public_path('images/sbphead.png');
        $footerPath = public_path('images/footer.png');
        $employerSignaturePath = public_path('images/Annsign.png');
        $employeeSignaturePath = $signature ? public_path('images/melcomcon/' . $signature) : null;
    @endphp

    <div class="content">
        @if(file_exists($headerPath))
            <img src="file://{{ $headerPath }}" style="margin-top: -50px; margin-bottom: 10px; width: 100%;">
        @else
            <p class="image-placeholder">Header image (sbphead.png) not found</p>
        @endif

        <h3>FIXED-TERM EMPLOYMENT CONTRACT</h3>

        <div class="section">
            <p>
                This Employment Contract is made between:
            </p>
            <p>
                The company <strong>SBP AFRICA LTD.</strong>, whose registered office is located at North Legon, Tel: 0302546664, duly registered under the laws of Ghana, hereinafter referred to as "The employer" On the one hand,under the laws of the Republic of Ghana (hereinafter referred to as "the Employer")
            </p>
            <p> And </p>
            <p><strong>{{ $employeename }}</strong>, ("the employee”) with Ghana Card No. <strong>{{ $ghanacard }}</strong> and Digital Address <strong>{{ $digiaddress }}</strong> agree and decide as follows:</p>
        </div>

        <div class="section">
            <h4>1.	APPOINTMENT AND DURATION</h4>
            <p>
                The Employer hereby engages the Employee as a <strong>{{ $position }}</strong> or in any other role of comparable status as may be assigned. This is a fixed-term outsourced employment contract commencing on <strong>{{ $startdate }}</strong> and shall be subject to a probationary period of <strong>six (6) months</strong> in accordance with the Labour Act, 2003 (Act 651). Upon successful completion of the probationary period, the employment shall continue on an ongoing basis unless and until terminated by either party in accordance with the termination provisions of this contract or unless otherwise formally communicated in writing by the Employer. No annual renewal shall be required for the continuation of this employment contract. The employer reserves the right to review the terms and conditions of employment periodically.
            </p>
            <p>
                The Employee shall be assigned to work with <strong>MELCOM GHANA LIMITED</strong> and be stationed at <strong>{{ $location }}</strong>. The Employer reserves the right to transfer the Employee to any client location within Ghana, provided such transfer does not substantially alter the terms of this contract.
            </p>
        </div>

        <div class="section">
            <h4>2.	CONDITIONS OF EMPLOYMENT</h4>
            <ol type="i">
                <li>This employment relationship shall be governed by the Labour Act, 2003 (Act 651), and the internal rules and policies of the Employer and the Client organization. The Employee agrees to comply with all lawful instructions, policies, safety, and security procedures of both the Employer and Client; any such breach shall lead to immediate termination.</li>
                <li>The Employee agrees not to participate, or incite persons to participate in any action that may adversely affect any of the operations of our Client; any such action that may also obstruct or retard the work of the employer shall result in immediate dismissal.</li>
                
            </ol>
        </div>

        <div class="section">
            <h4>3.	CONFIDENTIALITY AND NON-DISCLOSURE</h4>
            <p>The Employee shall not disclose any confidential information obtained during the course of employment to third parties, both during and after termination of employment, except where disclosure is required by law or authorized in writing.</p>
        </div>

        <div class="section">
            <h4>4.	LEAVE AND ABSENCE</h4>
            <ol type="i">
                <li>The employee shall be entitled to fifteen (15) working days of paid annual leave after twelve (12) months of continuous service, in line with Act 651. The Employee shall be entitled, upon the termination of employment, to receive payment only in lieu of any annual leave due to him/her in terms of the Contract of Employment and the law. Such payment shall be subject to normal deductions of tax and any other applicable laws.</li>
                <li>Paid sick leave shall be granted in accordance with the Labour Act upon submission of a valid medical certificate from a registered medical practitioner within 48 hours of the start of the absence.</li>
                
            </ol>
        </div>

        <div class="section">
            <h4>5.	DRESS CODE AND COMPANY PROPERTY</h4>
            <p>The Employee shall adhere to the dress code prescribed by the Client. Where the employee wishes to exit from the employment, all properties acquired must be returned to the employer. Failure of which the employee shall be surcharged with the cost of those properties.</p>
        </div>

        <div class="section">
            <h4>6.	HOURS OF WORK AND REMUNERATION</h4>
            <p>The Employee shall work up to 8 hours per day, excluding meal breaks, Mondays through to Fridays. You may be required to work overtime depending upon the Client’s operational requirements and by signing this contract, you agree unequivocally to such arrangements.</p>
            <p>
                Payment shall be made monthly via bank transfer, net of statutory deductions including SSNIT (1&2), PAYE and any authorized deductions where applicable.
            </p>
            <p><strong>SALARY STRUCTURE</strong></p>
            <ul>
                <li>Basic Salary: GHS {{ $basic }}</li>
            </ul>
            <p>The employee shall receive a pay slip indicating the various elements of the remuneration.</p>
        </div>

        <div class="section" style="margin-top:20px">
            <h4>7.	DOCUMENTATION, SALARY WITHHOLDING, AND STATUTORY COMPLIANCE</h4>
            <p>The Employee shall <strong>within two (2) weeks </strong> of commencement of employment, submit all required documents necessary for payroll processing and statutory compliance in accordance with the Labour Act, 2003 (Act 651), and applicable regulatory requirements.</p>
            
            <p>Where the Employee fails or neglects to submit the required documents within the stipulated period, the Employer reserves the right to <strong>temporarily withhold payment of salary</strong> until such documents are duly submitted. Salary so withheld shall be released upon full compliance by the Employee and shall not constitute forfeiture of wages.</p>
            
           
           
        </div>
        
        <div class="section" >
            
            
            <p>Where the Employee’s failure or delay in submitting the required documents results in statutory penalties, surcharges, interest, or liabilities being imposed on the Employer by any statutory authority (including but not limited to SSNIT, NPRA or the Ghana Revenue Authority), such liabilities shall be <strong>borne by the Employee</strong> and may be <strong>lawfully recovered through deductions from the Employee’s salary,</strong> subject to the provisions of the Labour Act, 2003 (Act 651).</p>
            
            <p>The Employer shall not be liable for any delays in salary processing arising directly from the Employee’s non-compliance with documentation requirements.
            The required documents are:</p>
            <ul>
                <li>Certified copy of Educational Certificate</li>
                <li>Two (2) passport-size photographs</li>
                <li>Bank Account Details</li>
                <li>SSNIT Number</li>
                <li>GH Card Number</li>
            </ul>
           
        </div>

        <div class="section">
            <h4>8.	NON-COMPETITION</h4>
            <p>The Employee warrants that they are not bound by any non-competition clause with a previous employer. The Employee owes all their professional activity to the employer. They are prohibited from exercising, even outside working hours, a professional activity likely to compete with or hinder the provision of services by the Client.</p>
        </div>

        <div class="section">
            <h4>9.	TERMINATION</h4>
            <p>Either party may terminate this contract in accordance with the Labour Act and the Company’s termination procedures by giving one (1) month’s written notice or payment of one (1) month’s basic salary in lieu of notice. The notice period shall be one (1) week for probationary employees and two (2) weeks for one (1) year of engagement or pay in lieu of notice.</p>
        </div>

        <div class="section">
            <h4>10.	DISCIPLINARY PROCEDURE</h4>
            <p>Disciplinary matters shall be handled in accordance with the Employer’s disciplinary policy and the Labour Act. Summary dismissal shall only occur for just cause as defined under the law.</p>
        </div>

        <div class="section">
            <h4>11.	DISPUTE RESOLUTION</h4>
            <p>Any dispute arising under this contract shall first be resolved amicably, failing which it shall be referred to the National Labour Commission for redress.</p>
        </div>

        <div class="section">
            <p>The provision of this contract of employment have been read and understood by me and or explained to me: and by my signature, hereunder I voluntarily have accepted this contract of employment.</p>
        </div>

        <div class="section">
            <p style="font-style: italic; font-weight: bold;">
               KINDLY RETURN A SIGNED COPY OF THIS CONTRACT TO THE HR DEPARTMENT WITHIN THREE (3) WORKING DAYS OF RECEIPT. PLEASE NOTE THAT THE CONTENT OF THIS CONTRACT CANNOT BE IMPLEMENTED UNTIL THE SIGNED COPY IS RECEIVED.
            </p>
        </div>
        
        <div class="section">
            <h4>SIGNED BY THE PARTIES:</h4>
           
        </div>

        <div class="section">
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
                        @if($signature && file_exists($employeeSignaturePath))
                            <img src="file://{{ $employeeSignaturePath }}" width="200px">
                        @elseif($signature)
                            <p class="image-placeholder">Employee signature ({{ $signature }}) not found</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td>{{ $employername }}<br>HR-SBP Africa Ltd.</td>
                    <td>{{ $employeename }}<br></td>
                </tr>
            </table>
        </div>
    </div>

    @if(file_exists($footerPath))
        <div class="footer" id="footer">
            <p style="font-size: 8pt; color: blue;"></p>
            <img src="file://{{ $footerPath }}" style="width: 100%; height: auto; margin: 0; padding: 0;">
        </div>
        <div class="footer-first" id="footer-first">
            <p style="font-size: 8pt; color: blue;"></p>
            <img src="file://{{ $footerPath }}" style="width: 100%; height: auto; margin: 0; padding: 0;">
        </div>
    @else
        <div class="footer" id="footer">
            <p class="image-placeholder">Footer image (footer.png) not found</p>
        </div>
        <div class="footer-first" id="footer-first">
            <p class="image-placeholder">Footer image (footer.png) not found (First Page)</p>
        </div>
    @endif
</body>
</html>
