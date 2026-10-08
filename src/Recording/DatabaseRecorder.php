<?php

namespace Nsd7\AiMeter\Recording;

use Illuminate\Support\Carbon;
use Nsd7\AiMeter\Core\Contracts\Recorder;
use Nsd7\AiMeter\Core\Contracts\Redactor;
use Nsd7\AiMeter\Core\Data\LlmCall;
use Nsd7\AiMeter\Models\AiMeterCall;

/**
 * Persists AI calls to the ai_meter_calls table, applying redaction, I/O
 * truncation and optional display-currency conversion.
 */
class DatabaseRecorder implements Recorder
{
    public function __construct(protected Redactor $redactor)
    {
    }

    public function record(LlmCall $call): void
    {
        AiMeterCall::create($this->toAttributes($call));
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(LlmCall $call): array
    {
        $storeIo = (bool) config('ai-meter.recording.store_io', true);
        $currency = (string) config('ai-meter.pricing.currency', 'USD');
        $cost = $call->costUsd ?? 0.0;

        return [
            'source' => $call->source,
            'provider' => $call->provider,
            'model' => $call->model,
            'operation' => $call->operation,
            'status' => $call->status,
            'prompt_tokens' => $call->usage->promptTokens,
            'completion_tokens' => $call->usage->completionTokens,
            'total_tokens' => $call->usage->totalTokens,
            'cached_tokens' => $call->usage->cachedTokens,
            'reasoning_tokens' => $call->usage->reasoningTokens,
            'cost_usd' => $cost,
            'currency' => $currency,
            'cost_display' => $this->displayCost($cost),
            'latency_ms' => $call->latencyMs,
            'trace_id' => $call->traceId,
            'run_id' => $call->runId,
            'tool_name' => $call->toolName,
            'scope_type' => $call->scopeType,
            'scope_id' => $call->scopeId !== null ? (string) $call->scopeId : null,
            'causer_type' => $call->causerType,
            'causer_id' => $call->causerId !== null ? (string) $call->causerId : null,
            'input' => $storeIo ? $this->prepareText($call->input) : null,
            'output' => $storeIo ? $this->prepareText($call->output) : null,
            'error' => $call->error,
            'properties' => $call->properties ?: null,
            'created_at' => Carbon::now(),
        ];
    }

    protected function prepareText(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        if (config('ai-meter.recording.redact', true)) {
            $text = $this->redactor->scrub($text);
        }

        $max = (int) config('ai-meter.recording.max_io_chars', 8000);

        if ($max > 0 && $text !== null && mb_strlen($text) > $max) {
            $text = mb_substr($text, 0, $max) . '…';
        }

        return $text;
    }

    protected function displayCost(float $usd): ?float
    {
        $fx = config('ai-meter.pricing.fx_rate');

        if ($fx === null) {
            return null;
        }

        $rate = is_callable($fx) ? (float) $fx($usd) : (float) $fx;

        return round($usd * $rate, 6);
    }
}
