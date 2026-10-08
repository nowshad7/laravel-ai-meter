# Changelog

## Unreleased (0.1.0)

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
