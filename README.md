# Laravel AI Meter

**Self-hosted metering *and* budget enforcement for AI/LLM & agent calls in Laravel.** Trace every call, attribute token cost to a user/tenant, see it in a built-in dashboard, and **block runaway spend before it happens** — all in your own app, with **no external service**.

> Think *Telescope + a spend governor* for your AI calls.

[![Tests](https://github.com/nowshad7/laravel-ai-meter/actions/workflows/tests.yml/badge.svg)](https://github.com/nowshad7/laravel-ai-meter/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

---

## Why this exists

Shipping AI features is easy; **controlling their cost is not.** Existing tools make you choose:

| | Send data to an external service? | Built-in dashboard? | Enforce budgets? | Scope |
|---|:--:|:--:|:--:|---|
| Langfuse-style tracing | **Yes** (run a second service) | Its own | ❌ observe only | — |
| Budget packages | No | ❌ (raw table) | ⚠️ per-run only | per run |
| **Laravel AI Meter** | **No** | ✅ | ✅ **block/alert** | **user / tenant / day / month / total** |

AI Meter is the only option that **meters and enforces, self-hosted, with a dashboard**, and attributes spend to whoever caused it.

## Features

- 📊 **Dashboard** (Blade + Tailwind, dark mode): spend today / this month / all-time, spend-over-time chart, cost by model & provider, top scopes, and a filterable call log with per-call detail.
- 🧾 **Cost attribution** — every call priced from a bundled, overridable price book (unknown models use a configurable fallback, never silently $0).
- 🛑 **Budget enforcement** — spend ceilings per **user / tenant / global**, over **run / day / month / total**, with `block` (HTTP 402) or `alert` actions.
- 🔌 **Capture anything** — a reliable `Meter::log()` / `Meter::recordPrism()` API today; deeper auto-instrumentation for Prism, the Laravel AI SDK and Neuron is on the roadmap.
- 🔐 **Safe by default** — prompt/response storage is optional, redacted (keys, tokens, emails, card-like numbers) and truncated; dashboard behind an auth gate; `noindex`.
- 🗄️ **Self-hosted** — stores in your database, works on MySQL, PostgreSQL, SQLite and SQL Server. No accounts, no egress.

## Requirements

- PHP `^8.1`
- Laravel `^10 | ^11 | ^12`

## Installation

```bash
composer require nsd7/laravel-ai-meter
php artisan migrate
```

Visit **`/ai-meter`** while logged in. Publish the config to customise:

```bash
php artisan vendor:publish --tag=ai-meter-config
```

## Usage

### Record a call

The reliable, version-proof way to meter any provider/SDK:

```php
use Nsd7\AiMeter\Facades\Meter;
use Nsd7\AiMeter\Core\Budget\BudgetScope;

Meter::log([
    'provider' => 'openai',
    'model'    => 'gpt-4o',
    'usage'    => ['prompt_tokens' => 1200, 'completion_tokens' => 350],
    'scope'    => BudgetScope::user(auth()->id()),
    'latency_ms' => 820,
    'input'    => $prompt,     // optional, redacted + truncated
    'output'   => $answer,     // optional
]);
```

### Record a Prism response

```php
$response = Prism::text()->using('openai', 'gpt-4o')->withPrompt($prompt)->asText();

Meter::recordPrism($response, [
    'provider' => 'openai',
    'scope'    => BudgetScope::user(auth()->id()),
]);
```

Token usage and model are read straight off the Prism response, so cost is computed for you.

### Enforce a budget

Config:

```php
'budgets' => [
    ['scope' => 'user',   'period' => 'month', 'limit' => 50,  'action' => 'block'],
    ['scope' => 'global', 'period' => 'day',   'limit' => 500, 'action' => 'alert'],
],
```

Guard a route — returns **HTTP 402** when the user is over their monthly ceiling:

```php
Route::post('/chat', ChatController::class)->middleware('ai.budget:user');
```

Or check in code (great for pausing an agent mid-run):

```php
$decision = Meter::check(BudgetScope::user($id));
if (! $decision->allowed) {
    // over a blocking budget — stop before the next expensive step
}

$left = Meter::remaining(BudgetScope::user($id)); // USD remaining, or null
```

## Configuration

Key options in `config/ai-meter.php`:

| Key | Default | Description |
|---|---|---|
| `route.prefix` / `route.middleware` | `ai-meter` / `['web','auth']` | Dashboard routing. |
| `gate` | `viewAiMeter` | Gate checked when defined. |
| `recording.store_io` | `true` | Persist (redacted) prompt/response text. |
| `recording.redact` / `max_io_chars` | `true` / `8000` | Scrub secrets; truncate. |
| `pricing.currency` / `fx_rate` | `USD` / `null` | Display currency + USD→local conversion. |
| `pricing.unknown_model` | `{input:1,output:3}` | Fallback price per 1M tokens. |
| `pricing.prices` | `[]` | Override/extend the bundled price book. |
| `budgets` | `[]` | Spend ceilings (scope, period, limit, action). |
| `prune.keep_days` | `90` | Retention for `ai-meter:prune`. |

Prune old records: `php artisan ai-meter:prune`.

## Roadmap

- **v0.2** — auto-instrumentation adapters (Prism / Laravel AI SDK / Neuron), agent **runs** with a trace tree, `Meter::run()` with per-run tool-call & wall-clock caps (runaway-loop guard).
- **v0.3** — agent-facing budget endpoint + MCP resource ("how much budget is left?"), alert notifications, `ai-meter:update-prices`, exports.
- **v1.0** — docs site, stability.
- **Later** — a framework-agnostic core + a Symfony bundle.

## Testing

```bash
composer install
composer test
```

## License

MIT. See [LICENSE](LICENSE).
