<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Olam Food Fixed Term Contract</title>
    <style>
        @page {
           margin: 5mm 10mm 18mm 10mm; /* Bottom = footer height only */
            size: A4;
        }

        body {
            font-family: serif; /* Fallback to built-in serif (Times-like) to avoid font errors */
            line-height: 1.6;
            color: #000;
            margin: 0;
            padding-top: 5mm;
            font-size: 14px;
        }

        .content {
            padding: 5mm 5mm 5mm 5mm;
        }

        h3, h4 {
            font-family: serif;
        }

        h3 {
            font-size: 16px;
            margin: 8px 0 10px 0;
            text-align: center;
            font-weight: bold;
        }

        h4 {
            font-size: 14px;
            margin: 12px 0 6px 0;
            font-weight: bold;
        }

        p, li {
            font-size: 14px;
            margin: 10px 0;
            text-align: justify;
        }

        ul {
            margin: 6px 0;
            padding-left: 20px;
        }

        ul li {
            margin: 3px 0;
        }

        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .italic { font-style: italic; }

        .header-img {
            display: block;
            width: 100%;
            height: auto;
            margin: 0;
            padding: 0;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 18mm;
            text-align: center;
            padding: 0;
            margin: 0;

            z-index: 9999;
        }

        .footer img {
            height: 18mm;
            width: auto;
            max-width: 100%;
            display: block;
            margin: 0;
            padding: 0;
        }

        .signature-table {
            width: 100%;
            margin-top: 40px;
        }

        .signature-table td {
            vertical-align: top;
            padding: 10px 0;
            text-align: center;
            width: 50%;
        }

        .signature-img {
            max-width: 150px;
            height: auto;
        }
    </style>
</head>
<body>

    <!-- HEADER IMAGE (base64 embedded) -->
    @if($headerImg)
        <img src="{{ $headerImg }}" class="header-img" alt="Header">
    @else
        <div style="color:red; text-align:center; font-style:italic;">HEADER IMAGE MISSING</div>
    @endif

    <div class="content">

        <h3 class="text-center">FIXED TERM CONTRACT OF EMPLOYMENT</h3>

        <p class="text-center"><strong>Between</strong></p>
        <p class="text-center"><strong>SBP AFRICA LTD.</strong><br>(hereinafter known as “the employer”)</p>
        <p class="text-center"><strong>And</strong></p>
        <p class="text-center"><strong>{{ htmlspecialchars($employeename ?? 'Employee Name') }}</strong><br>(hereinafter known as “the employee”)</p>

        <p><strong>1.</strong> This Contract of Employment is made in its terms of The Labour Act of Ghana 2003 (Act 651).</p>

        <h4>2. DESIGNATION AND LOCATION</h4>
        <p class="clause">
            <strong>2.1.</strong> The employee shall report to {{ !empty($client) ? strtoupper($client) : 'OLAM FOOD INGREDIENTS (ofi)' }} (hereinafter known as “the Client”). The specific address location of the site to which the employee is assigned is recorded as <strong>{{ strtoupper($location ?? 'NKAWKAW') }}</strong> and under direct supervision of CLMRS Field Supervisor.
        </p>

        <p class="clause">
            <strong>2.2.</strong> The employee is employed in the capacity of <strong>{{ strtoupper($position1 ?? 'CLMRS FIELD OFFICER') }}</strong> or in such other capacity of a like status as the client of the company may require. CLMRS Field Officer is the primary point of contact for households covered by the CLMRS.
        </p>

        <div class="job-desc">
            <p><strong>Job Description</strong></p>
            <ul>
                <li>Visit assigned households and their farms to engage with farming families and support them to better protect their children through the following activities:</li>
                <li>Raise awareness about child labour and child rights and supporting households to positively change behaviour to protect children</li>
                <li>Interview adults and all children aged 5-17 in each household, to identify children in child labour or in situations of risk</li>
                <li>Travel to farms to verify if children are working and complement information collected during household visits</li>
                <li>Advise and support households to change behaviour to help children out of situations of child labour, including delivering material support, where needed</li>
                <li>Recommend additional support, based on the situation and needs of the child(ren) and household</li>
                <li>Conduct follow-up visits (every 3-6 months) to each child identified in child labour to their changing situation and needs</li>
                <li>Monitor the implementation of prevention and remediation actions to ensure the best possible impact</li>
                <li>Support child protection in assigned communities through:</li>
                <li>Raising awareness about child labour and child rights at community level</li>
                <li>Collecting accurate community level data about the presence and quality of essential services</li>
                <li>Use standard data collection tools to collect data, ensuring all data collected is uploaded using the correct mobile data collection application.</li>
                <li>Facilitate visits and mission by International Cocoa Initiative staff and other partners to the community and/or the households</li>
                <li>Maintain all equipment and materials provided for the purpose of this project in good condition.</li>
            </ul>
        </div>

        <p class="clause">
            <strong>2.3.</strong> The employer agrees to hire the services of the employee and the employee in turn agrees to render to the employer his/her services at all fair and reasonable times on the terms and conditions set out hereunder. Notwithstanding that the employee has been employed in the above capacity he/she may be required from time to time to render additional reasonable services for which he/she will be trained but will not necessarily be entitled to additional remuneration.
        </p>

        <h4>3. DURATION</h4>
        <p class="clause">
            The contract and/or assignment shall be for a duration of <strong>{{ !empty($duration) ? $duration . ' months' : 'Twelve (12) months' }}</strong> and shall commence on <strong>{{ \Carbon\Carbon::parse($startdate)->format('d/m/Y') }}</strong> and endure through to <strong>{{ \Carbon\Carbon::parse($enddate)->format('d/m/Y') }}</strong>. This contract is subject to <strong>six (6) months of probation</strong> and shall be renewed on grounds of performance and discipline.
        </p>

        <p class="clause">
            <strong>3.1.</strong> The Client may withdraw and/or shorten the agreement period entered into between itself and the employer, if
            <div class="subclause">
                <strong>3.1.1.</strong> The Client indicating written or verbal dissatisfaction as to the work or conduct of the employee to the employer;<br>
                <strong>3.1.2.</strong> The contract being terminated if the employee is found guilty of conduct justifying a dismissal or commits a breach of any term of the Contract of Employment and/or company policy.
            </div>
        </p>

        <p class="clause">
            <strong>3.2.</strong> Given the nature of assignments awarded to the company which are dependent upon the Client’s operational requirements, the employer is not in a position to offer permanent employment to the employee, or with any degree of certainty predict the duration of an assignment and hence the fixed term duration of this contract of employment.
        </p>

        <p class="clause">
            <strong>3.3.</strong> The employee shall only be remunerated whilst actively working on an assignment at the agreed rate between the parties.
        </p>

        <p class="clause">
            <strong>3.4.</strong> Any extension or renewal of this contract of employment must be in writing as an Addendum to this contract.
        </p>

        <h4>4. HOURS OF WORK</h4>
        <p class="clause">
            <strong>4.1.</strong> You will be required to work in accordance with the operational needs and requirements of the Client. You may, however, be required to work up to a maximum of <strong>40 ordinary hours of work per working week</strong> (excluding meal intervals), depending upon the operational needs and requirements of the Client at any particular time.
        </p>

        <p class="clause">
            <strong>4.2.</strong> Due to the nature of the Clients business, you will be required to work on extra time and on weekends within each 24-hour operating period on any day of the week
        </p>

        <p class="clause">
            <strong>4.3.</strong> Your starting and finishing times will be decided upon and may be changed by management depending on the operational needs of the Client.
        </p>

        <p class="clause">
            <strong>4.4.</strong> It is your obligation to maintain contact with the employer to obtain timely update of the engagement which will apply to the Client to which you are assigned to, however, the company will initiate most updates when necessary.
        </p>

        <p class="clause">
            <strong>4.5.</strong> You may be required to work overtime and to work into late hours depending upon the Client’s operational requirements and by signing this contract you agree unequivocally to such arrangements.
        </p>

        <p class="clause">
            <strong>4.6.</strong> <strong>Clocking</strong> – The employee is aware that should he/she fail to clock in or out, or sign the attendance register, he/she will not be remunerated for those hours worked. This is a strict rule and will always be adhered to. No exceptions will be made. Any leniency on behalf of the Employer will not be considered a permanent relaxation of this clause.
        </p>

        <h4>5. REMUNERATION</h4>
        <p class="clause">
            <strong>5.1.</strong> Payment of remuneration and overtime shall be made by way of electronic transfer directly into the employee’s bank account as provided, and will be net of any deductions made from your salary each month for SSNIT (1&2), PAYE and any authorized deductions where applicable. Your salary will be paid monthly.<br>
            The employer shall, in consideration for the due rendering by the employee of his/her services in terms of this agreement, pay the employee a <strong>Basic Salary of GHC {{ $basic ?? '5,500.00' }}</strong>@if(!empty($insurance))<strong> plus {{ $insurance }}</strong>@endif.<br>
            The employee will receive a pay slip indicating the various elements of the remuneration.
        </p>

        <p class="clause">
            <strong>5.2.</strong> It is specifically recorded that the employee’s rate of pay may be varied with prior notice to the employee from time to time, depending on the site and/or Client to which the employee’s services are allocated, in accordance with the Agreement(s) concluded between the employer and the Client.<br>
            It is recorded further that the variation in the employee’s rate of pay referred to herein is a vagary inherent in the industry, which the employee accepts as fair and reasonable standard practice.
        </p>

        <p class="clause">
            <strong>5.3.</strong> You will not be entitled to any remuneration for any period of unauthorized absence including Industrial Action.
        </p>

        <p class="clause">
            <strong>5.4.</strong> You hereby irrevocably authorize the Employer to deduct from your salary all amounts which may be due by you to the company, in accordance with instructions received by the Employer or the Client for goods bought/ lost or money borrowed for which you have acknowledged indebtedness to the company and/or the Client in writing. You will be notified in writing of any amendments to be made to such deductions by the Employer from time to time, which must, unless required by operation of law, be approved in writing by you prior to such deductions being made.
        </p>

        <h4>6. ANNUAL LEAVE</h4>
        <p class="clause">
            <strong>6.1.</strong> You will be entitled to <strong>15 days annual leave</strong> for a continuous service of one year.
        </p>

        <h4>7. SICK AND FAMILY RESPONSIBILITY LEAVE</h4>
        <p class="clause">
            <strong>7.1.</strong> Sick leave may only be claimed when you are rostered to work but are unable to do so due to ill health.
        </p>

        <p class="clause">
            <strong>7.2.</strong> If you are absent from work for two (2) or more consecutive days or on more than two (2) consecutive occasions during an eight (8) week period, you will be required to produce a medical certificate to qualify for paid sick leave.
        </p>

        <p class="clause">
            <strong>7.3.</strong> If you become permanently unable, in the opinion of the company, to perform your duties adequately by reason of ill health or incapacity, the company reserves the right to terminate your Contract of Employment.
        </p>

        <p class="clause">
            <strong>7.4.</strong> In the event of absence due to illness or other reasonable cause, employees are required to notify the company or assigned Client supervisor at least six hours within the day. A medical certificate or other reasonable proof of illness must be provided; failure to do so may result in disciplinary procedures.
        </p>

        <p class="clause">
            <strong>7.5.</strong> Family Responsibility (birth, death or severe illness) is limited to a direct family relationship (grandparents, parents, siblings, spouse and children). After the first four (4) months of employment, and on presentation of reasonable proof, the employee is entitled to up to <strong>five (5) days paid family responsibility leave per annum</strong>, non‐accumulative.
        </p>
<br><br>
        <h4>8. OVERTIME, SUNDAY WORK AND PUBLIC HOLIDAYS</h4>
        <p class="clause">
            <strong>8.1</strong> It is a material term and condition of this Contract that you agree to work overtime, on Weekends as well as on public holidays as this forms part of your paid commission/ overtime for the month when there is an unavoidable need.
        </p>

        <h4>9. MEAL INTERVALS</h4>
        <p class="clause">
            <strong>9.1.</strong> Meal Intervals are unpaid.
        </p>

        <p class="clause">
            <strong>9.2.</strong> By your signature to this agreement, you agree to reduce your meal interval to a minimum of <strong>thirty (30) minutes</strong> should you work fewer than six (6) hours per day otherwise, you have <strong>one hour of meal hours per day</strong>.
        </p>

        <h4>10. EMPLOYMENT POLICIES</h4>
        <p class="clause">You undertake to:</p>
        <p class="clause">
            <strong>10.1.</strong> Carry out all functions and duties that are assigned to you by the company and/or clients of the company which are reasonable or lawful.
        </p>
        <p class="clause">
            <strong>10.2.</strong> Obey and comply with all lawful and reasonable instructions given to you by supervisors in the employ of the clients of the company and/or the company.
        </p>
        <p class="clause">
            <strong>10.3.</strong> Demonstrate loyalty and honesty to the clients of the company and/or the company in all dealings and transactions relating to its business and interests and to protect the business reputation and goodwill of the company to the best of your abilities.
        </p>
        <p class="clause">
            <strong>10.4.</strong> Provide your Manager with such information and reports as may be required of you in connection with the performance of your duties in the business of the company and/or client.
        </p>
        <p class="clause">
            <strong>10.5.</strong> Devote the whole of your time and attention during working hours to the client’s business and such additional time as the exigencies of the company and/or the clients of the company’s businesses may require.
        </p>

        <h4>11. HEALTH AND SAFETY, UNIFORMS AND PROTECTIVE CLOTHING</h4>
        <p class="clause">
            The employee accepts and acknowledges that he/she will at all times exercise due caution whilst conducting employment duties and will abide by all Health and Safety rules and Regulations pertaining to his/her place of work.
        </p>

        <p class="clause">
            <strong>11.1.</strong> Notwithstanding that any uniform, protective garment and/or equipment may be issued to you by the company, it remains the property of the company and you will be responsible for the maintenance of such attire and/or equipment until the stated replacement date. The employee will be personally liable for the cost of repair or replacement for loss or damage to personal protective equipment prior to the replacement date.
        </p>

        <p class="clause">
            <strong>11.2.</strong> You will be required to return the items immediately upon termination of your employment with the company if this date is prior to the replacement date. Final pay will only be disbursed once all outstanding personal protective equipment has been returned.
        </p>

        <h4>12. SEARCHING</h4>
        <p class="clause">
            The employee hereby consents to being searched at your place of employment by a person or persons designated by the clients of the company and/or the company should a personal search be required. The client of the company and/or the company undertake to ensure as far as possible that female employees will be searched by female persons and male employees by male persons.
        </p>

        <h4>13. PERFORMANCE STANDARDS</h4>
        <p class="clause">
            It is important that you perform in accordance with the agreed performance standards applicable to your job as communicated from time to time to you by the company and/or the clients of the company.
        </p>

        <h4>14. CONFIDENTIAL NATURE OF YOUR WORK</h4>
        <p class="clause">
            You will not at any time, either during your employment or after the termination thereof and without prior express written consent of the company, divulge, reveal, publish or otherwise communicate to any person, firm or company (other than to an officer or employee of the company who should know) any of the trade secrets, operations, processes, formulae or methods of the company and/or its associated companies which have come or which may come to your knowledge during your employment and any and all other information of a confidential nature concerning the business of the company and/or its controlling, subsidiary or associated companies and the sources from which it obtained its materials.
        </p>

        <p class="clause">
            <strong>14.1.</strong> You will respect the confidentiality of such information. You will use your best endeavours to prevent the disclosure or publication (other than as aforesaid) of any writings made by you relative to the business of the company and/or its controlling, subsidiary or associated companies. Such writings are confidential and shall be handed over by you to the company and/ or its associated companies, from time to time on demand and in any event by you upon leaving the services of the company for any reason whatsoever.
        </p>

        <h4>15. TERMINATION OF EMPLOYMENT</h4>
        <p class="clause">
            <strong>15.1.</strong> Your employment may be terminated, either for reasons of misconduct, incapacity or the operational requirements of the company or for any reason justified in law, which includes, inter alia, the following. If you:
            <div class="subclause">
                <strong>15.1.1.</strong> commit any serious or persistent breach of any provisions of the agreement;<br>
                <strong>15.1.2.</strong> are guilty of any serious misconduct or deliberate neglect in the discharge of your duties under this agreement.<br>
                <strong>15.1.3.</strong> absent yourself from your employment without prior notification and/or approval upon having been rostered to work scheduled shifts;<br>
                <strong>15.1.4.</strong> Disobey a lawful order or direction from your supervisor and/or a supervisor in the employ of the Client of the company or the management of the company;<br>
                <strong>15.1.5.</strong> Are convicted of any criminal offence other than an offence which in the reasonable opinion of the company does not affect your position as an employee of the company;<br>
                <strong>15.1.6.</strong> Fail to give your whole time and attention to the business of the company and/or the clients of the company and/or carry on another business enterprise which may affect your relationship with the company;<br>
                <strong>15.1.7.</strong> fail to carry out any of your duties in a proper manner;<br>
                <strong>15.1.8.</strong> Fail to comply with the terms and conditions applicable to any rules of conduct laid down by the clients of the company and/or the company from time to time;<br>
                <strong>15.1.9.</strong> in the absence of written authorization from the company to the contrary, fail to maintain contact with the client to obtain details of weekly shift rosters for a period in excess of fourteen (14) days calculated from the date on which you last worked, the Contract of Employment will automatically terminate without further notice.
            </div>
        </p>

        <p class="clause">
            <strong>15.2. Non-Compete</strong><br>
            The employee declares that he/she is free from any commitment and is not bound by any non-competition clause with a previous employer. You owe all of your professional activity to the employer. You are prohibited from exercising, even outside working hours, a professional activity likely to compete with or hinder the provision of services by the company and/or the client
        </p>

        <p class="clause">
            <strong>15.2.1.</strong> The employee will have to undertake a compliance training and to declare any conflict of interests using the Declaration of Conflict of Interests form which shall be attached to the employment contract. The declaration itself is not grounds for automatic termination, however providing false information may result in termination.
        </p>

        <h4>16. NOTICE PERIOD</h4>
        <p class="clause">
            The employee understands that the termination of the assignment prior to the date specified in this contract, is subject to the requirements of the client of the employer and the notice period will be in accordance with the notice period given to the employer by the client. In any event the notice period shall be <strong>one week for probationary employees, two weeks for one year of engagement and one month for a year and over</strong>. The employee shall give notice equivalent to the same period or pay in lieu if they wish to resign.
        </p>

        <h4>17. CRIMINAL RECORD</h4>
        <p class="clause">
            The employee herewith agrees to criminal record checks being requested by the employer, both during the recruitment process, as well as any time during this contract agreement.<br>
            Should it be discovered that an employee indicated that he/she has no criminal record, and the contrary is revealed at any time, this contract may be terminated.
        </p>

        <h4>18. GENERAL</h4>
        <p class="clause">
            <strong>18.1.</strong> It is recorded that in accepting the Fixed Term nature of your employment, you specifically indemnify and hold harmless the employer and the client of the employer against:
            <div class="subclause">
                <strong>18.1.1.</strong> any claim for retrenchment pay;<br>
                <strong>18.1.2.</strong> any claim for pay in lieu of notice or damages for termination of employment without notice;
            </div>
        </p>

        <p class="clause">
            <strong>18.2.</strong> All remaining terms and conditions applicable to your employment will be regulated by company policy and the company’s grievance and disciplinary procedures, including any other applicable legislation as amended from time to time. Copies of this documentation are available from the Human Resource Office of the company.
        </p>

        <p class="clause">
            <strong>18.3.</strong> The employee agrees to submit the underlisted documents within one (1) week into his/her employment as part of his/her acceptance of this contract. The required documents are:
            <ul>
                <li>1 Copy of Certified Educational Certificate</li>
                <li>2 Copies of Passport Photographs</li>
                <li>Bank Account Details</li>
                <li>SSNIT number</li>
                <li>Police Clearance Report</li>
                <li>Guarantor’s Form</li>
            </ul>
            <strong class="italic bold">THE EMPLOYEE AGREES THAT FAILURE TO SUBMIT THE ABOVE DOCUMENTS MEANS HIS/HER SALARY WILL BE WITHHELD UNTIL ALL DOCUMENTS ARE RECEIVED.</strong>
        </p>

        <p class="italic bold" style="margin-top: 75px;">
            KINDLY RETURN A SIGNED COPY OF THIS CONTRACT TO THE HR DEPARTMENT WITHIN THREE (3) WORKING DAYS OF RECEIPT.<br>
            PLEASE NOTE THAT THE CONTENT OF THIS CONTRACT CANNOT BE IMPLEMENTED UNTIL THE SIGNED COPY IS RECEIVED.
        </p>

        <!-- SIGNATURE TABLE -->
        <table class="signature-table">
            <tr>
                <td class="bold">EMPLOYER: SBP AFRICA LTD.</td>
                <td class="bold">EMPLOYEE:</td>
            </tr>
            <tr>
                <td>
                    @if($employerSigImg)
                        <img src="{{ $employerSigImg }}" class="signature-img">
                    @else
                        <div style="width:150px; height:50px; border:1px dashed #999; margin:0 auto; text-align:center;"></div>
                    @endif
                </td>
                <td>
                    @if($employeeSigImg)
                        <img src="{{ $employeeSigImg }}" class="signature-img">
                    @else
                        <div style="width:150px; height:50px; border:1px dashed #ffffff; margin:0 auto;"></div>
                    @endif
                </td>
            </tr>
            <tr>
                <td>
                    ANTOINETTE AWUKU<br>

                    {{ \Carbon\Carbon::parse($issuedate)->format('d/m/Y') }}
                </td>
                <td>
                    {{ htmlspecialchars($employeename ?? 'Employee Name') }}<br>

                    {{ $updated_at ? \Carbon\Carbon::parse($updated_at)->format('d/m/Y') : date('d/m/Y') }}
                </td>
            </tr>
        </table>

    </div>

    <!-- FOOTER IMAGE ON EVERY PAGE -->
    <div class="footer" >
        @if($footerImg)
            <img src="{{ $footerImg }}" alt="Footer" style="padding-top: 60px" >
        @else
            <div style="color:red; text-align:center; font-style:italic;">FOOTER IMAGE MISSING</div>
        @endif
    </div>

</body>
</html>
