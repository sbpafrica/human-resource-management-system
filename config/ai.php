<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master AI Toggle & Modes
    |--------------------------------------------------------------------------
    |
    | When enabled is false, the system automatically uses Tier 2 (Rule-Based)
    | and Tier 3 (Legacy Hardcoded) extractors with 0 external API calls.
    |
    */
    'enabled' => env('AI_PAYROLL_ENABLED', true),

    'dry_run' => env('AI_DRY_RUN_MODE', false),

    'provider' => env('AI_PROVIDER', 'grok'),

    'timeout' => (int) env('AI_REQUEST_TIMEOUT_SECONDS', 6),

    'min_confidence' => (float) env('AI_MIN_CONFIDENCE_THRESHOLD', 0.85),

    /*
    |--------------------------------------------------------------------------
    | Grok (xAI) Configuration
    |--------------------------------------------------------------------------
    |
    | Connects via OpenAI-compatible endpoints to xAI API (https://api.x.ai/v1).
    | Supported models: 'grok-2-latest', 'grok-2-mini', 'grok-beta'
    |
    */
    'grok' => [
        'api_key' => env('GROK_API_KEY'),
        'model' => env('GROK_MODEL', 'grok-2-latest'),
        'base_url' => env('GROK_BASE_URL', 'https://api.x.ai/v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Granular Feature Toggles
    |--------------------------------------------------------------------------
    */
    'features' => [
        'column_matching' => env('AI_FEATURE_COLUMN_MATCHING', true),
        'anomaly_detection' => env('AI_FEATURE_ANOMALY_DETECTION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client-Level Overrides (All 31 Clients Enabled By Default)
    |--------------------------------------------------------------------------
    |
    | All clients (Jaykay, Melcom, OFI, Transsion, Bunge, Twellium, etc.) are
    | automatically enabled by default. To disable AI for any specific client,
    | set: 'client_slug' => ['enabled' => false].
    |
    */
    'clients' => [
        '*' => ['enabled' => true],
    ],
];
