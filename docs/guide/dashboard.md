# Dashboard

A self-hosted dashboard ships with the package (Blade + Tailwind + Alpine + Chart.js, dark
mode), served at your configured prefix (`/ai-meter` by default), behind the route
middleware and an optional authorization gate.

## Pages

- **Overview** — spend today / this month / all-time, a spend-over-time chart, cost by model
  & provider, and top scopes.
- **Budgets** — every configured budget with its live used-fraction; global budgets as
  spend-vs-limit, per-user/tenant budgets as the top spenders of that type, with the alert
  thresholds drawn as ticks.
- **Calls** — a filterable call log (provider, model, status, scope, search) with CSV / JSON
  export and per-call detail.
- **Runs** — agent runs with their trace.

![Overview](https://raw.githubusercontent.com/nowshad7/laravel-ai-meter/main/art/screenshots/overview.png)

![Budgets](https://raw.githubusercontent.com/nowshad7/laravel-ai-meter/main/art/screenshots/budgets.png)

## Access control

```php
// config/ai-meter.php
'route' => [
    'prefix'     => env('AI_METER_PATH', 'ai-meter'),
    'domain'     => env('AI_METER_DOMAIN'),
    'middleware' => ['web', 'auth'],
],

'gate' => 'viewAiMeter',
```

If a gate named by `gate` is defined, only users who pass it can open the dashboard; if it is
not defined, anyone passing the route middleware may. The agent-facing JSON budget API is a
separate opt-in under `features.api`.
