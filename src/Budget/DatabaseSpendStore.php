<?php

namespace Nsd7\AiMeter\Budget;

use Illuminate\Support\Carbon;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Core\Budget\Period;
use Nsd7\AiMeter\Core\Contracts\SpendStore;
use Nsd7\AiMeter\Models\AiMeterCall;

/**
 * Computes spend directly from the ai_meter_calls table. The database is the
 * source of truth; a cache layer can be added later without changing callers.
 */
class DatabaseSpendStore implements SpendStore
{
    public function spent(BudgetScope $scope, Period $period): float
    {
        $query = AiMeterCall::query();

        if ($scope->type !== 'global') {
            $query->where('scope_type', $scope->type)
                ->where('scope_id', $scope->id !== null ? (string) $scope->id : null);
        }

        $start = $period->windowStart(Carbon::now()->toImmutable());

        if ($start !== null) {
            $query->where('created_at', '>=', $start->format('Y-m-d H:i:s'));
        }

        return (float) $query->sum('cost_usd');
    }
}
