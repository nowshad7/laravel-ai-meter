<?php

namespace Nsd7\AiMeter\Adapters;

use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Meter;
use Nsd7\AiMeter\Support\UsageReader;
use Throwable;

/**
 * Records calls made through Neuron AI by listening to the event it dispatches
 * when an inference completes (e.g. InferenceStop), mirroring the Laravel AI
 * adapter.
 *
 * The event class and payload differ across Neuron versions, so the response is
 * read defensively (including getUsage()/getModel() accessors) and the listener
 * is wired only when enabled and the configured event class exists — it never
 * breaks an app that isn't using it.
 */
class NeuronListener
{
    public function __construct(protected Meter $meter)
    {
    }

    public function handle(object $event): void
    {
        $response = UsageReader::responseFrom($event);
        $usage = UsageReader::usage($response);

        // Nothing meaningful to record (e.g. a "start" event with no usage).
        if ($usage->totalTokens === 0 && $usage->promptTokens === 0 && $usage->completionTokens === 0) {
            return;
        }

        $this->meter->log([
            'source' => 'neuron',
            'provider' => $this->provider($event, $response),
            'model' => UsageReader::model($response) ?? 'unknown',
            'usage' => $usage,
            'scope' => $this->scope(),
        ]);
    }

    protected function provider(object $event, object $response): string
    {
        $provider = $response->provider ?? ($event->provider ?? null)
            ?? config('ai-meter.adapters.neuron.default_provider');

        return is_scalar($provider) ? (string) $provider : 'unknown';
    }

    protected function scope(): ?BudgetScope
    {
        $resolver = config('ai-meter.adapters.neuron.scope');

        if (is_callable($resolver)) {
            $scope = $resolver();

            return $scope instanceof BudgetScope ? $scope : null;
        }

        // Default: attribute to the authenticated user when there is one.
        $id = (function () {
            try {
                return auth()->id();
            } catch (Throwable $e) {
                return null;
            }
        })();

        return $id !== null ? BudgetScope::user($id) : null;
    }
}
