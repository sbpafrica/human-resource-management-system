<?php

namespace App\Console\Commands;

use App\Mail\BirthdayBalanceAlert;
use App\Models\BirthdayLog;
use App\Models\BirthdaySetting;
use App\Services\MnotifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBirthdayMessages extends Command
{
    protected $signature   = 'birthday:send';
    protected $description = 'Send birthday SMS to employees whose birthday is today';

    public function handle(MnotifyService $mnotify): int
    {
        $settings = BirthdaySetting::current();

        if (!$settings->is_active) {
            $this->info('Birthday SMS is disabled in settings. Skipping.');
            return self::SUCCESS;
        }

        $today      = now()->toDateString();          // e.g. "2026-04-02"
        $todayDay   = now()->day;                     // e.g. 2
        $todayMonth = now()->month;                   // e.g. 4

        // ── Step 1: Check SMS balance before starting ─────────────────────
        $balanceCheck = $mnotify->checkBalance();
        if ($balanceCheck['success'] && $balanceCheck['balance'] !== null && $balanceCheck['balance'] < 1) {
            $this->warn('mNotify balance is zero. Aborting send.');
            Log::warning('Birthday SMS aborted: zero mNotify balance');
            Mail::to($settings->alert_email)->send(new BirthdayBalanceAlert(0, $today));
            return self::SUCCESS;
        }

        // ── Step 2: Find today's birthdays from newemployeedata ───────────
        //
        // dateofbirth is stored as VARCHAR with many mixed formats in the DB:
        //   Y-m-d       → 1991-10-05
        //   d/m/Y       → 8/11/1997  or  22/06/1997
        //   d-Mon-YY    → 2-Nov-06
        //   d(th) Month Y → 8th March 1997
        //   Y/m/d       → 2016/10/19
        //
        // MySQL DAY()/MONTH() only works on Y-m-d strings — all other formats
        // return NULL or wrong values. So we fetch all employees and parse
        // dates in PHP using Carbon which handles most formats gracefully.
        //
        $allEmployees = DB::table('newemployeedata')
            ->whereNotNull('dateofbirth')
            ->where('dateofbirth', '!=', '')
            ->select('staffid', 'employeename', 'company', 'shop', 'dateofbirth')
            ->get();

        // ── Parse each date in PHP and filter to today's day+month ────────
        $employees = $allEmployees->filter(function ($emp) use ($todayDay, $todayMonth) {
            $raw = trim((string) $emp->dateofbirth);
            if (empty($raw)) return false;

            $carbon = $this->parseFlexibleBirthday($raw);
            if ($carbon) {
                return $carbon->day === $todayDay && $carbon->month === $todayMonth;
            }

            // Only log debug notice if it had digits and wasn't obvious non-date data (e.g. emails, GHA card)
            if (preg_match('/\d/', $raw) && !str_contains($raw, '@') && !str_starts_with($raw, 'GHA-')) {
                \Illuminate\Support\Facades\Log::debug(
                    "Birthday parse skipped for {$emp->staffid}",
                    ['raw' => $raw]
                );
            }
            return false;
        });

        if ($employees->isEmpty()) {
            $this->info("No birthdays today ({$today}). Nothing to send.");
            return self::SUCCESS;
        }

        $this->info("Found {$employees->count()} birthday(s) today.");

        $sentCount            = 0;
        $failedCount          = 0;
        $skippedCount         = 0;
        $insufficientBalance  = false;

        foreach ($employees as $employee) {

            // ── Skip if already sent today (duplicate guard) ───────────────
            $alreadySent = BirthdayLog::where('staffid', $employee->staffid)
                ->where('sent_date', $today)
                ->where('status', 'sent')
                ->exists();

            if ($alreadySent) {
                $this->line("  Skipping {$employee->staffid} — already sent today.");
                $skippedCount++;
                continue;
            }

            // ── Look up phone in employeecontact only ──────────────────────
            $contact = DB::table('employeecontact')
                ->where('staffid', $employee->staffid)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->first();

            if (!$contact) {
                $this->line("  Skipping {$employee->staffid} — no contact in employeecontact table.");
                $skippedCount++;

                // Log skipped employees so they appear in the report
                BirthdayLog::firstOrCreate(
                    [
                        'staffid'   => $employee->staffid,
                        'sent_date' => $today,
                        'status'    => 'skipped',
                    ],
                    [
                        'name'           => $employee->employeename,
                        'company'        => $employee->company,
                        'shop'           => $employee->shop,
                        'phone'          => '',
                        'message'        => '',
                        'status'         => 'skipped',
                        'failure_reason' => 'No contact details found in employee contact table',
                        'birthday_date'  => $today,
                        'sent_date'      => $today,
                        'retried'        => false,
                    ]
                );
                continue;
            }

            // ── Build personalised message ─────────────────────────────────
            $message = $settings->message_template;

            // ── Send via mNotify ───────────────────────────────────────────
            $result = $mnotify->send($contact->phone, $message);

            // ── Safely parse the raw dateofbirth string ──────────────────
            // dateofbirth may be stored in many formats (d/m/y, d/m/Y, Y-m-d, etc.)
            // We only need the day/month for matching — store today as birthday_date
            // to avoid Carbon crashing on malformed date strings.
            $safeBirthdayDate = $today; // always valid — the day this SMS ran

            if ($result['insufficient_balance']) {
                $insufficientBalance = true;
                $failedCount++;
                $this->warn("  Insufficient balance — logging failed for {$employee->staffid}.");

                BirthdayLog::create([
                    'staffid'        => $employee->staffid,
                    'name'           => $employee->employeename,
                    'company'        => $employee->company,
                    'shop'           => $employee->shop,
                    'phone'          => $contact->phone,
                    'message'        => $message,
                    'status'         => 'failed',
                    'api_response'   => json_encode($result['response']),
                    'failure_reason' => 'Insufficient mNotify balance',
                    'birthday_date'  => $safeBirthdayDate,
                    'sent_date'      => $today,
                    'retried'        => false,
                ]);

                // If balance is gone, no point trying the rest
                break;
            }

            if ($result['success']) {
                $sentCount++;
                $this->info("  ✓ Sent to {$employee->employeename} ({$contact->phone})");

                BirthdayLog::create([
                    'staffid'       => $employee->staffid,
                    'name'          => $employee->employeename,
                    'company'       => $employee->company,
                    'shop'          => $employee->shop,
                    'phone'         => $contact->phone,
                    'message'       => $message,
                    'status'        => 'sent',
                    'api_response'  => json_encode($result['response']),
                    'birthday_date' => $safeBirthdayDate,
                    'sent_date'     => $today,
                    'retried'       => false,
                ]);
            } else {
                $failedCount++;
                $failureReason = $result['response']['errors']['message'][0]
                    ?? $result['response']['message']
                    ?? $result['response']['error']
                    ?? 'Unknown error';

                $this->error("  ✗ Failed for {$employee->employeename}: {$failureReason}");

                BirthdayLog::create([
                    'staffid'        => $employee->staffid,
                    'name'           => $employee->employeename,
                    'company'        => $employee->company,
                    'shop'          => $employee->shop,
                    'phone'          => $contact->phone,
                    'message'        => $message,
                    'status'         => 'failed',
                    'api_response'   => json_encode($result['response']),
                    'failure_reason' => $failureReason,
                    'birthday_date'  => $safeBirthdayDate,
                    'sent_date'      => $today,
                    'retried'        => false,
                ]);
            }
        }

        // ── Step 3: Send balance alert email if needed ────────────────────
        if ($insufficientBalance && $failedCount > 0) {
            try {
                Mail::to($settings->alert_email)
                    ->send(new BirthdayBalanceAlert($failedCount, $today));
                $this->warn("Balance alert email sent to {$settings->alert_email}.");
            } catch (\Exception $e) {
                Log::error('Failed to send balance alert email', ['error' => $e->getMessage()]);
            }
        }

        $this->info("Done. Sent: {$sentCount} | Failed: {$failedCount} | Skipped: {$skippedCount}");
        Log::info("Birthday SMS complete", compact('sentCount', 'failedCount', 'skippedCount', 'today'));

        return self::SUCCESS;
    }

    /**
     * Flexibly parses diverse user birthday string formats into a Carbon instance.
     * Supports:
     * - DD/MM/YYYY, DD/MM/YY, DD-MM-YYYY, DD_MM_YYYY
     * - 8-digit numeric strings: 19101990 (DDMMYYYY)
     * - Excel serial dates (e.g. 34842)
     * - DD/MonthName/YYYY (e.g. 5/July/1997)
     * - Ordinal dates (e.g. 8th March 1997)
     * - 2-digit year expansions (e.g. 2-Nov-06 -> 2-Nov-2006)
     */
    private function parseFlexibleBirthday(string $raw): ?\Carbon\Carbon
    {
        $cleaned = trim($raw);
        if (empty($cleaned)) {
            return null;
        }

        // Normalise ordinal days: '8th March 1997' -> '8 March 1997'
        $cleaned = preg_replace('/\b(\d+)(st|nd|rd|th)\b/i', '$1', $cleaned);

        // Replace underscores or multiple spaces
        $cleaned = str_replace('_', '/', $cleaned);
        $cleaned = preg_replace('/[ ]+/', ' ', $cleaned);

        // Excel serial dates: e.g. 34842 (numeric between 10000 and 60000)
        if (is_numeric($cleaned) && (int)$cleaned >= 10000 && (int)$cleaned <= 60000) {
            return \Carbon\Carbon::createFromTimestamp(((int)$cleaned - 25569) * 86400);
        }

        // 8-digit numeric string: e.g. 19101990 -> DDMMYYYY
        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $cleaned, $m)) {
            $d = (int)$m[1]; $mo = (int)$m[2]; $y = (int)$m[3];
            if ($mo >= 1 && $mo <= 12 && $d >= 1 && $d <= 31) {
                return \Carbon\Carbon::createFromDate($y, $mo, $d);
            }
        }

        // Space separated: e.g. '02 01 2002'
        if (preg_match('/^(\d{1,2})\s+(\d{1,2})\s+(\d{2,4})$/', $cleaned, $m)) {
            $cleaned = $m[1] . '/' . $m[2] . '/' . $m[3];
        }

        // d/m/Y or d/m/y or d-m-Y: e.g. 22/06/1997, 28/6/1995, 26/11/93
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $cleaned, $m)) {
            $d = (int)$m[1];
            $mo = (int)$m[2];
            $y = (int)$m[3];
            if ($mo > 12 && $d <= 12) {
                $temp = $d; $d = $mo; $mo = $temp;
            }
            if ($y < 100) {
                $y = ($y > 35) ? (1900 + $y) : (2000 + $y);
            }
            if ($mo >= 1 && $mo <= 12 && $d >= 1 && $d <= 31) {
                return \Carbon\Carbon::createFromDate($y, $mo, $d);
            }
        }

        // Fix common spelling typo: e.g. '0ct' instead of 'Oct'
        $cleaned = str_ireplace('0ct', 'Oct', $cleaned);

        // d/MonthName/Y: e.g. 5/July/1997
        if (preg_match('/^(\d{1,2})[\/\-]([A-Za-z]+)[\/\-](\d{2,4})$/', $cleaned, $m)) {
            $cleaned = $m[1] . ' ' . $m[2] . ' ' . $m[3];
        }

        // 2-Nov-06 -> 2-Nov-2006
        $cleaned = preg_replace_callback(
            '/^(\d{1,2})-([A-Za-z]{3})-(\d{2})$/',
            fn($m) => $m[1] . '-' . $m[2] . '-20' . $m[3],
            $cleaned
        );

        try {
            return \Carbon\Carbon::parse($cleaned);
        } catch (\Exception $e) {
            return null;
        }
    }
}
