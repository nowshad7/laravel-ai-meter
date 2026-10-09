@extends('ai-meter::layouts.app')
@section('title', __('ai-meter::messages.budgets'))

@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 2);
    $card = 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900';
    $barColor = fn ($used) => $used >= 1 ? 'bg-rose-500' : ($used >= 0.8 ? 'bg-amber-500' : 'bg-emerald-500');
    $actionBadge = fn ($a) => $a === 'block'
        ? 'bg-rose-100 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300'
        : 'bg-amber-100 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300';
@endphp

@php
    // A spend bar with alert-threshold ticks. $used may be null (no limit).
    $bar = function ($used) use ($barColor, $thresholds) {
        $pct = $used === null ? 0 : min(100, round($used * 100));
        $ticks = '';
        foreach ($thresholds as $t) {
            if ($t > 0 && $t < 1) {
                $ticks .= '<div class="absolute top-0 h-full w-px bg-slate-400/70 dark:bg-slate-500/70" style="left: ' . round($t * 100, 2) . '%"></div>';
            }
        }
        return '<div class="relative h-2.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">'
            . '<div class="h-full rounded-full ' . $barColor($used ?? 0) . '" style="width: ' . $pct . '%"></div>'
            . $ticks . '</div>';
    };
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold tracking-tight">{{ __('ai-meter::messages.budgets') }}</h1>
        <span class="text-xs text-slate-500 dark:text-slate-400">
            @if ($alertsEnabled && $thresholds)
                {{ __('ai-meter::messages.alerts_at') }}
                {{ collect($thresholds)->map(fn ($t) => round($t * 100) . '%')->implode(' · ') }}
            @else
                {{ __('ai-meter::messages.alerts_off') }}
            @endif
        </span>
    </div>

    @forelse ($budgets as $budget)
        <div class="{{ $card }} mb-4">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-sm font-semibold">{{ $budget['scope_type'] }}</span>
                    <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $budget['period']->label() }}</span>
                    <span class="rounded-md px-2 py-0.5 text-xs font-medium {{ $actionBadge($budget['action']) }}">{{ $budget['action'] }}</span>
                </div>
                <span class="tabular-nums text-sm text-slate-500 dark:text-slate-400">
                    {{ __('ai-meter::messages.budget_limit') }}: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $fmt($budget['limit']) }}</span>
                </span>
            </div>

            @if ($budget['period'] === \Nsd7\AiMeter\Core\Budget\Period::Run)
                <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('ai-meter::messages.per_run_note') }}</p>

            @elseif ($budget['global'])
                @php($used = $budget['global']['used'] ?? 0)
                <div class="mb-1.5 flex items-center justify-between text-sm">
                    <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ $fmt($budget['global']['spent']) }} / {{ $fmt($budget['limit']) }}</span>
                    <span class="tabular-nums font-medium {{ $used >= 1 ? 'text-rose-600 dark:text-rose-400' : ($used >= 0.8 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400') }}">{{ round($used * 100) }}% {{ __('ai-meter::messages.used') }}</span>
                </div>
                {!! $bar($budget['global']['used']) !!}

            @else
                <p class="mb-2 text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    {{ __('ai-meter::messages.top_scope_spend', ['type' => $budget['scope_type'], 'period' => $budget['period']->label()]) }}
                </p>
                @forelse ($budget['scopes'] as $scope)
                    @php($used = $scope['used'] ?? 0)
                    <div class="mb-2.5 last:mb-0">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="truncate font-mono text-xs">{{ $budget['scope_type'] }}:{{ $scope['id'] ?? '—' }}</span>
                            <span class="tabular-nums text-slate-500 dark:text-slate-400">
                                {{ $fmt($scope['spent']) }} / {{ $fmt($budget['limit']) }}
                                <span class="ml-1 font-medium {{ $used >= 1 ? 'text-rose-600 dark:text-rose-400' : ($used >= 0.8 ? 'text-amber-600 dark:text-amber-400' : '') }}">({{ round($used * 100) }}%)</span>
                            </span>
                        </div>
                        {!! $bar($scope['used']) !!}
                    </div>
                @empty
                    <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('ai-meter::messages.no_scope_spend') }}</p>
                @endforelse
            @endif
        </div>
    @empty
        <div class="{{ $card }}">
            <p class="mb-3 text-sm font-medium">{{ __('ai-meter::messages.no_budgets') }}</p>
            <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">{!! __('ai-meter::messages.no_budgets_hint', ['file' => '<code class="rounded bg-slate-100 px-1 py-0.5 text-xs dark:bg-slate-800">config/ai-meter.php</code>']) !!}</p>
            <pre class="overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs leading-relaxed text-slate-100 dark:bg-slate-950"><code>'budgets' =&gt; [
    ['scope' =&gt; 'user',   'period' =&gt; 'month', 'limit' =&gt; 50,  'action' =&gt; 'block'],
    ['scope' =&gt; 'global', 'period' =&gt; 'day',   'limit' =&gt; 500, 'action' =&gt; 'alert'],
],</code></pre>
        </div>
    @endforelse
@endsection
