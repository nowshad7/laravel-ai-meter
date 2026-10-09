# Configuration

Publish the config file to customize:

```bash
php artisan vendor:publish --tag=ai-meter-config
```

| Key | Default | Purpose |
|---|---|---|
| `enabled` | `true` | Master switch. |
| `route.prefix` / `route.middleware` | `ai-meter` / `['web','auth']` | Dashboard routing. |
| `route.domain` | `null` | Serve the dashboard on a dedicated domain. |
| `gate` | `viewAiMeter` | Gate checked when defined. |
| `features.dashboard` / `runs` / `api` | `true` / `true` / `false` | Toggle the dashboard, Runs tab, and JSON budget API. |
| `recording.store_io` | `true` | Persist (redacted) prompt/response text. |
| `recording.redact` / `max_io_chars` | `true` / `8000` | Scrub secrets; truncate. |
| `pricing.currency` / `fx_rate` | `USD` / `null` | Display currency + USD→local conversion. |
| `pricing.unknown_model` | `{input:1,output:3}` | Fallback price per 1M tokens. |
| `pricing.prices` | `[]` | Override/extend the bundled price book (wins over everything). |
| `pricing.prices_path` | `null` | App-owned price-book JSON, loaded above the bundled table. |
| `pricing.update.source` | package book URL | Source for `ai-meter:update-prices`. |
| `adapters.laravel_ai` / `adapters.neuron` | disabled | Auto-instrumentation adapters. |
| `budgets` | `[]` | Spend ceilings (scope, period, limit, action). |
| `alerts` | 80% / 100% | Threshold alert notifications. |
| `spend_store.driver` | `database` | `database` (SUM per request) or `cache` (Redis counters). |
| `runs.max_tool_calls` / `max_wall_clock` | `25` / `300` | Default runaway-loop caps. |
| `scope.default` | `user` | How `ai.budget` attributes spend when no scope is passed. |
| `prune.keep_days` | `90` | Retention for `ai-meter:prune`. |
| `per_page` | `25` | Dashboard page size. |

See the other guide pages for the behaviour behind each group:
[budgets & alerts](/guide/budgets), [auto-instrumentation](/guide/auto-instrumentation),
[commands](/guide/commands).
