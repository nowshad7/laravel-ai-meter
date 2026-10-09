# Budgets & alerts

## Configure budgets

```php
// config/ai-meter.php
'budgets' => [
    ['scope' => 'user',   'period' => 'month', 'limit' => 50,  'action' => 'block'],
    ['scope' => 'global', 'period' => 'day',   'limit' => 500, 'action' => 'alert'],
],
```

- **scope** — `user`, `tenant`, or `global`.
- **period** — `run`, `day`, `month`, or `total`.
- **action** — `block` (reject the request) or `alert` (only notify).

## Enforce on a route

Returns **HTTP 402** when the user is over their monthly ceiling:

```php
Route::post('/chat', ChatController::class)->middleware('ai.budget:user');
```

## Enforce in code

Great for pausing an agent mid-run:

```php
$decision = Meter::check(BudgetScope::user($id));
if (! $decision->allowed) {
    // over a blocking budget — stop before the next expensive step
}

$left = Meter::remaining(BudgetScope::user($id)); // USD remaining, or null
```

## Alerts (80% / 100%)

Both `block` and `alert` budgets fire a notification the first time a scope's spend crosses
a threshold within the budget's window — so you hear about runaway spend as it happens.

```php
'alerts' => [
    'enabled'    => true,
    'thresholds' => [0.8, 1.0],   // alert at 80% and 100% of the limit
    'notify' => [
        'mail'  => env('AI_METER_ALERT_MAIL'),           // address or array
        'slack' => env('AI_METER_ALERT_SLACK_WEBHOOK'),  // Slack incoming-webhook URL
    ],
],
```

Each threshold alerts **once per window** (a `day` budget re-arms tomorrow, a `month` budget
next month), and a jump past several thresholds sends only the highest. Prefer your own
handling? Leave `notify` empty and listen for the event:

```php
use Nsd7\AiMeter\Events\BudgetThresholdReached;

Event::listen(function (BudgetThresholdReached $e) {
    // $e->scope, $e->budget, $e->threshold, $e->spentUsd, $e->exceeded()
});
```

## High-volume enforcement

By default, "how much has this scope spent?" is a `SUM` of the calls table on each check.
Apps enforcing budgets on *every* request can switch to cache counters:

```php
'spend_store' => ['driver' => 'cache'],   // or AI_METER_SPEND_STORE=cache
```

The `cache` driver (`CacheSpendStore`) keeps a Redis (or any cache) counter per scope and
period window, so budget reads are O(1). Counters reflect spend recorded after the driver is
enabled; day/month counters expire with their window.
