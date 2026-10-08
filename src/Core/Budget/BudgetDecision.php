<?php

namespace Nsd7\AiMeter\Core\Budget;

/**
 * The result of evaluating a scope against its budgets. "allowed" is false only
 * when a blocking budget has been exceeded.
 */
final class BudgetDecision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly float $spentUsd,
        public readonly ?Budget $budget = null,
        public readonly bool $exceeded = false,
    ) {
    }

    public static function unlimited(float $spentUsd = 0.0): self
    {
        return new self(allowed: true, spentUsd: $spentUsd);
    }

    public function limitUsd(): ?float
    {
        return $this->budget?->limitUsd;
    }

    public function remainingUsd(): ?float
    {
        if (! $this->budget) {
            return null;
        }

        return max(0.0, $this->budget->limitUsd - $this->spentUsd);
    }

    public function usedFraction(): ?float
    {
        if (! $this->budget || $this->budget->limitUsd <= 0) {
            return null;
        }

        return $this->spentUsd / $this->budget->limitUsd;
    }
}
