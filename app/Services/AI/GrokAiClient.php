<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GrokAiClient
{
    protected ?string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiKey = config('ai.grok.api_key') ?: env('GROK_API_KEY');
        $this->model = config('ai.grok.model', 'grok-2-latest');
        $this->baseUrl = rtrim(config('ai.grok.base_url', 'https://api.x.ai/v1'), '/');
        $this->timeout = (int) config('ai.timeout', 6);
    }

    /**
     * Checks if Grok is properly configured with an API key.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && $this->apiKey !== 'your_grok_api_key_here';
    }

    /**
     * Send a structured chat completion request to Grok expecting JSON output.
     *
     * @param string $systemPrompt
     * @param string $userPrompt
     * @param float $temperature
     * @return array|null Returns decoded JSON array or null on failure
     */
    public function chatJson(string $systemPrompt, string $userPrompt, float $temperature = 0.1): ?array
    {
        if (!$this->isConfigured()) {
            Log::channel('payroll_ai')->warning('GrokAiClient: GROK_API_KEY is missing or using placeholder.');
            return null;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->acceptJson()
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => $temperature,
                ]);

            if (!$response->successful()) {
                Log::channel('payroll_ai')->error("Grok API error [{$response->status()}]: " . $response->body());
                return null;
            }

            $rawContent = $response->json('choices.0.message.content');
            if (empty($rawContent)) {
                Log::channel('payroll_ai')->warning('Grok API returned empty choice content.');
                return null;
            }

            $decoded = json_decode($rawContent, true);
            if (!is_array($decoded)) {
                Log::channel('payroll_ai')->warning('Grok response could not be JSON decoded: ' . $rawContent);
                return null;
            }

            return $decoded;
        } catch (Throwable $e) {
            Log::channel('payroll_ai')->error('GrokAiClient exception: ' . $e->getMessage(), [
                'model' => $this->model,
                'baseUrl' => $this->baseUrl,
            ]);
            return null;
        }
    }

    /**
     * Send a conversational request with multi-turn history expecting text/markdown response.
     *
     * @param string $systemPrompt
     * @param array<int, array{role: string, content: string}> $messages
     * @param float $temperature
     * @return string|null
     */
    public function chatText(string $systemPrompt, array $messages, float $temperature = 0.3): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $formattedMessages = array_merge([
                ['role' => 'system', 'content' => $systemPrompt]
            ], $messages);

            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout + 4)
                ->acceptJson()
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $this->model,
                    'messages' => $formattedMessages,
                    'temperature' => $temperature,
                ]);

            if (!$response->successful()) {
                Log::channel('payroll_ai')->error("Grok chatText API error [{$response->status()}]: " . $response->body());
                return null;
            }

            return $response->json('choices.0.message.content');
        } catch (Throwable $e) {
            Log::channel('payroll_ai')->error('Grok chatText exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Multimodal vision request to extract structured JSON from image documents
     * (Ghana Card, SSNIT card, contracts, bank statements).
     *
     * @param string $systemPrompt
     * @param string $instruction
     * @param string $imageBase64
     * @param string $mimeType e.g. 'image/jpeg', 'image/png'
     * @return array|null
     */
    public function chatVisionJson(string $systemPrompt, string $instruction, string $imageBase64, string $mimeType = 'image/jpeg'): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        // Use vision-capable model
        $visionModel = str_contains($this->model, 'mini') ? 'grok-2-vision-1212' : ($this->model === 'grok-beta' ? 'grok-2-vision-1212' : $this->model);

        try {
            $dataUrl = "data:{$mimeType};base64,{$imageBase64}";

            $response = Http::withToken($this->apiKey)
                ->timeout($this->timeout + 10) // Vision OCR can take a few seconds longer
                ->acceptJson()
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $visionModel,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => $instruction],
                                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                            ],
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                ]);

            if (!$response->successful()) {
                Log::channel('payroll_ai')->error("Grok Vision error [{$response->status()}]: " . $response->body());
                return null;
            }

            $content = $response->json('choices.0.message.content');
            $decoded = json_decode((string) $content, true);

            return is_array($decoded) ? $decoded : null;
        } catch (Throwable $e) {
            Log::channel('payroll_ai')->error('Grok chatVisionJson exception: ' . $e->getMessage());
            return null;
        }
    }
}
