# Agent runs

Wrap an agent loop in a **run** to group its calls, see the whole trace in the dashboard,
and **stop a runaway loop** before it drains the budget.

```php
use Nsd7\AiMeter\Facades\Meter;
use Nsd7\AiMeter\Core\Budget\BudgetScope;

$run = Meter::run(BudgetScope::user(auth()->id()), [
    'budget'         => 2.00,   // USD for this whole run
    'max_tool_calls' => 10,     // runaway-loop cap
    'max_wall_clock' => 120,    // seconds
]);

try {
    while ($agent->thinking()) {
        $run->guard();                 // throws before the next step if over a cap/budget
        $response = $agent->step();
        $run->recordPrism($response);  // or $run->record([...]) for tool calls
    }
    $run->finish();
} catch (\Nsd7\AiMeter\Runs\Exceptions\RunLimitExceededException
       | \Nsd7\AiMeter\Budget\Exceptions\BudgetExceededException $e) {
    $run->fail($e->getMessage());      // loop stopped safely
}
```

Every run shows up under the **Runs** tab with its step/tool-call count, cost vs. budget,
and a per-call trace. Default caps live under `runs.*` in the config.
