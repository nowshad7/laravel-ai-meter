<?php

namespace Nsd7\AiMeter\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Nsd7\AiMeter\Core\Budget\Budget;
use Nsd7\AiMeter\Core\Budget\BudgetScope;
use Nsd7\AiMeter\Core\Budget\Period;
use Nsd7\AiMeter\Export\CallLogExporter;
use Nsd7\AiMeter\Meter;
use Nsd7\AiMeter\Models\AiMeterCall;
use Nsd7\AiMeter\Models\AiMeterRun;

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

    public function budgets(Request $request)
    {
        $thresholds = array_values(array_filter(
            array_map('floatval', (array) config('ai-meter.alerts.thresholds', [0.8, 1.0])),
            fn ($t) => $t > 0
        ));
        sort($thresholds);

        $budgets = array_map(function (Budget $budget) {
            $row = [
                'scope_type' => $budget->scopeType,
                'period' => $budget->period,
                'limit' => $budget->limitUsd,
                'action' => $budget->action,
                'global' => null,
                'scopes' => [],
            ];

            if ($budget->period === Period::Run) {
                return $row; // per-run ceiling, enforced by MeterRun — no time window.
            }

            if ($budget->scopeType === 'global') {
                $spent = $this->meter->spent(BudgetScope::global(), $budget->period);
                $row['global'] = [
                    'spent' => $spent,
                    'used' => $budget->limitUsd > 0 ? $spent / $budget->limitUsd : null,
                ];

                return $row;
            }

            $row['scopes'] = $this->topScopesForBudget($budget, 5);

            return $row;
        }, $this->meter->budgets());

        return view('ai-meter::budgets', [
            'budgets' => $budgets,
            'thresholds' => $thresholds,
            'alertsEnabled' => (bool) config('ai-meter.alerts.enabled', true),
            'currency' => (string) config('ai-meter.pricing.currency', 'USD'),
        ]);
    }

    public function calls(Request $request)
    {
        $calls = (new CallLogExporter())->query($request->query())
            ->paginate((int) config('ai-meter.per_page', 25))
            ->withQueryString();

        return view('ai-meter::calls', [
            'calls' => $calls,
            'providers' => $this->distinct('provider'),
            'models' => $this->distinct('model'),
            'filters' => $request->query(),
            'currency' => (string) config('ai-meter.pricing.currency', 'USD'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format') === 'json' ? 'json' : 'csv';
        $withIo = $request->boolean('io');

        $exporter = new CallLogExporter();
        $query = $exporter->query($request->query());
        $chunks = $format === 'json' ? $exporter->json($query, $withIo) : $exporter->csv($query, $withIo);

        $filename = 'ai-meter-calls-' . now()->format('Ymd-His') . '.' . $format;

        return response()->streamDownload(function () use ($chunks) {
            foreach ($chunks as $chunk) {
                echo $chunk;
            }
        }, $filename, [
            'Content-Type' => $format === 'json' ? 'application/json' : 'text/csv; charset=UTF-8',
        ]);
    }

    public function show(Request $request, $call)
    {
        return view('ai-meter::call', [
            'call' => AiMeterCall::query()->findOrFail($call),
            'currency' => (string) config('ai-meter.pricing.currency', 'USD'),
        ]);
    }

    public function runs(Request $request)
    {
        abort_unless(config('ai-meter.features.runs', true), 404);

        $runs = AiMeterRun::query()
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id')
            ->paginate((int) config('ai-meter.per_page', 25))
            ->withQueryString();

        return view('ai-meter::runs', [
            'runs' => $runs,
            'filters' => $request->query(),
            'currency' => (string) config('ai-meter.pricing.currency', 'USD'),
        ]);
    }

    public function runShow(Request $request, $run)
    {
        abort_unless(config('ai-meter.features.runs', true), 404);

        $run = AiMeterRun::query()->findOrFail($run);

        return view('ai-meter::run', [
            'run' => $run,
            'calls' => AiMeterCall::query()->where('run_id', $run->run_id)->orderBy('id')->get(),
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
     * The highest-spending scopes of a budget's type within its current window,
     * each with its used-fraction against the limit — the live view of who is
     * closest to tripping the budget.
     *
     * @return array<int, array{id: ?string, spent: float, used: ?float}>
     */
    protected function topScopesForBudget(Budget $budget, int $limit): array
    {
        $start = $budget->period->windowStart(Carbon::now()->toImmutable());

        return AiMeterCall::query()
            ->where('scope_type', $budget->scopeType)
            ->when($start, fn ($q) => $q->where('created_at', '>=', $start->format('Y-m-d H:i:s')))
            ->selectRaw('scope_id, sum(cost_usd) as total')
            ->groupBy('scope_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->scope_id !== null ? (string) $r->scope_id : null,
                'spent' => round((float) $r->total, 8),
                'used' => $budget->limitUsd > 0 ? (float) $r->total / $budget->limitUsd : null,
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
