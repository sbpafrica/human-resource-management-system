<?php

namespace App\Services\Grok;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GrokClient
{
    protected ?string $apiKey;
    protected string $baseUrl;
    protected string $model;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? config('services.grok.api_key');
        $this->baseUrl = rtrim($baseUrl ?? config('services.grok.base_url', 'https://api.x.ai/v1'), '/');
        $this->model = $model ?? config('services.grok.model', 'grok-2-latest');
    }

    /**
     * Send chat completion request to xAI Grok API.
     *
     * @param array $messages Array of ['role' => ..., 'content' => ...]
     * @param array $tools Optional list of function tool schemas
     * @param float $temperature Sampling temperature (0.0 - 1.0)
     * @return array Grok API response payload
     */
    public function chatCompletion(array $messages, array $tools = [], float $temperature = 0.3): array
    {
        if (empty($this->apiKey)) {
            // Graceful fallback for mock/local development without key
            return [
                'choices' => [
                    [
                        'message' => [
                            'role'    => 'assistant',
                            'content' => "Grok API Key is not configured yet in .env. Please set GROK_API_KEY to enable live AI responses.",
                        ],
                    ],
                ],
            ];
        }

        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => $temperature,
        ];

        if (!empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            /** @var Response $response */
            $response = Http::withToken($this->apiKey)
                ->baseUrl($this->baseUrl)
                ->timeout(60)
                ->post('/chat/completions', $payload);

            if ($response->failed()) {
                Log::error('Grok API Error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new RuntimeException('Grok API request failed: ' . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Grok Client Exception: ' . $e->getMessage());
            throw $e;
        }
    }
}
