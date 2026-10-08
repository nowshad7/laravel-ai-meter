@extends('ai-meter::layouts.app')
@section('title', __('ai-meter::messages.overview'))

@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 4);
    $card = 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900';
@endphp

@section('content')
    <h1 class="mb-6 text-2xl font-bold tracking-tight">{{ __('ai-meter::messages.overview') }}</h1>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="{{ $card }}"><p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('ai-meter::messages.today') }}</p><p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $fmt($totals['today']) }}</p></div>
        <div class="{{ $card }}"><p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('ai-meter::messages.this_month') }}</p><p class="mt-1 text-2xl font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">{{ $fmt($totals['month']) }}</p></div>
        <div class="{{ $card }}"><p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('ai-meter::messages.all_time') }}</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ $fmt($totals['all']) }}</p></div>
        <div class="{{ $card }}"><p class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('ai-meter::messages.calls') }}</p><p class="mt-1 text-2xl font-semibold tabular-nums">{{ number_format($totals['calls']) }}</p></div>
    </div>

    @if ($globalBudget->budget)
        @php($used = $globalBudget->usedFraction() ?? 0)
        <div class="{{ $card }} mb-6">
            <div class="mb-2 flex items-center justify-between text-sm">
                <span class="font-medium">{{ __('ai-meter::messages.global_budget') }} ({{ $globalBudget->budget->period->label() }})</span>
                <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ $fmt($globalBudget->spentUsd) }} / {{ $fmt($globalBudget->budget->limitUsd) }}</span>
            </div>
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div class="h-full rounded-full {{ $used >= 1 ? 'bg-rose-500' : ($used >= 0.8 ? 'bg-amber-500' : 'bg-emerald-500') }}" style="width: {{ min(100, round($used * 100)) }}%"></div>
            </div>
        </div>
    @endif

    <div class="{{ $card }} mb-6">
        <h2 class="mb-4 text-sm font-semibold">{{ __('ai-meter::messages.spend_over_time') }}</h2>
        <div class="h-64"><canvas id="alm-perday"></canvas></div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach ([['by_model', $byModel], ['by_provider', $byProvider]] as [$key, $data])
            <div class="{{ $card }}">
                <h2 class="mb-3 text-sm font-semibold">{{ __('ai-meter::messages.' . $key) }}</h2>
                @forelse ($data as $label => $total)
                    <div class="flex items-center justify-between border-b border-slate-100 py-1.5 text-sm last:border-0 dark:border-slate-800">
                        <span class="truncate">{{ $label }}</span><span class="tabular-nums text-slate-500 dark:text-slate-400">{{ $fmt($total) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-slate-400">{{ __('ai-meter::messages.no_data') }}</p>
                @endforelse
            </div>
        @endforeach
    </div>

    <div class="{{ $card }} mt-6">
        <h2 class="mb-3 text-sm font-semibold">{{ __('ai-meter::messages.top_scopes') }}</h2>
        @forelse ($topScopes as $scope)
            <div class="flex items-center justify-between border-b border-slate-100 py-1.5 text-sm last:border-0 dark:border-slate-800">
                <span class="truncate font-mono text-xs">{{ $scope['label'] }}</span><span class="tabular-nums text-slate-500 dark:text-slate-400">{{ $fmt($scope['total']) }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-400">{{ __('ai-meter::messages.no_data') }}</p>
        @endforelse
    </div>

    <script id="alm-perday-data" type="application/json">@json($perDay)</script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            var el = document.getElementById('alm-perday');
            if (!el || !window.Chart) return;
            var data = JSON.parse(document.getElementById('alm-perday-data').textContent);
            var dark = document.documentElement.classList.contains('dark');
            var grid = dark ? 'rgba(148,163,184,0.15)' : 'rgba(100,116,139,0.15)';
            Chart.defaults.color = dark ? '#cbd5e1' : '#475569';
            new Chart(el, {
                type: 'bar',
                data: { labels: Object.keys(data), datasets: [{ label: 'USD', data: Object.values(data), backgroundColor: '#10b981', borderRadius: 4 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
                    scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: grid } } } }
            });
        })();
    </script>
@endsection
