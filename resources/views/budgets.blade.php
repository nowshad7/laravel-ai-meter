@extends('ai-meter::layouts.app')
@section('title', __('ai-meter::messages.budgets'))
@section('page_title', __('ai-meter::messages.budgets'))

@php
    $fmt = fn ($v) => '$' . number_format((float) $v, 2);
    $barColor = fn ($used) => $used >= 1 ? 'bg-rose-500' : ($used >= 0.8 ? 'bg-amber-500' : 'bg-brand-500');
    $actionBadge = fn ($a) => $a === 'block'
        ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'
        : 'bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';

    $bar = function ($used) use ($barColor, $thresholds) {
        $pct = $used === null ? 0 : min(100, round($used * 100));
        $ticks = '';
        foreach ($thresholds as $t) {
            if ($t > 0 && $t < 1) {
                $ticks .= '<div class="absolute top-0 h-full w-px bg-slate-400/70 dark:bg-slate-500/70" style="left: ' . round($t * 100, 2) . '%"></div>';
            }
        }
        return '<div class="relative h-2.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">'
            . '<div class="h-full rounded-full transition-all ' . $barColor($used ?? 0) . '" style="width: ' . $pct . '%"></div>'
            . $ticks . '</div>';
    };
@endphp

@section('actions')
    <span class="hidden items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-500 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 sm:inline-flex">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        @if ($alertsEnabled && $thresholds)
            {{ __('ai-meter::messages.alerts_at') }}
            {{ collect($thresholds)->map(fn ($t) => round($t * 100) . '%')->implode(' · ') }}
        @else
            {{ __('ai-meter::messages.alerts_off') }}
        @endif
    </span>
@endsection

@section('content')
    @forelse ($budgets as $budget)
        <div class="alm-card-p mb-4">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="font-mono text-sm font-bold">{{ $budget['scope_type'] }}</span>
                    <span class="alm-badge bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $budget['period']->label() }}</span>
                    <span class="alm-badge {{ $actionBadge($budget['action']) }}">{{ $budget['action'] }}</span>
                </div>
                <span class="tabular-nums text-sm text-slate-500 dark:text-slate-400">
                    {{ __('ai-meter::messages.budget_limit') }}: <span class="font-bold text-slate-800 dark:text-slate-100">{{ $fmt($budget['limit']) }}</span>
                </span>
            </div>

            @if ($budget['period'] === \Nsd7\AiMeter\Core\Budget\Period::Run)
                <p class="text-sm text-slate-400 dark:text-slate-500">{{ __('ai-meter::messages.per_run_note') }}</p>

            @elseif ($budget['global'])
                @php($used = $budget['global']['used'] ?? 0)
                <div class="mb-1.5 flex items-center justify-between text-sm">
                    <span class="tabular-nums text-slate-500 dark:text-slate-400">{{ $fmt($budget['global']['spent']) }} / {{ $fmt($budget['limit']) }}</span>
                    <span class="tabular-nums font-semibold {{ $used >= 1 ? 'text-rose-600 dark:text-rose-400' : ($used >= 0.8 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500 dark:text-slate-400') }}">{{ round($used * 100) }}% {{ __('ai-meter::messages.used') }}</span>
                </div>
                {!! $bar($budget['global']['used']) !!}

            @else
                <p class="mb-3 alm-label">{{ __('ai-meter::messages.top_scope_spend', ['type' => $budget['scope_type'], 'period' => $budget['period']->label()]) }}</p>
                @forelse ($budget['scopes'] as $scope)
                    @php($used = $scope['used'] ?? 0)
                    <div class="mb-3 last:mb-0">
                        <div class="mb-1 flex items-center justify-between text-sm">
                            <span class="truncate font-mono text-xs text-slate-600 dark:text-slate-300">{{ $budget['scope_type'] }}:{{ $scope['id'] ?? '—' }}</span>
                            <span class="tabular-nums text-slate-500 dark:text-slate-400">
                                {{ $fmt($scope['spent']) }} / {{ $fmt($budget['limit']) }}
                                <span class="ml-1 font-semibold {{ $used >= 1 ? 'text-rose-600 dark:text-rose-400' : ($used >= 0.8 ? 'text-amber-600 dark:text-amber-400' : '') }}">({{ round($used * 100) }}%)</span>
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
        <div class="alm-card-p">
            <div class="mb-3 flex items-center gap-2">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-400">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 6a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6z"/></svg>
                </span>
                <p class="text-sm font-semibold">{{ __('ai-meter::messages.no_budgets') }}</p>
            </div>
            <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">{!! __('ai-meter::messages.no_budgets_hint', ['file' => '<code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-800">config/ai-meter.php</code>']) !!}</p>
            <pre class="overflow-x-auto rounded-xl bg-slate-900 p-4 text-xs leading-relaxed text-slate-100 ring-1 ring-black/5 dark:bg-slate-950"><code>'budgets' =&gt; [
    ['scope' =&gt; 'user',   'period' =&gt; 'month', 'limit' =&gt; 50,  'action' =&gt; 'block'],
    ['scope' =&gt; 'global', 'period' =&gt; 'day',   'limit' =&gt; 500, 'action' =&gt; 'alert'],
],</code></pre>
        </div>
    @endforelse
@endsection
