<?php

namespace App\Services\AI\DocumentIntelligence;

use App\Services\AI\GrokAiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentOcrService
{
    public function __construct(
        protected GrokAiClient $grokClient
    ) {}

    /**
     * Parses an uploaded image document (Ghana Card, SSNIT card, Bank document, or Contract).
     *
     * @param UploadedFile|string $file
     * @param string|null $documentType 'ghana_card', 'bank_document', 'ssnit_card', 'contract', or null (auto-detect)
     * @return array [
     *     'success' => bool,
     *     'document_type' => string,
     *     'confidence' => float,
     *     'extracted' => array,
     *     'raw_text' => string|null,
     *     'error' => string|null,
     * ]
     */
    public function parseDocument(UploadedFile|string $file, ?string $documentType = null): array
    {
        try {
            $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
            $mimeType = $file instanceof UploadedFile ? $file->getMimeType() : (mime_content_type($filePath) ?: 'image/jpeg');

            $fileData = file_get_contents($filePath);
            $base64 = base64_encode($fileData);

            if (!$this->grokClient->isConfigured()) {
                return [
                    'success' => false,
                    'error' => 'Grok API key is not configured. Please add GROK_API_KEY in .env.',
                    'document_type' => $documentType ?? 'unknown',
                    'confidence' => 0.0,
                    'extracted' => [],
                ];
            }

            $systemPrompt = <<<PROMPT
You are an expert Document Intelligence and OCR specialist for HR onboarding in Ghana.
Your job is to examine images of Ghanaian identification cards, bank proofs, and employment contracts and extract all key data with 100% precision.

Documents you recognize:
1. "ghana_card" (Republic of Ghana National Identity Card):
   - "employeename": Surname, First Name, Middle Name
   - "dateofbirth": YYYY-MM-DD
   - "gender": Male or Female
   - "ghcard": Format must be GHA-XXXXXXXXX-X (e.g. GHA-712345678-1)
   - "nationality": e.g. "Ghanaian"
   - "expiry_date": YYYY-MM-DD

2. "bank_document" (Cheque leaf, bank statement header, or deposit slip):
   - "bank": Name of the bank (e.g. GCB, Ecobank, Stanbic, CalBank, Zenith, Fidelity)
   - "account": Bank account number or Mobile Money number
   - "branch": Bank branch name
   - "account_name": Name on the account

3. "ssnit_card" (Social Security and National Insurance Trust Card):
   - "ssnit_number": Format typically 13 digits (e.g. C123456789012 or alphanumeric)
   - "employeename": Name printed on SSNIT card

4. "contract" (Employment agreement / Appointment letter):
   - "company": Client / employer name
   - "position": Job title or designation
   - "startdate": Employment commencement date (YYYY-MM-DD)
   - "basic_salary": Monthly or basic compensation amount (number)
   - "daily_rate": Daily wage if casual worker (number)
   - "shop": Work station or factory location

Respond ONLY with a valid JSON object matching this schema:
{
  "document_type": "ghana_card",
  "confidence": 0.95,
  "extracted": {
    "employeename": "KWAME MENSAH ADDO",
    "dateofbirth": "1994-05-18",
    "gender": "Male",
    "ghcard": "GHA-712345678-1",
    "nationality": "Ghanaian"
  }
}
PROMPT;

            $instruction = "Please inspect this document image. Identify the document type (" . ($documentType ?: "auto-detect") . ") and extract all relevant fields into standard JSON.";

            $result = $this->grokClient->chatVisionJson($systemPrompt, $instruction, $base64, $mimeType);

            if (!$result || !isset($result['extracted']) || !is_array($result['extracted'])) {
                return [
                    'success' => false,
                    'error' => 'Could not extract valid structured data from image.',
                    'document_type' => $documentType ?? 'unknown',
                    'confidence' => 0.0,
                    'extracted' => [],
                ];
            }

            // Clean up and normalize extracted data
            $cleaned = $this->normalizeExtractedFields($result['extracted'], $result['document_type'] ?? $documentType);

            return [
                'success' => true,
                'document_type' => $result['document_type'] ?? ($documentType ?: 'unknown'),
                'confidence' => (float) ($result['confidence'] ?? 0.88),
                'extracted' => $cleaned,
            ];
        } catch (Throwable $e) {
            Log::channel('payroll_ai')->error('DocumentOcrService exception: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'OCR processing failed: ' . $e->getMessage(),
                'document_type' => $documentType ?? 'unknown',
                'confidence' => 0.0,
                'extracted' => [],
            ];
        }
    }

    /**
     * Normalizes and validates extracted fields.
     */
    protected function normalizeExtractedFields(array $fields, ?string $docType): array
    {
        $normalized = $fields;

        // Clean Ghana Card PIN (e.g. GHA-712345678-1)
        if (isset($normalized['ghcard'])) {
            $pin = strtoupper(trim((string) $normalized['ghcard']));
            $pin = preg_replace('/\s+/', '', $pin);
            // If missing GHA- prefix, prepend it
            if (preg_match('/^(\d{9}-\d)$/', $pin, $m)) {
                $pin = 'GHA-' . $m[1];
            }
            $normalized['ghcard'] = $pin;
        }

        // Clean dates to YYYY-MM-DD
        foreach (['dateofbirth', 'startdate', 'expiry_date'] as $dateKey) {
            if (!empty($normalized[$dateKey])) {
                try {
                    $normalized[$dateKey] = date('Y-m-d', strtotime((string) $normalized[$dateKey]));
                } catch (Throwable) {
                    // keep original
                }
            }
        }

        // Clean account number (strip non-digits)
        if (isset($normalized['account'])) {
            $acc = preg_replace('/[^0-9]/', '', (string) $normalized['account']);
            if (strlen($acc) >= 6) {
                $normalized['account'] = $acc;
            }
        }

        return $normalized;
    }
}
