<?php

namespace Nsd7\AiMeter\Adapters;

use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Meter;
use Nsd7\AiMeter\Support\UsageReader;

/**
 * Records calls made through the Laravel AI SDK by listening to the event it
 * dispatches at the end of a prompt/step (e.g. AgentPrompted / StepCompleted).
 *
 * The exact event class and payload differ across SDK versions, so this reads
 * the response defensively and is wired only when enabled and the configured
 * event class exists — it never breaks an app that isn't using it.
 */
class LaravelAiListener
{
    public function __construct(protected Meter $meter)
    {
    }

    public function handle(object $event): void
    {
        $response = UsageReader::responseFrom($event);
        $usage = UsageReader::usage($response);

        // Nothing meaningful to record (e.g. a "before" event with no usage).
        if ($usage->totalTokens === 0 && $usage->promptTokens === 0 && $usage->completionTokens === 0) {
            return;
        }

        $this->meter->log([
            'source' => 'laravel-ai',
            'provider' => $this->provider($event, $response),
            'model' => UsageReader::model($response) ?? 'unknown',
            'usage' => $usage,
            'scope' => $this->scope(),
        ]);
    }

    protected function provider(object $event, object $response): string
    {
        $provider = $response->provider ?? ($event->provider ?? null)
            ?? config('ai-meter.adapters.laravel_ai.default_provider');

        return is_scalar($provider) ? (string) $provider : 'unknown';
    }

    protected function scope(): ?BudgetScope
    {
        $resolver = config('ai-meter.adapters.laravel_ai.scope');

        if (is_callable($resolver)) {
            $scope = $resolver();

            return $scope instanceof BudgetScope ? $scope : null;
        }

        // Default: attribute to the authenticated user when there is one.
        $id = (function () {
            try {
                return auth()->id();
            } catch (\Throwable $e) {
                return null;
            }
        })();

        return $id !== null ? BudgetScope::user($id) : null;
    }
}
