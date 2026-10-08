import zipfile
import os

content_types_xml = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>"""

rels_xml = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>"""

doc_rels_xml = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>"""

document_xml = """<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:pPr><w:pStyle w:val="Title"/></w:pPr>
      <w:r><w:rPr><w:b/><w:sz w:val="48"/><w:color w:val="1F497D"/></w:rPr><w:t>HRS Payroll System - Accomplishments Summary</w:t></w:r>
    </w:p>
    <w:p>
      <w:r><w:rPr><w:i/><w:color w:val="595959"/></w:rPr><w:t>Period: October 6 – October 7, 2026 | SBP Africa HRS Project</w:t></w:r>
    </w:p>
    <w:p/>

    <!-- Section 1 -->
    <w:p>
      <w:r><w:rPr><w:b/><w:sz w:val="32"/><w:color w:val="1F497D"/></w:rPr><w:t>1. Avara Payroll Management Module</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>• Database Architecture &amp; Dynamic Tables: Designed the Avarapay Eloquent model and dynamic table generator (createTableIfNotExists) supporting monthly tables (avaramarch26 through avaraseptember26).</w:t></w:r></w:p>
    <w:p><w:r><w:t>• Resilient Upload Engine (AvaraPayController.php): Built dynamic header-driven upload parser ($getCell) supporting 43 columns, date parsing, numeric cleanups, duplicate checking, and fallback net pay calculations.</w:t></w:r></w:p>
    <w:p><w:r><w:t>• Admin Dashboard Interface (Avarapay/Index.tsx): Developed month selection, staff search, pagination, and file upload UI.</w:t></w:r></w:p>
    <w:p/>

    <!-- Section 2 -->
    <w:p>
      <w:r><w:rPr><w:b/><w:sz w:val="32"/><w:color w:val="1F497D"/></w:rPr><w:t>2. Avara Payslip Engine (Web Preview &amp; PDF)</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>• Petra (5%) Formula Integration: Calculated Petra on Avara payslips as 5% of basic salary earned (BASIC_SALARY_EARNED_GHC * 0.05), matching the exact formula used in Melcom payslips.</w:t></w:r></w:p>
    <w:p><w:r><w:t>• Dynamic PDF View Resolver (resolvePayslipView): Implemented substring matching in PayslipController.php and PayrollController.php to guarantee that Avara client variants (avara, avarapay, avara-rc-limited) render avara-pdf.blade.php with the Petra 5% deduction item.</w:t></w:r></w:p>
    <w:p><w:r><w:t>• Fixed 0.00 Data Extraction: Resolved column normalization order by applying lowercasing BEFORE suffix stripping (_GHC, _PERCENT, %), enabling accurate extraction of all database columns (BASIC_SALARY_EARNED_GHC, GROSS_SALARY_GHC, SSNIT_EE_0_5_PERCENT, PETRA_5_PERCENT, PAYE_GHC, TAKE_HOME_PAY_GHC).</w:t></w:r></w:p>
    <w:p><w:r><w:t>• Description/Days Integer Formatting: Formatted workdays and absent days as clean integers (e.g. 26), calculated weekend overtime in days (Weekend OT Amount / Daily Rate), and displayed 0 for transport description when transport allowance is 0.00 GHS.</w:t></w:r></w:p>
    <w:p><w:r><w:t>• Fallback Bank &amp; SSNIT Resolution: Implemented automatic fallback to read Bank Name, Account Number, Branch, and SSNIT Card Number directly from the payroll row when external bank records are empty.</w:t></w:r></w:p>
    <w:p><w:r><w:t>• Web Preview Modal (Index.tsx): Built dedicated isAvara web modal rendering earnings, deductions, totals, and bank details.</w:t></w:r></w:p>
    <w:p/>

    <!-- Section 3 -->
    <w:p>
      <w:r><w:rPr><w:b/><w:sz w:val="32"/><w:color w:val="1F497D"/></w:rPr><w:t>3. Excel Export Binary Stream Fix</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>• Resolved JSON Parsing Syntax Error: Fixed "Unexpected token 'P', PK... is not valid JSON" error when exporting PETRA pay data on UnityCorp (TecnoPetraPay) and Avara (AvaraPetraPay).</w:t></w:r></w:p>
    <w:p><w:r><w:t>• Binary Blob File Download: Updated frontend fetch handling in TecnoPetraPay/Index.tsx and AvaraPetraPay/Index.tsx to receive .xlsx binary streams as blobs and trigger automatic browser file downloads cleanly.</w:t></w:r></w:p>
    <w:p/>

    <!-- Section 4 -->
    <w:p>
      <w:r><w:rPr><w:b/><w:sz w:val="32"/><w:color w:val="1F497D"/></w:rPr><w:t>4. Bank Details Shortcode Clean-Up</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>• Clean Dropdown Display: Updated BankDetail/Index.tsx and BankDetailInhouse/Index.tsx so ABSA, ADB, and ECOBANK sort codes display strictly as numeric shortcodes without bank/branch names attached.</w:t></w:r></w:p>
    <w:p/>

    <!-- Section 5 -->
    <w:p>
      <w:r><w:rPr><w:b/><w:sz w:val="32"/><w:color w:val="1F497D"/></w:rPr><w:t>5. User Roles &amp; Access Control</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>• Receipt Manager &amp; Officer Roles: Created database migration 2026_10_07_000001_create_receipt_roles.php and updated RolePermissionSeeder.php to seed Receipt Manager and Receipt Officer roles with view receipts &amp; manage receipts permissions.</w:t></w:r></w:p>
    <w:p><w:r><w:t>• Self-Healing Provisioning (UserController.php): Added automatic role check to guarantee receipt roles appear in admin role selection forms.</w:t></w:r></w:p>
    <w:p/>

    <!-- Section 6 -->
    <w:p>
      <w:r><w:rPr><w:b/><w:sz w:val="32"/><w:color w:val="1F497D"/></w:rPr><w:t>6. Production Build &amp; Deployment Package</w:t></w:r>
    </w:p>
    <w:p><w:r><w:t>• Asset Bundling: Re-compiled frontend assets cleanly using npx vite build.</w:t></w:r></w:p>
    <w:p><w:r><w:t>• Verified Deployment Archive: Built hrs-deploy.zip excluding .env and root .htaccess files for safe deployment via cPanel endpoint (/cpanel-setup.php?key=sbp-deploy-2026).</w:t></w:r></w:p>
  </w:body>
</w:document>"""

out_filename = "HRS_Accomplishments_Summary_Oct2026.docx"
with zipfile.ZipFile(out_filename, 'w', zipfile.ZIP_DEFLATED) as docx:
    docx.writestr('[Content_Types].xml', content_types_xml)
    docx.writestr('_rels/.rels', rels_xml)
    docx.writestr('word/_rels/document.xml.rels', doc_rels_xml)
    docx.writestr('word/document.xml', document_xml)

print(f"Successfully generated {out_filename}")
