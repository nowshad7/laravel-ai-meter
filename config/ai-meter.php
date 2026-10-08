<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable the package
    |--------------------------------------------------------------------------
    */

    'enabled' => env('AI_METER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Dashboard routing
    |--------------------------------------------------------------------------
    */

    'route' => [
        'prefix' => env('AI_METER_PATH', 'ai-meter'),
        'domain' => env('AI_METER_DOMAIN'),
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization gate
    |--------------------------------------------------------------------------
    |
    | If a gate with this name is defined, only users who pass it can open the
    | dashboard. If it is not defined, anyone passing the route middleware may.
    |
    */

    'gate' => 'viewAiMeter',

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    */

    'features' => [
        'dashboard' => true,
        'runs' => true,
        'api' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-instrumentation adapters
    |--------------------------------------------------------------------------
    |
    | When enabled, the package listens to the Laravel AI SDK's events and
    | records each call automatically — no manual Meter::log() needed. The
    | "event" is the class the SDK dispatches at the end of a prompt/step; it
    | differs across versions, so override it to match yours (see
    | `php artisan event:list`). "scope" may be a callable returning a
    | BudgetScope; by default calls are attributed to the authenticated user.
    |
    | Prism has no global event hook — use Meter::prismTap() with Prism's
    | ->asText($callback) instead (see the README).
    |
    */

    'adapters' => [
        'laravel_ai' => [
            'enabled' => env('AI_METER_LARAVEL_AI', false),
            'event' => 'Laravel\\Ai\\Events\\AgentPrompted',
            'default_provider' => null,
            'scope' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recording
    |--------------------------------------------------------------------------
    |
    | "store_io" persists the prompt/response text (redacted + truncated).
    | Turn it off to store only metadata (tokens, cost, latency).
    |
    */

    'recording' => [
        'store_io' => env('AI_METER_STORE_IO', true),
        'redact' => true,
        'max_io_chars' => 8000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    |
    | Prices are loaded from the bundled price book and then merged with (and
    | overridden by) "prices" below. "unknown_model" is used when a model is not
    | found so costs are never silently zero. All figures are USD per 1,000,000
    | tokens. "fx_rate" (callable or numeric) converts USD to a display currency.
    |
    */

    'pricing' => [
        'currency' => env('AI_METER_CURRENCY', 'USD'),
        'fx_rate' => null,
        'unknown_model' => ['input' => 1.0, 'output' => 3.0],
        'prices' => [
            // 'openai' => ['my-model' => ['input' => 1, 'output' => 2]],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Budgets
    |--------------------------------------------------------------------------
    |
    | Spend ceilings per scope type ("user", "tenant", "global") over a period
    | ("run", "day", "month", "total"). action "block" stops the request via the
    | ai.budget middleware / Meter::check(); "alert" only flags it.
    |
    */

    'budgets' => [
        // ['scope' => 'user',   'period' => 'month', 'limit' => 50,  'action' => 'block'],
        // ['scope' => 'global', 'period' => 'day',   'limit' => 500, 'action' => 'alert'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Runs (agent runs)
    |--------------------------------------------------------------------------
    |
    | Default caps applied to Meter::run() when not overridden per call. These
    | are the runaway-loop guard: a run that hits a cap throws when guard() is
    | called. Set a value to null to disable that cap by default.
    |
    */

    'runs' => [
        'table' => 'ai_meter_runs',
        'max_tool_calls' => 25,
        'max_wall_clock' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scope resolution
    |--------------------------------------------------------------------------
    |
    | How the ai.budget middleware figures out "who" to attribute spend to when
    | a scope is not passed explicitly. For "user" it uses the authenticated
    | user's identifier.
    |
    */

    'scope' => [
        'default' => 'user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */

    'prune' => [
        'keep_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Presentation
    |--------------------------------------------------------------------------
    */

    'date_format' => 'Y-m-d H:i:s',

    'per_page' => 25,

];
