<?php

namespace App\Jobs;

use App\Models\Employeeuser;
use App\Models\ParsedResume;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Exception;

class ParseResumeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $employeeuserId;
    public $tries = 3; // Retry up to 3 times on failure
    public $timeout = 180; // 3-minute timeout

    public function __construct($employeeuserId)
    {
        $this->employeeuserId = $employeeuserId;
    }

   public function handle()
{
    try {
        $user = Employeeuser::findOrFail($this->employeeuserId);
        $resumePath = public_path('resumes/' . $user->resume);

        if (!file_exists($resumePath)) {
            throw new Exception('Resume file not found');
        }

        // **STEP 1: EXTRACT RAW TEXT (your existing Python)**
        $pythonPath = base_path('python-parser/venv/Scripts/python.exe');
        $scriptPath = base_path('python-parser/parse_resume.py');

        $process = new Process([$pythonPath, $scriptPath, $resumePath]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('Python extraction failed');
        }

        $rawOutput = $process->getOutput();
        $rawData = json_decode($rawOutput, true);
        $rawText = $rawData['clean_text'] ?? '';

        if (!$rawText) {
            throw new Exception('No text extracted');
        }

        // **STEP 2: HUGGING FACE - REDACT SENSITIVE DATA**
        $client = new \GuzzleHttp\Client();
        $apiKey = env('HUGGINGFACE_API_KEY');

        // Redact with spaCy NER
        $redactResponse = $client->post('https://api-inference.huggingface.co/models/dslim/bert-base-NER', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'inputs' => $rawText,
                'options' => ['wait_for_model' => true]
            ]
        ]);

        $nerResult = json_decode($redactResponse->getBody(), true);
        $cleanText = $this->redactFromNER($rawText, $nerResult);

        // **STEP 3: HUGGING FACE - FORMAT RESUME**
        $formatResponse = $client->post('https://api-inference.huggingface.co/models/microsoft/DialoGPT-medium', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'inputs' => "Format this resume with bold section headers, bullets, and professional layout:\n\n" . $cleanText,
                'parameters' => ['max_length' => 2048]
            ]
        ]);

        $formattedResult = json_decode($formatResponse->getBody(), true);
        $formattedText = $formattedResult[0]['generated_text'] ?? $cleanText;

        // **STEP 4: SAVE**
        ParsedResume::updateOrCreate(
            ['employeeuser_id' => $this->employeeuserId],
            [
                'parsed_data' => [
                    'raw_text' => $rawText,
                    'clean_text' => $cleanText,
                    'formatted_resume' => $formattedText
                ],
                'parse_status' => 'success'
            ]
        );

        Log::info('Hugging Face resume formatted', ['id' => $this->employeeuserId]);

    } catch (Exception $e) {
        Log::error('Hugging Face failed: ' . $e->getMessage());
        $this->fail($e);
    }
}

private function redactFromNER($text, $nerResult)
{
    $clean = $text;

    if (isset($nerResult[0]['entity_grouped_entities'])) {
        foreach ($nerResult[0]['entity_grouped_entities'] as $entities) {
            foreach ($entities as $entity) {
                if (in_array($entity['type'], ['PER', 'ORG', 'MISC'])) {
                    $clean = str_replace($entity['word'], '[REMOVED]', $clean);
                }
            }
        }
    }

    // **EXTRA PHONE CLEANUP**
    $clean = preg_replace('/(?:\+233|0)[\s\-\(\)\.]*(?:[2-5]\d{2}|20)[\s\-\(\)\.]*(?:\d{3})[\s\-\(\)\.]*(?:\d{3,4})/', '[PHONE REMOVED]', $clean);

    return $clean;
}

    private function cleanExperience(array $experience): array
    {
        return collect($experience)->map(function ($line) {
            $line = str_replace(["\uf076", "\u25cf", "\u2022", "\u00b7", "\u25aa", "\u25a0", "\u25fe", "\u2219"], '', $line);
            $line = preg_replace('/^[\s\x{25cf}\x{2022}\x{00b7}\x{25aa}\x{25a0}\x{25fe}\x{2219}\x{f076}\x{2013}\x{2014}\x{00b0}-\x{00b7}]+/u', '', $line);
            return trim($line);
        })->filter(function ($line) {
            $lower = strtolower($line);
            return !preg_match('/references|seminars|tel|email|^\d{3,}$|^references$/i', $lower) && $line !== '';
        })->values()->all();
    }

    private function extractSensitiveFields(array $parsedData): array
    {
        $positions = $parsedData['positions'] ?? [];
        $topLevel = [
            'email' => $parsedData['email'] ?? null,
            'mobile_number' => $parsedData['mobile_number'] ?? null,
            'name' => $parsedData['name'] ?? null,
            'address' => $parsedData['address'] ?? null,
        ];

        return [
            'name_positions' => $positions['name'][0] ?? null,
            'email_positions' => $positions['email'][0] ?? null,
            'mobile_positions' => $positions['mobile_number'][0] ?? null,
            'address_positions' => $positions['address'][0] ?? null,
            'extracted_values' => array_filter($topLevel),
        ];
    }

    private function markFailed(string $message)
    {
        ParsedResume::updateOrCreate(
            ['employeeuser_id' => $this->employeeuserId],
            [
                'parsed_data' => null,
                'sensitive_fields' => null,
                'parse_status' => 'failed',
                'error_message' => $message,
            ]
        );
    }
}
