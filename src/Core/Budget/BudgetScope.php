<?php

namespace Nsd7\AiMeter\Core\Budget;

/**
 * Who a spend is attributed to: a user, a tenant, a global bucket, or a custom
 * dimension. "global" has no id.
 */
final class BudgetScope
{
    public function __construct(
        public readonly string $type,
        public readonly string|int|null $id = null,
    ) {
    }

    public static function user(string|int $id): self
    {
        return new self('user', $id);
    }

    public static function tenant(string|int $id): self
    {
        return new self('tenant', $id);
    }

    public static function global(): self
    {
        return new self('global', null);
    }

    public function key(): string
    {
        return $this->id === null ? $this->type : $this->type . ':' . $this->id;
    }
}
