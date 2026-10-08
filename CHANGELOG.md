# Changelog

## Unreleased (0.4.0)

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
