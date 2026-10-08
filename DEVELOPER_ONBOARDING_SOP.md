# Developer Onboarding & System Operations SOP
## Human Resource & Payroll Management System (HRS)

> **Handover Notice for New Developers:**  
> Welcome to the SBP Africa HRS platform repository! This document is your complete operational guide to maintaining, debugging, extending, and deploying the Human Resource & Payroll Management System.

---

## Table of Contents
1. [System Purpose & Domain Architecture](#1-system-purpose--domain-architecture)
2. [Tech Stack & Dependencies](#2-tech-stack--dependencies)
3. [Local Development Environment Setup](#3-local-development-environment-setup)
4. [Codebase Architecture & Key Files](#4-codebase-architecture--key-files)
5. [Client Multi-Tenancy & Data Scoping](#5-client-multi-tenancy--data-scoping)
6. [Statutory Business Logic & Formulas](#6-statutory-business-logic--formulas)
7. [Automated Tasks & Background Jobs](#7-automated-tasks--background-jobs)
8. [CI/CD Deployment & Release Protocol](#8-cicd-deployment--release-protocol)
9. [Developer Golden Rules & Common Troubleshooting](#9-developer-golden-rules--common-troubleshooting)

---

## 1. System Purpose & Domain Architecture

The **HRS Application** manages employee records, contract lifecycles, monthly salary processing, statutory tax/pension reporting, payslip compilation, and field sales PIN management for SBP Africa and its client companies (e.g. *Melcom, Twellium, Transsion, PZ Cussons, Jaykay, OFI, Bunge West Africa, Olam Food, Shefer, etc.*).

### Primary System Modules:
- **Employee Portal & Master Data:** Employee contact details, bank accounts, SSNIT numbers, and document archives.
- **Multi-Client Payroll Engine:** Individual payroll calculations per subsidiary entity.
- **Statutory Deductions & Audits:** SSNIT Tier 1 & 2, Petra Trust Provident Fund (5%), GRA PAYE Tax, and compliance audit reports.
- **Payslip Generation:** Automated PDF compilation and bulk distribution.
- **Twellium & Field PIN Management:** 4-digit PIN code generation and mNotify SMS dispatches for field sales teams.
- **IT Asset Allocation:** Hardware inventory management and staff assignments.
- **Automated Birthday Engine:** Daily automated SMS greetings with retry mechanisms.

---

## 2. Tech Stack & Dependencies

- **PHP / Backend:** PHP 8.2+, Laravel 11
- **Frontend / Client:** Inertia.js (React 18 + TypeScript)
- **Styling & UI:** Tailwind CSS, Shadcn UI, Lucide React Icons
- **PDF Generation:** DomPDF / TCPDF
- **Spreadsheet Operations:** PhpSpreadsheet (`Xlsx`)
- **SMS Gateway:** mNotify API Integration (`config/services.php`)
- **Database:** MySQL / MariaDB
- **CI/CD & Hosting:** GitHub Actions + cPanel / Linux SSH (`sbpafrica@jobportal.sbpafrica.group`)

---

## 3. Local Development Environment Setup

Follow these steps to run the application on your local machine:

### 1. Prerequisites
Ensure your local machine has installed:
- PHP >= 8.2 with `pdo_mysql`, `mbstring`, `gd`, `bcmath`, `xml`, `zip` extensions.
- Node.js >= 20.x & NPM
- Composer >= 2.x
- MySQL Server

### 2. Clone Repository & Install Dependencies
```bash
git clone https://github.com/sbpafrica/human-resource-management-system.git
cd human-resource-management-system

# Install PHP dependencies
composer install

# Install Frontend JavaScript dependencies
npm install
```

### 3. Environment Configuration
Copy the environment example file and generate an application encryption key:
```bash
cp .env.example .env
php artisan key:generate
```

Configure your local database credentials in `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrs_db
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Run Migrations & Build Assets
```bash
# Run database migrations
php artisan migrate

# Compile assets in development mode (hot reloading)
npm run dev
```

### 5. Launch Development Server
In a separate terminal tab:
```bash
php artisan serve
```
Access the application at `http://127.0.0.1:8000`.

---

## 4. Codebase Architecture & Key Files

| Folder / Path | Description / Key Components |
| :--- | :--- |
| [`routes/web.php`](file:///routes/web.php) | Main HTTP routes definition file. |
| [`app/Http/Controllers/`](file:///app/Http/Controllers/) | Business logic controllers for all modules. |
| ├── [`PayrollController.php`](file:///app/Http/Controllers/PayrollController.php) | Centralized payroll calculation engine. |
| ├── [`PayslipController.php`](file:///app/Http/Controllers/PayslipController.php) | PDF payslip compilation & download routines. |
| ├── [`TwelliumPinController.php`](file:///app/Http/Controllers/TwelliumPinController.php) | Twellium field staff PIN management & SMS dispatch. |
| ├── [`BankDetailController.php`](file:///app/Http/Controllers/BankDetailController.php) | Bank account Excel bulk upload & validation. |
| ├── [`SsnitController.php`](file:///app/Http/Controllers/SsnitController.php) | Tier 1/2 SSNIT reporting controller. |
| ├── [`PetraController.php`](file:///app/Http/Controllers/PetraController.php) | Petra Trust 5% Provident Fund controller. |
| └── `*PayController.php` | Client-specific payroll controllers (*MelcomPayController*, *TecnoPayController*, etc.). |
| [`app/Console/Kernel.php`](file:///app/Console/Kernel.php) | Task scheduler definitions (Birthday SMS cron schedules). |
| [`resources/js/pages/`](file:///resources/js/pages/) | Inertia React views (TypeScript `.tsx` files). |
| ├── [`TwelliumPin/Index.tsx`](file:///resources/js/pages/TwelliumPin/Index.tsx) | Twellium PIN UI table & modal components. |
| └── [`Payroll/`](file:///resources/js/pages/Payroll/) | Salary payment interface. |
| [`resources/js/components/app-sidebar.tsx`](file:///resources/js/components/app-sidebar.tsx) | Main sidebar menu structure and permissions scoping. |
| [`resources/js/components/nav-main.tsx`](file:///resources/js/components/nav-main.tsx) | Navigation tree component with auto-expansion logic. |
| [`.github/workflows/deploy.yml`](file:///.github/workflows/deploy.yml) | GitHub Actions CI/CD deployment script. |

---

## 5. Client Multi-Tenancy & Data Scoping

The system segregates client data dynamically based on user role:

### Super Admin Access (`$user->is_super_admin === true`):
- Has full access to all client companies, salary data, and system settings.

### Scoped Client Admin Access:
- Scoped users belong to specific client entities via `$user->clients()`.
- Controllers enforce keyword matching between client records (`u.company`, `c.shop`, `client_name`, `short_name`, `slug`).

> [!TIP]
> When adding new database queries filtering by company, always check both full client name and `short_name` to ensure records match existing database entries.

---

## 6. Statutory Business Logic & Formulas

1. **SSNIT Deductions:**
   - **Tier 1 (Employer & Employee Ratios):** Calculated on Basic Earnings according to standard SSNIT guidelines.
   - **Tier 2 (Private Scheme):** 5% mandatory statutory contribution.

2. **Petra Trust 5% Provident Fund:**
   - Applied on basic earnings.
   - **Excel Formula Equivalent:**  
     `=ROUND(IF(basic <= 587.79, 29.39, basic * 0.05), 2)`

3. **GRA PAYE Income Tax:**
   - Applied progressively using Ghana Revenue Authority monthly tax brackets on taxable income (Gross Earnings minus SSNIT Tier 1 employee contribution).

---

## 7. Automated Tasks & Background Jobs

The application relies on Laravel Task Scheduler for automated events:

1. **Birthday SMS Dispatch (`birthday:send`)**:
   - **Schedule:** Daily at **08:00 AM**.
   - **Action:** Fetches active employees born on the current date and dispatches personalized birthday SMS via mNotify API.

2. **Birthday SMS Retry Mechanism (`birthday:retry-failed`)**:
   - **Schedule:** Every **4 hours**.
   - **Action:** Re-attempts sending failed SMS messages once gateway balance or connection is restored.

3. **Background Resume Parser (`ParseResumeJob`)**:
   - Dispatched asynchronously via Laravel Queue worker whenever a job applicant submits a CV.

### cPanel Cron Setup Requirement:
Ensure the following cron job is active on the server:
```bash
* * * * * cd /home/sbpafrica/jobportal.sbpafrica.group && php artisan schedule:run >> /dev/null 2>&1
```

---

## 8. CI/CD Deployment & Release Protocol

Deployment to production (`jobportal.sbpafrica.group`) is fully automated via GitHub Actions.

### Deployment Flow:
1. Developer pushes code to `main` branch.
2. GitHub Actions runner compiles frontend assets (`npm run build`) and packages the release archive.
3. SCP uploads `deploy.tar.gz` to server directory `/home/sbpafrica/jobportal.sbpafrica.group`.
4. SSH executes server commands (`tar -xzf`, `chmod -R 775 storage bootstrap/cache`, `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`).

### Required GitHub Secrets (In Repository Settings):
- `SSH_HOST`: `jobportal.sbpafrica.group`
- `SSH_USERNAME`: `sbpafrica`
- `SSH_PASSWORD`: *(Stored in GitHub Secrets)*
- `SSH_PORT`: `22`
- `TARGET_DIR`: `/home/sbpafrica/jobportal.sbpafrica.group`

---

## 9. Developer Golden Rules & Common Troubleshooting

> [!CAUTION]
> **RULE 1: STRICT SECURITY REGARDING `.env`**  
> NEVER commit or push `.env` files to git. `.env` is listed in `.gitignore`. Production `.env` resides strictly on the cPanel server.

> [!IMPORTANT]
> **RULE 2: FRONTEND ASSETS REBUILD**  
> Whenever you modify React or TypeScript files in `resources/js/`, you MUST run `npm run build` before packaging manual ZIP archives.

> [!TIP]
> **RULE 3: LARAVEL QUERY BUILDER CLONING**  
> When fetching record counts in controllers before pagination, ALWAYS use `(clone $query)->count()`. Calling `$query->count()` without `clone` mutates the internal `select` columns of the query builder object.

### Debugging & Inspection Checklist:
- **Server Application Logs:** `tail -f storage/logs/laravel.log`
- **Clearing Caches on Server:**
  ```bash
  php artisan config:clear
  php artisan route:clear
  php artisan cache:clear
  php artisan view:clear
  ```
- **Updating Deployment Archive Manually:**
  ```bash
  npm run build
  zip -r hrs-deploy.zip . -x "*.env*" "*.git*" "storage/*.key" "node_modules/*"
  ```
