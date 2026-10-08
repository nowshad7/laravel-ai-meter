<?php

namespace Nsd7\AiMeter\Core\Budget;

/**
 * A spend ceiling for a scope type over a period. "block" stops the request;
 * "alert" only flags it.
 */
final class Budget
{
    public const ACTION_BLOCK = 'block';
    public const ACTION_ALERT = 'alert';

    public function __construct(
        public readonly string $scopeType,
        public readonly Period $period,
        public readonly float $limitUsd,
        public readonly string $action = self::ACTION_BLOCK,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scopeType: (string) ($data['scope'] ?? 'user'),
            period: Period::from((string) ($data['period'] ?? 'month')),
            limitUsd: (float) ($data['limit'] ?? 0),
            action: (string) ($data['action'] ?? self::ACTION_BLOCK),
        );
    }

    public function blocks(): bool
    {
        return $this->action === self::ACTION_BLOCK;
    }
}
