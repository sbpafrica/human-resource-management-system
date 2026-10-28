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
        $rawModel = config('ai.grok.model') ?: env('GROK_MODEL', 'openai/gpt-oss-120b');
        $this->baseUrl = rtrim(config('ai.grok.base_url') ?: env('GROK_BASE_URL', 'https://api.groq.com/openai/v1'), '/');
        $this->timeout = max((int) (config('ai.timeout') ?: env('AI_REQUEST_TIMEOUT_SECONDS', 15)), 15);

        // Normalize model if on Groq to active endpoints
        if (str_contains($this->baseUrl, 'groq.com')) {
            if (empty($rawModel) || str_contains($rawModel, 'llama') || $rawModel === 'grok-2-latest') {
                $this->model = 'openai/gpt-oss-120b';
            } else {
                $this->model = $rawModel;
            }
        } else {
            $this->model = $rawModel;
        }
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

        $modelsToTry = array_unique([$this->model, 'openai/gpt-oss-120b', 'qwen/qwen3.8-27b']);

        foreach ($modelsToTry as $currentModel) {
            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout($this->timeout)
                    ->acceptJson()
                    ->post("{$this->baseUrl}/chat/completions", [
                        'model' => $currentModel,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userPrompt],
                        ],
                        'response_format' => ['type' => 'json_object'],
                        'temperature' => $temperature,
                    ]);

                if (!$response->successful()) {
                    Log::channel('payroll_ai')->error("Grok API error with {$currentModel} [{$response->status()}]: " . $response->body());
                    // If model 404, try next model in list
                    if ($response->status() === 404) {
                        continue;
                    }
                    return null;
                }

                $rawContent = trim((string) $response->json('choices.0.message.content'));
                if (empty($rawContent)) {
                    Log::channel('payroll_ai')->warning('Grok API returned empty choice content.');
                    continue;
                }

                // Strip markdown json fences if present
                if (str_starts_with($rawContent, '```')) {
                    $rawContent = preg_replace('/^```(?:json)?\s*/i', '', $rawContent);
                    $rawContent = preg_replace('/\s*```$/', '', $rawContent);
                }

                $decoded = json_decode(trim($rawContent), true);
                if (!is_array($decoded)) {
                    Log::channel('payroll_ai')->warning('Grok response could not be JSON decoded: ' . $rawContent);
                    continue;
                }

                return $decoded;
            } catch (Throwable $e) {
                Log::channel('payroll_ai')->error('GrokAiClient exception with ' . $currentModel . ': ' . $e->getMessage());
            }
        }

        return null;
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

        $modelsToTry = array_unique([$this->model, 'openai/gpt-oss-120b', 'qwen/qwen3.8-27b']);
        $formattedMessages = array_merge([
            ['role' => 'system', 'content' => $systemPrompt]
        ], $messages);

        foreach ($modelsToTry as $currentModel) {
            try {
                $response = Http::withToken($this->apiKey)
                    ->timeout($this->timeout)
                    ->acceptJson()
                    ->post("{$this->baseUrl}/chat/completions", [
                        'model' => $currentModel,
                        'messages' => $formattedMessages,
                        'temperature' => $temperature,
                    ]);

                if (!$response->successful()) {
                    if ($response->status() === 404) {
                        continue;
                    }
                    return null;
                }

                $raw = trim((string) $response->json('choices.0.message.content'));
                if (!empty($raw)) {
                    return $raw;
                }
            } catch (Throwable $e) {
                Log::channel('payroll_ai')->error('GrokAiClient chatText error: ' . $e->getMessage());
            }
        }

        return null;
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
