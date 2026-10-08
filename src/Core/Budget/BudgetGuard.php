<?php

namespace Nsd7\AiMeter\Core\Budget;

use Nsd7\AiMeter\Core\Contracts\SpendStore;

/**
 * Evaluates a scope against the configured budgets for its type.
 */
class BudgetGuard
{
    /** @var array<int, Budget> */
    protected array $budgets;

    /**
     * @param array<int, Budget> $budgets
     */
    public function __construct(protected SpendStore $spendStore, array $budgets = [])
    {
        $this->budgets = $budgets;
    }

    /**
     * Budgets that apply to a given scope type.
     *
     * @return array<int, Budget>
     */
    public function budgetsFor(BudgetScope $scope): array
    {
        return array_values(array_filter(
            $this->budgets,
            fn (Budget $budget) => $budget->scopeType === $scope->type
        ));
    }

    /**
     * Evaluate a scope, optionally adding an about-to-happen cost to the spend.
     * Returns the strictest (most-exceeded) decision; a blocking, exceeded
     * budget makes the decision not allowed.
     */
    public function evaluate(BudgetScope $scope, float $additionalUsd = 0.0): BudgetDecision
    {
        $budgets = $this->budgetsFor($scope);

        if ($budgets === []) {
            return BudgetDecision::unlimited();
        }

        $worst = null;

        foreach ($budgets as $budget) {
            $spent = $this->spendStore->spent($scope, $budget->period) + $additionalUsd;
            $exceeded = $budget->limitUsd > 0 && $spent >= $budget->limitUsd;
            $allowed = ! ($exceeded && $budget->blocks());

            $decision = new BudgetDecision(
                allowed: $allowed,
                spentUsd: $spent,
                budget: $budget,
                exceeded: $exceeded,
            );

            if ($worst === null || $this->isStricter($decision, $worst)) {
                $worst = $decision;
            }
        }

        return $worst ?? BudgetDecision::unlimited();
    }

    protected function isStricter(BudgetDecision $a, BudgetDecision $b): bool
    {
        // A blocked decision always wins; otherwise the one closer to its limit.
        if ($a->allowed !== $b->allowed) {
            return ! $a->allowed;
        }

        return (($a->usedFraction() ?? 0.0) > ($b->usedFraction() ?? 0.0));
    }
}
