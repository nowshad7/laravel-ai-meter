<?php

namespace Nsd7\AiMeter\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Meter;

/**
 * Blocks a request with HTTP 402 when the resolved scope is already over a
 * blocking budget. Usage: ->middleware('ai.budget') or 'ai.budget:user'.
 */
class EnforceBudget
{
    public function __construct(protected Meter $meter)
    {
    }

    public function handle(Request $request, Closure $next, ?string $type = null)
    {
        $type ??= (string) config('ai-meter.scope.default', 'user');
        $scope = $this->resolveScope($request, $type);

        if ($scope !== null) {
            $decision = $this->meter->check($scope);

            if (! $decision->allowed) {
                abort(402, 'AI budget exceeded for ' . $scope->key() . '.');
            }
        }

        return $next($request);
    }

    protected function resolveScope(Request $request, string $type): ?BudgetScope
    {
        if ($type === 'global') {
            return BudgetScope::global();
        }

        if ($type === 'user') {
            $id = $request->user()?->getAuthIdentifier();

            return $id !== null ? BudgetScope::user($id) : null;
        }

        // Custom dimension: let the app supply the id via a request attribute.
        $id = $request->attributes->get('ai_meter_scope_id');

        return $id !== null ? new BudgetScope($type, $id) : null;
    }
}
