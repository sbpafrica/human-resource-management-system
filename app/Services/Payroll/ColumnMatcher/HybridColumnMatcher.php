<?php

namespace App\Services\Payroll\ColumnMatcher;

use App\Services\AI\Contracts\ColumnMatcherInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class HybridColumnMatcher implements ColumnMatcherInterface
{
    public function __construct(
        protected GrokColumnMatcher $grokMatcher,
        protected RuleBasedColumnMatcher $ruleMatcher,
        protected LegacyColumnMatcher $legacyMatcher
    ) {}

    /**
     * Executes the 3-Tier Fail-Safe Column Resolution:
     * Tier 1 (Grok AI) -> Tier 2 (PHP Regex Rules) -> Tier 3 (Legacy Offsets).
     */
    public function match(array $headers, string $clientSlug, array $sampleRows = []): array
    {
        $headerHash = md5(implode('|', $headers) . '|' . $clientSlug);
        $cacheKey = "payroll_col_mapping_{$clientSlug}_{$headerHash}";

        // 0. Cache Check: reuse verified mapping if already matched in the last 24 hours
        $cached = Cache::get($cacheKey);
        if ($cached && is_array($cached) && !empty($cached['mapping'])) {
            Log::channel('payroll_ai')->info("Using cached column mapping for client: {$clientSlug}");
            return $cached;
        }

        $minConfidence = (float) config('ai.min_confidence', 0.85);
        $aiEnabled = (bool) config('ai.enabled', true)
            && (bool) config('ai.features.column_matching', true)
            && (bool) config("ai.clients.{$clientSlug}.enabled", true);

        // =================================================================
        // TIER 1: GROK AI MATCHER
        // =================================================================
        if ($aiEnabled) {
            try {
                Log::channel('payroll_ai')->info("Attempting Tier 1 (Grok AI) column matching for {$clientSlug}");
                $aiResult = $this->grokMatcher->match($headers, $clientSlug, $sampleRows);

                if (!empty($aiResult['mapping']) && ($aiResult['confidence'] >= $minConfidence)) {
                    Log::channel('payroll_ai')->info("Tier 1 Grok AI succeeded for {$clientSlug} (Confidence: {$aiResult['confidence']})");

                    // Cache for 24 hours
                    Cache::put($cacheKey, $aiResult, now()->addHours(24));

                    if (config('ai.dry_run', false)) {
                        Log::channel('payroll_ai')->info("AI Dry Run mode active. Logging AI match but falling through to Rule/Legacy.");
                    } else {
                        return $aiResult;
                    }
                } else {
                    Log::channel('payroll_ai')->warning("Tier 1 Grok AI low confidence ({$aiResult['confidence']}) for {$clientSlug}. Falling back to Tier 2.");
                }
            } catch (Throwable $e) {
                Log::channel('payroll_ai')->error("Tier 1 Grok AI exception for {$clientSlug}: {$e->getMessage()}. Falling back to Tier 2.");
            }
        }

        // =================================================================
        // TIER 2: DETERMINISTIC PHP RULE / REGEX MATCHER
        // =================================================================
        try {
            Log::channel('payroll_ai')->info("Attempting Tier 2 (PHP Rule-Based) column matching for {$clientSlug}");
            $ruleResult = $this->ruleMatcher->match($headers, $clientSlug, $sampleRows);

            if (!empty($ruleResult['mapping']) && count($ruleResult['mapping']) >= 3) {
                Log::channel('payroll_ai')->info("Tier 2 PHP Rule matcher succeeded for {$clientSlug} (" . count($ruleResult['mapping']) . " columns mapped)");
                Cache::put($cacheKey, $ruleResult, now()->addHours(24));
                return $ruleResult;
            }

            Log::channel('payroll_ai')->warning("Tier 2 PHP Rule matcher matched insufficient columns for {$clientSlug}. Falling back to Tier 3.");
        } catch (Throwable $e) {
            Log::channel('payroll_ai')->error("Tier 2 Rule matcher exception for {$clientSlug}: {$e->getMessage()}. Falling back to Tier 3.");
        }

        // =================================================================
        // TIER 3: LEGACY HARDCODED POSITION MAP (GUARANTEED SAFETY NET)
        // =================================================================
        Log::channel('payroll_ai')->info("Using Tier 3 Legacy column mapping safety net for {$clientSlug}");
        $legacyResult = $this->legacyMatcher->match($headers, $clientSlug, $sampleRows);

        return $legacyResult;
    }
}
