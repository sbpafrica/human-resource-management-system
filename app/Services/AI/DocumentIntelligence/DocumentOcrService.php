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
     * Parses an uploaded document (Ghana Card, SSNIT card, Bank document, or Contract).
     *
     * @param UploadedFile|string $file
     * @param string|null $documentType 'ghana_card', 'bank_document', 'ssnit_card', 'contract', or null (auto-detect)
     * @param string|null $rawText Optional raw OCR text extracted client-side or from PDF
     * @return array
     */
    public function parseDocument(UploadedFile|string $file, ?string $documentType = null, ?string $rawText = null): array
    {
        try {
            $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
            $mimeType = $file instanceof UploadedFile ? $file->getMimeType() : (mime_content_type($filePath) ?: 'image/jpeg');

            $trimmedText = is_string($rawText) ? trim($rawText) : '';

            // If PDF and no raw text provided, extract via pdftotext
            if (empty($trimmedText) && (str_contains($mimeType, 'pdf') || str_ends_with(strtolower($filePath), '.pdf'))) {
                $trimmedText = $this->extractTextFromPdf($filePath);
            }

            // Path 1: Process via Raw Text (Fast Groq LLM + Fallback Regex)
            if (!empty($trimmedText)) {
                return $this->parseFromText($trimmedText, $documentType);
            }

            // Path 2: Process via Multimodal Vision API
            if ($this->grokClient->isConfigured()) {
                $visionResult = $this->parseViaVision($filePath, $mimeType, $documentType);
                if ($visionResult !== null) {
                    return $visionResult;
                }
            }

            return [
                'success' => false,
                'error' => 'Could not extract valid text from image. Please ensure the document is clear, or upload as a PDF.',
                'document_type' => $documentType ?? 'unknown',
                'confidence' => 0.0,
                'extracted' => [],
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
     * Parses structured fields from extracted OCR text using Groq LLM with Regex fallback.
     */
    protected function parseFromText(string $text, ?string $documentType): array
    {
        $detectedType = $this->detectDocumentType($text, $documentType);

        // 1. Try Groq AI JSON chat
        if ($this->grokClient->isConfigured()) {
            $systemPrompt = <<<PROMPT
You are an expert Document Intelligence and OCR specialist for HR onboarding in Ghana.
Your job is to examine extracted text from Ghanaian identity cards, bank proofs, and employment contracts and extract all key data with 100% precision into JSON.

Document types:
1. "ghana_card":
   - "employeename": Surname First Name Middle Name (e.g. VERA CECILIA KETU)
   - "dateofbirth": YYYY-MM-DD
   - "gender": Male or Female
   - "ghcard": Format GHA-XXXXXXXXX-X (e.g. GHA-718337482-5)
   - "nationality": e.g. "Ghanaian"

2. "bank_document":
   - "bank": Bank name (e.g. GCB, Ecobank, Stanbic, CalBank, Zenith, Fidelity, UMB, CBG)
   - "account": Bank account or MoMo number (digits only)
   - "branch": Bank branch name
   - "account_name": Account holder name

3. "ssnit_card":
   - "ssnit_number": 13-character SSNIT number
   - "employeename": Full name on card

4. "contract":
   - "company": Employer / Client company
   - "position": Designation / Job role
   - "startdate": YYYY-MM-DD
   - "basic_salary": numeric amount
   - "shop": work location

Respond ONLY with a JSON object:
{
  "document_type": "{$detectedType}",
  "confidence": 0.95,
  "extracted": { ... }
}
PROMPT;

            $userPrompt = "Extract structured onboarding fields from the following document text (Category: {$detectedType}):\n\n{$text}";

            $result = $this->grokClient->chatJson($systemPrompt, $userPrompt);

            if ($result && isset($result['extracted']) && is_array($result['extracted']) && !empty($result['extracted'])) {
                $cleaned = $this->normalizeExtractedFields($result['extracted'], $result['document_type'] ?? $detectedType);
                if (!empty($cleaned['employeename']) || !empty($cleaned['ghcard']) || !empty($cleaned['account']) || !empty($cleaned['company'])) {
                    return [
                        'success' => true,
                        'document_type' => $result['document_type'] ?? $detectedType,
                        'confidence' => (float) ($result['confidence'] ?? 0.95),
                        'extracted' => $cleaned,
                        'raw_text' => mb_substr($text, 0, 500),
                    ];
                }
            }
        }

        // 2. Rule-based regex fallback
        $regexExtracted = $this->extractWithRegex($text, $detectedType);
        $cleaned = $this->normalizeExtractedFields($regexExtracted, $detectedType);

        if (!empty($cleaned['employeename']) || !empty($cleaned['ghcard']) || !empty($cleaned['account'])) {
            return [
                'success' => true,
                'document_type' => $detectedType,
                'confidence' => 0.88,
                'extracted' => $cleaned,
                'raw_text' => mb_substr($text, 0, 500),
            ];
        }

        return [
            'success' => false,
            'error' => 'Could not extract structured data from document text.',
            'document_type' => $detectedType,
            'confidence' => 0.0,
            'extracted' => [],
        ];
    }

    /**
     * Multimodal vision OCR attempt.
     */
    protected function parseViaVision(string $filePath, string $mimeType, ?string $documentType): ?array
    {
        try {
            $fileData = file_get_contents($filePath);
            $base64 = base64_encode($fileData);

            $systemPrompt = <<<PROMPT
You are an expert Document Intelligence specialist for HR onboarding in Ghana.
Identify the document type ("ghana_card", "bank_document", "ssnit_card", "contract") and extract fields:
- "ghana_card": employeename, dateofbirth (YYYY-MM-DD), gender, ghcard (GHA-XXXXXXXXX-X), nationality
- "bank_document": bank, account, branch, account_name
- "ssnit_card": ssnit_number, employeename
- "contract": company, position, startdate, basic_salary, shop
Output JSON: { "document_type": "...", "confidence": 0.95, "extracted": { ... } }
PROMPT;

            $instruction = "Inspect this document image. Identify type and extract all fields into standard JSON.";
            $result = $this->grokClient->chatVisionJson($systemPrompt, $instruction, $base64, $mimeType);

            if ($result && isset($result['extracted']) && is_array($result['extracted'])) {
                $cleaned = $this->normalizeExtractedFields($result['extracted'], $result['document_type'] ?? $documentType);
                return [
                    'success' => true,
                    'document_type' => $result['document_type'] ?? ($documentType ?: 'unknown'),
                    'confidence' => (float) ($result['confidence'] ?? 0.88),
                    'extracted' => $cleaned,
                ];
            }
        } catch (Throwable $e) {
            Log::channel('payroll_ai')->warning('Vision OCR failed: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Detects document category dynamically from recognized text.
     */
    protected function detectDocumentType(string $text, ?string $providedType): string
    {
        $upper = strtoupper($text);

        if (str_contains($upper, 'REPUBLIC OF GHANA') || str_contains($upper, 'NATIONAL IDENTITY CARD') || str_contains($upper, 'ECOWAS IDENTITY CARD') || preg_match('/GHA[ -]?\d{9}[ -]?\d/', $upper)) {
            return 'ghana_card';
        }

        if (str_contains($upper, 'SSNIT') || str_contains($upper, 'SOCIAL SECURITY') || str_contains($upper, 'NATIONAL INSURANCE TRUST')) {
            return 'ssnit_card';
        }

        if (str_contains($upper, 'CHEQUE') || str_contains($upper, 'ACCOUNT NUMBER') || str_contains($upper, 'BANK OF') || str_contains($upper, 'ECOBANK') || str_contains($upper, 'GCB BANK') || str_contains($upper, 'STANBIC') || str_contains($upper, 'CALBANK') || str_contains($upper, 'ZENITH') || str_contains($upper, 'FIDELITY')) {
            return 'bank_document';
        }

        if (str_contains($upper, 'CONTRACT OF EMPLOYMENT') || str_contains($upper, 'APPOINTMENT LETTER') || str_contains($upper, 'TERMS OF EMPLOYMENT') || str_contains($upper, 'COMMENCEMENT DATE')) {
            return 'contract';
        }

        return $providedType ?: 'ghana_card';
    }

    /**
     * Rule-based regex extraction from raw OCR text.
     */
    protected function extractWithRegex(string $text, string $docType): array
    {
        $extracted = [];

        // Ghana Card PIN
        if (preg_match('/GHA[ -]?(\d{9})[ -]?(\d)/i', $text, $m)) {
            $extracted['ghcard'] = "GHA-{$m[1]}-{$m[2]}";
        }

        // Surname & First Names (Ghana Card)
        $surname = null;
        $firstName = null;
        if (preg_match('/(?:SURNAME|SURNAME\/NOM)[\s:]+([A-Z\s]+)/i', $text, $m)) {
            $surname = trim(preg_split('/[\r\n]/', trim($m[1]))[0]);
        }
        if (preg_match('/(?:FIRST NAMES?|PRENOMS?)[\s:]+([A-Z\s]+)/i', $text, $m)) {
            $firstName = trim(preg_split('/[\r\n]/', trim($m[1]))[0]);
        }
        if ($surname || $firstName) {
            $extracted['employeename'] = trim(($firstName ? "{$firstName} " : "") . ($surname ?? ''));
        }

        // Date of Birth
        if (preg_match('/(?:DATE OF BIRTH|DOB|DATE DE NAISSANCE)[\s:]+(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{4})/i', $text, $m)) {
            $d = str_replace(['.', '/'], '-', $m[1]);
            $time = strtotime($d);
            if ($time) {
                $extracted['dateofbirth'] = date('Y-m-d', $time);
            }
        }

        // Gender
        if (preg_match('/(?:SEX|GENDER|SEXE)[\s:]+([MF]|Male|Female)/i', $text, $m)) {
            $extracted['gender'] = strtoupper(substr($m[1], 0, 1)) === 'M' ? 'Male' : 'Female';
        }

        // Nationality
        if (stripos($text, 'GHANAIAN') !== false) {
            $extracted['nationality'] = 'Ghanaian';
        }

        // SSNIT Number (13 alphanumeric chars)
        if (preg_match('/\b([A-Z0-9]{13})\b/i', $text, $m)) {
            $extracted['ssnit_number'] = strtoupper($m[1]);
        }

        // Bank Name
        $banks = ['GCB', 'ECOBANK', 'STANBIC', 'CALBANK', 'ZENITH', 'FIDELITY', 'UMB', 'CBG', 'ABSA', 'PRUDENTIAL', 'ACCESS', 'STANDARD CHARTERED', 'SOCIETE GENERALE', 'GT BANK', 'FBN'];
        foreach ($banks as $b) {
            if (stripos($text, $b) !== false) {
                $extracted['bank'] = $b;
                break;
            }
        }

        // Account Number (10 to 16 digits)
        if (preg_match('/(?:ACC(?:OUNT)?(?:\s*NO\.?)?[\s:]+)(\d{9,16})/i', $text, $m)) {
            $extracted['account'] = $m[1];
        } elseif (preg_match('/\b(\d{10,16})\b/', $text, $m)) {
            $extracted['account'] = $m[1];
        }

        return $extracted;
    }

    /**
     * Extracts text from PDF files via pdftotext.
     */
    protected function extractTextFromPdf(string $filePath): string
    {
        try {
            $cmd = 'pdftotext ' . escapeshellarg($filePath) . ' - 2>/dev/null';
            $output = shell_exec($cmd);
            return is_string($output) ? trim($output) : '';
        } catch (Throwable) {
            return '';
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
            if (preg_match('/^(\d{9}-\d)$/', $pin, $m)) {
                $pin = 'GHA-' . $m[1];
            }
            $normalized['ghcard'] = $pin;
        }

        // Clean dates to YYYY-MM-DD
        foreach (['dateofbirth', 'startdate', 'expiry_date'] as $dateKey) {
            if (!empty($normalized[$dateKey])) {
                try {
                    $d = str_replace(['.', '/'], '-', (string) $normalized[$dateKey]);
                    $time = strtotime($d);
                    if ($time) {
                        $normalized[$dateKey] = date('Y-m-d', $time);
                    }
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
