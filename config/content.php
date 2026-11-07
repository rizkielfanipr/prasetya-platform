<?php

declare(strict_types=1);

return [
    /*
    | Content Studio (ADR-0011). Post images live on a private disk and are only streamed to signed-in users.
    | Never point this at a public disk.
    */
    'disk' => env('CONTENT_DISK', 'local'),

    /*
    | AI copy drafts (phase 3). Off unless a provider is configured: the manual workflow never depends on it.
    |
    | provider: none | fake | openai_compatible. "fake" writes deterministic drafts for local demos and tests and
    | is refused in production. "openai_compatible" calls POST {base_url}/chat/completions, e.g. DeepSeek at
    | https://api.deepseek.com, or OpenAI, OpenRouter, or another compatible gateway.
    |
    | Prices are USD per million tokens, from the provider's price page. They are required for a real provider,
    | because the budget is enforced on estimated cost.
    */
    'ai' => [
        'copy' => [
            'provider' => env('CONTENT_TEXT_PROVIDER', 'none'),
            'base_url' => env('CONTENT_TEXT_BASE_URL'),
            'api_key' => env('CONTENT_TEXT_API_KEY'),
            'model' => env('CONTENT_TEXT_MODEL'),
            'price_input_per_million' => (float) env('CONTENT_TEXT_PRICE_INPUT', 0),
            'price_output_per_million' => (float) env('CONTENT_TEXT_PRICE_OUTPUT', 0),
            // Models that may be chosen in Pengaturan AI, each with its own prices:
            // "model:input-price:output-price,model:input-price:output-price". Empty: only CONTENT_TEXT_MODEL.
            'models' => env('CONTENT_TEXT_MODELS', ''),
            'timeout_seconds' => (int) env('CONTENT_TEXT_TIMEOUT', 60),
            'max_output_tokens' => (int) env('CONTENT_TEXT_MAX_OUTPUT_TOKENS', 1500),
            // Calls per run: the first try plus retries after a timeout, rate limit, server error, or invalid output.
            'max_attempts' => (int) env('CONTENT_TEXT_MAX_ATTEMPTS', 2),
        ],
        'budget' => [
            'run_usd' => (float) env('CONTENT_AI_BUDGET_RUN_USD', 0.50),
            'week_usd' => (float) env('CONTENT_AI_BUDGET_WEEK_USD', 5.00),
            'runs_per_revision' => (int) env('CONTENT_AI_RUNS_PER_REVISION', 5),
        ],
        // After this many provider failures in a row within the window, new requests wait until it passes.
        'circuit' => ['failures' => 3, 'minutes' => 10],
        // Kept apart from anything payment-related; run with: php artisan queue:work --queue=content-ai
        'queue' => env('CONTENT_AI_QUEUE', 'content-ai'),
    ],
];
