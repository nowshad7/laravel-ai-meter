# Auto-instrumentation

Opt-in adapters record every call automatically — no manual `Meter::log()`. Each is wired
only when enabled **and** its configured event class exists, so it never affects an app that
isn't using it.

## Laravel AI SDK

```php
// config/ai-meter.php
'adapters' => [
    'laravel_ai' => [
        'enabled' => true,
        // Match your installed version (see `php artisan event:list`):
        'event'   => 'Laravel\\Ai\\Events\\AgentPrompted',
        'scope'   => fn () => BudgetScope::user(auth()->id()), // optional; defaults to the auth user
    ],
],
```

## Neuron AI

```php
'adapters' => [
    'neuron' => [
        'enabled' => true,
        // Match your installed version:
        'event'   => 'NeuronAI\\Observability\\Events\\InferenceStop',
        'scope'   => fn () => BudgetScope::user(auth()->id()),
    ],
],
```

Both read usage/model off the event's response/message defensively (including
`getUsage()` / `getModel()` accessors), so they keep working across SDK versions.

## Prism

Prism has no global event hook — use [`Meter::prismTap()`](/guide/recording#record-a-prism-response).
