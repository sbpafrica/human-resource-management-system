<?php

namespace App\Console\Commands;

use App\Mail\BirthdayBalanceAlert;
use App\Models\BirthdayLog;
use App\Models\BirthdaySetting;
use App\Services\MnotifyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RetryFailedBirthdaySms extends Command
{
    protected $signature   = 'birthday:retry-failed';
    protected $description = 'Retry all failed birthday SMS messages that have not been retried yet';

    public function handle(MnotifyService $mnotify): int
    {
        $settings = BirthdaySetting::current();

        // ── Check balance first ───────────────────────────────────────────
        $balanceCheck = $mnotify->checkBalance();
        if ($balanceCheck['success'] && ($balanceCheck['balance'] ?? 0) < 1) {
            $this->warn('mNotify balance is still zero. Cannot retry yet.');
            return self::SUCCESS;
        }

        $failed = BirthdayLog::pendingRetry()->get();

        if ($failed->isEmpty()) {
            $this->info('No failed SMS records to retry.');
            return self::SUCCESS;
        }

        $this->info("Retrying {$failed->count()} failed SMS(s)...");

        $retriedSent   = 0;
        $retriedFailed = 0;
        $stillNoBalance = false;

        foreach ($failed as $log) {
            $result = $mnotify->send($log->phone, $log->message);

            if ($result['insufficient_balance']) {
                $stillNoBalance = true;
                $this->warn("  Balance depleted again during retry. Stopping.");
                break;
            }

            if ($result['success']) {
                $retriedSent++;
                $log->update([
                    'status'       => 'sent',
                    'api_response' => json_encode($result['response']),
                    'retried'      => true,
                ]);
                $this->info("  ✓ Retry sent: {$log->name} ({$log->phone})");
            } else {
                $retriedFailed++;
                $log->update([
                    'retried'        => true,
                    'failure_reason' => $result['response']['message']
                        ?? $result['response']['error']
                        ?? 'Retry failed',
                    'api_response'   => json_encode($result['response']),
                ]);
                $this->error("  ✗ Retry failed: {$log->name}");
            }
        }

        if ($stillNoBalance && $retriedFailed > 0) {
            try {
                Mail::to($settings->alert_email)
                    ->send(new BirthdayBalanceAlert($retriedFailed, now()->toDateString()));
            } catch (\Exception $e) {
                Log::error('Failed to send balance alert on retry', ['error' => $e->getMessage()]);
            }
        }

        $this->info("Retry complete. Sent: {$retriedSent} | Still failed: {$retriedFailed}");
        return self::SUCCESS;
    }
}
