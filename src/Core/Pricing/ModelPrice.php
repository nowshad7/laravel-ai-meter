<?php

namespace Nsd7\AiMeter\Core\Pricing;

use Nsd7\AiMeter\Core\Data\TokenUsage;

/**
 * Per-million-token prices for one model, in USD.
 */
final class ModelPrice
{
    public function __construct(
        public readonly float $inputPerMillion,
        public readonly float $outputPerMillion,
        public readonly ?float $cachedInputPerMillion = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            inputPerMillion: (float) ($data['input'] ?? 0),
            outputPerMillion: (float) ($data['output'] ?? 0),
            cachedInputPerMillion: isset($data['cached_input']) ? (float) $data['cached_input'] : null,
        );
    }

    /**
     * Compute the USD cost of a usage record against this price.
     */
    public function cost(TokenUsage $usage): float
    {
        $cachedRate = $this->cachedInputPerMillion ?? $this->inputPerMillion;

        // Cached tokens are billed at the (usually cheaper) cached rate and are
        // assumed to be a subset of the prompt tokens.
        $billablePrompt = max(0, $usage->promptTokens - $usage->cachedTokens);

        $cost = ($billablePrompt / 1_000_000) * $this->inputPerMillion
            + ($usage->cachedTokens / 1_000_000) * $cachedRate
            + ($usage->completionTokens / 1_000_000) * $this->outputPerMillion;

        return round($cost, 8);
    }
}
