<?php

namespace Nsd7\AiMeter\Core\Contracts;

use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Core\Budget\Period;

interface SpendStore
{
    /**
     * Total USD spent by a scope within a period's window.
     */
    public function spent(BudgetScope $scope, Period $period): float;
}
