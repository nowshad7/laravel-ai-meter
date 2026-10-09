# Recording calls

Everything funnels through the `Meter` facade, which normalizes any SDK's response into one
shape, prices it, and records it.

## Meter::log()

```php
use Nsd7\AiMeter\Facades\Meter;
use Nsd7\AiMeter\Core\Budget\BudgetScope;

Meter::log([
    'provider'   => 'openai',
    'model'      => 'gpt-4o',
    'usage'      => ['prompt_tokens' => 1200, 'completion_tokens' => 350],
    'scope'      => BudgetScope::user(auth()->id()),
    'operation'  => 'chat',       // optional
    'latency_ms' => 820,          // optional
    'input'      => $prompt,      // optional, redacted + truncated
    'output'     => $answer,      // optional
    'properties' => ['feature' => 'summarize'], // optional metadata
]);
```

If you already know the USD cost, pass `cost_usd` and it's used as-is; otherwise it's
computed from the price book.

## Record a Prism response

```php
$response = Prism::text()->using('openai', 'gpt-4o')->withPrompt($prompt)->asText();

Meter::recordPrism($response, [
    'provider' => 'openai',
    'scope'    => BudgetScope::user(auth()->id()),
]);
```

Token usage and model are read straight off the Prism response.

Prism has no global event hook, so to meter automatically use the ready-made tap with
Prism's `->asText($callback)`:

```php
Prism::text()->using('openai', 'gpt-4o')->withPrompt($prompt)
    ->asText(Meter::prismTap(['provider' => 'openai', 'scope' => BudgetScope::user(auth()->id())]));
```

## Scopes

A `BudgetScope` is *who* a spend is attributed to:

```php
BudgetScope::user($id);     // per user
BudgetScope::tenant($id);   // per tenant
BudgetScope::global();      // everyone
new BudgetScope('team', 42); // any custom dimension
```

## Querying spend

```php
use Nsd7\AiMeter\Core\Budget\Period;

Meter::spent(BudgetScope::user($id), Period::Month); // USD this month
Meter::remaining(BudgetScope::user($id));            // USD left on the tightest budget, or null
Meter::budgetReport(BudgetScope::user($id));         // JSON-friendly report for every budget
```
