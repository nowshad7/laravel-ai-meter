<?php

namespace Nsd7\AiMeter\Events;

use Nsd7\AiMeter\Core\Budget\Budget;
use Nsd7\AiMeter\Core\Budget\BudgetScope;

/**
 * Dispatched once per budget window when a scope's spend first reaches one of
 * the configured alert thresholds (e.g. 80% and 100% of its limit).
 */
class BudgetThresholdReached
{
    public function __construct(
        public readonly BudgetScope $scope,
        public readonly Budget $budget,
        public readonly float $threshold,
        public readonly float $spentUsd,
    ) {
    }

    public function usedFraction(): float
    {
        return $this->budget->limitUsd > 0 ? $this->spentUsd / $this->budget->limitUsd : 0.0;
    }

    public function exceeded(): bool
    {
        return $this->spentUsd >= $this->budget->limitUsd;
    }
}
