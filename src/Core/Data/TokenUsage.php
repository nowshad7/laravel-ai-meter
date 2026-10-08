<?php

namespace Nsd7\AiMeter\Core\Data;

/**
 * Normalized token usage for a single AI call, independent of any SDK.
 */
final class TokenUsage
{
    public function __construct(
        public readonly int $promptTokens = 0,
        public readonly int $completionTokens = 0,
        public readonly int $totalTokens = 0,
        public readonly int $cachedTokens = 0,
        public readonly int $reasoningTokens = 0,
    ) {
    }

    public static function of(int $prompt, int $completion, ?int $total = null): self
    {
        return new self(
            promptTokens: max(0, $prompt),
            completionTokens: max(0, $completion),
            totalTokens: $total !== null ? max(0, $total) : max(0, $prompt) + max(0, $completion),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $prompt = (int) ($data['prompt_tokens'] ?? $data['promptTokens'] ?? $data['input_tokens'] ?? $data['inputTokens'] ?? 0);
        $completion = (int) ($data['completion_tokens'] ?? $data['completionTokens'] ?? $data['output_tokens'] ?? $data['outputTokens'] ?? 0);
        $total = $data['total_tokens'] ?? $data['totalTokens'] ?? null;

        return new self(
            promptTokens: max(0, $prompt),
            completionTokens: max(0, $completion),
            totalTokens: $total !== null ? max(0, (int) $total) : max(0, $prompt) + max(0, $completion),
            cachedTokens: max(0, (int) ($data['cached_tokens'] ?? $data['cacheReadInputTokens'] ?? 0)),
            reasoningTokens: max(0, (int) ($data['reasoning_tokens'] ?? $data['thoughtTokens'] ?? 0)),
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'total_tokens' => $this->totalTokens,
            'cached_tokens' => $this->cachedTokens,
            'reasoning_tokens' => $this->reasoningTokens,
        ];
    }
}
