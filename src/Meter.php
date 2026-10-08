<?php

namespace Nsd7\AiMeter;

use Nsd7\AiMeter\Budget\BudgetAlerter;
use Nsd7\AiMeter\Core\Budget\Budget;
use Nsd7\AiMeter\Core\Budget\BudgetDecision;
use Nsd7\AiMeter\Core\Budget\BudgetGuard;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Core\Budget\Period;
use Nsd7\AiMeter\Core\Contracts\PriceProvider;
use Nsd7\AiMeter\Core\Contracts\Recorder;
use Nsd7\AiMeter\Core\Contracts\SpendStore;
use Closure;
use Nsd7\AiMeter\Core\Data\LlmCall;
use Nsd7\AiMeter\Core\Data\TokenUsage;
use Nsd7\AiMeter\Runs\MeterRun;
use Nsd7\AiMeter\Support\UsageReader;
use Throwable;

/**
 * The package's main entry point: compute cost, record calls, and check budgets.
 */
class Meter
{
    public function __construct(
        protected PriceProvider $prices,
        protected Recorder $recorder,
        protected SpendStore $spendStore,
        protected ?BudgetAlerter $alerter = null,
    ) {
    }

    /**
     * USD cost for a usage against a provider/model, using the configured
     * unknown-model fallback when the model is not priced.
     */
    public function cost(string $provider, string $model, TokenUsage $usage): float
    {
        $price = $this->prices->priceFor($provider, $model);

        return $price ? $price->cost($usage) : 0.0;
    }

    /**
     * Record a normalized call, computing its cost when not already set.
     */
    public function record(LlmCall $call): LlmCall
    {
        if ($call->costUsd === null) {
            $call = $call->withCost($this->cost($call->provider, $call->model, $call->usage));
        }

        $this->recorder->record($call);

        $this->alert($call);

        return $call;
    }

    /**
     * Fire threshold alerts for the budgets this call counts towards. Alerting
     * never breaks recording: failures are reported and swallowed.
     */
    protected function alert(LlmCall $call): void
    {
        if (! $this->alerter || ! config('ai-meter.alerts.enabled', true)) {
            return;
        }

        $scope = $call->scopeType !== null ? new BudgetScope($call->scopeType, $call->scopeId) : null;

        try {
            $this->alerter->check($scope, $this->budgets());
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Convenience: build and record a call from a plain array.
     *
     * @param array<string, mixed> $attributes
     */
    public function log(array $attributes): LlmCall
    {
        $rawUsage = $attributes['usage'] ?? [];
        $usage = $rawUsage instanceof TokenUsage ? $rawUsage : TokenUsage::fromArray((array) $rawUsage);

        [$scopeType, $scopeId] = $this->normalizeScope($attributes['scope'] ?? null);

        return $this->record(new LlmCall(
            provider: (string) ($attributes['provider'] ?? 'unknown'),
            model: (string) ($attributes['model'] ?? 'unknown'),
            usage: $usage,
            source: (string) ($attributes['source'] ?? 'manual'),
            operation: (string) ($attributes['operation'] ?? 'chat'),
            status: (string) ($attributes['status'] ?? 'success'),
            costUsd: isset($attributes['cost_usd']) ? (float) $attributes['cost_usd'] : null,
            latencyMs: isset($attributes['latency_ms']) ? (int) $attributes['latency_ms'] : null,
            traceId: $attributes['trace_id'] ?? null,
            runId: $attributes['run_id'] ?? null,
            toolName: $attributes['tool_name'] ?? null,
            scopeType: $scopeType,
            scopeId: $scopeId,
            causerType: $attributes['causer_type'] ?? null,
            causerId: $attributes['causer_id'] ?? null,
            input: $attributes['input'] ?? null,
            output: $attributes['output'] ?? null,
            error: $attributes['error'] ?? null,
            properties: (array) ($attributes['properties'] ?? []),
        ));
    }

    /**
     * Record a Prism response. Token usage and model are read off the response
     * object defensively, so it keeps working across Prism versions.
     *
     * @param object $response A Prism response (text/structured/stream result).
     * @param array<string, mixed> $attributes Extra attributes (provider, scope, …).
     */
    public function recordPrism(object $response, array $attributes = []): LlmCall
    {
        return $this->log(array_merge([
            'source' => 'prism',
            'provider' => $attributes['provider'] ?? 'unknown',
            'model' => $attributes['model'] ?? UsageReader::model($response) ?? 'unknown',
            'usage' => UsageReader::usage($response),
        ], $attributes));
    }

    /**
     * A ready-made callback for Prism's `->asText($callback)` (and the other
     * `as*()` methods), which records the response as it comes back:
     *
     *   Prism::text()->using('openai', 'gpt-4o')->withPrompt($p)
     *       ->asText(Meter::prismTap(['provider' => 'openai', 'scope' => $scope]));
     *
     * @param array<string, mixed> $attributes
     */
    public function prismTap(array $attributes = []): Closure
    {
        return function (...$args) use ($attributes) {
            foreach ($args as $arg) {
                if (is_object($arg) && (isset($arg->usage) || isset($arg->meta))) {
                    $this->recordPrism($arg, $attributes);

                    return;
                }
            }
        };
    }

    /**
     * Begin an agent run: a context that groups calls, tracks aggregates and
     * enforces per-run caps (the runaway-loop guard).
     *
     * @param array{budget?: float, max_tool_calls?: int, max_wall_clock?: int, label?: string, run_id?: string} $options
     */
    public function run(BudgetScope $scope, array $options = []): MeterRun
    {
        return new MeterRun(
            meter: $this,
            scope: $scope,
            budgetUsd: isset($options['budget']) ? (float) $options['budget'] : null,
            maxToolCalls: $options['max_tool_calls'] ?? config('ai-meter.runs.max_tool_calls'),
            maxWallClock: $options['max_wall_clock'] ?? config('ai-meter.runs.max_wall_clock'),
            label: $options['label'] ?? null,
            runId: $options['run_id'] ?? null,
        );
    }

    public function spent(BudgetScope $scope, Period $period): float
    {
        return $this->spendStore->spent($scope, $period);
    }

    /**
     * Evaluate a scope against its configured budgets (optionally pre-adding a
     * cost about to be incurred). Use before an expensive run.
     */
    public function check(BudgetScope $scope, float $additionalUsd = 0.0): BudgetDecision
    {
        return $this->guard()->evaluate($scope, $additionalUsd);
    }

    /**
     * Remaining USD for the tightest budget of a scope type, or null if none.
     */
    public function remaining(BudgetScope $scope): ?float
    {
        return $this->check($scope)->remainingUsd();
    }

    /**
     * A JSON-friendly budget report for a scope — spend by period plus the
     * status of every configured budget. Consumable by an SPA, a dashboard, or
     * an AI agent asking "how much budget do I have left?".
     *
     * @return array<string, mixed>
     */
    public function budgetReport(BudgetScope $scope): array
    {
        $guard = $this->guard();

        $budgets = array_map(function (Budget $budget) use ($scope) {
            $spent = $this->spent($scope, $budget->period);
            $exceeded = $budget->limitUsd > 0 && $spent >= $budget->limitUsd;

            return [
                'period' => $budget->period->value,
                'action' => $budget->action,
                'limit_usd' => round($budget->limitUsd, 8),
                'spent_usd' => round($spent, 8),
                'remaining_usd' => round(max(0.0, $budget->limitUsd - $spent), 8),
                'used_fraction' => $budget->limitUsd > 0 ? round($spent / $budget->limitUsd, 6) : null,
                'exceeded' => $exceeded,
                'allowed' => ! ($exceeded && $budget->blocks()),
            ];
        }, $guard->budgetsFor($scope));

        return [
            'scope' => ['type' => $scope->type, 'id' => $scope->id],
            'allowed' => $guard->evaluate($scope)->allowed,
            'spend' => [
                'day' => round($this->spent($scope, Period::Day), 8),
                'month' => round($this->spent($scope, Period::Month), 8),
                'total' => round($this->spent($scope, Period::Total), 8),
            ],
            'currency' => (string) config('ai-meter.pricing.currency', 'USD'),
            'budgets' => $budgets,
        ];
    }

    public function guard(): BudgetGuard
    {
        return new BudgetGuard($this->spendStore, $this->budgets());
    }

    /**
     * @return array<int, Budget>
     */
    public function budgets(): array
    {
        return array_map(
            fn (array $b) => Budget::fromArray($b),
            array_values((array) config('ai-meter.budgets', []))
        );
    }

    /**
     * @return array{0: ?string, 1: string|int|null}
     */
    protected function normalizeScope(mixed $scope): array
    {
        if ($scope instanceof BudgetScope) {
            return [$scope->type, $scope->id];
        }

        if (is_array($scope)) {
            return [$scope['type'] ?? null, $scope['id'] ?? null];
        }

        return [null, null];
    }
}
