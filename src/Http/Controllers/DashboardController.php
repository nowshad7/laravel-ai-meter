<?php

namespace Nsd7\AiMeter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Routing\Controller;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Core\Budget\Period;
use Nsd7\AiMeter\Meter;
use Nsd7\AiMeter\Models\AiMeterCall;

class DashboardController extends Controller
{
    public function __construct(protected Meter $meter)
    {
    }

    public function overview(Request $request)
    {
        $rangeDays = 14;

        return view('ai-meter::overview', [
            'totals' => [
                'today' => $this->sumSince(Period::Day),
                'month' => $this->sumSince(Period::Month),
                'all' => (float) AiMeterCall::query()->sum('cost_usd'),
                'calls' => (int) AiMeterCall::query()->count(),
            ],
            'perDay' => $this->perDay($rangeDays),
            'byModel' => $this->topBy('model', 8),
            'byProvider' => $this->topBy('provider', 8),
            'topScopes' => $this->topScopes(8),
            'globalBudget' => $this->meter->check(BudgetScope::global()),
            'currency' => (string) config('ai-meter.pricing.currency', 'USD'),
        ]);
    }

    public function calls(Request $request)
    {
        $perPage = (int) config('ai-meter.per_page', 25);

        $calls = AiMeterCall::query()
            ->when($request->query('provider'), fn ($q, $v) => $q->where('provider', $v))
            ->when($request->query('model'), fn ($q, $v) => $q->where('model', $v))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->query('scope_type'), fn ($q, $v) => $q->where('scope_type', $v))
            ->when($request->query('scope_id'), fn ($q, $v) => $q->where('scope_id', $v))
            ->when($request->query('search'), fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('model', 'like', "%{$v}%")->orWhere('provider', 'like', "%{$v}%");
            }))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return view('ai-meter::calls', [
            'calls' => $calls,
            'providers' => $this->distinct('provider'),
            'models' => $this->distinct('model'),
            'filters' => $request->query(),
            'currency' => (string) config('ai-meter.pricing.currency', 'USD'),
        ]);
    }

    public function show(Request $request, $call)
    {
        return view('ai-meter::call', [
            'call' => AiMeterCall::query()->findOrFail($call),
            'currency' => (string) config('ai-meter.pricing.currency', 'USD'),
        ]);
    }

    protected function sumSince(Period $period): float
    {
        $start = $period->windowStart(Carbon::now()->toImmutable());

        return (float) AiMeterCall::query()
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start->format('Y-m-d H:i:s')))
            ->sum('cost_usd');
    }

    /**
     * @return array<string, float>
     */
    protected function perDay(int $days): array
    {
        $expr = $this->dateExpression('created_at');
        $start = (Carbon::now()->toImmutable())->modify('-' . ($days - 1) . ' days')->setTime(0, 0);

        $rows = AiMeterCall::query()
            ->where('created_at', '>=', $start->format('Y-m-d H:i:s'))
            ->selectRaw("{$expr} as day, sum(cost_usd) as total")
            ->groupByRaw($expr)
            ->pluck('total', 'day');

        $series = [];
        for ($d = $start; $d <= Carbon::now()->toImmutable(); $d = $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $series[$key] = round((float) ($rows[$key] ?? 0), 6);
        }

        return $series;
    }

    /**
     * @return array<string, float>
     */
    protected function topBy(string $column, int $limit): array
    {
        return AiMeterCall::query()
            ->whereNotNull($column)
            ->selectRaw("{$column} as label, sum(cost_usd) as total")
            ->groupBy($column)
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'label')
            ->map(fn ($v) => round((float) $v, 6))
            ->all();
    }

    /**
     * @return array<int, array{label: string, total: float}>
     */
    protected function topScopes(int $limit): array
    {
        return AiMeterCall::query()
            ->whereNotNull('scope_type')
            ->selectRaw('scope_type, scope_id, sum(cost_usd) as total')
            ->groupBy('scope_type', 'scope_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'label' => $r->scope_type . ($r->scope_id !== null ? ':' . $r->scope_id : ''),
                'total' => round((float) $r->total, 6),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function distinct(string $column): array
    {
        return AiMeterCall::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    protected function dateExpression(string $column): string
    {
        $driver = AiMeterCall::query()->getConnection()->getDriverName();

        return match ($driver) {
            'pgsql', 'sqlsrv' => "CAST({$column} AS date)",
            'sqlite' => "date({$column})",
            default => "DATE({$column})",
        };
    }
}
