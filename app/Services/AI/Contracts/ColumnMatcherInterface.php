<?php

namespace App\Services\AI\Contracts;

interface ColumnMatcherInterface
{
    /**
     * Resolves raw spreadsheet headers into standardized internal keys.
     *
     * @param array<int, string|null> $headers List of raw string headers from the Excel header row
     * @param string $clientSlug e.g., 'jaykay', 'ofitemp', 'melcom', 'transsion'
     * @param array<int, array> $sampleRows Optional first 2-5 rows for contextual value inspection
     * @return array [
     *     'mapping' => array<string, int>, // 'standard_field' => columnIndex (0-based)
     *     'confidence' => float,           // 0.0 to 1.0
     *     'tier' => string,                // 'ai', 'rule', or 'legacy'
     *     'unmatched' => array<string>,    // headers that could not be mapped
     * ]
     */
    public function match(array $headers, string $clientSlug, array $sampleRows = []): array;
}
