<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MnotifyService
{
    private string $apiKey;
    private string $senderId;
    private string $baseUrl = 'https://api.mnotify.com/api/sms/quick';

    public function __construct()
    {
        $this->apiKey   = (string) config('services.mnotify.api_key', config('services.mnotify.key'));
        $this->senderId = (string) config('services.mnotify.sender_id', 'SBPAFRICA');
    }

    /**
     * Send a single SMS message.
     *
     * @return array{success: bool, response: array, insufficient_balance: bool}
     */
    public function send(string $phone, string $message): array
    {
        $phone = $this->normalizePhone($phone);

        try {
            $response = Http::withoutVerifying()->timeout(15)->post($this->baseUrl, [
                'key'           => $this->apiKey,
                'recipient'     => [$phone],
                'sender'        => $this->senderId,
                'message'       => $message,
                'is_schedule'   => 'false',
                'schedule_date' => '',
            ]);

            $body = $response->json() ?? [];

            Log::info('mNotify SMS response', ['phone' => $phone, 'body' => $body]);

            // mNotify returns code "2000" and status "success" for successful sends
            $success = $response->successful()
                && (
                    (isset($body['code'])   && (string) $body['code']   === '2000') ||
                    (isset($body['status']) && (string) $body['status'] === 'success')
                );

            // Detect insufficient balance — mNotify returns code "1002" or status "error"
            // with a message referencing balance/credit
            $insufficientBalance = !$success
                && isset($body['code'])
                && (string) $body['code'] === '1002';

            return [
                'success'             => $success,
                'response'            => $body,
                'insufficient_balance' => $insufficientBalance,
            ];

        } catch (\Exception $e) {
            Log::error('mNotify SMS exception', ['phone' => $phone, 'error' => $e->getMessage()]);

            return [
                'success'             => false,
                'response'            => ['error' => $e->getMessage()],
                'insufficient_balance' => false,
            ];
        }
    }

    /**
     * Check the current SMS balance.
     *
     * @return array{balance: int|null, success: bool}
     */
    public function checkBalance(): array
    {
        try {
            $response = Http::timeout(10)
                ->get('https://api.mnotify.com/api/balance/sms', [
                    'key' => $this->apiKey,
                ]);

            $body = $response->json() ?? [];

            return [
                'success' => $response->successful(),
                'balance' => $body['data']['sms_balance'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('mNotify balance check failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'balance' => null];
        }
    }

    /**
     * Normalise Ghana phone numbers to 0XXXXXXXXX format.
     * Accepts: 0244..., +233244..., 233244...
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone); // strip non-digits

        if (str_starts_with($phone, '233') && strlen($phone) === 12) {
            return '0' . substr($phone, 3);
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            return $phone;
        }

        // Return as-is and let mNotify handle the error
        return $phone;
    }
}
