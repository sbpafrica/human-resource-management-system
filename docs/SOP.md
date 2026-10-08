# Standard Operating Procedure (SOP)
## Human Resource & Payroll Management System (HRS)

**Organization:** SBP Africa  
**Repository:** [https://github.com/sbpafrica/human-resource-management-system.git](https://github.com/sbpafrica/human-resource-management-system.git)  
**Production Server Host:** `jobportal.sbpafrica.group`  
**Production SSH User:** `sbpafrica`  

---

## 1. System Overview & Technology Stack

The **Human Resource & Payroll Management System (HRS)** is a full-stack enterprise HR and multi-tenant payroll web application designed to manage employee profiles, contracts, statutory deductions (SSNIT, Petra Trust, GRA PAYE), leave requests, IT assets, and client-specific payrolls.

- **Backend Framework:** Laravel 11 (PHP 8.2+)
- **Frontend Engine:** Inertia.js + React 18 + TypeScript
- **UI Components & Styling:** Tailwind CSS + Shadcn UI + Lucide Icons
- **Database Engine:** MySQL / MariaDB
- **Automation & Scheduler:** Laravel Task Scheduler (Daily Birthday SMS & Retry Engine) + Background Queue Workers

---

## 2. GitHub CI/CD Deployment Workflow

The repository includes an automated GitHub Actions deployment workflow located at:
[`.github/workflows/deploy.yml`](file:///.github/workflows/deploy.yml)

### Workflow Actions:
1. **Build Stage:**
   - Checks out the latest codebase from `main` / `master` branch.
   - Installs PHP 8.2 dependencies via `composer install --no-dev --optimize-autoloader`.
   - Installs Node dependencies and compiles frontend production assets via `npm run build`.
   - Packages compiled application files into a clean `deploy.tar.gz` archive (excluding `.env`, `.git`, and development dependencies).
2. **Deploy Stage:**
   - Connects securely to the production server (`jobportal.sbpafrica.group`) via SCP/SSH using GitHub Secrets.
   - Extracts the deployment package into the target web root folder.
   - Sets required file permissions (`chmod -R 775 storage bootstrap/cache`).
   - Executes production optimizations (`php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`).

---

## 3. Configuring GitHub Secrets & Variables

Before enabling automated deployment, you must add your SSH credentials as **Repository Secrets** in GitHub.

### Step-by-Step GitHub Setup:
1. Go to your GitHub Repository:  
   `https://github.com/sbpafrica/human-resource-management-system`
2. Click **Settings** ➔ **Secrets and variables** ➔ **Actions**.
3. Click **New repository secret** and add the following 5 secrets:

| Secret Name | Recommended Value | Description |
| :--- | :--- | :--- |
| `SSH_HOST` | `jobportal.sbpafrica.group` | Production domain or IP address |
| `SSH_USERNAME` | `sbpafrica` | SSH SSH account username |
| `SSH_PASSWORD` | `Your_SSH_Password` | Password for `sbpafrica@jobportal.sbpafrica.group` |
| `SSH_PORT` | `22` | SSH port (default is 22) |
| `TARGET_DIR` | `/home/sbpafrica/jobportal.sbpafrica.group` | Absolute path to project directory on server |

> [!IMPORTANT]
> **Never commit your `.env` file or SSH passwords directly into git.**  
> Always keep production passwords strictly inside GitHub Secrets and server `.env` files.

---

## 4. Operational & Deployment Procedures

### Method A: Automated Continuous Deployment (Recommended)
Simply push changes to the main branch:
```bash
git add .
git commit -m "feat: updated payroll feature"
git push origin main
```
The GitHub Actions pipeline will automatically trigger, build frontend assets, and deploy to `jobportal.sbpafrica.group`.

### Method B: Manual Trigger via GitHub Actions
1. Navigate to the **Actions** tab on GitHub.
2. Select **Deploy HRS to Production** from the left sidebar.
3. Click **Run workflow** ➔ Select branch (`main`) ➔ Click **Run workflow**.

### Method C: Manual Archive Fallback (cPanel Zip Upload)
If GitHub Actions is offline or unavailable:
1. Run `npm run build` locally.
2. Package the project files into `hrs-deploy.zip` (*excluding `.env`*).
3. Upload `hrs-deploy.zip` to cPanel File Manager under `/home/sbpafrica/jobportal.sbpafrica.group`.
4. Extract the zip file over the directory.

---

## 5. Server Cron Job & Scheduler Setup

To ensure automated birthday messages, SMS retries, and scheduled tasks run properly on cPanel, add the following cron job entry:

### cPanel Cron Command (Runs Every Minute):
```bash
* * * * * cd /home/sbpafrica/jobportal.sbpafrica.group && php artisan schedule:run >> /dev/null 2>&1
```

### Active Automated Scheduled Tasks:
- **`birthday:send`**: Runs daily at **08:00 AM** to send birthday greetings via SMS.
- **`birthday:retry-failed`**: Runs every **4 hours** to retry failed birthday SMS dispatches.

---

## 6. Post-Deployment Verification Checklist

After deploying updates, verify the following steps:
- [ ] Visit `https://jobportal.sbpafrica.group/login` to confirm web server response.
- [ ] Check sidebar links (e.g. *Database ➔ Twillium Pin Code*) to verify frontend routes load cleanly.
- [ ] Verify database connectivity and client scoping.
- [ ] Inspect error logs if issues occur: `tail -f storage/logs/laravel.log`.

---

## 7. Emergency & Support Contacts

For infrastructure or deployment assistance, contact the SBP Africa System Administration team.
