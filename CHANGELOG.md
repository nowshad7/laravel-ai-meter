# Changelog

## Unreleased (0.4.3)

### Added
- **`ai-meter:update-prices` command**: refresh the price book from a source (a URL or local JSON file, defaulting to the package's maintained book) into an app-owned file, so token costs stay current without upgrading the package. Merges by default (`--replace` to overwrite), with `--dry-run`, `--source` and `--path` options and a "N new, M changed" summary. A new `pricing.prices_path` is loaded **above** the bundled, install-frozen table but **below** explicit `pricing.prices` overrides, so a refresh never clobbers custom prices.

## 0.4.2

### Added
- **Budgets dashboard page** (`/ai-meter/budgets`): a new tab that lists every configured budget with its **live used-fraction**. Global budgets show spend vs. limit directly; per-user / per-tenant budgets show the top spenders of that type in the current window, each as a bar against the limit (so you can see who's closest to tripping it). Alert thresholds (80% / 100%) are drawn as ticks on each bar, and an empty state links to the config. Backed by the same data as `Meter::budgetReport()` / the JSON API.

## 0.4.1

### Added
- **Budget alert notifications** (`alerts`): both `block` and `alert` budgets now fire a `BudgetThresholdReached` event the first time a scope's spend crosses a configurable threshold (default 80% and 100%) within a budget's window — once per threshold per day/month/all-time window, and a jump past several thresholds sends only the highest. A bundled listener delivers it as a mail and/or Slack-webhook notification (`alerts.notify`), or you can listen for the event yourself. Alerting is checked after each recorded call and never breaks recording when a mail server or webhook fails.

## 0.4.0

### Added
- **Agent-facing budget API** (`features.api`): a read-only `GET {prefix}/api/budget` endpoint returning spend-by-period and the status of every configured budget for a scope ("how much budget is left?"), consumable by an SPA, a dashboard, or an AI agent. Backed by the reusable `Meter::budgetReport($scope)`.

## 0.3.0

### Added
- **Auto-instrumentation for the Laravel AI SDK** (`adapters.laravel_ai`): opt-in event listener that records every call automatically — no manual `Meter::log()`. Version-tolerant (configurable event class, defensive usage/model extraction) and attributes spend to the authenticated user (or a configured scope resolver).
- **`Meter::prismTap()`** — a ready-made callback for Prism's `->asText($callback)`, since Prism has no global event hook.
- Shared `UsageReader` support class for reading token usage/model from SDK responses.

## 0.2.0

### Added
- **Agent runs** (`Meter::run()`): group calls under one run, track step/tool-call/cost aggregates, and view them as a **trace** in the dashboard (Runs tab). New `ai_meter_runs` table (publishable migration) and `features.runs` toggle.
- **Runaway-loop guard**: `$run->guard()` throws `RunLimitExceededException` on a per-run tool-call or wall-clock cap, and `BudgetExceededException` on a per-run or scope budget — the safeguard against the "agent looped for 11 days" class of bills. Configurable defaults under `runs.*`.
- `BudgetExceededException` for programmatic budget checks (the HTTP middleware still uses 402).

## 0.1.0

Initial release — self-hosted metering + budget enforcement for AI/LLM calls.

### Added
- Framework-agnostic core: `TokenUsage`, `LlmCall`, `ModelPrice`, `PriceBook`, budget `Period`/`Scope`/`Budget`/`Guard`, and contracts (`Recorder`, `SpendStore`, `PriceProvider`, `Redactor`).
- `Meter` service + facade: `log()`, `recordPrism()`, `cost()`, `spent()`, `check()`, `remaining()`.
- Cost attribution via a bundled, overridable price book with an unknown-model fallback.
- Budget enforcement: per user/tenant/global over run/day/month/total, with `block` (HTTP 402 via the `ai.budget` middleware) or `alert`.
- Database recording with optional, redacted and truncated prompt/response storage.
- Self-hosted dashboard (overview + filterable call log + call detail), Blade/Tailwind/Alpine/Chart.js, dark mode, behind an auth gate.
- Publishable config, views and migrations; `ai-meter:prune` command.
- Test suite (Orchestra Testbench) and a Laravel 10–12 CI matrix.
