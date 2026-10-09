# Introduction

**Laravel AI Meter** is self-hosted metering **and** budget enforcement for AI/LLM & agent
calls in Laravel. Trace every call, attribute token cost to a user or tenant, see it in a
built-in dashboard, and **block runaway spend before it happens** — all in your own app,
with **no external service**.

> Think *Telescope + a spend governor* for your AI calls.

## Requirements

- PHP `^8.1`
- Laravel `^10 | ^11 | ^12`

## Installation

```bash
composer require nsd7/laravel-ai-meter
php artisan migrate
```

That's it — the dashboard is available at `/ai-meter` (behind your `web` + `auth`
middleware by default). Publish the config, views or migrations when you need to customize:

```bash
php artisan vendor:publish --tag=ai-meter-config
php artisan vendor:publish --tag=ai-meter-views
php artisan vendor:publish --tag=ai-meter-migrations
```

## Record your first call

```php
use Nsd7\AiMeter\Facades\Meter;
use Nsd7\AiMeter\Core\Budget\BudgetScope;

Meter::log([
    'provider' => 'openai',
    'model'    => 'gpt-4o',
    'usage'    => ['prompt_tokens' => 1200, 'completion_tokens' => 350],
    'scope'    => BudgetScope::user(auth()->id()),
    'latency_ms' => 820,
    'input'    => $prompt,   // optional, redacted + truncated
    'output'   => $answer,   // optional
]);
```

Cost is computed for you from the bundled price book. Open `/ai-meter` to see it.

## Next steps

- [Configuration](/guide/configuration) — the full `config/ai-meter.php` reference.
- [Recording calls](/guide/recording) — the `Meter` API and Prism support.
- [Auto-instrumentation](/guide/auto-instrumentation) — meter the Laravel AI SDK & Neuron automatically.
- [Budgets & alerts](/guide/budgets) — enforce ceilings and get notified.
- [Agent runs](/guide/runs) — group an agent's calls and cap runaway loops.
