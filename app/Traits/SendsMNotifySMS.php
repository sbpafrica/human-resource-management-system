<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

trait SendsMNotifySMS
{
    public function sendSMS(string $phone, string $message): bool
    {
        $apiKey   = config('services.mnotify.api_key');
        $senderId = config('services.mnotify.sender_id', 'SBPAFRICA');

        if (empty($apiKey)) {
            Log::error('MNotify API key missing');
            return false;
        }

        // Clean phone: 024xxxxxxx → +23324xxxxxxx
        $clean = preg_replace('/\D/', '', $phone);
        if (strlen($clean) === 10 && $clean[0] === '0') {
            $clean = '233' . substr($clean, 1);
        }
        $to = '+' . $clean;

        if (strlen($to) !== 13) {
            Log::warning("Invalid phone after cleaning", ['original' => $phone, 'to' => $to]);
            return false;
        }

        // Truncate message safely
        if (mb_strlen($message) > 160) {
            $message = mb_substr($message, 0, 157) . '...';
        }

        // Sender ID max 11 chars
        $senderId = strlen($senderId) > 11 ? substr($senderId, 0, 11) : $senderId;

        // CORRECT PAYLOAD — THIS IS THE FIX
        $payload = [
            'key'       => $apiKey,
            'recipient' => [$to],           // ← ARRAY!
            'sender'    => $senderId,
            'message'   => $message,
            'is_schedule' => 'false',
            'schedule_date' => ''
        ];

        try {
            $response = Http::withoutVerifying() // disables SSL check (needed for mNotify)
                ->timeout(30)
                ->post('https://api.mnotify.com/api/sms/quick', $payload);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? '') === 'success') {
                Log::info('SMS sent successfully', ['to' => $to, 'message' => $message]);
                return true;
            }

            Log::error('SMS failed', [
                'to' => $to,
                'response' => $data,
                'status' => $response->status()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error('SMS exception', ['error' => $e->getMessage(), 'to' => $to]);
            return false;
        }
    }
}
