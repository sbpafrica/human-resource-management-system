# SBP Africa HRMS - Complete System User Manual & Operating SOP

**Document Title:** End-to-End User Manual & Standard Operating Procedure  
**Target Audience:** HR Managers, HR Officers, Payroll Officers, System Administrators, Managers, and Staff  
**System Version:** 2026.1  
**Last Updated:** October 2026  

---

## Executive Summary
This Standard Operating Procedure (SOP) provides step-by-step instructions on how to navigate, operate, and manage every module within the **SBP Africa HRMS** platform. Whether you are onboarding new staff, running client payrolls, processing statutory pension filings (SSNIT & Petra Trust), issuing contract renewals, or managing leave requests, this manual serves as your complete operational reference guide.

---

## Table of Contents
- [1. Getting Started & System Access](#1-getting-started--system-access)
- [2. Employee Portal & Profile Management](#2-employee-portal--profile-management)
- [3. SSNIT & SSNIT Inhouse Management](#3-ssnit--ssnit-inhouse-management)
- [4. Bank Details & Bank Codes Management](#4-bank-details--bank-codes-management)
- [5. PETRA (Tier 2/3 Pension) Details Management](#5-petra-tier-23-pension-details-management)
- [6. Contract Management & Automated Renewals](#6-contract-management--automated-renewals)
- [7. Payroll Uploads & Processing](#7-payroll-uploads--processing)
- [8. Payslip Viewing & Bulk PDF Export](#8-payslip-viewing--bulk-pdf-export)
- [9. Leave Management & Approval Workflows](#9-leave-management--approval-workflows)
- [10. Attendance & Time Tracking](#10-attendance--time-tracking)
- [11. HR Audits & Statutory Reports](#11-hr-audits--statutory-reports)
- [12. IT Asset Management & Employee Archiving](#12-it-asset-management--employee-archiving)
- [13. System Administration (Users & Roles)](#13-system-administration-users--roles)

---

## 1. Getting Started & System Access

### 1.1 Logging In
1. Open your web browser and navigate to the application URL (e.g. `https://jobportal.sbpafrica.group`).
2. Enter your authorized **Email Address** and **Password**.
3. Click **Log In**.
4. Upon successful authentication, you will be redirected to the main **Dashboard**.

### 1.2 Dashboard Overview
The main dashboard provides high-level metric cards and shortcuts:
- **Total Employees:** Total active staff count across all managed client companies.
- **Active Contracts:** Contracts currently valid or pending renewal.
- **Pending Leave Applications:** Leave requests awaiting line manager or HR review.
- **Quick Navigation:** Side menu links to all functional modules.

---

## 2. Employee Portal & Profile Management

The Employee Portal (`/employeeportal`) serves as the central directory for staff records across all client companies (Melcom, Twellium, Transsion, PZ Cussons, Jaykay, OFI, Bunge, etc.).

### 2.1 Adding a Single Employee
1. Navigate to **Database** ➔ **Employee Portal** ➔ **Add Employee**.
2. Fill in the required fields:
   - **Staff ID**, **First Name**, **Last Name**, **Client / Company Name**.
   - **Department**, **Position**, **Date of Joining**, **Contact Number**, **Email**.
3. Click **Save Employee**.

### 2.2 Bulk Uploading Employees via Excel
1. Go to **Employee Portal**.
2. Click **Download Template** to get the standard `.xlsx` format.
3. Populate employee records in the Excel template.
4. Click **Bulk Upload**, select your Excel file, and click **Import**.
5. The system automatically maps headers and creates or updates staff records.

### 2.3 Editing and Archiving Employee Records
- **Edit:** Click the **Pencil Icon** next to any employee row to edit contact details, position, or department.
- **Archive Exited Staff:** Click **Archive** to move a resigned/terminated employee to the **Archived** directory (`/employeearchived`) without deleting their historical payroll and SSNIT history.

---

## 3. SSNIT & SSNIT Inhouse Management

Manage outsourced and inhouse SSNIT contribution records (`/ssnitdetails` and `/ssnitdetailsinhouse`).

### 3.1 Searching Employee SSNIT Records
Use the top Search Bar to locate any employee. The multi-parameter search engine supports:
- **Full Name:** Search complete names (e.g. *"John Mensah"*).
- **Individual Name Parts:** Search by first or last name.
- **Staff ID:** Search exact ID (e.g. *"TR001"*).
- **SSNIT Number / National ID:** Search by SSNIT digits or Ghana Card number.

### 3.2 Bulk Uploading SSNIT Data
1. Navigate to **SSNIT & BANK** ➔ **Tier1/SSNIT Details** or **SSNIT Details Inhouse**.
2. Click **Bulk Upload** and attach your Excel spreadsheet.
3. **Automatic Features:**
   - **Header Auto-Seeking:** Locates headers even if summary rows exist above.
   - **Name Auto-Splitting:** If your sheet contains a single Full Name column instead of separate first/last name columns, the system automatically splits them.
   - **Update or Create:** Existing staff SSNIT numbers are updated automatically without throwing duplicate errors.

### 3.3 Exporting SSNIT Reports
Click **Export Excel** to download the complete SSNIT table formatted for statutory compliance.

---

## 4. Bank Details & Bank Codes Management

Manage employee bank account numbers, bank names, branch codes, and swift codes (`/bankdetails` and `/bankcodes`).

### 4.1 Managing Bank Codes
1. Navigate to **BankCodes** (`/bankcodes`).
2. Add or update official bank codes (e.g. GCB, Ecobank, Stanbic, Absa, Fidelity) to ensure salary disbursement files generate correctly.

### 4.2 Bulk Uploading & Mapping Bank Details
1. Go to **SSNIT & BANK** ➔ **Bank Details** (`/bankdetails`).
2. Click **Download Template** for the standard column structure.
3. Upload the completed Excel file containing Staff IDs, Account Numbers, Bank Names, and Branch Codes.
4. Export updated bank schedules for payroll file uploads to banking portals.

---

## 5. PETRA (Tier 2/3 Pension) Details Management

Track mandatory Tier 2 and Tier 3 pension details (`/petradetails`, `/petradetailsinhouse`, `/tecnopetrapay`, `/avarapetrapay`, etc.).

### 5.1 Calculation Standard
- **Formula:** PETRA pension entitlement is calculated as **5% of Basic Entitlement** (`BASIC_SALARY_EARNED_GHC * 0.05`), matching Melcom & Avara statutory payroll standards.

### 5.2 Procedure & Excel Exports
1. Navigate to **SSNIT & BANK** ➔ **Tier2/PETRA Details** or client-specific PETRA Pay views (e.g. UnityCorp PETRA Pay, Avara PETRA Pay).
2. Search staff members by Staff ID, Employee Name, PETRA ID, or Ghana Card.
3. View or update Petra ID, Tier 2 SSNIT Number, and Tier 3 Contribution Percentage.
4. Click **Export to Excel** to download the complete `.xlsx` payment summary spreadsheet (including Staff ID, Shop, Name, PETRA ID, Ghana Card, Basic Salary, and 5% PETRA Amount with totals) directly for Petra Trust submission.

---

## 6. Contract Management & Automated Renewals

### 6.1 Creating a Contract Renewal (Transsion & Client Contracts)
1. Navigate to **CONTRACT** ➔ **Client** ➔ **Renewals** (e.g. `/transsion/contract-renewals`).
2. Click **Create Renewal** or upload via **Bulk Upload**.
3. Fill in contract terms:
   - Staff ID, Employee Name, Position, Department.
   - Start Date, End Date, Expired Date, Employer Name, Issue Date.
   - HR Email (Issuer), Employee Email, Copy Email.

### 6.2 Initial Renewal Email Notification (Unsigned PDF)
- When saved, the system automatically generates an **Unsigned PDF Addendum**.
- An email is dispatched automatically to the Employee Email with CC to HR Email, attaching the PDF renewal addendum for signing.

### 6.3 Signing & Activation Workflow
1. Once the staff member returns the signed contract addendum, navigate to the Renewals table.
2. Click the **Activate / Sign** button for that renewal record.
3. The system sets `Activate = Yes (1)`.

### 6.4 Automated Signed Confirmation Email to Issuer / HR
- Upon activation, the system compiles the **Signed PDF Addendum** (with signature attached).
- An automated email notification is immediately dispatched to HR / Issuer Email (`hremail`), with CC to `copymail`, delivering the final signed contract addendum for HR records.

---

## 7. Payroll Uploads & Processing

The system includes client-specific payroll engines (Transsion BFA, Tecno, Melcom, Xiaomi, PZ Cussons, Twellium, OFI External, Bunge, etc.).

### 7.1 Monthly Payroll Upload Process
1. Navigate to your specific client payroll module (e.g. **Salary Payment** ➔ **Transsion Pay** `/transsionpay`).
2. Click **Upload Payroll File** and select the month's Excel sheet.
3. **The engine performs:**
   - **Header seeking:** Automatically skips summary rows at the top of raw Excel sheets.
   - **Column alias matching:** Maps variations like *Staff.No, Fisrt name, 1st Dept., FINAL NET SALARY*.
4. Review the uploaded payroll summary table for accuracy.

### 7.2 Deleting or Replacing a Payroll Month
If errors exist in an uploaded sheet, click **Delete Month**, select the affected payroll period, and re-upload the corrected spreadsheet.

---

## 8. Payslip Viewing & Bulk PDF Export

Navigate to **Payslips** (`/payslips`) to view web previews and download PDF payslips for staff across all clients.

### 8.1 Viewing Payslips Online
1. Filter payslips by Client, Month, or Staff ID.
2. Click **View** on any row to open the clean web preview.
3. **Normalized Fields:** Displays Basic Salary, Gross Pay, PAYE Tax, SSNIT 5.5% EE deduction, Petra (5%) deduction, Net Pay, and special line items.
4. **Description/Days/Hours Formatting:** Workdays and absent days display as clean integers (e.g., `26` workdays), weekend overtime displays in days (`Weekend OT Amount / Daily Rate`), and transport description displays `0` when transport allowance is `0.00` GHS.
5. **Special Earnings:** Displays Performance Bonus, Allowances, Overtime, and End of Contract Rights conditionally if present.

### 8.2 Client-Specific Confidential Layouts (Avara, Melcom, etc.)
- **Avara Payslips:** Automatically selects the dedicated `avara-pdf.blade.php` layout featuring SSNIT EE (5.5%), Petra (5%), PAYE, Gross Salary, and Net Pay along with employee bank and branch information.
- **Bulk PDF Download:** Select client and month, then click **Bulk Download PDFs** to generate a single `.zip` containing all individual confidential PDFs.

---

## 9. Leave Management & Approval Workflows

Manage annual, casual, sick, and maternity leave requests (`/leave-applications`).

### 9.1 Employee Application Steps
1. Navigate to **Leave System** ➔ **Leave Applications** ➔ **Apply for Leave**.
2. Select Leave Type (*Annual, Sick, Casual, Maternity, Paternity*).
3. Select Start Date, End Date, and enter Reason.
4. Enter Manager Email and click **Submit Application**.

### 9.2 Manager Email Review & Approval
1. The line manager receives an automated email containing a secure review link.
2. Clicking the link opens the manager review page (no login required for the manager).
3. The manager clicks **Approve** or **Reject** with optional comments.
4. The system updates the leave balance and notifies HR and the employee automatically.

---

## 10. Attendance & Time Tracking

Track daily staff attendance, clock-ins/outs, and monthly work days (`/attendance`).

### 10.1 Steps
1. Navigate to **Attendance**.
2. Click **Upload Employees** or **Upload Attendance Log**.
3. Select the biometric Excel/CSV export file.
4. Click **Export Summary** to generate monthly working days reports for payroll input.

---

## 11. HR Audits & Statutory Reports

Access dedicated compliance audit modules under **HR Audit**:
- **Bank Audit (`/hr-audit/bank`):** Cross-references staff bank account numbers against payroll sheets.
- **SSNIT Audit (`/hr-audit/ssnit`):** Validates SSNIT numbers and contribution calculations.
- **PETRA Audit (`/hr-audit/petra`):** Audits Tier 2 and Tier 3 pension data completeness.
- **Contacts Audit (`/hr-audit/contacts`):** Verifies phone numbers, emails, and address data.
- **New Joiners & Exited Staff (`/hr-audit/new-joiners`, `/hr-audit/exited-staff`):** Reports staff onboarding and offboarding for statutory filings.
- **Melcom Basic (`/hr-audit/melcom-basic`):** Verifies basic salary thresholds.

Click **Export Excel** on any audit page to download formatted compliance spreadsheets.

---

## 12. IT Asset Management & Employee Archiving

### 12.1 IT Asset Tracking (`/it-assets`)
1. Register laptops, mobile phones, SIM cards, and accessories assigned to staff.
2. Track asset serial numbers, assignment dates, and return statuses upon offboarding.

### 12.2 Archived Staff Directory (`/employeearchived`)
Retrieve historical data for former employees without clogging active employee lists.

---

## 13. System Administration (Users & Roles)

System Administrators manage user accounts and system permissions under **Admin Management** (`/admin/users` and `/admin/roles`).

### 13.1 Creating System Users
1. Go to **Admin Management** ➔ **Users** ➔ **Create User**.
2. Enter Name, Email Address, Password, and assign a Role (e.g. *HR Admin, Payroll Officer, Read-Only Auditor*).
3. Click **Save User**.

### 13.2 Role Permissions Matrix
- **Super Admin:** Full access to all modules, client payrolls, and system settings.
- **HR Officer:** Access to Employee Portal, Contracts, SSNIT, Bank Details, and Leave Management.
- **Payroll Officer:** Access to Client Payroll Uploads, Payslips, and Bank Audits.
- **Auditor:** Read-only access to HR Audits and statutory reports.

---

## 14. Additional Support & Contact

If you encounter system errors or require access permissions, contact the SBP Africa System Administration team or refer to the developer documentation in [`DEVELOPER_ONBOARDING_SOP.md`](file:///DEVELOPER_ONBOARDING_SOP.md).
