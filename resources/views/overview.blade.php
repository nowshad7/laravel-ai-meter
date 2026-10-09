@extends('ai-meter::layouts.app')
@section('title', __('ai-meter::messages.overview'))
@section('page_title', __('ai-meter::messages.overview'))

@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 4);

    $tiles = [
        ['label' => __('ai-meter::messages.today'), 'value' => $fmt($totals['today']), 'accent' => true,
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['label' => __('ai-meter::messages.this_month'), 'value' => $fmt($totals['month']), 'accent' => true,
            'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['label' => __('ai-meter::messages.all_time'), 'value' => $fmt($totals['all']), 'accent' => false,
            'icon' => 'M3 3v18h18M7 14l3-3 4 4 5-6'],
        ['label' => __('ai-meter::messages.calls'), 'value' => number_format($totals['calls']), 'accent' => false,
            'icon' => 'M8 10h.01M12 10h.01M16 10h.01M21 12a8.96 8.96 0 01-1.3 4.68L21 21l-4.5-1.2A9 9 0 1121 12z'],
    ];
@endphp

@section('content')
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($tiles as $t)
            <div class="alm-card-p">
                <div class="flex items-start justify-between">
                    <p class="alm-label">{{ $t['label'] }}</p>
                    <span @class([
                        'flex h-9 w-9 items-center justify-center rounded-xl',
                        'bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400' => $t['accent'],
                        'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $t['accent'],
                    ])>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $t['icon'] }}"/></svg>
                    </span>
                </div>
                <p @class(['mt-3 text-2xl font-bold tabular-nums tracking-tight', 'text-brand-600 dark:text-brand-400' => $t['accent']])>{{ $t['value'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($globalBudget->budget)
        @php($used = $globalBudget->usedFraction() ?? 0)
        <div class="alm-card-p mb-6">
            <div class="mb-3 flex items-center justify-between text-sm">
                <span class="font-semibold">{{ __('ai-meter::messages.global_budget') }} <span class="text-slate-400">· {{ $globalBudget->budget->period->label() }}</span></span>
                <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ $fmt($globalBudget->spentUsd) }} / {{ $fmt($globalBudget->budget->limitUsd) }}</span>
            </div>
            <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div class="h-full rounded-full transition-all {{ $used >= 1 ? 'bg-rose-500' : ($used >= 0.8 ? 'bg-amber-500' : 'bg-brand-500') }}" style="width: {{ min(100, round($used * 100)) }}%"></div>
            </div>
        </div>
    @endif

    <div class="alm-card-p mb-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold">{{ __('ai-meter::messages.spend_over_time') }}</h2>
            <span class="alm-label">USD</span>
        </div>
        <div class="h-64"><canvas id="alm-perday"></canvas></div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach ([['by_model', $byModel], ['by_provider', $byProvider]] as [$key, $data])
            <div class="alm-card-p">
                <h2 class="mb-4 text-sm font-semibold">{{ __('ai-meter::messages.' . $key) }}</h2>
                @php($max = collect($data)->max() ?: 1)
                @forelse ($data as $label => $total)
                    <div class="mb-3 last:mb-0">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="truncate font-medium">{{ $label }}</span>
                            <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ $fmt($total) }}</span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-full rounded-full bg-brand-500/70" style="width: {{ max(2, round(($total / $max) * 100)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-400">{{ __('ai-meter::messages.no_data') }}</p>
                @endforelse
            </div>
        @endforeach
    </div>

    <div class="alm-card-p mt-6">
        <h2 class="mb-3 text-sm font-semibold">{{ __('ai-meter::messages.top_scopes') }}</h2>
        @forelse ($topScopes as $scope)
            <div class="flex items-center justify-between border-b border-slate-100 py-2.5 text-sm last:border-0 dark:border-slate-800">
                <span class="truncate rounded-md bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $scope['label'] }}</span>
                <span class="tabular-nums font-medium">{{ $fmt($scope['total']) }}</span>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-slate-400">{{ __('ai-meter::messages.no_data') }}</p>
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
            var grid = dark ? 'rgba(148,163,184,0.12)' : 'rgba(100,116,139,0.12)';
            Chart.defaults.font.family = "Inter, ui-sans-serif, system-ui, sans-serif";
            Chart.defaults.color = dark ? '#94a3b8' : '#64748b';
            var ctx = el.getContext('2d');
            var grad = ctx.createLinearGradient(0, 0, 0, 256);
            grad.addColorStop(0, 'rgba(16,185,129,0.95)');
            grad.addColorStop(1, 'rgba(16,185,129,0.55)');
            new Chart(el, {
                type: 'bar',
                data: { labels: Object.keys(data), datasets: [{ label: 'USD', data: Object.values(data), backgroundColor: grad, borderRadius: 6, maxBarThickness: 46 }] },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return '$' + Number(c.parsed.y).toFixed(4); } } } },
                    scales: {
                        x: { grid: { display: false }, border: { display: false }, ticks: { maxRotation: 0, autoSkipPadding: 12 } },
                        y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { callback: function (v) { return '$' + v; } } },
                    },
                },
            });
        })();
    </script>
@endsection
