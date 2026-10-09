<?php

namespace Nsd7\AiMeter\Core\Contracts;

use Nsd7\AiMeter\Core\Budget\BudgetScope;

/**
 * A SpendStore that keeps its own running totals (e.g. cache counters) instead
 * of aggregating the database on each read. The Meter feeds it every recorded
 * cost so its counters stay current. A null scope still counts toward global.
 */
interface RecordsSpend
{
    public function addSpend(?BudgetScope $scope, float $usd): void;
}
