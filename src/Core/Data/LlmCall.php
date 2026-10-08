<?php

namespace Nsd7\AiMeter\Core\Data;

/**
 * A single, SDK-agnostic AI call ready to be recorded.
 *
 * Every adapter (Prism, Laravel AI SDK, Neuron, manual) normalizes into this
 * shape, so the rest of the package never needs to know which SDK produced it.
 */
final class LlmCall
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly TokenUsage $usage,
        public readonly string $source = 'manual',
        public readonly string $operation = 'chat',
        public readonly string $status = 'success',
        public readonly ?float $costUsd = null,
        public readonly ?int $latencyMs = null,
        public readonly ?string $traceId = null,
        public readonly ?string $runId = null,
        public readonly ?string $toolName = null,
        public readonly ?string $scopeType = null,
        public readonly string|int|null $scopeId = null,
        public readonly ?string $causerType = null,
        public readonly string|int|null $causerId = null,
        public readonly ?string $input = null,
        public readonly ?string $output = null,
        public readonly ?string $error = null,
        public readonly array $properties = [],
    ) {
    }

    public function withCost(float $costUsd): self
    {
        return new self(
            provider: $this->provider,
            model: $this->model,
            usage: $this->usage,
            source: $this->source,
            operation: $this->operation,
            status: $this->status,
            costUsd: $costUsd,
            latencyMs: $this->latencyMs,
            traceId: $this->traceId,
            runId: $this->runId,
            toolName: $this->toolName,
            scopeType: $this->scopeType,
            scopeId: $this->scopeId,
            causerType: $this->causerType,
            causerId: $this->causerId,
            input: $this->input,
            output: $this->output,
            error: $this->error,
            properties: $this->properties,
        );
    }
}
