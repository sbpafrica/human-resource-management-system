<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Exceptions\RateLimitException;

class OpenAIHelper
{
    public static function safeChatCompletion($prompt, $retries = 3)
    {
        set_time_limit(0);
        $delay = 2;
        for ($i = 0; $i < $retries; $i++) {
            try {
                $response = OpenAI::chat()->create([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a professional resume formatting assistant.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => 800,
                ]);

                return $response['choices'][0]['message']['content'] ?? null;
            } catch (RateLimitException $e) {
                Log::warning("OpenAI rate limit hit (attempt " . ($i + 1) . "). Retrying in {$delay}s...");
                sleep($delay);
                $delay *= 2;
            } catch (\Exception $e) {
                Log::error("OpenAI error (attempt " . ($i + 1) . "): " . $e->getMessage());
                sleep($delay);
                $delay *= 2;
            }
        }

        Log::error("OpenAI failed after {$retries} attempts for prompt: " . substr($prompt, 0, 100));
        return null;  // Fallback in controller
    }
}
