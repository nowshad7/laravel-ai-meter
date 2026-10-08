<?php

namespace Nsd7\AiMeter\Support;

use Nsd7\AiMeter\Core\Data\TokenUsage;

/**
 * Reads token usage and model from an SDK response object defensively, so it
 * keeps working across Prism and Laravel AI SDK versions (which use different
 * property names, e.g. promptTokens vs inputTokens).
 */
class UsageReader
{
    public static function usage(object $response): TokenUsage
    {
        $usage = $response->usage ?? null;

        if (is_object($usage)) {
            $prompt = (int) ($usage->promptTokens ?? $usage->inputTokens ?? 0);
            $completion = (int) ($usage->completionTokens ?? $usage->outputTokens ?? 0);
            $total = (int) ($usage->totalTokens ?? 0);

            return new TokenUsage(
                promptTokens: $prompt,
                completionTokens: $completion,
                totalTokens: $total ?: $prompt + $completion,
                cachedTokens: (int) ($usage->cacheReadInputTokens ?? $usage->cachedTokens ?? 0),
                reasoningTokens: (int) ($usage->thoughtTokens ?? $usage->reasoningTokens ?? 0),
            );
        }

        if (is_array($usage)) {
            return TokenUsage::fromArray($usage);
        }

        return new TokenUsage();
    }

    public static function model(object $response): ?string
    {
        $model = $response->model
            ?? ($response->meta->model ?? null)
            ?? ($response->meta->model_id ?? null);

        return is_scalar($model) ? (string) $model : null;
    }

    /**
     * Find the response-like object inside an event (which may expose it under
     * a few different property names, or be the response itself).
     */
    public static function responseFrom(object $event): object
    {
        foreach (['response', 'agentResponse', 'result'] as $property) {
            if (isset($event->{$property}) && is_object($event->{$property})) {
                return $event->{$property};
            }
        }

        return $event;
    }
}
