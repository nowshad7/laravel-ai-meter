<?php

namespace Nsd7\AiMeter\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Meter;

/**
 * Read-only "how much budget is left?" endpoint. Consumable by an SPA, a
 * dashboard, or an AI agent deciding whether it can afford another step.
 */
class BudgetController extends Controller
{
    public function __construct(protected Meter $meter)
    {
    }

    public function show(Request $request): JsonResponse
    {
        abort_unless(config('ai-meter.features.api', false), 404);

        return response()->json($this->meter->budgetReport($this->resolveScope($request)));
    }

    protected function resolveScope(Request $request): BudgetScope
    {
        $type = (string) ($request->query('scope_type') ?: config('ai-meter.scope.default', 'user'));

        if ($type === 'global') {
            return BudgetScope::global();
        }

        $id = $request->query('scope_id');

        if ($type === 'user' && $id === null) {
            $id = $request->user()?->getAuthIdentifier();
        }

        return new BudgetScope($type, $id);
    }
}
